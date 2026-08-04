<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ParticipanteDePruebaSeeder
 *
 * Responsabilidad: crear un participante de prueba para desarrollar el flujo
 * mientras no exista el alta real desde el pre-registro.
 */
class ParticipanteDePruebaSeeder extends Seeder
{
    public function run()
    {
        // PERSONA exige una entidad federativa existente (llave foránea).
        DB::table('entidad_federativa')->updateOrInsert(
            ['enfe_clave_inegi' => '009'],
            ['enfe_entidad_federativa' => 'Ciudad de México']
        );

        $rol = Rol::firstOrCreate(['rol_tipo_rol' => 'Participante']);

        $usuario = Usuario::create([
            'usua_id_rol' => $rol->rol_id_rol,
            'usua_clave_acceso' => Hash::make('SUIF-2026'),
        ]);

        Persona::create([
            'pers_clave_inegi' => '009',
            'pers_id_usuario' => $usuario->usua_id_usuario,
            'pers_curp' => 'GAMJ900101MDFRRN01',
            'pers_nombre' => 'Juana',
            'pers_apellido_paterno' => 'García',
            'pers_apellido_materno' => 'Martínez',
            'pers_fecha_registro' => now()->toDateString(),
        ]);

        $this->command->info('Participante de prueba creado. CURP: GAMJ900101MDFRRN01 / Clave: SUIF-2026');
    }
}