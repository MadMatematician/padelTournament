<?php

namespace Tests\Concerns;

/**
 * Per i test i cui scenari sono costruiti sulla formula "classica": gironi da 4 squadre al massimo e partite
 * al meglio di 3 set a 6 game con il tie-break a 7 punti, in tutte le fasi.
 *
 * I valori predefiniti dei tornei nuovi (config/tornei.php) sono altri e possono cambiare: chi usa questo trait
 * dichiara la formula su cui conta invece di dipendere da quelli. I predefiniti veri sono verificati in
 * Tests\Feature\Amministrazione\FormulaPredefinitaTest.
 *
 * Laravel chiama da solo `setUpFormulaClassica()` nel setUp dei test che usano il trait.
 */
trait FormulaClassica
{
    protected function setUpFormulaClassica(): void
    {
        config([
            'tornei.gironi.squadre.predefinito' => 4,
            'tornei.formato.predefinito' => [
                'set' => 3,
                'game' => 6,
                'tie_break' => true,
                'punti_tie_break' => 7,
                'due_game_scarto' => false,
            ],
            'tornei.formato.predefinito_per_fase' => [],
        ]);
    }
}
