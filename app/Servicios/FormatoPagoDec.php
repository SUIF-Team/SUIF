<?php

namespace App\Servicios;

use App\Support\Admin\ConsultaPagos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * FormatoPagoDec
 *
 * Responsabilidad: llenar el «Formato de pago · Certificación UIF» con el que
 * la DEC emite el CFDI o el ticket de un pago ya validado.
 *
 * Se entrega en PDF y no en el Excel de la DEC porque ya nadie lo llena a
 * mano: la persona eligió ticket o CFDI, la forma de pago y el banco al subir
 * su comprobante, y quien lo genera elige «Atendido por». El PDF es una
 * réplica de la hoja «PROGRAMAS NACIONALES» de aquella plantilla, con sus
 * logos, sus secciones y sus casillas, para que la DEC reconozca su formato.
 *
 * Mismo molde que ComprobanteSede y ListaAsistencia: una vista Blade que
 * Dompdf arma en memoria en cada descarga y que no se guarda en ningún lado.
 *
 * ponytail: la vista pdf.pago.formato y formato-pago.css copian las medidas de
 * esa versión de la hoja de la DEC. Si la DEC cambia su formato, se rehacen
 * ahí; los datos que llevan salen de datos() y no cambian.
 */
class FormatoPagoDec
{
    /* Formas de pago que llevan banco: el formato lo pide bajo «TARJETA».
       El catálogo se consulta por nombre, como los demás del sistema. */
    public const CON_TARJETA = ['Tarjeta de crédito', 'Tarjeta de débito'];

    /* Lo que el formato imprime en un campo que no aplica. */
    private const VACIO = '—';

    /**
     * Formas de pago para el formulario del comprobante, en el orden de las
     * casillas del formato.
     *
     * @return array<int, array{id: int, nombre: string, conTarjeta: bool}>
     */
    public function metodosPago(): array
    {
        return DB::table('metodo_pago')
            ->orderBy('mepa_id_metodo_pago')
            ->get()
            ->map(fn (object $metodo): array => [
                'id' => (int) $metodo->mepa_id_metodo_pago,
                'nombre' => (string) $metodo->mepa_metodo_pago,
                'conTarjeta' => in_array($metodo->mepa_metodo_pago, self::CON_TARJETA, true),
            ])
            ->all();
    }

    /**
     * Bancos para el formulario del comprobante. Van en el orden del script,
     * que deja «Otro» al final.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public function bancos(): array
    {
        return DB::table('banco')
            ->orderBy('banc_id_banco')
            ->get()
            ->map(fn (object $banco): array => [
                'id' => (int) $banco->banc_id_banco,
                'nombre' => (string) $banco->banc_banco,
            ])
            ->all();
    }

    /**
     * Por qué todavía no se puede generar el formato; null si ya se puede.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @param  array<int, array<string, mixed>>  $responsables  GestionResponsables::activos()
     */
    public function motivoNoDisponible(array $pago, array $responsables): ?string
    {
        if ($pago['estado_persistido'] !== ConsultaPagos::COMPLETADO) {
            return 'El formato se genera cuando el pago está validado.';
        }

        /* Sin datos fiscales la DEC no puede facturar: el formato saldría con
           la sección de facturación vacía y el uso «gastos en general». */
        if ($pago['uso_cfdi'] === true && $pago['datos_fiscales'] === null) {
            return 'La persona eligió CFDI y todavía no captura sus datos de facturación.';
        }

        if ($responsables === []) {
            return 'No hay responsables activos. Da de alta a quien atiende los pagos en «Responsables de pago».';
        }

        return null;
    }

    /**
     * Genera el formato, deja registrado quién atendió el pago y lo entrega
     * como descarga.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @param  array{id: int, nombre_completo: string}  $responsable
     */
    public function descarga(array $pago, array $responsable): Response
    {
        $contenido = Pdf::loadView('pdf.pago.formato', $this->datos($pago, $responsable['nombre_completo']))
            ->setPaper('letter')
            ->output();

        /* Se escribe después de generar: si el PDF falla, el pago no se queda
           con el responsable de un formato que nunca existió. */
        DB::table('pago')
            ->where('pago_id_pago', (int) $pago['id'])
            ->update(['pago_id_responsable' => $responsable['id']]);

        /* La referencia sale del CSV de la DEC: se deja sólo lo alfanumérico
           para que no pueda partir el encabezado. */
        $referencia = preg_replace('/[^A-Za-z0-9]/', '', (string) $pago['referencia_bancaria']) ?: $pago['id'];

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="formato-pago-'.$referencia.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Qué va en cada campo del formato.
     *
     * - CFDI: las tres secciones, con el uso «gastos en general».
     * - Ticket, o un pago anterior a que la elección fuera obligatoria: la
     *   sección de facturación sale con VACIO y el uso «sin efectos fiscales».
     * - Pago compartido de una referencia especial: en el participante va el
     *   nombre o razón social de quien paga, no el de una de las personas.
     *
     * forma_pago es el nombre del catálogo, o null en un pago anterior a que
     * se pidiera: la vista marca con él la casilla que le toca.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @return array<string, string|null>
     */
    public function datos(array $pago, string $atendido_por): array
    {
        /* El evento es la convocatoria de la solicitud. Las solicitudes de un
           pago compartido son todas de la misma. */
        $evento = DB::table('solicitud as s')
            ->join('convocatoria as cv', 'cv.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->where('s.soli_id_pago', (int) $pago['id'])
            ->orderBy('s.soli_id_solicitud')
            ->value('cv.conv_nombre');

        $fiscales = $pago['datos_fiscales'];
        $cfdi = $pago['uso_cfdi'] === true && $fiscales !== null;

        $participante = $pago['pago_grupal'] && $fiscales !== null
            ? $fiscales['razon_social']
            : implode(' ', array_filter([$pago['nombre'], $pago['primer_apellido'], $pago['segundo_apellido']]));

        $datos = [
            'nombre_fiscal' => $cfdi ? $fiscales['razon_social'] : '',
            'rfc' => $cfdi ? $fiscales['rfc'] : '',
            'uso' => $cfdi ? 'Gastos en general' : 'Sin efectos fiscales',
            'codigo_postal' => $cfdi ? $fiscales['codigo_postal'] : '',
            'regimen' => $cfdi ? $fiscales['regimen_fiscal'] : '',
            'participante' => $participante,
            'evento' => $evento,
            'importe' => '$'.number_format((float) $pago['monto_pagado'], 2),
            'referencia' => $pago['referencia_bancaria'],
            'banco' => in_array($pago['metodo_pago'], self::CON_TARJETA, true) ? $pago['banco'] : '',
            'atendido_por' => $atendido_por,
        ];

        /* El formato se llena en mayúsculas, como lo llena la DEC a mano. Lo
           que no aplica se imprime como VACIO y no como un renglón en blanco,
           que parecería pendiente de llenar. */
        $datos = array_map(function (mixed $valor): string {
            $valor = mb_strtoupper(trim((string) $valor), 'UTF-8');

            return $valor === '' ? self::VACIO : $valor;
        }, $datos);

        $datos['forma_pago'] = $pago['metodo_pago'];

        return $datos;
    }
}
