<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge il nome utente (credenziale di accesso) e il ruolo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique();
            $table->string('ruolo', 20)->default('utente')->index();
        });

        // Eventuali utenti già presenti ricevono un nome utente provvisorio.
        DB::table('users')->whereNull('username')->orderBy('id')->get(['id'])->each(
            fn (object $utente) => DB::table('users')
                ->where('id', $utente->id)
                ->update(['username' => 'utente'.$utente->id])
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['ruolo']);
            $table->dropColumn(['username', 'ruolo']);
        });
    }
};
