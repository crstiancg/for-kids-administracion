<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class InventarioMovimientosTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    public function test_sin_permiso_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/inventario')->assertForbidden();
    }

    public function test_filtra_por_producto_del_mas_nuevo_al_mas_viejo(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actuarCon('inventario.index');
        $producto = Producto::where('nombre', 'Jogger cargo')->firstOrFail();
        $esperados = MovimientoInventario::whereHas('variante', fn ($q) => $q->where('producto_id', $producto->id))
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $respuesta = $this->getJson("/api/inventario?producto_id={$producto->id}&rowsPerPage=100")
            ->assertOk()
            ->assertJsonPath('total', count($esperados));

        // Ni un movimiento de otro producto, y en el orden del libro.
        $this->assertSame($esperados, array_column($respuesta->json('data'), 'id'));
    }

    public function test_variantes_de_un_producto_sin_paginar_para_precargar_el_movimiento(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actuarCon('inventario.variantes');
        $producto = Producto::where('nombre', 'Jogger cargo')->firstOrFail();

        $respuesta = $this->getJson("/api/inventario/variantes?producto_id={$producto->id}&rowsPerPage=0")->assertOk();

        $this->assertEqualsCanonicalizing(
            $producto->variantes()->pluck('id')->all(),
            array_column($respuesta->json('data'), 'id'),
        );
        $this->assertArrayHasKey('stock', $respuesta->json('data.0'));
        $this->assertArrayHasKey('costo_promedio', $respuesta->json('data.0'));
    }
}
