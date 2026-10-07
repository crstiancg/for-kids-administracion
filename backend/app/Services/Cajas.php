<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caja, pagos y arqueo. El único lugar que registra dinero:
 *
 * - Todo pago (de cualquier método) entra a la caja abierta: así el cierre
 *   muestra lo cobrado del día por método. El arqueo compara sólo efectivo.
 * - Pagos y movimientos son inmutables: un error se corrige con otro
 *   registro (una devolución), nunca editando.
 * - La caja abierta se bloquea (FOR UPDATE) al registrar: un pago no puede
 *   colarse en una caja que se está cerrando en ese mismo instante.
 * - Es POR USUARIO: cada cajero cobra en SU caja (`abierta_por`), con su
 *   propio arqueo.
 * - Es DIARIA: una caja abierta en un día anterior no recibe más dinero; hay
 *   que cerrarla con su arqueo y abrir la de hoy.
 */
class Cajas
{
    /**
     * La caja abierta del usuario (puede estar vencida: así la ve y la cierra).
     */
    public function actual(?User $usuario): ?Caja
    {
        if (! $usuario) {
            return null;
        }

        return Caja::query()->where('estado', Caja::ABIERTA)->where('abierta_por', $usuario->id)->first();
    }

    public function abrir(float $montoApertura, ?User $usuario): Caja
    {
        if (! $usuario) {
            throw $this->conflicto('caja', 'La caja se abre con un usuario.');
        }

        // Una caja olvidada de ayer se cierra con su arqueo, no se pisa: si
        // no, ese efectivo nunca se cuenta.
        if ($pendiente = $this->actual($usuario)) {
            throw $this->conflicto('caja', $pendiente->vencida()
                ? 'Tenés la caja del '.$this->dia($pendiente).' sin cerrar: cerrala con su arqueo antes de abrir la de hoy.'
                : 'Ya tenés una caja abierta hoy.');
        }

        try {
            return DB::transaction(fn () => Caja::query()->forceCreate([
                'estado' => Caja::ABIERTA,
                'abierta' => true,
                'monto_apertura' => $montoApertura,
                'abierta_por' => $usuario?->id,
                'abierta_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // El unique (abierta_por, abierta) ganó la carrera: doble click.
            throw $this->conflicto('caja', 'Ya tenés una caja abierta hoy.');
        }
    }

    /**
     * Totales de la caja: cobros por método, ingresos/egresos y el efectivo
     * que DEBERÍA haber en el cajón.
     *
     * @return array<string, mixed>
     */
    public function resumen(Caja $caja): array
    {
        $porMetodo = $caja->pagos()
            ->selectRaw('metodo, SUM(monto) as total, COUNT(*) as cantidad')
            ->groupBy('metodo')
            ->get()
            ->keyBy('metodo');

        $cobros = collect(Pago::METODOS)->map(fn ($label, $metodo) => [
            'metodo' => $metodo,
            'label' => $label,
            'total' => round((float) ($porMetodo[$metodo]->total ?? 0), 2),
            'cantidad' => (int) ($porMetodo[$metodo]->cantidad ?? 0),
        ])->values();

        $ingresos = (float) $caja->movimientos()->where('tipo', MovimientoCaja::INGRESO)->sum('monto');
        $egresos = (float) $caja->movimientos()->where('tipo', MovimientoCaja::EGRESO)->sum('monto');
        $efectivo = (float) ($porMetodo[Pago::EFECTIVO]->total ?? 0);

        return [
            'monto_apertura' => round((float) $caja->monto_apertura, 2),
            'cobros' => $cobros,
            'total_cobrado' => round($cobros->sum('total'), 2),
            'ingresos' => round($ingresos, 2),
            'egresos' => round($egresos, 2),
            'efectivo_esperado' => round((float) $caja->monto_apertura + $efectivo + $ingresos - $egresos, 2),
        ];
    }

    /**
     * Cobro de un pedido pendiente o confirmado (un pendiente puede recibir
     * un adelanto). Nunca más que el saldo. En efectivo, `recibido` es lo que
     * entregó el cliente y se calcula el vuelto.
     *
     * @param  array{metodo: string, monto: numeric-string|float, recibido?: numeric-string|float|null, referencia?: string|null}  $datos
     */
    public function cobrar(Pedido $pedido, array $datos, ?User $usuario): Pago
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if (! in_array($pedido->estado, [Pedido::PENDIENTE, Pedido::CONFIRMADO], true)) {
                throw $this->conflicto('pedido', "No se cobra un pedido {$pedido->estado}.");
            }

            $monto = round((float) $datos['monto'], 2);
            $saldo = $pedido->saldo();
            if ($monto > $saldo) {
                throw ValidationException::withMessages([
                    'pago.monto' => 'El monto supera el saldo del pedido ('.$this->soles($saldo).').',
                ]);
            }

            $recibido = null;
            $vuelto = null;
            if ($datos['metodo'] === Pago::EFECTIVO) {
                $recibido = round((float) ($datos['recibido'] ?? $monto), 2);
                if ($recibido < $monto) {
                    throw ValidationException::withMessages([
                        'pago.recibido' => 'Lo recibido no alcanza para el monto a cobrar.',
                    ]);
                }
                $vuelto = round($recibido - $monto, 2);
            }

            return Pago::create([
                'pedido_id' => $pedido->id,
                'caja_id' => $caja->id,
                'metodo' => $datos['metodo'],
                'monto' => $monto,
                'recibido' => $recibido,
                'vuelto' => $vuelto,
                'referencia' => $datos['referencia'] ?? null,
                'user_id' => $usuario?->id,
            ]);
        });
    }

