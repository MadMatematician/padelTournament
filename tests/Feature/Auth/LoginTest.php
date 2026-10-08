<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_di_login_si_apre(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Nome utente')
            ->assertSee('Password');
    }

    public function test_admin_admin_accede_e_atterra_in_amministrazione(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post(route('login.store'), ['username' => 'admin', 'password' => 'admin'])
            ->assertRedirect(route('amministrazione.dashboard'));

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->eAmministratore());
    }

    public function test_un_utente_atterra_nella_sua_area(): void
    {
        $utente = User::factory()->create(['username' => 'mario.rossi']);

        $this->post(route('login.store'), ['username' => 'mario.rossi', 'password' => 'password'])
            ->assertRedirect(route('area-utente.dashboard'));

        $this->assertAuthenticatedAs($utente);
    }

    public function test_il_nome_utente_non_distingue_maiuscole_e_minuscole(): void
    {
        $utente = User::factory()->create(['username' => 'mario.rossi']);

        $this->post(route('login.store'), ['username' => ' Mario.Rossi ', 'password' => 'password'])
            ->assertRedirect(route('area-utente.dashboard'));

        $this->assertAuthenticatedAs($utente);
    }

    public function test_password_errata_non_accede(): void
    {
        User::factory()->create(['username' => 'mario.rossi']);

        $this->from(route('login'))
            ->post(route('login.store'), ['username' => 'mario.rossi', 'password' => 'sbagliata'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_non_si_accede_con_l_email(): void
    {
        $utente = User::factory()->create();

        $this->post(route('login.store'), ['username' => $utente->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_dopo_troppi_tentativi_l_accesso_viene_bloccato(): void
    {
        User::factory()->create(['username' => 'mario.rossi']);
        $tentativi = config('sicurezza.login.tentativi_massimi');

        for ($i = 0; $i < $tentativi; $i++) {
            $this->post(route('login.store'), ['username' => 'mario.rossi', 'password' => 'sbagliata']);
        }

        // Anche la password giusta viene rifiutata finché dura il blocco.
        $this->post(route('login.store'), ['username' => 'mario.rossi', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_chi_e_gia_autenticato_non_rivede_il_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }
}
