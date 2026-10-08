<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * I tornei nuovi nascono con gironi da 3 squadre al massimo (prima 4): il valore coincide con
     * config/tornei.php → gironi.squadre.predefinito. I tornei già creati tengono il valore che hanno.
     */
    public function up(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->unsignedTinyInteger('squadre_per_girone')->default(3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tornei', function (Blueprint $table) {
            $table->unsignedTinyInteger('squadre_per_girone')->default(4)->change();
        });
    }
};
