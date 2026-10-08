<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formula del torneo, impostata nella pagina di gestione: quante squadre al massimo in un girone
     * e se le partite del girone sono di sola andata o di andata e ritorno.
     * I valori predefiniti corrispondono a config/tornei.php → gironi.
     */
    public function up(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->unsignedTinyInteger('squadre_per_girone')->default(4);
            $table->boolean('andata_ritorno')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->dropColumn(['squadre_per_girone', 'andata_ritorno']);
        });
    }
};
