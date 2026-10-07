<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Services\Inventario;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba para usar el sistema a mano: catálogo, stock y clientes.
 * NO va en DatabaseSeeder (producción no lo quiere, y ahí WithoutModelEvents
 * dejaría las variantes sin código de barras). Se corre aparte:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Idempotente: el stock inicial entra sólo para las variantes que crea, así
 * que correrlo dos veces no duplica nada. Al final carga 30 días de ventas
 * de prueba (DemoVentasSeeder).
 */
class DemoSeeder extends Seeder
{
    private const TALLAS = ['2' => 1, '4' => 2, '6' => 3, '8' => 4, '10' => 5, 'S' => 6, 'M' => 7, 'L' => 8];

    private const COLORES = [
        'Blanco' => '#FFFFFF',
        'Negro' => '#111827',
        'Rojo' => '#DC2626',
        'Azul' => '#2563EB',
        'Rosado' => '#EC4899',
        'Verde' => '#16A34A',
    ];

    /** [padre => [hijas]] */
    private const CATEGORIAS = [
        'Niños' => ['Polos', 'Pantalones'],
        'Niñas' => ['Vestidos', 'Blusas'],
        'Bebés' => ['Bodies'],
    ];

    /** nombre => [padre, hija, precio, costo, tallas, colores, descripcion] */
    private const PRODUCTOS = [
        'Polo básico algodón' => ['Niños', 'Polos', 25, 12, ['4', '6', '8', '10'], ['Blanco', 'Negro', 'Azul'], 'Polo de algodón peinado, cuello redondo.'],
        'Polo estampado dinosaurio' => ['Niños', 'Polos', 32, 15, ['2', '4', '6'], ['Verde', 'Azul'], 'Estampado al frente, ideal para el día a día.'],
        'Jogger cargo' => ['Niños', 'Pantalones', 49, 24, ['6', '8', '10'], ['Negro', 'Verde'], 'Jogger con bolsillos laterales y puño elástico.'],
        'Vestido floral' => ['Niñas', 'Vestidos', 69, 33, ['4', '6', '8'], ['Rosado', 'Blanco'], 'Vestido de verano con estampado floral.'],
        'Blusa con vuelos' => ['Niñas', 'Blusas', 39, 18, ['S', 'M', 'L'], ['Blanco', 'Rosado', 'Rojo'], null],
        'Body manga larga' => ['Bebés', 'Bodies', 22, 9, ['S', 'M'], ['Blanco', 'Azul', 'Rosado'], 'Body de algodón con broches.'],
    ];

    private const CLIENTES = [
        ['DNI', '70123456', 'María Quispe Mamani', '951234567'],
        ['DNI', '70234567', 'José Condori Apaza', '952345678'],
        ['RUC', '20601234567', 'Comercial Los Andes S.A.C.', '051365432'],
        [null, null, 'Rosa (WhatsApp)', '953456789'],
    ];

    public function run(Inventario $inventario): void
    {
        $tallas = collect(self::TALLAS)
            ->map(fn ($orden, $nombre) => Talla::firstOrCreate(['nombre' => (string) $nombre], ['orden' => $orden]));
        $colores = collect(self::COLORES)
            ->map(fn ($hex, $nombre) => Color::firstOrCreate(['nombre' => $nombre], ['hexadecimal' => $hex]));

        $categorias = [];
        foreach (self::CATEGORIAS as $padre => $hijas) {
            $raiz = Categoria::firstOrCreate(['nombre' => $padre, 'parent_id' => null]);
            foreach ($hijas as $hija) {
                $categorias["{$padre}/{$hija}"] = Categoria::firstOrCreate(['nombre' => $hija, 'parent_id' => $raiz->id]);
            }
        }

        $lineas = [];
        foreach (self::PRODUCTOS as $nombre => [$padre, $hija, $precio, $costo, $tallasProducto, $coloresProducto, $descripcion]) {
            $producto = Producto::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias["{$padre}/{$hija}"]->id,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'activo' => true,
            ]);

            foreach ($tallasProducto as $talla) {
                foreach ($coloresProducto as $color) {
                    $variante = $producto->variantes()->firstOrCreate(
                        ['talla_id' => $tallas[$talla]->id, 'color_id' => $colores[$color]->id],
                        ['sku' => sprintf('DEMO-%d-%s-%s', $producto->id, $talla, strtoupper(substr($color, 0, 3)))],
                    );

                    if ($variante->wasRecentlyCreated) {
                        $lineas[] = ['variante_id' => $variante->id, 'cantidad' => random_int(3, 20), 'costo_unitario' => $costo];
                    }
                }
            }
        }

        if ($lineas !== []) {
            $inventario->entrada($lineas, 'DEMO', 'Stock inicial de prueba', User::where('username', 'admin')->first());
        }

        foreach (self::CLIENTES as [$tipo, $numero, $nombre, $telefono]) {
            Cliente::firstOrCreate(
                ['nombre' => $nombre],
                ['tipo_documento' => $tipo, 'numero_documento' => $numero, 'telefono' => $telefono],
            );
        }

        $this->call(DemoVentasSeeder::class);
    }
}
