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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
