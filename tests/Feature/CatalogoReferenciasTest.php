<?php

namespace Tests\Feature;

use App\Servicios\CatalogoReferencias;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * CatalogoReferenciasTest
 *
 * Las referencias entran en un solo paquete ZIP: el CSV que manda la DEC —con
 * su membrete institucional encima de la tabla, así que el encabezado no es el
 * primer renglón— y un PDF por referencia. Estas pruebas cubren que el
 * encabezado se encuentre solo, que el paquete se rechace entero cuando el CSV
 * y los formatos no cuadran uno a uno, y que un rechazo no deje nada escrito ni
 * en la base ni en el disco.
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

    /** Lo mínimo que el servicio acepta como PDF: la firma y algo de cuerpo. */
    private const PDF = "%PDF-1.4\nformato de pago de prueba\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('referencias');

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
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertSame(10, $resultado['total']);
        $this->assertSame(10, DB::table('referencia_bancaria')->count());

        $this->assertDatabaseHas('referencia_bancaria', [
            'reba_referencia' => '4130326001856RJ30299',
            'reba_vigencia' => '2026-09-20',
            'reba_fecha_emision' => '2026-08-20',
            'reba_path' => 'catalogo/4130326001856RJ30299.pdf',
        ]);

        Storage::disk('referencias')->assertExists('catalogo/4130326001856RJ30299.pdf');
    }

    public function test_ninguna_referencia_queda_sin_su_formato(): void
    {
        $this->importar($this->archivoOficial());

        $this->assertSame(
            0,
            DB::table('referencia_bancaria')->whereNull('reba_path')->count()
        );

        foreach (DB::table('referencia_bancaria')->pluck('reba_path') as $ruta) {
            Storage::disk('referencias')->assertExists($ruta);
        }
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
        Storage::disk('referencias')->assertDirectoryEmpty('/');
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

    public function test_recargar_el_mismo_paquete_actualiza_sin_duplicar(): void
    {
        $this->importar($this->archivoOficial());

        DB::table('referencia_bancaria')
            ->where('reba_referencia', '4130326001856RJ30299')
            ->update(['reba_monto' => 1]);

        $resultado = $this->importar($this->archivoOficial());

        $this->assertSame(0, $resultado['nuevas']);
        $this->assertSame(10, $resultado['actualizadas']);
        $this->assertSame(10, DB::table('referencia_bancaria')->count());

        $this->assertSame(
            100.0,
            (float) DB::table('referencia_bancaria')
                ->where('reba_referencia', '4130326001856RJ30299')
                ->value('reba_monto')
        );
    }

    public function test_rechaza_el_paquete_que_incluye_una_referencia_ya_entregada(): void
    {
        $this->importar($this->archivoOficial());

        /* La primera ya se entregó a una persona: su formato es el que trae en
           la mano para pagar en ventanilla y el paquete no lo puede pisar. */
        DB::table('referencia_bancaria')
            ->where('reba_referencia', '4130326001856RJ30299')
            ->update(['reba_id_pago' => 77, 'reba_monto' => 7000]);

        try {
            $this->importar($this->archivoOficial());
            $this->fail('Se esperaba que el paquete con una referencia entregada fuera rechazado.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('4130326001856RJ30299', $excepcion->getMessage());
        }

        $this->assertSame(
            7000.0,
            (float) DB::table('referencia_bancaria')
                ->where('reba_referencia', '4130326001856RJ30299')
                ->value('reba_monto')
        );
    }

    public function test_rechaza_el_paquete_al_que_le_falta_un_pdf(): void
    {
        $renglones = $this->archivoOficial();
        $formatos = $this->referenciasDe($renglones);
        array_pop($formatos);

        try {
            $this->importar($renglones, $formatos);
            $this->fail('Se esperaba que el paquete sin uno de los PDF fuera rechazado.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('faltan los PDF', $excepcion->getMessage());
            $this->assertStringContainsString('4130326001865RJ30201', $excepcion->getMessage());
        }

        $this->assertSame(0, DB::table('referencia_bancaria')->count());
        Storage::disk('referencias')->assertDirectoryEmpty('/');
    }

    public function test_rechaza_el_paquete_con_un_pdf_que_el_csv_no_menciona(): void
    {
        $renglones = $this->archivoOficial();
        $formatos = $this->referenciasDe($renglones);
        $formatos[] = '4130326001899RJ30288';

        try {
            $this->importar($renglones, $formatos);
            $this->fail('Se esperaba que el PDF de sobra fuera rechazado.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('sobran los PDF', $excepcion->getMessage());
            $this->assertStringContainsString('4130326001899RJ30288', $excepcion->getMessage());
        }

        $this->assertSame(0, DB::table('referencia_bancaria')->count());
        Storage::disk('referencias')->assertDirectoryEmpty('/');
    }

    public function test_rechaza_el_csv_que_repite_una_referencia(): void
    {
        $renglones = $this->archivoOficial();
        $renglones[] = '20/08/2026,4130326001856RJ30299,100,20/09/2026';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('repite');

        $this->importar($renglones);
    }

    public function test_rechaza_el_paquete_sin_csv(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no trae el archivo CSV');

        $this->importarEntradas(['4130326001856RJ30299.pdf' => self::PDF]);
    }

    public function test_rechaza_el_paquete_con_dos_csv(): void
    {
        $csv = implode("\r\n", $this->archivoOficial())."\r\n";

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('más de un archivo CSV');

        $this->importarEntradas([
            'referencias.csv' => $csv,
            'referencias-corregido.csv' => $csv,
            '4130326001856RJ30299.pdf' => self::PDF,
        ]);
    }

    public function test_un_pdf_falso_aborta_y_no_deja_los_formatos_ya_escritos(): void
    {
        /* El primero es un PDF de verdad y alcanza a escribirse; el segundo no,
           y al abortar tiene que llevarse al primero por delante. */
        $renglones = array_merge(self::MEMBRETE, [
            'Fecha,Referencia,Importe,Vigencia',
            '20/08/2026,4130326001856RJ30299,100,20/09/2026',
            '20/08/2026,4130326001857RJ30210,100,20/09/2026',
        ]);

        try {
            $this->importarEntradas([
                'referencias.csv' => implode("\r\n", $renglones)."\r\n",
                '4130326001856RJ30299.pdf' => self::PDF,
                '4130326001857RJ30210.pdf' => 'esto no es un PDF',
            ]);
            $this->fail('Se esperaba que el archivo que no es PDF fuera rechazado.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('no es un archivo PDF', $excepcion->getMessage());
        }

        $this->assertSame(0, DB::table('referencia_bancaria')->count());
        Storage::disk('referencias')->assertDirectoryEmpty('/');
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
     * fechas son las que imprimen los PDF del paquete.
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
     * Arma el paquete con el CSV y un PDF por referencia.
     *
     * Por omisión los formatos son los que nombra el propio CSV, que es el
     * paquete bien hecho; se pasan a mano cuando la prueba necesita que falte o
     * sobre alguno.
     *
     * @param  array<int, string>  $renglones
     * @param  array<int, string>|null  $formatos
     */
    private function importar(array $renglones, ?array $formatos = null): array
    {
        $entradas = ['referencias.csv' => implode("\r\n", $renglones)."\r\n"];

        foreach ($formatos ?? $this->referenciasDe($renglones) as $referencia) {
            $entradas[$referencia.'.pdf'] = self::PDF;
        }

        return $this->importarEntradas($entradas);
    }

    /**
     * @param  array<string, string>  $entradas
     */
    private function importarEntradas(array $entradas): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'suif').'.zip';

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entradas as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }

        $zip->close();

        try {
            return app(CatalogoReferencias::class)->importarPaquete(
                new UploadedFile($ruta, 'referencias.zip', 'application/zip', null, true)
            );
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * Las referencias que nombra el CSV, para generarles su PDF.
     *
     * @param  array<int, string>  $renglones
     * @return array<int, string>
     */
    private function referenciasDe(array $renglones): array
    {
        preg_match_all('/\b\d{13}[A-Z]{2}\d{5}\b/', implode("\n", $renglones), $coincidencias);

        return array_values(array_unique($coincidencias[0]));
    }
}
