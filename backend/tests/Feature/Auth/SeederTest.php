<?php

namespace Tests\Feature\Auth;

use Database\Seeders\ClientTokenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.password_client.id' => '9d1f6a3e-0000-4000-8000-000000000001',
            'passport.password_client.secret' => 'secret-de-prueba',
        ]);
    }

    public function test_el_seeder_deja_listo_el_login_del_admin_con_el_cliente_configurado(): void
    {
        $this->seed();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => '9d1f6a3e-0000-4000-8000-000000000001',
            'client_secret' => 'secret-de-prueba',
            'username' => 'admin',
            'password' => 'forkids',
            'scope' => '',
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_el_seeder_crea_el_usuario_de_prueba_con_email(): void
    {
        $this->seed();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => '9d1f6a3e-0000-4000-8000-000000000001',
            'client_secret' => 'secret-de-prueba',
            'username' => 'password@gmail.com',
            'password' => 'password',
            'scope' => '',
        ])->assertOk();
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('oauth_clients', 1);
    }

    public function test_sin_nada_en_el_env_siembra_el_cliente_por_defecto(): void
    {
        config([
            'passport.password_client.id' => null,
            'passport.password_client.secret' => null,
        ]);

        $this->seed();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => ClientTokenSeeder::DEFAULT_CLIENT_ID,
            'client_secret' => ClientTokenSeeder::DEFAULT_CLIENT_SECRET,
            'username' => 'password@gmail.com',
            'password' => 'password',
            'scope' => '',
        ])->assertOk();
    }
}
