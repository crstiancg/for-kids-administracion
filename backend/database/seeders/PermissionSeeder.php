<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Los permisos salen de las rutas (config/permisos.php). --prune borra
        // los que ya no son rutas, como los viejos admin-roles/admin-usuarios.
        Artisan::call('permisos:sync', ['--prune' => true]);

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'api']);

        // El Administrador tiene todos los permisos del sistema; se
        // re-sincroniza al sembrar para que reciba los de rutas nuevas.
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        // Vendedor: vende y cobra, pero nunca SACA plata de la caja (ni
        // egresos, ni devoluciones de dinero, ni cancelar pedidos cobrados:
        // eso es de un encargado). Los cambios sí: dejan saldo, no efectivo.
        // Sin costos: ni inventario ni reportes.
        // Los permisos se asignan SÓLO al crearlo: si después el admin lo
        // ajusta desde Roles, volver a sembrar no le pisa los cambios.
        $vendedor = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'api']);
        if ($vendedor->wasRecentlyCreated) {
            $vendedor->syncPermissions(Permission::where('guard_name', 'api')->whereIn('name', self::VENDEDOR)->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // El dashboard, el buscador y la campana son rutas libres: no van acá;
    // cada bloque se filtra con estos mismos permisos.
    public const VENDEDOR = [
        // Punto de venta y su caja (la propia: abrir y cerrar con arqueo).
        'ventas.store',
        'ventas.catalogo',
        'cajas.actual',
        'cajas.abrir',
        'cajas.cerrar',
        // Pedidos (WhatsApp, redes): armarlos, confirmarlos, cobrarlos y
        // entregarlos. Cambios de prenda.
        'pedidos.index',
        'pedidos.show',
        'pedidos.store',
        'pedidos.update',
        'pedidos.confirmar',
        'pedidos.entregar',
        'pedidos.pagos',
        'pedidos.cambios',
        'pedidos.cambios.preparar',
        // Clientes.
        'clientes.index',
        'clientes.show',
        'clientes.store',
        'clientes.update',
        'clientes.consultar-documento',
        // Ver el catálogo (sin costos: esos van con inventario.index).
        'productos.index',
        'productos.show',
        'inventario.variantes',
    ];
}
