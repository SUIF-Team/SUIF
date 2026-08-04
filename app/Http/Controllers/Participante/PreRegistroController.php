<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PreRegistroController extends Controller
{
    private $documentos = [
        'solicitud-firmada' => 'Solicitud firmada',
        'aceptacion-notificaciones' => 'Aceptación de notificaciones',
        'carta-bajo-protesta' => 'Carta bajo protesta',
        'autorizacion-publicacion' => 'Autorización de la publicación',
        'curp' => 'CURP',
        'identificacion-oficial' => 'Identificación oficial',
    ];

    public function index(Request $request)
    {
        $estado = $this->estado($request);

        if ($estado['fase'] === 'completado') {
            return redirect()->route('participante.preregistro.completado');
        }

        return view('participante.preregistro', [
            'estado' => $estado,
            'documentos' => $this->documentos,
            'entidades' => $this->entidades(),
            'grados' => $this->grados(),
        ]);
    }

       public function guardarDatos(Request $request)
    {
        $entidades = implode(',', $this->entidades());
        $grados = implode(',', array_keys($this->grados()));

        $datos = $this->validate($request, [
            'nombre' => 'required|string|max:45',
            'primer_apellido' => 'required|string|max:45',
            'segundo_apellido' => 'required|string|max:45',
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Za-z0-9]{18}$/', 'unique:persona,pers_curp'],
            'correo_principal' => 'required|email|max:65',
            'telefono' => 'required|digits:10',
            'entidad_federativa' => 'required|in:'.$entidades,
            'correo_alterno' => 'required|email|max:65|different:correo_principal',
            'grado_estudios' => 'required|in:'.$grados,
            'actividad_vulnerable' => 'required|in:si,no',
            'responsable_cumplimiento' => 'required|in:si,no',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'Escribe un correo válido.',
            'digits' => 'El teléfono debe contener exactamente 10 dígitos.',
            'size' => 'La CURP debe contener exactamente 18 caracteres.',
            'regex' => 'La CURP solo debe contener letras y números.',
            'different' => 'El correo alterno debe ser distinto al principal.',
            'in' => 'Selecciona una opción válida.',
            'curp.unique' => 'Esa CURP ya tiene un pre-registro. Inicia sesión con tu clave de acceso.',
        ]);

        $datos['curp'] = strtoupper($datos['curp']);

        $estado = $this->estado($request);
        $clave = empty($estado['clave']) ? $this->generarClave() : $estado['clave'];

        /* Alta real del participante en la base de datos. */
        $this->registrarParticipante($datos, $clave);

        $estado['datos'] = $datos;
        $estado['clave'] = $clave;
        $estado['fase'] = 'clave';
        $request->session()->put('suif.preregistro', $estado);
        $this->enviarClave($datos['correo_principal'], $clave);

        return redirect()->route('participante.preregistro.index')
            ->with('success', 'Tus datos fueron guardados correctamente.');
    }

    /**
     * Da de alta al participante en la base de datos.
     *
     * Todo ocurre dentro de una transacción: si algo falla a la mitad,
     * no queda un participante incompleto.
     */
    private function registrarParticipante(array $datos, $clave)
    {
        DB::transaction(function () use ($datos, $clave) {
            $idRol = DB::table('rol')
                ->where('rol_tipo_rol', 'Participante')
                ->value('rol_id_rol');

            $idUsuario = DB::table('usuario')->insertGetId([
                'usua_id_rol' => $idRol,
                'usua_clave_acceso' => Hash::make($clave),
            ], 'usua_id_usuario');

            $claveInegi = DB::table('entidad_federativa')
                ->where('enfe_entidad_federativa', $datos['entidad_federativa'])
                ->value('enfe_clave_inegi');

            $idPersona = DB::table('persona')->insertGetId([
                'pers_clave_inegi' => $claveInegi,
                'pers_id_usuario' => $idUsuario,
                'pers_curp' => $datos['curp'],
                'pers_nombre' => $datos['nombre'],
                'pers_apellido_paterno' => $datos['primer_apellido'],
                'pers_apellido_materno' => $datos['segundo_apellido'],
                'pers_fecha_registro' => now()->toDateString(),
            ], 'pers_id_persona');

            /* Los correos y el teléfono no son columnas: son renglones de COMUNICACION. */
            $tipos = DB::table('tipo_comunicacion')
                ->pluck('tico_id_tipo_comunicacion', 'tico_tipo_comunicacion');

            DB::table('comunicacion')->insert([
                [
                    'comu_id_persona' => $idPersona,
                    'comu_id_tipo_comunicacion' => $tipos['Correo principal'],
                    'comu_descripcion' => $datos['correo_principal'],
                ],
                [
                    'comu_id_persona' => $idPersona,
                    'comu_id_tipo_comunicacion' => $tipos['Correo alterno'],
                    'comu_descripcion' => $datos['correo_alterno'],
                ],
                [
                    'comu_id_persona' => $idPersona,
                    'comu_id_tipo_comunicacion' => $tipos['Teléfono celular'],
                    'comu_descripcion' => $datos['telefono'],
                ],
            ]);

            $idTrabajo = DB::table('trabajo')->insertGetId([
                'trab_actividad_vulnerable' => $datos['actividad_vulnerable'] === 'si',
                'trab_responsable' => $datos['responsable_cumplimiento'] === 'si',
            ], 'trab_id_trabajo');

            DB::table('trabajo_persona')->insert([
                'trpe_id_trabajo' => $idTrabajo,
                'trpe_id_persona' => $idPersona,
            ]);

            $idNivel = DB::table('nivel_profesional')
                ->where('nipr_nivel_profesional', $this->grados()[$datos['grado_estudios']])
                ->value('nipr_id_nivel_profesional');

            DB::table('grado_persona')->insert([
                'grpe_id_nivel_profesional' => $idNivel,
                'grpe_id_persona' => $idPersona,
            ]);
        });
    }

    public function avanzar(Request $request)
    {
        $estado = $this->estado($request);

        if ($estado['fase'] === 'clave') {
            $estado['fase'] = 'formatos';
        } elseif ($estado['fase'] === 'formatos') {
            $estado['fase'] = 'documentos';
        }

        $request->session()->put('suif.preregistro', $estado);

        return redirect()->route('participante.preregistro.index');
    }

    public function formato($documento, $descargar = false)
    {
        if (!isset($this->documentos[$documento])) {
            abort(404);
        }

        $archivo = storage_path('app/preregistro/formatos/'.$documento.'.pdf');
        if (!is_file($archivo)) {
            abort(404, 'El formato todavía no está disponible.');
        }

        if ($descargar) {
            return response()->download($archivo, $documento.'.pdf');
        }

        return response()->file($archivo, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documento.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function subirDocumento(Request $request, $documento)
    {
        if (!isset($this->documentos[$documento])) {
            abort(404);
        }

        $this->validate($request, [
            'archivo' => 'required|file|mimes:pdf|max:1024',
        ], [
            'archivo.required' => 'Selecciona un archivo PDF.',
            'archivo.mimes' => 'Solo se permiten archivos PDF.',
            'archivo.max' => 'El PDF no puede pesar más de 1 MB.',
        ]);

        $estado = $this->estado($request);
        $directorio = 'preregistro/cargas/'.sha1($request->session()->getId());
        $nombre = $documento.'-'.time().'.pdf';
        $ruta = $request->file('archivo')->storeAs($directorio, $nombre);

        $estado['documentos'][$documento] = [
            'ruta' => $ruta,
            'nombre_original' => $request->file('archivo')->getClientOriginalName(),
            'estado' => 'cargado',
            'observacion' => null,
        ];
        $estado['fase'] = 'documentos';
        $request->session()->put('suif.preregistro', $estado);

        return redirect()->route('participante.preregistro.index')
            ->with('success', 'Documento cargado. Revísalo antes de continuar.');
    }

    public function verDocumento(Request $request, $documento)
    {
        $estado = $this->estado($request);
        if (empty($estado['documentos'][$documento]['ruta'])) {
            abort(404);
        }

        $archivo = storage_path('app/'.$estado['documentos'][$documento]['ruta']);
        if (!is_file($archivo)) {
            abort(404);
        }

        return response()->file($archivo, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documento.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function enviarRevision(Request $request)
    {
        $estado = $this->estado($request);

        foreach (array_keys($this->documentos) as $documento) {
            if (empty($estado['documentos'][$documento]['ruta'])) {
                return redirect()->route('participante.preregistro.index')
                    ->withErrors(['documentos' => 'Debes cargar todos los documentos antes de continuar.']);
            }
            $estado['documentos'][$documento]['estado'] = 'revision';
        }

        $estado['fase'] = 'revision';
        $request->session()->put('suif.preregistro', $estado);

        return redirect()->route('participante.preregistro.index')
            ->with('success', 'Tus documentos fueron enviados a revisión.');
    }

    public function finalizar(Request $request)
    {
        $estado = $this->estado($request);

        foreach (array_keys($this->documentos) as $documento) {
            if (empty($estado['documentos'][$documento]) || $estado['documentos'][$documento]['estado'] !== 'aprobado') {
                return redirect()->route('participante.preregistro.index')
                    ->withErrors(['documentos' => 'El pre-registro solo puede finalizar cuando todos los documentos estén aprobados.']);
            }
        }

        $estado['fase'] = 'completado';
        $request->session()->put('suif.preregistro', $estado);
        $request->session()->put('suif.participante.estado.preregistro_completo', true);

        return redirect()->route('participante.preregistro.completado');
    }

    public function completado(Request $request)
    {
        $estado = $this->estado($request);
        if ($estado['fase'] !== 'completado') {
            return redirect()->route('participante.preregistro.index');
        }

        return view('participante.preregistro', [
            'estado' => $estado,
            'documentos' => $this->documentos,
            'entidades' => $this->entidades(),
            'grados' => $this->grados(),
        ]);
    }

    public function demo(Request $request, $estadoDemo)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $estado = $this->estado($request);
        foreach (array_keys($this->documentos) as $documento) {
            if (empty($estado['documentos'][$documento]['ruta'])) {
                return redirect()->route('participante.preregistro.index')
                    ->withErrors(['documentos' => 'Carga todos los documentos antes de simular estados.']);
            }
            $estado['documentos'][$documento]['estado'] = $estadoDemo === 'aprobado' ? 'aprobado' : 'revision';
            $estado['documentos'][$documento]['observacion'] = null;
        }

        if ($estadoDemo === 'rechazado') {
            $claves = array_keys($this->documentos);
            $primero = reset($claves);
            $estado['documentos'][$primero]['estado'] = 'rechazado';
            $estado['documentos'][$primero]['observacion'] = 'El documento se ve borroso. Sustituye el archivo por una copia legible.';
            $estado['fase'] = 'rechazado';
        } elseif ($estadoDemo === 'aprobado') {
            $estado['fase'] = 'aprobado';
        } else {
            $estado['fase'] = 'revision';
        }

        $request->session()->put('suif.preregistro', $estado);

        return redirect()->route('participante.preregistro.index');
    }

    public function reiniciar(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $request->session()->forget('suif.preregistro');
        $request->session()->forget('suif.participante.estado.preregistro_completo');

        return redirect()->route('participante.preregistro.index');
    }

    private function estado(Request $request)
    {
        return array_merge([
            'fase' => 'datos',
            'datos' => [],
            'clave' => null,
            'documentos' => [],
        ], (array) $request->session()->get('suif.preregistro', []));
    }

    private function generarClave()
    {
        return strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
    }

    private function enviarClave($correo, $clave)
    {
        try {
            Mail::raw('Tu clave de acceso SUIF es: '.$clave, function ($mensaje) use ($correo) {
                $mensaje->to($correo)->subject('Clave de acceso para SUIF');
            });
        } catch (\Throwable $exception) {
            Log::warning('No fue posible enviar la clave de pre-registro.', ['error' => $exception->getMessage()]);
        }
    }

    private function entidades()
    {
        return ['Aguascalientes','Baja California','Baja California Sur','Campeche','Chiapas','Chihuahua','Ciudad de México','Coahuila','Colima','Durango','Estado de México','Guanajuato','Guerrero','Hidalgo','Jalisco','Michoacán','Morelos','Nayarit','Nuevo León','Oaxaca','Puebla','Querétaro','Quintana Roo','San Luis Potosí','Sinaloa','Sonora','Tabasco','Tamaulipas','Tlaxcala','Veracruz','Yucatán','Zacatecas'];
    }

    private function grados()
    {
        return ['bachillerato' => 'Bachillerato','licenciatura' => 'Licenciatura','especialidad' => 'Especialidad','maestria' => 'Maestría','doctorado' => 'Doctorado'];
    }
}
