<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La landing se pinta entera en el servidor.
 *
 * Su contenido vivía dentro de public/assets/js/pages/home.js, así que el HTML
 * salía con las llaves de Vue en crudo y al recargar se veían un instante
 * {{ card.title }}, {{ step.number }} y {{ faq.q }} antes de que la app montara.
 */
class LandingTest extends TestCase
{
    public function test_la_landing_llega_con_su_contenido_ya_pintado(): void
    {
        $respuesta = $this->get(route('home'))->assertOk();

        /* Uno de cada bloque: tarjetas, pasos y preguntas frecuentes. */
        $respuesta->assertSee('Comprobante de pago', false);
        $respuesta->assertSee('Validación de pago', false);
        $respuesta->assertSee('¿Quiénes pueden obtener la Certificación?', false);

        /* Nada que sustituir después de cargar. */
        $respuesta->assertDontSee('{{ card.title }}', false);
        $respuesta->assertDontSee('{{ step.number }}', false);
        $respuesta->assertDontSee('{{ faq.q }}', false);
        $respuesta->assertDontSee('assets/js/pages/home.js', false);

        /* El acordeón sigue abriendo en la primera pregunta y sólo en esa. */
        $respuesta->assertSee('class="accordion-collapse collapse show"', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), 'collapse show'));
    }

    public function test_la_landing_no_apunta_a_un_servidor_escrito_a_mano(): void
    {
        $respuesta = $this->get(route('home'))->assertOk();

        /* El botón del proceso llevaba la IP del servidor: se rompía en
           cualquier otro entorno y al cambiar de dirección. */
        $respuesta->assertDontSee('http://132.247.218.60', false);
        $respuesta->assertSee('href="'.route('login').'" class="btn btn-pill btn-gold', false);
    }

    public function test_las_hojas_de_estilo_externas_llevan_integrity(): void
    {
        $contenido = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/<link[^>]+href="https:\/\/cdn[^"]+"[^>]*>/', $contenido, $etiquetas);

        $this->assertCount(2, $etiquetas[0]);
        foreach ($etiquetas[0] as $etiqueta) {
            $this->assertStringContainsString('integrity="sha', $etiqueta);
            $this->assertStringContainsString('crossorigin="anonymous"', $etiqueta);
        }
    }
}
