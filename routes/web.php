<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RecuperacionClaveController;
use App\Http\Controllers\Admin\AdministradorController as AdminAdministradorController;
use App\Http\Controllers\Admin\ConvocatoriaController as AdminConvocatoriaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentoController as AdminDocumentoController;
use App\Http\Controllers\Admin\GrupoController as AdminGrupoController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\PersonaController as AdminPersonaController;
use App\Http\Controllers\Admin\ReferenciaController as AdminReferenciaController;
use App\Http\Controllers\Admin\ReferenciaEspecialController as AdminReferenciaEspecialController;
use App\Http\Controllers\Admin\ReporteController as AdminReporteController;
use App\Http\Controllers\Admin\ResultadoController as AdminResultadoController;
use App\Http\Controllers\Admin\SedeController as AdminSedeController;
use App\Http\Controllers\Persona\CertificadoController;
use App\Http\Controllers\Persona\DashboardController as PersonaDashboardController;
use App\Http\Controllers\Persona\FacturacionController;
use App\Http\Controllers\Persona\PagoController as PersonaPagoController;
use App\Http\Controllers\Persona\PreRegistroController;
use App\Http\Controllers\Persona\ReferenciaController as PersonaReferenciaController;
use App\Http\Controllers\Persona\ResultadoController as PersonaResultadoController;
use App\Http\Controllers\Persona\SedeController as PersonaSedeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/* Recuperación pública de la clave: la persona escribe su CURP y recibe
   una clave nueva en su correo principal. */
Route::get('/recuperar-clave', [RecuperacionClaveController::class, 'formulario'])->name('clave.recuperar');
Route::post('/recuperar-clave', [RecuperacionClaveController::class, 'restablecer'])->middleware('throttle:recuperar-clave')->name('clave.recuperar.post');

// Ajuste temporal: se eliminó 'middleware' => 'auth' (va al inicio de route::group)

Route::group(['prefix' => 'persona', 'as' => 'persona.'], function () {
    /* Públicas: la persona todavía no tiene cuenta cuando entra aquí. */
    Route::get('/preregistro', [PreRegistroController::class, 'index'])->name('preregistro.index');
    Route::post('/preregistro/datos', [PreRegistroController::class, 'guardarDatos'])->middleware('throttle:preregistro')->name('preregistro.datos.store');

    /* A partir de aquí ya existe la cuenta: se exige sesión iniciada. */
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [PersonaDashboardController::class, 'index'])->name('dashboard');

        Route::post('/preregistro/avanzar', [PreRegistroController::class, 'avanzar'])->name('preregistro.avanzar');
        Route::get('/preregistro/formatos/{documento}', [PreRegistroController::class, 'generarFormato'])->name('preregistro.formatos.generar');
        Route::post('/preregistro/documentos/enviar', [PreRegistroController::class, 'enviarRevision'])->name('preregistro.documentos.enviar');
        Route::post('/preregistro/documentos/{documento}', [PreRegistroController::class, 'subirDocumento'])->name('preregistro.documentos.store');
        Route::get('/preregistro/documentos/{documento}', [PreRegistroController::class, 'verDocumento'])->name('preregistro.documentos.ver');
        Route::get('/preregistro/editar', [PreRegistroController::class, 'editar'])->name('preregistro.editar');
        Route::post('/preregistro/editar', [PreRegistroController::class, 'actualizarDatos'])->name('preregistro.editar.store');

        Route::get('/pago', [PersonaPagoController::class, 'index'])->name('pago.index');
        Route::post('/pago/comprobante', [PersonaPagoController::class, 'subirComprobante'])->name('pago.comprobante');
        Route::post('/pago/tipo-comprobante', [PersonaPagoController::class, 'elegirComprobante'])->name('pago.tipo-comprobante');
        Route::get('/referencia', [PersonaReferenciaController::class, 'index'])->name('referencia.index');
        Route::get('/referencia/individual', [PersonaReferenciaController::class, 'individual'])->name('referencia.individual');
        /* Camino especial: un tercero paga por varias personas con una sola
           referencia. Se captura aquí y la emite la DEC. */
        Route::get('/referencia/especial', [PersonaReferenciaController::class, 'especial'])->name('referencia.especial');
        Route::post('/referencia/especial', [PersonaReferenciaController::class, 'solicitarEspecial'])->name('referencia.especial.store');
        /* Autollenado de la lista: entrega el nombre registrado de una CURP, así
           que va acotado como los demás caminos que exponen datos personales. */
        Route::get('/referencia/especial/persona', [PersonaReferenciaController::class, 'buscarPersona'])
            ->middleware('throttle:buscar-persona')
            ->name('referencia.especial.persona');
        Route::post('/referencia', [PersonaReferenciaController::class, 'generar'])->name('referencia.generar');
        Route::get('/referencia/formato', [PersonaReferenciaController::class, 'formato'])->name('referencia.formato');
        Route::get('/documentos', [PreRegistroController::class, 'documentos'])->name('documentos.index');
        Route::get('/sede', [PersonaSedeController::class, 'index'])->name('sede.index');
        Route::get('/sede/disponibilidad', [PersonaSedeController::class, 'disponibilidad'])->name('sede.disponibilidad');
        Route::post('/sede', [PersonaSedeController::class, 'seleccionar'])->name('sede.seleccionar');
        Route::get('/sede/comprobante', [PersonaSedeController::class, 'comprobante'])->name('sede.comprobante');
        Route::get('/resultados', [PersonaResultadoController::class, 'resultados'])->name('resultados');
        Route::get('/certificado', [CertificadoController::class, 'index'])->name('certificado');
        Route::get('/facturacion', [FacturacionController::class, 'index'])->name('facturacion.index');
        Route::post('/facturacion', [FacturacionController::class, 'store'])->name('facturacion.store');
    });
});

