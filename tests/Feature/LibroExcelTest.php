<?php

namespace Tests\Feature;

use App\Support\LibroExcel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * LibroExcelTest
 *
 * Los reportes llevan datos que escribe la persona. Una cadena que empieza con
 * «=» no debe llegar al Excel de quien lo abre como fórmula —se ejecutaría—.
 * Al cambiar de binder, una cadena numérica debe seguir conservando su cero a
 * la izquierda y los montos deben seguir siendo números para poder sumarlos.
 */
class LibroExcelTest extends TestCase
{
    private const FORMULA = '=HYPERLINK("https://x.test","Ver")';

    public function test_una_cadena_con_igual_se_escribe_como_texto_y_no_como_formula(): void
    {
        $celda = $this->hoja()->getCell('A2');

        $this->assertSame(DataType::TYPE_STRING, $celda->getDataType());
        $this->assertSame(self::FORMULA, $celda->getValue());
    }

    public function test_una_cadena_numerica_conserva_el_cero_a_la_izquierda(): void
    {
        $celda = $this->hoja()->getCell('B2');

        $this->assertSame(DataType::TYPE_STRING, $celda->getDataType());
        $this->assertSame('01000', $celda->getValue());
    }

    public function test_los_montos_siguen_siendo_numeros(): void
    {
        $celda = $this->hoja()->getCell('C2');

        $this->assertSame(DataType::TYPE_NUMERIC, $celda->getDataType());
        $this->assertEquals(1500.5, $celda->getValue());
    }

    public function test_un_valor_nulo_deja_la_celda_vacia(): void
    {
        $this->assertNull($this->hoja()->getCell('D2')->getValue());
    }

    /**
     * Genera el reporte por el mismo camino que los administrativos y lo vuelve
     * a abrir, como lo haría Excel.
     */
    private function hoja(): Worksheet
    {
        $respuesta = app(LibroExcel::class)->descarga(
            'prueba',
            ['Razón social', 'Código postal', 'Monto', 'Fecha de pago'],
            [[self::FORMULA, '01000', 1500.5, null]]
        );

        $ruta = tempnam(sys_get_temp_dir(), 'suif-prueba-');
        file_put_contents($ruta, $respuesta->getContent());

        try {
            return IOFactory::load($ruta)->getActiveSheet();
        } finally {
            @unlink($ruta);
        }
    }
}