    /**
     * Devuelve dinero de un pedido (un cobro mal cargado, o antes de cancelar
     * uno pagado). Queda como pago NEGATIVO en la caja abierta: el historial
     * no se reescribe. Nunca más que lo pagado; en efectivo, nunca más que lo
     * que hay en el cajón.
     *
     * @param  array{metodo: string, monto: numeric-string|float, motivo: string}  $datos
     */
    public function devolver(Pedido $pedido, array $datos, ?User $usuario): Pago
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if ($pedido->estado === Pedido::ENTREGADO) {
                throw $this->conflicto('pedido', 'Un pedido entregado no se devuelve desde acá.');
            }

            $monto = round((float) $datos['monto'], 2);
            $pagado = $pedido->pagado();
            if ($monto > $pagado) {
                throw ValidationException::withMessages([
                    'pago.monto' => 'No se puede devolver más de lo pagado ('.$this->soles($pagado).').',
                ]);
            }

            if ($datos['metodo'] === Pago::EFECTIVO) {
                $this->exigirEfectivo($caja, $monto, 'pago.monto');
            }

            return Pago::create([
                'pedido_id' => $pedido->id,
                'caja_id' => $caja->id,
                'metodo' => $datos['metodo'],
                'monto' => -$monto,
                'motivo' => $datos['motivo'],
                'user_id' => $usuario?->id,
            ]);
        });
    }

    public function movimiento(string $tipo, float $monto, string $concepto, ?User $usuario): MovimientoCaja
    {
        return DB::transaction(function () use ($tipo, $monto, $concepto, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);

            if ($tipo === MovimientoCaja::EGRESO) {
                $this->exigirEfectivo($caja, $monto, 'movimiento.monto');
            }

            return MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => $tipo,
                'monto' => round($monto, 2),
                'concepto' => $concepto,
                'user_id' => $usuario?->id,
            ]);
        });
    }

    /**
     * Arqueo: el efectivo contado contra el esperado. La diferencia queda
     * registrada (faltante negativo, sobrante positivo); no se "corrige".
     */
    public function cerrar(Caja $caja, float $montoContado, ?string $observacion, ?User $usuario): Caja
    {
        return DB::transaction(function () use ($caja, $montoContado, $observacion, $usuario) {
            $caja = Caja::query()->whereKey($caja->id)->lockForUpdate()->firstOrFail();
            if (! $caja->estaAbierta()) {
                throw $this->conflicto('caja', 'Esta caja ya está cerrada.');
            }

            $esperado = $this->resumen($caja)['efectivo_esperado'];

            // Una diferencia sin explicación queda para siempre en el
            // historial sin que nadie sepa qué pasó.
            if (round($montoContado - $esperado, 2) != 0 && blank($observacion)) {
                throw ValidationException::withMessages([
                    'caja.observacion' => 'Con diferencia, anotá qué pasó antes de cerrar.',
                ]);
            }

            $caja->forceFill([
                'estado' => Caja::CERRADA,
                'abierta' => null,
                'monto_esperado' => $esperado,
                'monto_contado' => round($montoContado, 2),
                'diferencia' => round($montoContado - $esperado, 2),
                'observacion_cierre' => $observacion,
                'cerrada_por' => $usuario?->id,
                'cerrada_at' => now(),
            ])->save();

            return $caja;
        });
    }

    private function cajaAbiertaBloqueada(?User $usuario): Caja
    {
        $caja = $usuario
            ? Caja::query()->where('estado', Caja::ABIERTA)->where('abierta_por', $usuario->id)->lockForUpdate()->first()
            : null;

        if (! $caja) {
            throw $this->conflicto('caja', 'No tenés una caja abierta: abrí tu caja para registrar dinero.');
        }
        if ($caja->vencida()) {
            throw $this->conflicto('caja', 'Tu caja es del '.$this->dia($caja).': cerrala con su arqueo y abrí la de hoy.');
        }

        return $caja;
    }

    private function dia(Caja $caja): string
    {
        return $caja->abierta_at->copy()->timezone(config('app.zona_negocio'))->format('d/m');
    }

    private function exigirEfectivo(Caja $caja, float $monto, string $campo): void
    {
        $disponible = $this->resumen($caja)['efectivo_esperado'];

        if ($monto > $disponible) {
            throw ValidationException::withMessages([
                $campo => 'No hay suficiente efectivo en caja ('.$this->soles($disponible).').',
            ]);
        }
    }

    private function conflicto(string $campo, string $mensaje): ValidationException
    {
        return ValidationException::withMessages([$campo => $mensaje])->status(409);
    }

    private function soles(float $monto): string
    {
        return 'S/ '.number_format($monto, 2, '.', ',');
    }
}
