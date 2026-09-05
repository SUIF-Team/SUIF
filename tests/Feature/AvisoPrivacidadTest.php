<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El aviso de privacidad es público: se consulta sin sesión iniciada y su
 * contenido cubre las ocho fracciones del artículo 21 de la Ley General de
 * Protección de Datos Personales en Posesión de Sujetos Obligados.
 */
class AvisoPrivacidadTest extends TestCase
{
    public function test_el_aviso_integral_es_publico_y_cubre_las_ocho_fracciones(): void
    {
        $respuesta = $this->get(route('aviso-privacidad'))->assertOk();

        foreach ([
            '1. Quién es responsable de tus datos',
            '2. Qué datos personales se tratan',
            '3. Con qué fundamento se tratan',
            '4. Para qué se usan tus datos',
            '5. Cómo ejercer tus derechos ARCO y de portabilidad',
            '6. Domicilio de la Unidad de Transparencia',
            '7. A quién se transfieren tus datos',
            '8. Cómo negarte a un uso o a una transferencia',
        ] as $seccion) {
            $respuesta->assertSee($seccion, false);
        }

        /* Lo que la ley pide distinguir: qué usos dependen del consentimiento
           y a quién se entregan los datos. */
        $respuesta->assertSee('Finalidades que requieren tu consentimiento', false);
        $respuesta->assertSee('Unidad de Inteligencia Financiera', false);
        $respuesta->assertSee('unidaddetransparencia@unam.mx', false);
    }

    public function test_el_aviso_simplificado_es_publico_y_remite_al_integral(): void
    {
        $this->get(route('aviso-privacidad.simplificado'))
            ->assertOk()
            ->assertSee('Aviso de privacidad simplificado', false)
            ->assertSee(route('aviso-privacidad'), false);
    }

    /**
     * El banner vive en el pie de página, así que aparece en todas las
     * pantallas del sitio sin tocar los layouts.
     */
    public function test_la_landing_muestra_el_aviso_al_entrar(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-aviso-privacidad', false)
            ->assertSee(route('aviso-privacidad'), false);
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
