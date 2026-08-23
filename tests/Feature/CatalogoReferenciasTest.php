<?php

namespace Tests\Feature;

use App\Servicios\CatalogoReferencias;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CatalogoReferenciasTest
 *
 * La DEC manda las referencias en un Excel con membrete institucional encima
 * de la tabla, así que el encabezado no es el primer renglón. Estas pruebas
 * cubren que se encuentre solo, que el archivo se rechace completo cuando le
 * falta una columna, y que nada de eso rompa la carga que ya funcionaba.
 */
class CatalogoReferenciasTest extends TestCase
{
    /** Membrete tal como lo exporta el archivo oficial: siete renglones. */
    private const MEMBRETE = [
        'UNIVERSIDAD NACIONAL AUTÓNOMA DE MÉXICO,,,',
        'FACULTAD DE CONTADURÍA Y ADMINISTRACIÓN,,,',
        'DIVISIÓN DE EDUCACIÓN CONTINUA,,,',
        ',,,',
        'CONTROL DE REFERENCIAS PROYECTO UIF,,,',
        ',,,',
        'Listado de referencias UIF,,,',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('referencia_bancaria');

        Schema::create('referencia_bancaria', function (Blueprint $table): void {
            $table->increments('reba_id_referencia_bancaria');
            $table->integer('reba_id_pago')->nullable();
            $table->string('reba_referencia', 20);
            $table->string('reba_path', 200)->nullable();
            $table->decimal('reba_monto', 10, 4)->nullable();
            $table->date('reba_vigencia')->nullable();
            $table->date('reba_fecha_emision')->nullable();
            $table->date('reba_fecha_carga');
            $table->time('reba_hora_carga');
            $table->date('reba_fecha_asignacion')->nullable();
            $table->time('reba_hora_asignacion')->nullable();
        });
    }

    public function test_carga_el_archivo_de_la_dec_con_su_membrete_encima_de_la_tabla(): void
    {
        $resultado = $this->importar($this->archivoOficial());

        $this->assertSame(10, $resultado['nuevas']);
        $this->assertSame(0, $resultado['omitidas']);
        $this->assertSame([], $resultado['errores']);
        $this->assertSame(10, DB::table('referencia_bancaria')->count());

        $this->assertDatabaseHas('referencia_bancaria', [
            'reba_referencia' => '4130326001856RJ30299',
            'reba_vigencia' => '2026-09-20',
            'reba_fecha_emision' => '2026-08-20',
        ]);
    }

    public function test_el_encabezado_se_reacomoda_si_la_dec_agrega_un_renglon_al_membrete(): void
    {
        /* La prueba de que no hay salto fijo de renglones: con un oficio
           encima, el encabezado baja de la fila 8 a la 9 y la carga sigue
           entera. Un salto fijo se habría comido la primera referencia. */
        $resultado = $this->importar(
            array_merge(['OFICIO DEC-2026-114,,,'], $this->archivoOficial())
        );

        $this->assertSame(10, $resultado['nuevas']);
        $this->assertDatabaseHas('referencia_bancaria', ['reba_referencia' => '4130326001856RJ30299']);
    }

