<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(
            route('b2c.register')
        );

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(
            route('b2c.register.store'),
            [
                'name' => 'Usuario',
                'apellido_paterno' => 'Prueba',
                'email' => 'usuario.prueba@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );

        $this->assertAuthenticated();

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    'usuario.prueba@example.com',
            ]
        );

        $response->assertRedirect(
            route('b2c.dashboard')
        );
    }
}