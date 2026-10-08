<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nelle finali le coppie si risorteggiano a ogni turno: oltre a quelle dei gironi, un torneo ha quindi
     * le coppie di ogni turno delle finali.
     *
     * - `fase`: la fase per cui la coppia è stata sorteggiata (`gironi` per quelle esistenti, poi `quarti`,
     *   `semifinali`…). Le coppie delle finali non hanno girone.
     * - `ripetuta`: i due giocatori avevano già fatto coppia nel torneo e non c'era un sorteggio che lo evitasse.
     *   Per questo l'unicità della coppia nel torneo diventa unicità nella fase.
     */
    public function up(): void
    {
        Schema::table('coppie', function (Blueprint $table) {
            $table->string('fase', 20)->default('gironi')->after('torneo_id');
            $table->boolean('ripetuta')->default(false)->after('prestito_id');

            $table->dropUnique(['torneo_id', 'prima_fascia_id', 'seconda_fascia_id']);
            $table->unique(['torneo_id', 'fase', 'prima_fascia_id', 'seconda_fascia_id'], 'coppie_fase_giocatori_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coppie', function (Blueprint $table) {
            $table->dropUnique('coppie_fase_giocatori_unique');
            $table->dropColumn(['fase', 'ripetuta']);
            $table->unique(['torneo_id', 'prima_fascia_id', 'seconda_fascia_id']);
        });
    }
};
