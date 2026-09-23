<?php

namespace App\Support;

/**
 * NombrePersona
 *
 * Responsabilidad: escribir el nombre de una persona como lo lee la zona
 * administrativa: apellido paterno, apellido materno y nombre(s).
 *
 * Es el orden con el que se busca a alguien en un padrón —por su apellido— y
 * el que hace que una lista ordenada alfabéticamente se lea de corrido. En el
 * lado de la persona no se usa: ahí el nombre se saluda en orden natural, y de
 * eso siguen encargándose Persona::nombreCompleto() y FormatoPreRegistro.
 *
 * Vive en una sola clase porque este armado estaba copiado en ocho servicios,
 * cada uno con su propio trim(implode(array_filter(...))). Con una copia por
 * pantalla, cambiar el orden obligaba a encontrarlas todas, y la que se
 * escapara dejaría una bandeja escribiendo el nombre al revés que las demás.
 */
class NombrePersona
{
    /**
     * Los tres campos van en el orden en que se imprimen, para que la llamada
     * se lea igual que su resultado. Cualquiera puede venir vacío o nulo
     * —APELLIDO_MATERNO no siempre está— y entonces no deja doble espacio.
     */
    public static function administrativo(mixed $paterno, mixed $materno, mixed $nombre): string
    {
        $partes = array_map(
            fn (mixed $parte): string => trim((string) $parte),
            [$paterno, $materno, $nombre]
        );

        return implode(' ', array_filter(
            $partes,
            fn (string $parte): bool => $parte !== ''
        ));
    }
}
