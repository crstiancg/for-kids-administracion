<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'password' => env('ADMIN_PASSWORD', 'forkids'),
                'active' => true,
            ],
        );

        $prueba = User::firstOrCreate(
            ['email' => 'password@gmail.com'],
            [
                'name' => 'Usuario de Prueba',
                'username' => 'password',
                'password' => 'password',
                'active' => true,
            ],
        );

        $admin->assignRole('Administrador');
        $prueba->assignRole('Administrador');
    }
}