/* Zona administrativa. El grupo entero exige sesión y al menos un privilegio
   del catálogo; qué se puede abrir una vez dentro lo decide el permiso de cada
   módulo.

   Antes no tenía middleware alguno: el tablero, las bandejas de pre-registro,
   el visor de los formatos firmados —que sirve el RFC y la CURP de cada
   solicitante— y los POST que aprueban e interrumpen trámites respondían sin
   sesión iniciada. */
Route::middleware(['auth', 'can:acceder-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        /* Pre-registro y documentación: lo que dictamina la UIF. */
        Route::middleware('can:validar-registro')->group(function () {
            Route::get('/personas', [AdminPersonaController::class, 'index'])->name('personas.index');
            Route::get('/personas-registradas', [AdminPersonaController::class, 'registradas'])->name('personas.registradas.index');
            Route::get('/personas/{solicitud}/documentos/{documento}', [AdminDocumentoController::class, 'ver'])->name('personas.documentos.ver');
            Route::get('/personas/{id}', [AdminPersonaController::class, 'show'])->name('personas.show');
            Route::get('/documentos', [AdminDocumentoController::class, 'index'])->name('documentos.index');
            Route::get('/documentos/{id}/resultado', [AdminDocumentoController::class, 'resultado'])->name('documentos.resultado');
            Route::get('/documentos/{id}', [AdminDocumentoController::class, 'show'])->name('documentos.show');
            Route::post('/documentos/{id}/validar', [AdminDocumentoController::class, 'validar'])->name('documentos.validar');
            Route::post('/documentos/{id}/interrumpir', [AdminDocumentoController::class, 'interrumpir'])->name('documentos.interrumpir');
        });

        /* Cuentas y credenciales: sólo el Superusuario. Restaurar la clave de
           una persona vive aquí y no junto a su bandeja porque toca
           credenciales, no el expediente. */
        Route::middleware('can:gestionar-usuarios')->group(function () {
            Route::post('/personas-registradas/{id}/restaurar-clave', [AdminPersonaController::class, 'restaurarClave'])->name('personas.registradas.restaurar-clave');

            Route::get('/administradores', [AdminAdministradorController::class, 'index'])->name('administradores.index');
            Route::get('/administradores/crear', [AdminAdministradorController::class, 'create'])->name('administradores.create');
            Route::post('/administradores', [AdminAdministradorController::class, 'store'])->name('administradores.store');
            Route::get('/administradores/{id}/editar', [AdminAdministradorController::class, 'edit'])->name('administradores.edit');
            Route::put('/administradores/{id}', [AdminAdministradorController::class, 'update'])->name('administradores.update');
            Route::delete('/administradores/{id}', [AdminAdministradorController::class, 'destroy'])->name('administradores.destroy');
            Route::post('/administradores/{id}/reactivar', [AdminAdministradorController::class, 'reactivar'])->name('administradores.reactivar');
        });

        /* Pagos y referencias bancarias: lo que resuelve la DEC. */
        Route::middleware('can:gestionar-pagos')->group(function () {
            Route::get('/pagos', [AdminPagoController::class, 'index'])->name('pagos.index');
            Route::get('/pagos/{id}/comprobante', [AdminPagoController::class, 'comprobante'])->name('pagos.comprobante');
            Route::post('/pagos/{id}/validar', [AdminPagoController::class, 'validar'])->name('pagos.validar');
            Route::post('/pagos/{id}/rechazar', [AdminPagoController::class, 'rechazar'])->name('pagos.rechazar');
            Route::get('/pagos/{id}/resultado', [AdminPagoController::class, 'resultado'])->name('pagos.resultado');
            Route::get('/pagos/{id}', [AdminPagoController::class, 'show'])->name('pagos.show');
        });

        /* El catálogo de referencias asigna dinero y archivos: lo emite la DEC. */
        Route::middleware('can:gestionar-referencias')->group(function () {
            Route::get('/referencias', [AdminReferenciaController::class, 'index'])->name('referencias.index');
            /* Las referencias especiales se piden desde el trámite y las emite
               la DEC: van antes que '/referencias/{id}/formato' para que
               'especiales' no se lea como un identificador. */
            Route::get('/referencias/especiales', [AdminReferenciaEspecialController::class, 'index'])->name('referencias.especiales.index');
            Route::get('/referencias/especiales/{id}', [AdminReferenciaEspecialController::class, 'show'])->name('referencias.especiales.show');
            Route::post('/referencias/especiales/{id}/emitir', [AdminReferenciaEspecialController::class, 'emitir'])->name('referencias.especiales.emitir');
            Route::get('/referencias/carga', [AdminReferenciaController::class, 'carga'])->name('referencias.carga');
            /* El catálogo y sus formatos entran juntos en un solo ZIP: cargarlos
               por separado dejaba referencias sin PDF, que nadie puede recibir. */
            Route::post('/referencias/paquete', [AdminReferenciaController::class, 'guardarPaquete'])->name('referencias.paquete.store');
            Route::get('/referencias/{id}/formato', [AdminReferenciaController::class, 'formato'])->name('referencias.formato');
        });

        /* Revertir una resolución ya notificada le toca a quien la dictó, no a
           cualquier administrador: la documentación la reabre la UIF y el pago
           lo reabre la DEC. */
        Route::middleware('can:reanudar-tramite')->group(function () {
            Route::post('/documentos/{id}/reanudar', [AdminDocumentoController::class, 'reanudar'])->name('documentos.reanudar');
        });

        Route::middleware('can:reanudar-pago')->group(function () {
            Route::post('/pagos/{id}/reanudar', [AdminPagoController::class, 'reanudar'])->name('pagos.reanudar');
        });

        Route::middleware('can:gestionar-sedes')->group(function () {
            Route::get('/sedes', [AdminSedeController::class, 'index'])->name('sedes.index');
            Route::get('/sedes/crear', [AdminSedeController::class, 'create'])->name('sedes.create');
            Route::post('/sedes', [AdminSedeController::class, 'store'])->name('sedes.store');
            Route::get('/sedes/{id}/editar', [AdminSedeController::class, 'edit'])->name('sedes.edit');
            Route::put('/sedes/{id}', [AdminSedeController::class, 'update'])->name('sedes.update');
            Route::delete('/sedes/{id}', [AdminSedeController::class, 'destroy'])->name('sedes.destroy');
            /* Los grupos son la programación de las sedes: mismo permiso. */
            Route::get('/grupos', [AdminGrupoController::class, 'index'])->name('grupos.index');
            Route::get('/grupos/crear', [AdminGrupoController::class, 'create'])->name('grupos.create');
            Route::post('/grupos', [AdminGrupoController::class, 'store'])->name('grupos.store');
            Route::get('/grupos/{id}/editar', [AdminGrupoController::class, 'edit'])->name('grupos.edit');
            Route::put('/grupos/{id}', [AdminGrupoController::class, 'update'])->name('grupos.update');
            Route::delete('/grupos/{id}', [AdminGrupoController::class, 'destroy'])->name('grupos.destroy');
        });

        /* Las convocatorias marcan el calendario de todo el trámite y el monto
           que se cobra: hoy sólo las administra el Superusuario. El permiso es
           propio y no reutiliza el de usuarios, para que el día que la gestión
           le toque a otra área baste con repartir el privilegio. */
        Route::middleware('can:gestionar-convocatorias')->group(function () {
            Route::get('/convocatorias', [AdminConvocatoriaController::class, 'index'])->name('convocatorias.index');
            Route::get('/convocatorias/crear', [AdminConvocatoriaController::class, 'create'])->name('convocatorias.create');
            Route::post('/convocatorias', [AdminConvocatoriaController::class, 'store'])->name('convocatorias.store');
            Route::get('/convocatorias/{id}/editar', [AdminConvocatoriaController::class, 'edit'])->name('convocatorias.edit');
            Route::put('/convocatorias/{id}', [AdminConvocatoriaController::class, 'update'])->name('convocatorias.update');
            Route::delete('/convocatorias/{id}', [AdminConvocatoriaController::class, 'destroy'])->name('convocatorias.destroy');
            /* Cerrar, interrumpir y reabrir: el destino viaja en el formulario. */
            Route::post('/convocatorias/{id}/estado', [AdminConvocatoriaController::class, 'estado'])->name('convocatorias.estado');
        });

        /* Reportes descargables. La pantalla la abre cualquier área, pero cada
           archivo lleva los datos de un módulo y exige su permiso: el índice
           sólo pinta las tarjetas que quien mira puede descargar, y aun así
           cada descarga se vuelve a comprobar aquí, porque la URL se puede
           escribir a mano. */
        Route::middleware('can:ver-reportes')->group(function () {
            Route::get('/reportes', [AdminReporteController::class, 'index'])->name('reportes.index');
        });

        Route::middleware('can:gestionar-pagos')->group(function () {
            Route::get('/reportes/pagos', [AdminReporteController::class, 'pagos'])->name('reportes.pagos');
            Route::get('/reportes/cfdi', [AdminReporteController::class, 'cfdi'])->name('reportes.cfdi');
        });

        Route::middleware('can:validar-registro')->group(function () {
            Route::get('/reportes/registros', [AdminReporteController::class, 'registros'])->name('reportes.registros');
        });

        /* La lista de un grupo es la programación de una sede: mismo permiso
           que grupos y sedes. El grupo viaja como parámetro de consulta y no
           como segmento de la ruta porque la pantalla lo elige con un <select>
           dentro de un formulario GET: un segmento obligaría a armar la URL
           con JavaScript para algo que el navegador ya hace solo. */
        Route::middleware('can:gestionar-sedes')->group(function () {
            Route::get('/reportes/grupos', [AdminReporteController::class, 'grupo'])->name('reportes.grupos');
            Route::get('/reportes/grupos/lista', [AdminReporteController::class, 'listaFirmas'])->name('reportes.grupos.lista');
        });

        Route::middleware('can:generar-reportes')->group(function () {
            Route::get('/resultados', [AdminResultadoController::class, 'index'])->name('resultados.index');
        });
    });
