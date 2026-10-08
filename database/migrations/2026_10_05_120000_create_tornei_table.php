<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tornei. `tipologia` è una chiave di config/tornei.php → tipologie.
     */
    public function up(): void
    {
        Schema::create('tornei', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('tipologia', 30)->index();
            $table->date('data_inizio')->nullable();
            $table->unsignedTinyInteger('numero_turni');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tornei');
    }
};
