<?php

namespace Database\Seeders;

use App\Enums\Ruolo;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea l'amministratore iniziale (admin / admin).
 *
 * Credenziali provvisorie, valide solo per lo sviluppo: il seeder si rifiuta
 * di girare in produzione e non tocca un utente `admin` già esistente.
 */
class AdminSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public const PASSWORD_PROVVISORIA = 'admin';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('AdminSeeder ignorato: le credenziali admin/admin non vanno create in produzione.');

            return;
        }

        if (User::where('username', self::USERNAME)->exists()) {
            return;
        }

        $admin = new User([
            'name' => 'Amministratore',
            'username' => self::USERNAME,
            'email' => 'admin@example.com',
            'password' => self::PASSWORD_PROVVISORIA,
        ]);
        $admin->ruolo = Ruolo::Amministratore;
        $admin->email_verified_at = now();
        $admin->save();
    }
}
