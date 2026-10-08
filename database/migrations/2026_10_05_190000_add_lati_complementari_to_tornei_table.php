<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quanto contano i lati preferiti (destra / sinistra) nel sorteggio delle coppie di un torneo:
     * chiave di config/tornei.php → lati_complementari, scelta nella scheda "Formula".
     * Il valore predefinito ("ignora": sorteggio del tutto casuale) coincide con
     * config/tornei.php → tipologie.*.sorteggio.lati_complementari.
     */
    public function up(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->string('lati_complementari', 20)->default('ignora');
        });
    }

    public function down(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->dropColumn('lati_complementari');
        });
    }
};
