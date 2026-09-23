<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El aviso de privacidad lo publica la FCA en PDF y el sitio sólo lo enlaza,
 * así que lo que queda por probar es el banner que avisa al entrar: que salga
 * en todas las pantallas y que no vuelva a quien ya lo cerró.
 */
class AvisoPrivacidadTest extends TestCase
{
    /**
     * El banner vive en el pie de página, así que aparece en todas las
     * pantallas del sitio sin tocar los layouts.
     */
    public function test_la_landing_muestra_el_aviso_al_entrar(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-aviso-privacidad', false)
            ->assertSee(config('suif.enlaces.aviso_privacidad'), false);
    }

    /**
     * A quien ya lo cerró el servidor no se lo manda. Antes la marca vivía en
     * localStorage y sólo el navegador la conocía, así que el aviso se pintaba
     * siempre y se escondía después de cargar: parpadeaba en cada recarga.
     *
     * La cookie va sin cifrar porque la escribe JavaScript; si se olvidara
     * eximirla en bootstrap/app.php, EncryptCookies la descartaría y este test
     * sería el que avisara.
     */
    public function test_la_landing_no_manda_el_aviso_a_quien_ya_lo_cerro(): void
    {
        $this->withUnencryptedCookie('suif_aviso_privacidad', '1')
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-aviso-privacidad', false);
    }
}
