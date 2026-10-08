<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sorteggio di un torneo: coppie, gironi e partite.
     *
     * - `coppie`: un giocatore per fascia. `prestito_id` è il giocatore che in quella coppia gioca in prestito
     *   (ha già la sua coppia e ne completa una seconda, in un altro girone); `girone_id` si riempie
     *   quando si generano i gironi.
     * - `gironi`: gruppi di coppie; `posizione` è l'ordine (0 = Girone A).
     * - `partite`: calendario all'italiana di un girone; `ritorno` distingue le partite di ritorno.
     *
     * Eliminare un torneo elimina tutto il suo sorteggio. Un giocatore che sta in una coppia non si può
     * eliminare dall'anagrafica (prima va annullato il sorteggio).
     */
    public function up(): void
    {
        Schema::create('gironi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneo_id')->constrained('tornei')->cascadeOnDelete();
            $table->string('nome', 30);
            $table->unsignedSmallInteger('posizione');
            $table->timestamps();

            $table->unique(['torneo_id', 'posizione']);
        });

        Schema::create('coppie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneo_id')->constrained('tornei')->cascadeOnDelete();
            $table->foreignId('girone_id')->nullable()->constrained('gironi')->nullOnDelete();
            $table->foreignId('prima_fascia_id')->constrained('giocatori')->restrictOnDelete();
            $table->foreignId('seconda_fascia_id')->constrained('giocatori')->restrictOnDelete();
            $table->foreignId('prestito_id')->nullable()->constrained('giocatori')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['torneo_id', 'prima_fascia_id', 'seconda_fascia_id']);
        });

        Schema::create('partite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('girone_id')->constrained('gironi')->cascadeOnDelete();
            $table->unsignedSmallInteger('giornata');
            $table->boolean('ritorno')->default(false);
            $table->foreignId('coppia_a_id')->constrained('coppie')->cascadeOnDelete();
            $table->foreignId('coppia_b_id')->constrained('coppie')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['girone_id', 'giornata']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partite');
        Schema::dropIfExists('coppie');
        Schema::dropIfExists('gironi');
    }
};
