<?php

namespace Tests\Feature\Auth;

use App\Enums\Ruolo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrazioneTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_SICURA = 'Padel-2026!ok';

    /**
     * @param  array<string, mixed>  $modifiche
     * @return array<string, mixed>
     */
    private function dati(array $modifiche = []): array
    {
        return array_merge([
            'name' => 'Mario Rossi',
            'username' => 'mario.rossi',
            'email' => 'mario@example.com',
            'password' => self::PASSWORD_SICURA,
            'password_confirmation' => self::PASSWORD_SICURA,
        ], $modifiche);
    }

    public function test_la_pagina_di_registrazione_si_apre(): void
    {
        $this->get(route('registrazione'))
            ->assertOk()
            ->assertSee('Conferma password')
            ->assertSee('almeno '.config('sicurezza.password.lunghezza_minima').' caratteri');
    }

    public function test_la_registrazione_crea_un_utente_e_lo_porta_nella_sua_area(): void
    {
        $this->post(route('registrazione.store'), $this->dati())
            ->assertRedirect(route('area-utente.dashboard'));

        $utente = User::where('username', 'mario.rossi')->sole();

        $this->assertAuthenticatedAs($utente);
        $this->assertSame(Ruolo::Utente, $utente->ruolo);
        $this->assertNotSame(self::PASSWORD_SICURA, $utente->password); // salvata come hash
    }

    public function test_il_ruolo_non_si_puo_scegliere_dal_form(): void
    {
        $this->post(route('registrazione.store'), $this->dati(['ruolo' => 'amministratore']));

        $this->assertSame(Ruolo::Utente, User::where('username', 'mario.rossi')->sole()->ruolo);
    }

    public function test_il_nome_utente_viene_salvato_in_minuscolo(): void
    {
        $this->post(route('registrazione.store'), $this->dati(['username' => 'Mario.Rossi']));

        $this->assertDatabaseHas('users', ['username' => 'mario.rossi']);
    }

    #[DataProvider('passwordDeboli')]
    public function test_le_password_deboli_vengono_rifiutate(string $password): void
    {
        $this->post(route('registrazione.store'), $this->dati([
            'password' => $password,
            'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function passwordDeboli(): array
    {
        return [
            'come quella provvisoria di admin' => ['admin'],
            'troppo corta' => ['Aa1!aaa'],
            'senza maiuscole' => ['padel-2026!ok'],
            'senza minuscole' => ['PADEL-2026!OK'],
            'senza numeri' => ['Padel-torneo!ok'],
            'senza simboli' => ['Padel2026torneo'],
        ];
    }

    public function test_la_conferma_deve_coincidere(): void
    {
        $this->post(route('registrazione.store'), $this->dati(['password_confirmation' => 'Altra-2026!ok']))
            ->assertSessionHasErrors('password');
    }

    public function test_nome_utente_ed_email_devono_essere_unici(): void
    {
        User::factory()->create(['username' => 'mario.rossi', 'email' => 'mario@example.com']);

        $this->post(route('registrazione.store'), $this->dati(['username' => 'MARIO.ROSSI', 'email' => 'Mario@Example.com']))
            ->assertSessionHasErrors(['username', 'email']);
    }

    #[DataProvider('nomiUtenteNonValidi')]
    public function test_i_nomi_utente_non_validi_vengono_rifiutati(string $username): void
    {
        $this->post(route('registrazione.store'), $this->dati(['username' => $username]))
            ->assertSessionHasErrors('username');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function nomiUtenteNonValidi(): array
    {
        return [
            'troppo corto' => ['ab'],
            'con spazi' => ['mario rossi'],
            'con caratteri speciali' => ['mario@rossi'],
            'inizia con un punto' => ['.mario'],
            'troppo lungo' => [str_repeat('a', 31)],
        ];
    }
}
