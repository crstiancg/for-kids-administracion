<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use App\Services\Inventario;
use App\Services\Pedidos;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * 30 días de movimiento de prueba sobre el catálogo DEMO: cajas diarias por
 * cajero, ventas con pagos, algún faltante en el arqueo y pedidos pendientes.
 *
 * Todo pasa por los servicios reales (Pedidos, Cajas, Inventario) viajando
 * en el tiempo con Carbon::setTestNow: cada venta descuenta stock del libro,
 * congela su costo y entra a la caja de ESE día, como una venta de verdad.
 * Datos que no respetan las reglas harían que el dashboard mienta.
 *
 * Idempotente: si ya hay ventas de prueba, no hace nada.
 */
class DemoVentasSeeder extends Seeder
{
    public const MARCA = 'Venta de prueba (DemoSeeder)';

    private const DIAS = 30;

    // Más efectivo y Yape que el resto, como en una tienda de barrio.
    private const METODOS = ['efectivo', 'efectivo', 'efectivo', 'yape', 'yape', 'plin', 'tarjeta', 'transferencia'];

    private const CANALES = ['mostrador', 'mostrador', 'mostrador', 'mostrador', 'whatsapp', 'redes'];

    public function __construct(
        private Pedidos $pedidos,
        private Cajas $cajas,
        private Inventario $inventario,
    ) {}

    public function run(): void
    {
        if (Pedido::where('observacion', self::MARCA)->exists()) {
            $this->command?->info('Ventas de prueba ya cargadas: no se repiten.');

            return;
        }

        $variantes = Variante::where('sku', 'like', 'DEMO-%')->with('producto:id,precio')->get();
        if ($variantes->isEmpty()) {
            return;
        }

        $cajeros = collect(['cajera.demo' => 'Lucía (demo)', 'cajero.demo' => 'Marco (demo)'])
            ->map(fn ($nombre, $usuario) => User::firstOrCreate(
                ['username' => $usuario],
                ['name' => $nombre, 'password' => 'password', 'active' => true],
            ))->values();
        $clientes = Cliente::pluck('id');

        $zona = config('app.zona_negocio');
        // La hora REAL, antes de empezar a viajar: lo de hoy no puede quedar
        // en el futuro.
        $ahora = CarbonImmutable::now($zona);
        $hoy = $ahora->startOfDay();

        // Los seeders corren con los modelos SIN `fillable` (unguarded), y
        // los servicios confían en él: Pedidos::guardar hace fill() con
        // `items` adentro. Se reactiva mientras se usan los servicios.
        Model::reguard();

        try {
            // Reposición antes del primer día: que alcance para un mes de
            // ventas, sin dejar todo lleno (algunas variantes van a quedar
            // bajas o agotadas, que es lo que el dashboard tiene que mostrar).
            $this->viajar($hoy->subDays(self::DIAS)->setTime(8, 0));
            $this->inventario->entrada(
                $variantes->map(fn (Variante $v) => ['variante_id' => $v->id, 'cantidad' => random_int(4, 12), 'costo_unitario' => $v->costo_promedio])->all(),
                'DEMO-REPO',
                'Reposición de prueba',
                $cajeros[0],
            );

            for ($d = self::DIAS - 1; $d >= 0; $d--) {
                $dia = $hoy->subDays($d);
                foreach ($cajeros as $i => $cajero) {
                    $this->jornada($dia, $cajero, $variantes, $clientes, $d === 0 ? $ahora : null, faltante: $d % 9 === 4 && $i === 1);
                }
            }

            // Pedidos de WhatsApp que esperan confirmación.
            $this->viajar($ahora);
            foreach (range(1, 3) as $_) {
                $this->pedidos->guardar(new Pedido, [
                    'cliente_id' => $clientes->random(),
                    'canal' => 'whatsapp',
                    'descuento' => 0,
                    'observacion' => self::MARCA,
                    'items' => $this->elegirItems($variantes, 2),
                ], $cajeros[0]);
            }
        } finally {
            $this->viajar(null);
            Model::unguard();
        }

        $this->command?->info('Ventas de prueba: '.Pedido::where('observacion', self::MARCA)->count().' pedidos en '.self::DIAS.' días.');
    }

