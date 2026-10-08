<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Risorse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RisorseTest extends TestCase
{
    use RefreshDatabase;

    public function test_ogni_risorsa_locale_porta_la_release_dichiarata_in_config(): void
    {
        config(['risorse.release' => [
            'app' => '9.1.0',
            'bootstrap' => '9.2.0',
            'bootstrap-icons' => '9.3.0',
            'jquery' => '9.4.0',
            'archivo' => '9.5.0',
        ]]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('css/torneo.css?v=9.1.0', false)
            ->assertSee('js/torneo.js?v=9.1.0', false)
            ->assertSee('vendor/bootstrap/css/bootstrap.min.css?v=9.2.0', false)
            ->assertSee('vendor/bootstrap/js/bootstrap.bundle.min.js?v=9.2.0', false)
            ->assertSee('vendor/bootstrap-icons/bootstrap-icons.min.css?v=9.3.0', false)
            ->assertSee('vendor/jquery/jquery.min.js?v=9.4.0', false)
            ->assertSee('vendor/fonts/archivo/archivo-latin-wdth-normal.woff2?v=9.5.0', false);
    }

    public function test_anche_le_librerie_caricate_dai_componenti_portano_la_release(): void
    {
        config(['risorse.release.datatables' => '9.6.0']);

        $this->actingAs(User::factory()->amministratore()->create())
            ->get(route('amministrazione.dashboard'))
            ->assertOk()
            ->assertSee('vendor/datatables/css/dataTables.bootstrap5.min.css?v=9.6.0', false)
            ->assertSee('vendor/datatables/js/dataTables.min.js?v=9.6.0', false)
            ->assertSee('vendor/datatables/js/dataTables.bootstrap5.min.js?v=9.6.0', false);
    }

    public function test_tutte_le_dipendenze_in_public_vendor_hanno_una_release(): void
    {
        foreach (glob(public_path('vendor/*'), GLOB_ONLYDIR) as $cartella) {
            $nome = basename($cartella);

            // public/vendor/fonts contiene un carattere per sottocartella
            $dipendenze = $nome === 'fonts'
                ? array_map('basename', glob($cartella.'/*', GLOB_ONLYDIR))
                : [$nome];

            foreach ($dipendenze as $dipendenza) {
                $this->assertNotSame('', Risorse::release($dipendenza));
            }
        }
    }

    public function test_una_dipendenza_senza_release_viene_segnalata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Risorse::url('libreria-inesistente', 'vendor/libreria-inesistente/file.js');
    }
}
