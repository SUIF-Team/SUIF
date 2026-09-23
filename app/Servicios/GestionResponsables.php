<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * GestionResponsables
 *
 * Responsabilidad: el personal de la DEC que atiende los pagos, cuyo nombre va
 * en «Atendido por» del formato de pago.
 *
 * La baja no borra el renglón: PAGO.PAGO_ID_RESPONSABLE lo referencia y un
 * formato ya emitido tiene que poder generarse otra vez con el mismo nombre.
 * Mismo criterio que GestionAdministradores con USUARIO.USUA_ACTIVO.
 */
class GestionResponsables
{
    /**
     * Todos, primero los activos.
     *
     * @return array<int, array{id: int, nombre: string, apellido_paterno: string,
     *                          apellido_materno: string, nombre_completo: string, activo: bool}>
     */
    public function lista(): array
    {
        return DB::table('responsable')
            ->orderByDesc('resp_activo')
            ->orderBy('resp_nombre')
            ->orderBy('resp_apellido_paterno')
            ->get()
            ->map(fn (object $fila): array => $this->normalizar($fila))
            ->all();
    }

    /**
     * Los que se ofrecen al generar un formato.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activos(): array
    {
        return array_values(array_filter(
            $this->lista(),
            fn (array $responsable): bool => $responsable['activo']
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function responsable(int $id): array
    {
        $fila = DB::table('responsable')->where('resp_id_responsable', $id)->first();

        if (!$fila) {
            throw new DomainException('El responsable solicitado no existe.');
        }

        return $this->normalizar($fila);
    }

    /**
     * @param  array{nombre: string, apellido_paterno: string, apellido_materno: ?string}  $datos
     */
    public function crear(array $datos): void
    {
        DB::table('responsable')->insert($this->columnas($datos));
    }

    /**
     * @param  array{nombre: string, apellido_paterno: string, apellido_materno: ?string}  $datos
     */
    public function actualizar(int $id, array $datos): void
    {
        $this->responsable($id);

        DB::table('responsable')
            ->where('resp_id_responsable', $id)
            ->update($this->columnas($datos));
    }

    public function desactivar(int $id): void
    {
        $this->cambiarActivo($id, false);
    }

    public function reactivar(int $id): void
    {
        $this->cambiarActivo($id, true);
    }

    private function cambiarActivo(int $id, bool $activo): void
    {
        $this->responsable($id);

        DB::table('responsable')
            ->where('resp_id_responsable', $id)
            ->update(['resp_activo' => $activo]);
    }

    /**
     * @param  array{nombre: string, apellido_paterno: string, apellido_materno: ?string}  $datos
     * @return array<string, string|null>
     */
    private function columnas(array $datos): array
    {
        return [
            'resp_nombre' => $datos['nombre'],
            'resp_apellido_paterno' => $datos['apellido_paterno'],
            'resp_apellido_materno' => $datos['apellido_materno'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(object $fila): array
    {
        $materno = (string) ($fila->resp_apellido_materno ?? '');

        return [
            'id' => (int) $fila->resp_id_responsable,
            'nombre' => (string) $fila->resp_nombre,
            'apellido_paterno' => (string) $fila->resp_apellido_paterno,
            'apellido_materno' => $materno,
            /* En el orden en que la DEC lo escribe en el formato. */
            'nombre_completo' => trim($fila->resp_nombre.' '.$fila->resp_apellido_paterno.' '.$materno),
            /* PostgreSQL devuelve true/false y SQLite 1/0. */
            'activo' => (bool) $fila->resp_activo,
        ];
    }
}