    /**
     * Un día de un cajero: abre su caja a las 9, vende hasta las 20 y cierra
     * con su arqueo. HOY ($ahora) vende sólo hasta la hora real y la caja
     * queda abierta (así se ve una caja en curso).
     */
    private function jornada(CarbonImmutable $dia, User $cajero, $variantes, $clientes, ?CarbonImmutable $ahora, bool $faltante): void
    {
        $apertura = $dia->setTime(9, 0);
        $fin = $ahora ?? $dia->setTime(19, 59);
        if ($fin->lte($apertura)) {
            // Antes de las 9 de hoy: abre recién, sin ventas todavía.
            $apertura = $fin->subMinute();
        }

        $this->viajar($apertura);
        $caja = $this->cajas->abrir(100, $cajero);

        $segundos = (int) $apertura->diffInSeconds($fin);
        $ventas = $segundos < 600 ? 0 : random_int(1, $dia->isWeekend() ? 7 : 4);
        $momentos = collect(range(1, max(1, $ventas)))->map(fn () => random_int(60, $segundos - 1))->sort()->take($ventas);
        foreach ($momentos as $segundo) {
            $this->viajar($apertura->addSeconds($segundo));
            $this->vender($cajero, $variantes, $clientes);
        }

        if ($ahora) {
            return;
        }

        $this->viajar($dia->setTime(20, 0));
        $esperado = $this->cajas->resumen($caja)['efectivo_esperado'];
        $this->cajas->cerrar(
            $caja,
            $faltante ? $esperado - 5 : $esperado,
            $faltante ? 'Faltan S/ 5: vuelto mal dado (dato de prueba).' : null,
            $cajero,
        );
    }

    private function vender(User $cajero, $variantes, $clientes): void
    {
        $items = $this->elegirItems($variantes, random_int(1, 3));
        if ($items === []) {
            return;
        }

        $canal = self::CANALES[array_rand(self::CANALES)];
        $pedido = $this->pedidos->guardar(new Pedido, [
            // Mostrador casi siempre es "Cliente varios"; WhatsApp y redes, no.
            'cliente_id' => $canal !== 'mostrador' || random_int(1, 4) === 1 ? $clientes->random() : null,
            'canal' => $canal,
            'descuento' => 0,
            'observacion' => self::MARCA,
            'items' => $items,
        ], $cajero);
        $this->pedidos->confirmar($pedido, $cajero);

        $pedido->refresh();
        $metodo = self::METODOS[array_rand(self::METODOS)];
        $total = (float) $pedido->total;
        $this->cajas->cobrar($pedido, [
            'metodo' => $metodo,
            'monto' => $total,
            // Paga con billete redondo: queda un vuelto que contar.
            'recibido' => $metodo === 'efectivo' ? ceil($total / 10) * 10 : null,
            'referencia' => $metodo !== 'efectivo' ? (string) random_int(100000, 999999) : null,
        ], $cajero);
        $this->pedidos->entregar($pedido);
    }

    /**
     * Ítems al azar con stock suficiente (se lee el stock actual: lo que se
     * vendió antes ya no está).
     *
     * @return array<int, array{variante_id: int, cantidad: int, precio_unitario: float}>
     */
    private function elegirItems($variantes, int $cuantos): array
    {
        $conStock = Variante::whereIn('id', $variantes->pluck('id'))->where('stock', '>', 0)->with('producto:id,precio')->get();
        if ($conStock->isEmpty()) {
            return [];
        }

        return $conStock->random(min($cuantos, $conStock->count()))
            ->map(fn (Variante $v) => [
                'variante_id' => $v->id,
                'cantidad' => min($v->stock, random_int(1, 2)),
                'precio_unitario' => (float) ($v->precio ?? $v->producto->precio),
            ])
            ->values()
            ->all();
    }

    private function viajar(?CarbonImmutable $momento): void
    {
        $utc = $momento?->utc();
        Carbon::setTestNow($utc);
        CarbonImmutable::setTestNow($utc);
    }
}
