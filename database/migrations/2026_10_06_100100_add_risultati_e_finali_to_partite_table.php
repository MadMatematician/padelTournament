<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le partite ricevono il risultato e ospitano anche le finali.
     *
     * - `torneo_id`: una partita delle finali non ha girone, quindi il torneo va scritto sulla partita;
     * - `fase` (App\Enums\Fase) e `posizione`: il turno e il posto dall'alto nel tabellone (0 per i gironi);
     * - `girone_id` e le due coppie diventano facoltativi: nelle finali i turni successivi al primo nascono vuoti
     *   e si riempiono man mano con le vincitrici;
     * - risultato: `risultato` è l'elenco dei set giocati ([{a, b, tb_a, tb_b}, …]); set e game totali e la
     *   vincitrice sono ricopiati in chiaro perché servono alle classifiche.
     *
     * Le coppie ricordano come sono entrate nelle finali (`finali`: diretta o ripescata) e con quale testa di serie.
     */
    public function up(): void
    {
        Schema::table('partite', function (Blueprint $table) {
            $table->foreignId('torneo_id')->nullable()->constrained('tornei')->cascadeOnDelete();
            $table->string('fase', 20)->default('gironi');
            $table->unsignedSmallInteger('posizione')->default(0);
            $table->json('risultato')->nullable();
            $table->unsignedTinyInteger('set_a')->nullable();
            $table->unsignedTinyInteger('set_b')->nullable();
            $table->unsignedSmallInteger('game_a')->nullable();
            $table->unsignedSmallInteger('game_b')->nullable();
            $table->foreignId('vincitrice_id')->nullable()->constrained('coppie')->nullOnDelete();

            $table->index(['torneo_id', 'fase', 'posizione']);
        });

        // Le partite già generate sono tutte di girone: il torneo è quello del loro girone.
        DB::table('partite')->update([
            'torneo_id' => DB::raw('(select torneo_id from gironi where gironi.id = partite.girone_id)'),
        ]);

        Schema::table('partite', function (Blueprint $table) {
            $table->unsignedBigInteger('torneo_id')->nullable(false)->change();
            $table->unsignedBigInteger('girone_id')->nullable()->change();
            $table->unsignedBigInteger('coppia_a_id')->nullable()->change();
            $table->unsignedBigInteger('coppia_b_id')->nullable()->change();
        });

        Schema::table('coppie', function (Blueprint $table) {
            $table->string('finali', 10)->nullable();
            $table->unsignedSmallInteger('testa_di_serie')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coppie', function (Blueprint $table) {
            $table->dropColumn(['finali', 'testa_di_serie']);
        });

        // Le partite delle finali non hanno posto nella struttura precedente.
        DB::table('partite')->where('fase', '!=', 'gironi')->delete();

        Schema::table('partite', function (Blueprint $table) {
            $table->unsignedBigInteger('girone_id')->nullable(false)->change();
            $table->unsignedBigInteger('coppia_a_id')->nullable(false)->change();
            $table->unsignedBigInteger('coppia_b_id')->nullable(false)->change();
        });

        Schema::table('partite', function (Blueprint $table) {
            $table->dropIndex(['torneo_id', 'fase', 'posizione']);
            $table->dropConstrainedForeignId('vincitrice_id');
            $table->dropConstrainedForeignId('torneo_id');
            $table->dropColumn(['fase', 'posizione', 'risultato', 'set_a', 'set_b', 'game_a', 'game_b']);
        });
    }
};
