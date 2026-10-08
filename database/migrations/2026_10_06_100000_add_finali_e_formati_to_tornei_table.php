<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formula del torneo, seconda parte: accesso alle finali e formato delle partite.
     *
     * - `qualificate_per_girone`: quante squadre accedono alle finali da ogni girone;
     * - `criterio_ripescaggio`: come si scelgono le ripescate che completano il primo turno delle finali
     *   (chiave di config/tornei.php → finali.ripescaggi);
     * - `formati`: per ogni fase (gironi, sedicesimi, …, finale) set, game e tie-break delle partite;
     *   le fasi non impostate usano config/tornei.php → formato.predefinito.
     *
     * I default coincidono con config/tornei.php → finali.
     */
    public function up(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->unsignedTinyInteger('qualificate_per_girone')->default(2);
            $table->string('criterio_ripescaggio', 20)->default('punti');
            $table->json('formati')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->dropColumn(['qualificate_per_girone', 'criterio_ripescaggio', 'formati']);
        });
    }
};
