<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Circolo di appartenenza del giocatore: testo libero e facoltativo, usato per filtrare gli elenchi.
     */
    public function up(): void
    {
        Schema::table('giocatori', function (Blueprint $table) {
            $table->string('circolo', 80)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('giocatori', function (Blueprint $table) {
            $table->dropIndex(['circolo']);
            $table->dropColumn('circolo');
        });
    }
};
