<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Variante;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_un_catalogo_completo_para_probar(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(5, Producto::count());
        $this->assertGreaterThanOrEqual(3, \App\Models\Categoria::count());
        $this->assertGreaterThanOrEqual(4, \App\Models\Talla::count());
        $this->assertGreaterThanOrEqual(4, \App\Models\Color::count());
        $this->assertGreaterThanOrEqual(3, \App\Models\Cliente::count());
        // Cada producto con al menos una variante, y cada variante con su
        // código de barras (lo arma el evento del modelo).
        Producto::with('variantes')->get()->each(fn ($p) => $this->assertNotEmpty($p->variantes, $p->nombre));
        $this->assertSame(0, Variante::whereNull('codigo_barras')->count());
    }

    public function test_el_stock_entra_por_el_libro_de_inventario(): void
    {
        $this->seed(DemoSeeder::class);

        // Ni un stock "puesto a mano": cada unidad tiene su movimiento.
        Variante::all()->each(function (Variante $variante) {
            $this->assertSame(
                $variante->stock,
                (int) MovimientoInventario::where('variante_id', $variante->id)->sum('cantidad'),
                $variante->sku,
            );
        });
        $this->assertGreaterThan(0, Variante::sum('stock'));
        $this->assertSame(0, Variante::whereNull('costo_promedio')->count());
    }

    public function test_es_idempotente(): void
    {
        $this->seed(DemoSeeder::class);
        $productos = Producto::count();
        $variantes = Variante::count();
        $movimientos = MovimientoInventario::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($productos, Producto::count());
        $this->assertSame($variantes, Variante::count());
        // Correrlo dos veces no duplica el stock.
        $this->assertSame($movimientos, MovimientoInventario::count());
    }
}
