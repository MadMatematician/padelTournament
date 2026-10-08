<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Iscrizioni: quali giocatori (anagrafica `giocatori`) partecipano a quale torneo.
     * Un giocatore può essere iscritto a più tornei, a ciascuno una sola volta.
     */
    public function up(): void
    {
        Schema::create('iscrizioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneo_id')->constrained('tornei')->cascadeOnDelete();
            $table->foreignId('giocatore_id')->constrained('giocatori')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['torneo_id', 'giocatore_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iscrizioni');
    }
};
