<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario;
use App\Servicios\FormatoPreRegistro;
use Barryvdh\DomPDF\Facade\Pdf;

class PreRegistroController extends Controller
{
   private $documentos = [];

   private $formatos = [];

       public function __construct()
    {
        $this->documentos = config('suif.documentos');
        $this->formatos = config('suif.formatos_descargables');
    }     
   
         public function index(Request $request)
    {
        $estado = $this->estado($request);

        /* Si ya tiene solicitud, sus datos están en la base. */
        if (empty($estado['datos'])) {
            $guardados = $this->datosGuardados();

            if ($guardados) {
                $estado['datos'] = $guardados;
                $estado['fase'] = 'registrado';
                $request->session()->put('suif.preregistro', $estado);
            }
        }

        /* Quien ya tiene solicitud ve el resumen de sus datos, no el formulario. */
        if ($this->solicitudActual() && $estado['fase'] !== 'clave') {
            $estado['fase'] = 'registrado';
        }

             return view('persona.preregistro', [
            'estado' => $estado,
            'entidades' => $this->entidades(),
            'grados' => $this->grados(),
            'puedeEditar' => $this->solicitudActual() ? $this->puedeEditar() : false,
        ]);
    }

        /**
     * Pantalla de documentación: formatos descargables y carga de archivos.
     */
    public function documentos(Request $request)
    {
        $idSolicitud = $this->solicitudActual();

        if (!$idSolicitud) {
            return redirect()->route('persona.preregistro.index');
        }

        $estado = $this->estado($request);
        $estado['documentos'] = $this->documentosGuardados($idSolicitud);
        $estado['fase'] = $this->faseSegunDocumentos($estado['documentos']);

        return view('persona.documentos', [
            'estado' => $estado,
            'documentos' => $this->documentos,
            'formatos' => $this->formatos,
            'verFormatos' => $request->query('ver') === 'formatos',
        ]);
    }

        /**
     * Muestra el formulario con los datos actuales para modificarlos.
     */
    public function editar(Request $request)
    {
        if (!$this->solicitudActual()) {
            return redirect()->route('persona.preregistro.index');
        }

        if (!$this->puedeEditar()) {
            return redirect()->route('persona.preregistro.index')
                ->withErrors(['datos' => 'Tus datos ya no se pueden modificar porque tu documentación está en revisión.']);
        }

        $estado = $this->estado($request);
        $estado['datos'] = $this->datosGuardados();
        $estado['fase'] = 'editar';

        return view('persona.preregistro', [
            'estado' => $estado,
            'entidades' => $this->entidades(),
            'grados' => $this->grados(),
            'puedeEditar' => true,
        ]);
    }

    /**
     * Guarda los cambios sobre los datos ya registrados.
     */
    public function actualizarDatos(Request $request)
    {
        $idPersona = $this->personaActual();

        /* Se vuelve a comprobar aquí: el permiso pudo cambiar entre que
           se abrió el formulario y se envió. */
        if (!$idPersona || !$this->puedeEditar()) {
            return redirect()->route('persona.preregistro.index')
                ->withErrors(['datos' => 'Tus datos ya no se pueden modificar porque tu documentación está en revisión.']);
        }

        $request->merge([
            'curp' => mb_strtoupper(trim((string) $request->input('curp')), 'UTF-8'),
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc')), 'UTF-8'),
        ]);

        $entidades = implode(',', $this->entidades());
        $grados = implode(',', array_keys($this->grados()));

        $datos = $this->validate($request, [
            'nombre' => 'required|string|max:45',
            'primer_apellido' => 'required|string|max:45',
            'segundo_apellido' => 'required|string|max:45',
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Za-z0-9]{18}$/', 'unique:persona,pers_curp,'.$idPersona.',pers_id_persona'],
            'rfc' => ['required', 'string', 'size:13', 'regex:/^[A-ZÑ]{4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$/', 'unique:persona,pers_rfc,'.$idPersona.',pers_id_persona'],
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
            'curp.unique' => 'Esa CURP ya está registrada por otra persona.',
            'rfc.required' => 'El campo RFC es obligatorio.',
            'rfc.size' => 'El RFC debe contener exactamente 13 caracteres.',
            'rfc.regex' => 'Escribe tu RFC con homoclave, como aparece en tu constancia fiscal.',
            'rfc.unique' => 'Ese RFC ya está registrado por otra persona.',
        ]);

