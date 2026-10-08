<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPerRuoloTest extends TestCase
{
    use RefreshDatabase;

    public function test_gli_ospiti_vengono_mandati_al_login(): void
    {
        $this->get(route('amministrazione.dashboard'))->assertRedirect(route('login'));
        $this->get(route('area-utente.dashboard'))->assertRedirect(route('login'));
    }

    public function test_la_radice_porta_ognuno_alla_pagina_del_proprio_ruolo(): void
    {
        $this->actingAs(User::factory()->amministratore()->create())
            ->get('/')->assertRedirect(route('amministrazione.dashboard'));

        $this->actingAs(User::factory()->create())
            ->get('/')->assertRedirect(route('area-utente.dashboard'));
    }

    public function test_l_amministratore_vede_la_sua_pagina_con_gli_utenti(): void
    {
        $utente = User::factory()->create(['username' => 'mario.rossi']);

        $this->actingAs(User::factory()->amministratore()->create())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('Amministrazione')
            ->assertSee($utente->username);
    }

    public function test_l_utente_vede_la_sua_area(): void
    {
        $utente = User::factory()->create(['name' => 'Mario Rossi']);

        $this->actingAs($utente)
            ->get(route('area-utente.dashboard'))
            ->assertOk()
            ->assertSee('Ciao, Mario Rossi');
    }

    public function test_un_utente_non_entra_in_amministrazione(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('amministrazione.dashboard'))
            ->assertForbidden()
            ->assertSee('Accesso negato');
    }

    public function test_un_amministratore_non_entra_nell_area_utente(): void
    {
        $this->actingAs(User::factory()->amministratore()->create())
            ->get(route('area-utente.dashboard'))
            ->assertForbidden();
    }
}
