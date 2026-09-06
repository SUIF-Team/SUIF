<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * ComprobanteFiscal
 *
 * Responsabilidad: el comprobante que la persona pide de su pago —ticket o
 * CFDI— y, cuando pide CFDI, los datos con los que se le factura.
 *
 * La elección vive en PAGO.PAGO_USO_CFDI, que es booleano: NULL es que
 * todavía no elige —pedir comprobante no es obligatorio y el trámite sigue
 * igual—, FALSE es ticket sin efectos fiscales y TRUE es CFDI de gastos en
 * general.
 *
 * Vive del lado de la persona y no en App\Support\Admin porque quien escribe
 * es ella: no hay decisión administrativa que registrar y ESTADO_PAGO no se
 * toca. El molde es GestionSedes::seleccionarParaUsuario(), que resuelve la
 * otra elección irreversible del trámite.
 */
class ComprobanteFiscal
{
    public const TICKET = 'ticket';

    public const CFDI = 'cfdi';

    /**
     * El valor tal como lo devuelve el motor: PostgreSQL entrega true/false,
     * SQLite —el de las pruebas— 1/0, y un dump viejo podría traer texto. Es
     * el único lugar del código que necesita saberlo.
     */
    public static function normalizarUsoCfdi(mixed $valor): ?bool
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        return in_array(strtolower((string) $valor), ['1', 't', 'true'], true);
    }

    /**
     * La misma información en el vocabulario de la interfaz.
     */
    public static function tipoDesdeUsoCfdi(mixed $valor): ?string
    {
        $uso = self::normalizarUsoCfdi($valor);

        if ($uso === null) {
            return null;
        }

        return $uso ? self::CFDI : self::TICKET;
    }

    /**
     * Registra la elección, que es definitiva.
     */
    public function registrarEleccion(int $id_usuario, string $tipo): void
    {
        if (!in_array($tipo, [self::TICKET, self::CFDI], true)) {
            throw new DomainException('Selecciona ticket o CFDI.');
        }

        $uso = $tipo === self::CFDI;

        DB::transaction(function () use ($id_usuario, $uso): void {
            $pago = $this->pagoBloqueadoDeUsuario($id_usuario);
            $this->verificarPagoValidado((int) $pago->pago_id_pago);

            $actual = self::normalizarUsoCfdi($pago->pago_uso_cfdi);

            /* Repetir la misma elección no es un error: un doble clic o el
               reenvío del formulario no deben reventar la pantalla. */
            if ($actual === $uso) {
                return;
            }

            if ($actual !== null) {
                throw new DomainException('Ya elegiste el tipo de comprobante y no puede modificarse.');
            }

            /* El whereNull no es adorno: SQLite ignora lockForUpdate en
               silencio, así que en las pruebas es lo único que serializa dos
               peticiones simultáneas. En PostgreSQL se suman las dos. */
            DB::table('pago')
                ->where('pago_id_pago', $pago->pago_id_pago)
                ->whereNull('pago_uso_cfdi')
                ->update(['pago_uso_cfdi' => $uso]);
        });
    }

    /**
     * Da de alta el renglón de DATO_FISCAL y lo liga al pago.
     *
     * @param array{razon_social: string, persona_moral: string, regimen_fiscal: int|string,
     *              codigo_postal: string, rfc: string, correo_cfdi: string} $datos
     */
    public function guardarDatosFiscales(int $id_usuario, array $datos): void
    {
        DB::transaction(function () use ($id_usuario, $datos): void {
            $pago = $this->pagoBloqueadoDeUsuario($id_usuario);
            $this->verificarPagoValidado((int) $pago->pago_id_pago);

            if (self::normalizarUsoCfdi($pago->pago_uso_cfdi) !== true) {
                throw new DomainException('Elige la opción CFDI antes de capturar tus datos de facturación.');
            }

            if ($pago->pago_id_dato_fiscal !== null) {
                throw new DomainException('Tus datos de facturación ya fueron registrados y no pueden modificarse.');
            }

            $id_regimen = (int) $datos['regimen_fiscal'];

            $regimen_existe = DB::table('regimen_fiscal')
                ->where('refi_id_regimen_fiscal', $id_regimen)
                ->exists();

            if (!$regimen_existe) {
                throw new DomainException('El régimen fiscal seleccionado no existe.');
            }

            /* CODIGO_POSTAL es un catálogo con llave foránea desde
               DATO_FISCAL y sólo trae los códigos que alguien sembró. Sin
               esta alta, capturar uno nuevo revienta por violación de FK. */
            DB::table('codigo_postal')->insertOrIgnore([
                'copo_id_codigo_postal' => $datos['codigo_postal'],
            ]);

            /* El segundo argumento nombra la secuencia: sin él Laravel busca
               una columna «id» y el RETURNING de PostgreSQL falla. */
            $id_dato_fiscal = DB::table('dato_fiscal')->insertGetId([
                'dafi_id_regimen_fiscal' => $id_regimen,
                'dafi_id_codigo_postal' => $datos['codigo_postal'],
                'dafi_razon_social' => $datos['razon_social'],
                'dafi_rfc' => $datos['rfc'],
                'dafi_persona_moral' => (string) $datos['persona_moral'] === '1',
                /* Se llega aquí sólo con CFDI elegido, así que las dos
                   columnas de uso cuentan la misma historia. */
                'dafi_uso_cfdi' => true,
            ], 'dafi_id_dato_fiscal');

            DB::table('pago')
                ->where('pago_id_pago', $pago->pago_id_pago)
                ->whereNull('pago_id_dato_fiscal')
                ->update(['pago_id_dato_fiscal' => $id_dato_fiscal]);

            $this->guardarCorreoDeFacturacion((int) $pago->pers_id_persona, $datos['correo_cfdi']);
        });
    }

    /**
     * Catálogo para el selector del formulario. Los valores empiezan con la
     * clave del SAT, así que el orden alfabético coincide con el numérico.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public function regimenesFiscales(): array
    {
        return DB::table('regimen_fiscal')
            ->orderBy('refi_regimen_fiscal')
            ->get()
            ->map(fn (object $regimen): array => [
                'id' => (int) $regimen->refi_id_regimen_fiscal,
                'nombre' => (string) $regimen->refi_regimen_fiscal,
            ])
            ->all();
    }

    /**
     * El correo del CFDI puede no ser el de la cuenta de la persona —la de
     * una empresa, por ejemplo—, así que es un tipo de comunicación aparte y
     * no reemplaza al correo principal.
     *
     * Público porque la referencia especial guarda el mismo dato por otro
     * camino: ahí el correo lo captura quien pide la referencia para todo el
     * grupo, y el renglón de COMUNICACION es idéntico.
     */
    public function guardarCorreoDeFacturacion(int $id_persona, string $correo): void
    {
        $id_tipo = DB::table('tipo_comunicacion')
            ->where('tico_tipo_comunicacion', 'Correo facturación')
            ->value('tico_id_tipo_comunicacion');

        if (!$id_tipo) {
            throw new DomainException('El catálogo de tipos de comunicación está incompleto.');
        }

        DB::table('comunicacion')->updateOrInsert(
            [
                'comu_id_persona' => $id_persona,
                'comu_id_tipo_comunicacion' => $id_tipo,
            ],
            ['comu_descripcion' => $correo]
        );
    }

    /**
     * El pago de la solicitud más reciente de la persona, bloqueado junto con
     * su solicitud para serializar peticiones concurrentes. Devuelve también
     * el identificador de la persona, que hace falta para el correo.
     */
    private function pagoBloqueadoDeUsuario(int $id_usuario): object
    {
        $solicitud = DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->where('p.pers_id_usuario', $id_usuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->lockForUpdate()
            ->select('s.soli_id_solicitud', 's.soli_id_pago', 'p.pers_id_persona')
            ->first();

        if (!$solicitud || !$solicitud->soli_id_pago) {
            throw new DomainException('Aún no existe un pago ligado a tu solicitud.');
        }

        $pago = DB::table('pago')
            ->where('pago_id_pago', $solicitud->soli_id_pago)
            ->lockForUpdate()
            ->first();

        if (!$pago) {
            throw new DomainException('El pago ligado a tu solicitud no existe.');
        }

        $pago->pers_id_persona = $solicitud->pers_id_persona;

        return $pago;
    }

    /**
     * El estado vigente es el último renglón de la bitácora.
     */
    private function verificarPagoValidado(int $id_pago): void
    {
        $estado = DB::table('estado_pago as ep')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('ep.espa_id_pago', $id_pago)
            ->orderByDesc('ep.espa_id_estado_pago')
            ->value('cep.esta_estado_pago');

        if ($estado !== 'Completado') {
            throw new DomainException('El tipo de comprobante se elige cuando tu pago ha sido validado.');
        }
    }
}
