<?php

namespace Tests\Unit;

use App\Support\NombrePersona;
use PHPUnit\Framework\TestCase;

/**
 * NombrePersonaTest
 *
 * El orden con el que la zona administrativa escribe un nombre: apellido
 * paterno, apellido materno y nombre(s). De esta clase dependen las seis
 * bandejas, las pantallas de detalle, los cuatro reportes en Excel y la lista
 * de asistencia, así que conviene que su contrato esté fijado en un solo lugar.
 *
 * No extiende Tests\TestCase: no toca base de datos ni necesita la aplicación.
 */
class NombrePersonaTest extends TestCase
{
    public function test_los_apellidos_van_antes_que_el_nombre(): void
    {
        $this->assertSame(
            'Alvarez Prueba Ana',
            NombrePersona::administrativo('Alvarez', 'Prueba', 'Ana')
        );
    }

    /**
     * APELLIDO_MATERNO puede faltar y el hueco no debe dejar un espacio doble:
     * en una lista ordenada alfabéticamente ese espacio se ve y además rompe
     * cualquier comparación contra el nombre escrito a mano.
     */
    public function test_una_parte_vacia_no_deja_espacios_de_mas(): void
    {
        $this->assertSame('Alvarez Ana', NombrePersona::administrativo('Alvarez', '', 'Ana'));
        $this->assertSame('Alvarez Ana', NombrePersona::administrativo('Alvarez', null, 'Ana'));
        $this->assertSame('Prueba Ana', NombrePersona::administrativo(null, 'Prueba', 'Ana'));
        $this->assertSame('', NombrePersona::administrativo(null, null, null));
    }

    public function test_recorta_los_espacios_con_los_que_se_capturo(): void
    {
        $this->assertSame(
            'Alvarez Prueba Ana',
            NombrePersona::administrativo('  Alvarez ', ' Prueba', 'Ana  ')
        );
    }

    /**
     * El nombre de pila va completo al final: no se parte ni se abrevia.
     */
    public function test_el_nombre_compuesto_se_conserva_entero(): void
    {
        $this->assertSame(
            'Guzmán Íñiguez Gabriela María',
            NombrePersona::administrativo('Guzmán', 'Íñiguez', 'Gabriela María')
        );
    }
}
