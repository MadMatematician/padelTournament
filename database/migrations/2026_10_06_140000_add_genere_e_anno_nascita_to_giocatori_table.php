<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Genere (chiave di config/tornei.php → giocatori.generi) e anno di nascita del giocatore.
     *
     * Il form li richiede sempre; le colonne restano facoltative solo perché le schede create
     * prima di questa migrazione non li hanno.
     */
    public function up(): void
    {
        Schema::table('giocatori', function (Blueprint $table) {
            $table->string('genere', 10)->nullable();
            $table->unsignedSmallInteger('anno_nascita')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('giocatori', function (Blueprint $table) {
            $table->dropColumn(['genere', 'anno_nascita']);
        });
    }
};