        $datos['nombre'] = $this->nombrePropio($datos['nombre']);
        $datos['primer_apellido'] = $this->nombrePropio($datos['primer_apellido']);
        $datos['segundo_apellido'] = $this->nombrePropio($datos['segundo_apellido']);
        $datos['curp'] = mb_strtoupper(trim($datos['curp']), 'UTF-8');
        $datos['rfc'] = mb_strtoupper(trim($datos['rfc']), 'UTF-8');
        $datos['correo_principal'] = mb_strtolower(trim($datos['correo_principal']), 'UTF-8');
        $datos['correo_alterno'] = mb_strtolower(trim($datos['correo_alterno']), 'UTF-8');

        $this->actualizarPersona($idPersona, $datos);

        $estado = $this->estado($request);
        $estado['datos'] = $datos;
        $estado['fase'] = 'registrado';
        $request->session()->put('suif.preregistro', $estado);

        return redirect()->route('persona.preregistro.index')
            ->with('success', 'Tus datos fueron actualizados correctamente.');
    }

       public function guardarDatos(Request $request)
    {
        /* Se normaliza la CURP antes de validar para que la comprobación
           de duplicados no dependa de cómo la haya escrito la persona. */
        $request->merge([
            'curp' => mb_strtoupper(trim((string) $request->input('curp')), 'UTF-8'),
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc')), 'UTF-8'),
        ]);
        $entidades = implode(',', $this->entidades());
        $grados = implode(',', array_keys($this->grados()));

        $datos = $this->validate($request, [
            'nombre' => 'required|string|max:45',
            'primer_apellido' => 'required|string|max:45',
            'segundo_apellido' => 'required|string|max:45',
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Za-z0-9]{18}$/', 'unique:persona,pers_curp'],
            'rfc' => ['required', 'string', 'size:13', 'regex:/^[A-ZÑ]{4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$/', 'unique:persona,pers_rfc'],
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
            'rfc.required' => 'El campo RFC es obligatorio.',
            'rfc.size' => 'El RFC debe contener exactamente 13 caracteres.',
            'rfc.regex' => 'Escribe tu RFC con homoclave, como aparece en tu constancia fiscal.',
            'rfc.unique' => 'Ese RFC ya tiene un pre-registro. Inicia sesión con tu clave de acceso.',
        ]);

        /* Formato uniforme sin importar cómo lo haya escrito la persona. */
        $datos['nombre'] = $this->nombrePropio($datos['nombre']);
        $datos['primer_apellido'] = $this->nombrePropio($datos['primer_apellido']);
        $datos['segundo_apellido'] = $this->nombrePropio($datos['segundo_apellido']);
        $datos['curp'] = mb_strtoupper(trim($datos['curp']), 'UTF-8');
        $datos['rfc'] = mb_strtoupper(trim($datos['rfc']), 'UTF-8');
        $datos['correo_principal'] = mb_strtolower(trim($datos['correo_principal']), 'UTF-8');
        $datos['correo_alterno'] = mb_strtolower(trim($datos['correo_alterno']), 'UTF-8');

        $estado = $this->estado($request);
        $clave = empty($estado['clave']) ? $this->generarClave() : $estado['clave'];

        /* Alta real de la persona en la base de datos. */
        $idUsuario = $this->registrarPersona($datos, $clave);

        /* Se inicia la sesión de la persona recién creada para que pueda
           continuar con sus documentos sin pasar de nuevo por el login. */
        $usuario = Usuario::find($idUsuario);

        if ($usuario) {
            Auth::login($usuario);
            $request->session()->regenerate();
        }

        $estado['datos'] = $datos;
        $estado['clave'] = $clave;
        $estado['fase'] = 'clave';
        $request->session()->put('suif.preregistro', $estado);
        $this->enviarClave($datos['correo_principal'], $clave);

        return redirect()->route('persona.preregistro.index')
            ->with('success', 'Tus datos fueron guardados correctamente.');
    }

    /**
     * Da de alta a la persona en la base de datos.
     *
     * Todo ocurre dentro de una transacción: si algo falla a la mitad,
     * no queda una persona incompleta.
     */
    private function registrarPersona(array $datos, $clave)
    {
       return DB::transaction(function () use ($datos, $clave) {
            $idRol = DB::table('rol')
                ->where('rol_tipo_rol', 'Persona')
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
                'pers_rfc' => $datos['rfc'],
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

                /* La SOLICITUD es la que amarra al persona con su trámite:
               convocatoria, pago, documentos, evaluación y certificado. */
            $idConvocatoria = DB::table('convocatoria')
                ->whereDate('conv_fecha_inicio_registro', '<=', now()->toDateString())
                ->whereDate('conv_fecha_fin_registro', '>=', now()->toDateString())
                ->orderByDesc('conv_id_convocatoria')
                ->value('conv_id_convocatoria');

            $idSolicitud = DB::table('solicitud')->insertGetId([
                'soli_id_persona' => $idPersona,
                'soli_id_convocatoria' => $idConvocatoria,
            ], 'soli_id_solicitud');

            $idEstadoInicial = DB::table('c_estado_solicitud')
                ->where('esso_estado_solicitud', 'Pre-registro')
                ->value('esso_id_c_estado_solicitud');

            DB::table('estado_solicitud')->insert([
                'esso_id_c_estado_solicitud' => $idEstadoInicial,
                'esso_id_solicitud' => $idSolicitud,
                'esso_fecha' => now()->toDateString(),
                'esso_hora' => now()->toTimeString(),
            ]);

            return $idUsuario;

        });
    }

        /**
     * Actualiza los datos personales sobre las tablas ya existentes.
     * No se toca pers_fecha_registro: es la fecha del alta original.
     */
    private function actualizarPersona($idPersona, array $datos)
    {
        DB::transaction(function () use ($idPersona, $datos) {
            $claveInegi = DB::table('entidad_federativa')
                ->where('enfe_entidad_federativa', $datos['entidad_federativa'])
                ->value('enfe_clave_inegi');

            DB::table('persona')
                ->where('pers_id_persona', $idPersona)
                ->update([
                    'pers_clave_inegi' => $claveInegi,
                    'pers_curp' => $datos['curp'],
                    'pers_rfc' => $datos['rfc'],
                    'pers_nombre' => $datos['nombre'],
                    'pers_apellido_paterno' => $datos['primer_apellido'],
                    'pers_apellido_materno' => $datos['segundo_apellido'],
                ]);

            $tipos = DB::table('tipo_comunicacion')
                ->pluck('tico_id_tipo_comunicacion', 'tico_tipo_comunicacion');

            $contactos = [
                'Correo principal' => $datos['correo_principal'],
                'Correo alterno' => $datos['correo_alterno'],
                'Teléfono celular' => $datos['telefono'],
            ];

            foreach ($contactos as $tipo => $valor) {
                DB::table('comunicacion')->updateOrInsert(
                    [
                        'comu_id_persona' => $idPersona,
                        'comu_id_tipo_comunicacion' => $tipos[$tipo],
                    ],
                    ['comu_descripcion' => $valor]
                );
            }

            DB::table('trabajo')
                ->whereIn('trab_id_trabajo', function ($consulta) use ($idPersona) {
                    $consulta->select('trpe_id_trabajo')
                        ->from('trabajo_persona')
                        ->where('trpe_id_persona', $idPersona);
                })
                ->update([
                    'trab_actividad_vulnerable' => $datos['actividad_vulnerable'] === 'si',
                    'trab_responsable' => $datos['responsable_cumplimiento'] === 'si',
                ]);

            $idNivel = DB::table('nivel_profesional')
                ->where('nipr_nivel_profesional', $this->grados()[$datos['grado_estudios']])
                ->value('nipr_id_nivel_profesional');

            DB::table('grado_persona')
                ->where('grpe_id_persona', $idPersona)
                ->update(['grpe_id_nivel_profesional' => $idNivel]);
        });
    }

    /**
     * Recupera de la base los datos de la persona que inició sesión.
     * Devuelve null si todavía no tiene un pre-registro.
     */
    private function datosGuardados()
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return null;
        }

        $persona = DB::table('persona as p')
            ->join('entidad_federativa as e', 'e.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->where('p.pers_id_usuario', $usuario->usua_id_usuario)
            ->select('p.*', 'e.enfe_entidad_federativa')
            ->first();

        if (!$persona) {
            return null;
        }

        /* Los tipos 1, 2 y 3 quedan fijos en suif_catalogos.sql. */
        $contactos = DB::table('comunicacion')
            ->where('comu_id_persona', $persona->pers_id_persona)
            ->pluck('comu_descripcion', 'comu_id_tipo_comunicacion');

        $trabajo = DB::table('trabajo as t')
            ->join('trabajo_persona as tp', 'tp.trpe_id_trabajo', '=', 't.trab_id_trabajo')
            ->where('tp.trpe_id_persona', $persona->pers_id_persona)
            ->first();

        $nivel = DB::table('nivel_profesional as n')
            ->join('grado_persona as g', 'g.grpe_id_nivel_profesional', '=', 'n.nipr_id_nivel_profesional')
            ->where('g.grpe_id_persona', $persona->pers_id_persona)
            ->value('n.nipr_nivel_profesional');

        return [
            'nombre' => $persona->pers_nombre,
            'primer_apellido' => $persona->pers_apellido_paterno,
            'segundo_apellido' => $persona->pers_apellido_materno,
            'curp' => $persona->pers_curp,
            'rfc' => $persona->pers_rfc,
            'correo_principal' => isset($contactos[1]) ? $contactos[1] : '',
            'correo_alterno' => isset($contactos[2]) ? $contactos[2] : '',
            'telefono' => isset($contactos[3]) ? $contactos[3] : '',
            'entidad_federativa' => $persona->enfe_entidad_federativa,
            'grado_estudios' => $nivel ? array_search($nivel, $this->grados()) : '',
            'actividad_vulnerable' => ($trabajo && $trabajo->trab_actividad_vulnerable) ? 'si' : 'no',
            'responsable_cumplimiento' => ($trabajo && $trabajo->trab_responsable) ? 'si' : 'no',
        ];
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

        return redirect()->route('persona.documentos.index');
    }

    /**
     * Genera el formato oficial que pidió la persona y lo entrega para
     * descarga. El PDF no se guarda en ningún lado: se arma en memoria en
     * cada clic con los datos vigentes del pre-registro.
     */
    public function generarFormato($documento)
    {
        /* Solo los cuatro que tienen formato oficial. */
        if (!in_array($documento, $this->formatos, true)) {
            abort(404);
        }

        /* datosGuardados() resuelve a la persona desde la sesión, así que
           nadie puede pedir el formato de alguien más. */
        $datos = $this->datosGuardados();

        if (!$datos) {
            return redirect()->route('persona.preregistro.index')
                ->withErrors(['datos' => 'Completa tu pre-registro antes de generar los formatos.']);
        }

        $formato = new FormatoPreRegistro($documento, $datos);
        $pdf = Pdf::loadView($formato->vista(), $formato->datos())->setPaper('letter');

        /* La respuesta se arma a mano porque download() del paquete no admite
           cabeceras extra y estos documentos llevan datos personales. */
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$formato->nombreArchivo().'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
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

        $idSolicitud = $this->solicitudActual();

        if (!$idSolicitud) {
            return redirect()->route('persona.documentos.index')
                ->withErrors(['documentos' => 'No encontramos tu solicitud. Vuelve a iniciar sesión.']);
        }

        $idTipo = DB::table('tipo_documento')
            ->where('tido_tipo_documento', $this->documentos[$documento])
            ->value('tido_id_tipo_documento');

        $ruta = $request->file('archivo')->storeAs(
            'preregistro/cargas/'.$idSolicitud,
            $documento.'-'.time().'.pdf'
        );

        /* El nombre original puede venir más largo que la columna. */
        $nombreOriginal = mb_substr($request->file('archivo')->getClientOriginalName(), 0, 150);

        DB::transaction(function () use ($idSolicitud, $idTipo, $ruta, $nombreOriginal) {
            $existente = DB::table('documento')
                ->where('soli_id_solicitud', $idSolicitud)
                ->where('tido_id_tipo_documento', $idTipo)
                ->first();

            if ($existente) {
                /* Reemplazo: se actualiza el renglón, no se duplica. */
                DB::table('documento')
                    ->where('docu_id_documento', $existente->docu_id_documento)
                    ->update([
                        'docu_nombre' => $nombreOriginal,
                        'docu_path' => $ruta,
                        'docu_fecha_carga' => now()->toDateString(),
                        'docu_hora_carga' => now()->toTimeString(),
                        'docu_fecha_autorizacion' => null,
                        'docu_hora_autorizacion' => null,
                    ]);

                $idDocumento = $existente->docu_id_documento;
            } else {
                $idDocumento = DB::table('documento')->insertGetId([
                    'tido_id_tipo_documento' => $idTipo,
                    'soli_id_solicitud' => $idSolicitud,
                    'docu_nombre' => $nombreOriginal,
                    'docu_path' => $ruta,
                    'docu_fecha_carga' => now()->toDateString(),
                    'docu_hora_carga' => now()->toTimeString(),
                ], 'docu_id_documento');
            }

            $this->registrarEstadoDocumento($idDocumento, 'Cargado');
        });

        return redirect()->route('persona.documentos.index')
            ->with('success', 'Documento cargado. Revísalo antes de continuar.');
    }

        public function verDocumento(Request $request, $documento)
    {
        if (!isset($this->documentos[$documento])) {
            abort(404);
        }

        /* Solo se sirve el documento de la solicitud de quien inició sesión. */
        $ruta = DB::table('documento as d')
            ->join('tipo_documento as t', 't.tido_id_tipo_documento', '=', 'd.tido_id_tipo_documento')
            ->where('d.soli_id_solicitud', $this->solicitudActual())
            ->where('t.tido_tipo_documento', $this->documentos[$documento])
            ->value('d.docu_path');

        if (!$ruta) {
            abort(404);
        }

        $archivo = storage_path('app/'.$ruta);

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
        $idSolicitud = $this->solicitudActual();
        $documentos = $this->documentosGuardados($idSolicitud);

        foreach (array_keys($this->documentos) as $slug) {
            if (empty($documentos[$slug])) {
                return redirect()->route('persona.documentos.index')
                    ->withErrors(['documentos' => 'Debes cargar todos los documentos antes de continuar.']);
            }
        }

        /* Los documentos ya aprobados conservan su estado: al subsanar sólo
           vuelven a revisión los que fueron rechazados o reemplazados. */
        $porRevisar = array_filter(
            $documentos,
            fn (array $doc): bool => $doc['estado'] !== 'aprobado'
        );

        if (!$porRevisar) {
            return redirect()->route('persona.documentos.index')
                ->withErrors(['documentos' => 'Todos tus documentos ya fueron aprobados.']);
        }

        DB::transaction(function () use ($idSolicitud, $porRevisar) {
            foreach ($porRevisar as $doc) {
                $this->registrarEstadoDocumento($doc['id'], 'En revisión');
            }

            $this->registrarEstadoSolicitud($idSolicitud, 'En revisión');
        });

        return redirect()->route('persona.documentos.index')
            ->with('success', count($porRevisar) === count($documentos)
                ? 'Tus documentos fueron enviados a revisión.'
                : 'Los documentos que corregiste fueron enviados a revisión.');
    }

    public function reiniciar(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $request->session()->forget('suif.preregistro');
        $request->session()->forget('suif.persona.estado.preregistro_completo');

        return redirect()->route('persona.preregistro.index');
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
   
    /**
     * Normaliza un nombre o apellido: primera letra de cada palabra en
     * mayúscula y el resto en minúsculas, respetando acentos.
     * Las partículas de los apellidos compuestos quedan en minúscula
     * salvo cuando abren el texto: "Ponce de León", "De la Cruz Pérez".
     */
    private function nombrePropio($texto)
    {
        $texto = trim(preg_replace('/\s+/u', ' ', (string) $texto));
        $texto = mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');

        $particulas = ['De', 'Del', 'La', 'Las', 'Los', 'Y', 'E', 'Da', 'Das', 'Do', 'Dos', 'Van', 'Von'];
        $palabras = explode(' ', $texto);

        foreach ($palabras as $indice => $palabra) {
            if ($indice > 0 && in_array($palabra, $particulas, true)) {
                $palabras[$indice] = mb_strtolower($palabra, 'UTF-8');
            }
        }

        return implode(' ', $palabras);
    }

    /**
     * Devuelve el id de la solicitud de la persona que inició sesión.
     */
    private function solicitudActual()
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return null;
        }

        return DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->where('p.pers_id_usuario', $usuario->usua_id_usuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->value('s.soli_id_solicitud');
    }
        /**
     * Id de la persona que inició sesión.
     */
    private function personaActual()
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return null;
        }

        return DB::table('persona')
            ->where('pers_id_usuario', $usuario->usua_id_usuario)
            ->value('pers_id_persona');
    }

    /**
     * Los datos personales solo se pueden modificar mientras la
     * documentación no haya entrado a revisión.
     */
    private function puedeEditar()
    {
        $fase = $this->faseSegunDocumentos(
            $this->documentosGuardados($this->solicitudActual())
        );

        return !in_array($fase, ['revision', 'aprobado'], true);
    }

    /**
     * Registra un cambio de estado de un documento.
     * No se pisa el anterior: cada cambio es un renglón nuevo.
     */
    private function registrarEstadoDocumento($idDocumento, $estado, $comentario = null)
    {
        $idEstado = DB::table('c_estado_documento')
            ->where('esdo_estado_documento', $estado)
            ->value('esdo_id_c_estado_documento');

        DB::table('estado_documento')->insert([
            'esdo_id_c_estado_documento' => $idEstado,
            'esdo_id_documento' => $idDocumento,
            'esdo_comentarios' => $comentario,
            'esdo_fecha' => now()->toDateString(),
            'esdo_hora' => now()->toTimeString(),
        ]);
    }

    /**
     * Registra un cambio de estado de la solicitud completa.
     */
    private function registrarEstadoSolicitud($idSolicitud, $estado, $motivo = null)
    {
        $idEstado = DB::table('c_estado_solicitud')
            ->where('esso_estado_solicitud', $estado)
            ->value('esso_id_c_estado_solicitud');

        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => $idEstado,
            'esso_id_solicitud' => $idSolicitud,
            'esso_fecha' => now()->toDateString(),
            'esso_hora' => now()->toTimeString(),
            'esso_motivo_rechazo' => $motivo,
        ]);
    }

    /**
     * Deduce la fase de la documentación a partir del estado real de los
     * documentos, en vez de confiar en lo que traiga la sesión.
     */
    private function faseSegunDocumentos(array $documentos)
    {
        $estados = [];

        foreach (array_keys($this->documentos) as $slug) {
            $estados[] = isset($documentos[$slug]) ? $documentos[$slug]['estado'] : 'pendiente';
        }

        $unicos = array_unique($estados);

        if (in_array('rechazado', $estados, true)) {
            return 'rechazado';
        }

        if (count($unicos) === 1 && $estados[0] === 'aprobado') {
            return 'aprobado';
        }

        if (count($unicos) === 1 && $estados[0] === 'revision') {
            return 'revision';
        }

        return 'documentos';
    }

    /**
     * Documentos guardados de una solicitud, con el mismo formato que
     * antes armaba la sesión, para no cambiar la vista.
     */
    private function documentosGuardados($idSolicitud)
    {
        if (!$idSolicitud) {
            return [];
        }

        $filas = DB::table('documento as d')
            ->join('tipo_documento as t', 't.tido_id_tipo_documento', '=', 'd.tido_id_tipo_documento')
            ->where('d.soli_id_solicitud', $idSolicitud)
            ->select('d.docu_id_documento', 'd.docu_path', 'd.docu_nombre', 't.tido_tipo_documento')
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        /* El estado vigente es el ÚLTIMO renglón de ESTADO_DOCUMENTO.
           Al ordenar de menor a mayor, keyBy conserva el último de cada
           documento, que es justo el más reciente. */
        $estados = DB::table('estado_documento as ed')
            ->join('c_estado_documento as ce', 'ce.esdo_id_c_estado_documento', '=', 'ed.esdo_id_c_estado_documento')
            ->whereIn('ed.esdo_id_documento', $filas->pluck('docu_id_documento'))
            ->orderBy('ed.esdo_id_estado_documento')
            ->select('ed.esdo_id_documento', 'ed.esdo_comentarios', 'ce.esdo_estado_documento')
            ->get()
            ->keyBy('esdo_id_documento');

        $equivalencias = [
            'Pendiente' => 'pendiente',
            'Cargado' => 'cargado',
            'En revisión' => 'revision',
            'Aprobado' => 'aprobado',
            'Rechazado' => 'rechazado',
        ];

        $porNombre = array_flip($this->documentos);
        $resultado = [];

        foreach ($filas as $fila) {
            if (!isset($porNombre[$fila->tido_tipo_documento])) {
                continue;
            }

            $estado = isset($estados[$fila->docu_id_documento])
                ? $estados[$fila->docu_id_documento]
                : null;

            $nombreEstado = $estado ? $estado->esdo_estado_documento : 'Cargado';

            $resultado[$porNombre[$fila->tido_tipo_documento]] = [
                'id' => $fila->docu_id_documento,
                'ruta' => $fila->docu_path,
                'nombre_original' => $fila->docu_nombre,
                'estado' => isset($equivalencias[$nombreEstado]) ? $equivalencias[$nombreEstado] : 'cargado',
                'observacion' => $estado ? $estado->esdo_comentarios : null,
            ];
        }

        return $resultado;
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