    public function test_rechaza_el_archivo_sin_columna_de_vigencia_y_no_escribe_nada(): void
    {
        $renglones = array_merge(self::MEMBRETE, [
            'Fecha,Referencia,Importe',
            '20/08/2026,4130326001856RJ30299,100',
        ]);

        try {
            $this->importar($renglones);
            $this->fail('Se esperaba que el archivo incompleto fuera rechazado.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('vigencia', $excepcion->getMessage());
        }

        $this->assertSame(0, DB::table('referencia_bancaria')->count());
    }

    public function test_reclama_de_una_vez_todas_las_columnas_que_faltan(): void
    {
        $renglones = array_merge(self::MEMBRETE, [
            'Referencia',
            '4130326001856RJ30299',
        ]);

        $this->expectException(DomainException::class);

        try {
            $this->importar($renglones);
        } catch (DomainException $excepcion) {
            $mensaje = $excepcion->getMessage();

            $this->assertStringContainsString('fecha de emisión', $mensaje);
            $this->assertStringContainsString('importe', $mensaje);
            $this->assertStringContainsString('vigencia', $mensaje);
            $this->assertSame(0, DB::table('referencia_bancaria')->count());

            throw $excepcion;
        }
    }

    public function test_rechaza_el_archivo_si_un_renglon_no_trae_vigencia(): void
    {
        $renglones = $this->archivoOficial();
        $renglones[] = '20/08/2026,4130326001866RJ30212,100,';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('vigencia');

        $this->importar($renglones);
    }

    public function test_rechaza_el_archivo_si_un_renglon_no_trae_importe_numerico(): void
    {
        $renglones = $this->archivoOficial();
        $renglones[] = '20/08/2026,4130326001866RJ30212,sin costo,20/09/2026';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('importe');

        $this->importar($renglones);
    }

    public function test_rechaza_el_archivo_sin_encabezado_reconocible(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se encontró el encabezado');

        $this->importar(['4130326001856RJ30299,100', '4130326001857RJ30210,100']);
    }

    public function test_un_renglon_de_basura_despues_de_la_tabla_aborta_la_carga(): void
    {
        $renglones = $this->archivoOficial();
        $renglones[] = 'Total de referencias emitidas: 10,,,';

        $this->expectException(DomainException::class);

        $this->importar($renglones);
    }

    public function test_recargar_el_mismo_archivo_actualiza_sin_duplicar_ni_tocar_las_asignadas(): void
    {
        $this->importar($this->archivoOficial());

        /* La primera ya se entregó a una persona: su renglón no se toca. */
        DB::table('referencia_bancaria')
            ->where('reba_referencia', '4130326001856RJ30299')
            ->update(['reba_id_pago' => 77, 'reba_monto' => 7000]);

        $resultado = $this->importar($this->archivoOficial());

        $this->assertSame(0, $resultado['nuevas']);
        $this->assertSame(9, $resultado['actualizadas']);
        $this->assertSame(1, $resultado['omitidas']);
        $this->assertSame(10, DB::table('referencia_bancaria')->count());

        $this->assertSame(
            7000.0,
            (float) DB::table('referencia_bancaria')
                ->where('reba_referencia', '4130326001856RJ30299')
                ->value('reba_monto')
        );
    }

    public function test_acepta_la_fecha_como_numero_de_serie_de_excel(): void
    {
        /* 46254 = 20/08/2026 y 46285 = 20/09/2026, contados desde el
           30/12/1899. Hay conversores que dejan la fecha así. */
        $this->importar(array_merge(self::MEMBRETE, [
            'Fecha,Referencia,Importe,Vigencia',
            '46254,4130326001856RJ30299,100,46285',
        ]));

        $this->assertDatabaseHas('referencia_bancaria', [
            'reba_referencia' => '4130326001856RJ30299',
            'reba_fecha_emision' => '2026-08-20',
            'reba_vigencia' => '2026-09-20',
        ]);
    }

    public function test_acepta_punto_y_coma_como_separador(): void
    {
        $this->importar([
            'CONTROL DE REFERENCIAS PROYECTO UIF;;;',
            'Fecha;Referencia;Importe;Vigencia',
            '20/08/2026;4130326001856RJ30299;100;20/09/2026',
        ]);

        $this->assertDatabaseHas('referencia_bancaria', [
            'reba_referencia' => '4130326001856RJ30299',
            'reba_vigencia' => '2026-09-20',
        ]);
    }

    /**
     * El archivo real de la DEC —membrete, encabezado en la fila 8 y las diez
     * referencias— con la columna de vigencia que el sistema ahora exige. Las
     * fechas son las que imprimen los PDF del ZIP.
     *
     * @return array<int, string>
     */
    private function archivoOficial(): array
    {
        $renglones = array_merge(self::MEMBRETE, ['Fecha,Referencia,Importe,Vigencia']);

        foreach ([
            '4130326001856RJ30299', '4130326001857RJ30210', '4130326001858RJ30221',
            '4130326001859RJ30235', '4130326001860RJ30246', '4130326001861RJ30257',
            '4130326001862RJ30268', '4130326001863RJ30279', '4130326001864RJ30290',
            '4130326001865RJ30201',
        ] as $referencia) {
            $renglones[] = '20/08/2026,'.$referencia.',100,20/09/2026';
        }

        return $renglones;
    }

    /**
     * @param  array<int, string>  $renglones
     */
    private function importar(array $renglones): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'suif').'.csv';
        file_put_contents($ruta, implode("\r\n", $renglones)."\r\n");

        try {
            return app(CatalogoReferencias::class)->importarCatalogo(
                new UploadedFile($ruta, 'referencias.csv', 'text/csv', null, true)
            );
        } finally {
            @unlink($ruta);
        }
    }
}
