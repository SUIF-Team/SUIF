<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMXPath;

/**
 * Vue compila como plantilla el HTML de su nodo raíz: unas llaves dobles que
 * Blade imprimió como texto se ejecutan en el navegador de quien abre la
 * pantalla. Ver docs/superpowers/specs/2026-09-25-plantillas-vue-y-tramite-cerrado-design.md.
 *
 * PHPUnit no ejecuta Vue; lo que se prueba es la condición suficiente: que el
 * dato no llegue como texto compilable dentro de la raíz.
 */
trait RevisaPlantillasVue
{
    /**
     * @param string $atributo_raiz Atributo que identifica la raíz, sin corchetes.
     * @param string $marcador      Texto sin comillas simples.
     */
    protected function assertFueraDeLaPlantillaVue(string $html, string $atributo_raiz, string $marcador): void
    {
        /* loadHTML es el analizador HTML 4 de libxml: los elementos propios
           (<alertas>, <back-navigation>) y los atributos con «:» generan avisos
           que se silencian, pero el árbol se construye igual. */
        $documento = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $xpath = new DOMXPath($documento);
        $raiz = "//*[@{$atributo_raiz}]";

        $this->assertSame(1, $xpath->query($raiz)->length, "No se encontró la raíz [{$atributo_raiz}].");

        $expuestos = $xpath->query(
            "{$raiz}//text()[contains(., '{$marcador}')][not(ancestor::*[@v-pre])]"
        );

        $this->assertSame(
            0,
            $expuestos->length,
            "«{$marcador}» quedó como texto que Vue compila dentro de [{$atributo_raiz}]."
        );
    }
}
