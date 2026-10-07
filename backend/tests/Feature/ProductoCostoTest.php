<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Color;
use App\Models\Producto;
use App\Models\Talla;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * El costo de compra es POR VARIANTE: el proveedor cobra distinto según la
 * talla, y de ese costo sale la ganancia de cada una.
 */
class ProductoCostoTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    public function test_al_crear_cada_variante_nueva_entra_con_su_propio_costo(): void
    {
        $this->actuarCon('productos.store');
        $categoria = Categoria::create(['nombre' => 'Polos']);
        $color = Color::create(['nombre' => 'Blanco', 'hexadecimal' => '#FFFFFF']);
        $chica = Talla::create(['nombre' => '4', 'orden' => 1]);
        $grande = Talla::create(['nombre' => '12', 'orden' => 2]);

        $this->postJson('/api/productos', ['producto' => [
            'nombre' => 'Polo piqué',
            'categoria_id' => $categoria->id,
            'precio' => 30,
            'activo' => true,
            'variantes' => [
                ['talla_id' => $chica->id, 'color_id' => $color->id, 'sku' => 'PIQ-4', 'stock_inicial' => 5, 'costo_unitario' => 11],
                ['talla_id' => $grande->id, 'color_id' => $color->id, 'sku' => 'PIQ-12', 'stock_inicial' => 3, 'costo_unitario' => 16.5],
            ],
        ]])->assertCreated();

        $this->assertDatabaseHas('variantes', ['sku' => 'PIQ-4', 'stock' => 5, 'costo_promedio' => 11]);
        $this->assertDatabaseHas('variantes', ['sku' => 'PIQ-12', 'stock' => 3, 'costo_promedio' => 16.5]);
    }

    public function test_el_detalle_muestra_el_costo_promedio_a_quien_ve_inventario(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actuarCon('productos.show', 'inventario.index');
        $producto = Producto::where('nombre', 'Jogger cargo')->firstOrFail();

        $this->getJson("/api/productos/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('variantes.0.costo_promedio', '24.0000');
    }

    public function test_sin_permiso_de_inventario_el_costo_no_viaja(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actuarCon('productos.show');
        $producto = Producto::where('nombre', 'Jogger cargo')->firstOrFail();

        $variante = $this->getJson("/api/productos/{$producto->id}")->assertOk()->json('variantes.0');

        $this->assertArrayNotHasKey('costo_promedio', $variante);
    }
}
