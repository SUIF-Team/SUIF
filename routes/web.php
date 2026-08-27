<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\AdministradorController as AdminAdministradorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentoController as AdminDocumentoController;
use App\Http\Controllers\Admin\GrupoController as AdminGrupoController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\PersonaController as AdminPersonaController;
use App\Http\Controllers\Admin\ReferenciaController as AdminReferenciaController;
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

/* Con sesión abierta, el formulario de acceso sobra: 'guest' devuelve a quien
   ya entró a la pantalla que le toca. */
Route::get('/login', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Ajuste temporal: se eliminó 'middleware' => 'auth' (va al inicio de route::group)

Route::group(['prefix' => 'persona', 'as' => 'persona.'], function () {
    /* Públicas: la persona todavía no tiene cuenta cuando entra aquí. */
    Route::get('/preregistro', [PreRegistroController::class, 'index'])->name('preregistro.index');
    Route::post('/preregistro/datos', [PreRegistroController::class, 'guardarDatos'])->name('preregistro.datos.store');

    /* A partir de aquí ya existe la cuenta: se exige sesión iniciada. */
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [PersonaDashboardController::class, 'index'])->name('dashboard');

        Route::post('/preregistro/avanzar', [PreRegistroController::class, 'avanzar'])->name('preregistro.avanzar');
        Route::get('/preregistro/formatos/{documento}', [PreRegistroController::class, 'generarFormato'])->name('preregistro.formatos.generar');
        Route::post('/preregistro/documentos/enviar', [PreRegistroController::class, 'enviarRevision'])->name('preregistro.documentos.enviar');
        Route::post('/preregistro/documentos/{documento}', [PreRegistroController::class, 'subirDocumento'])->name('preregistro.documentos.store');
        Route::get('/preregistro/documentos/{documento}', [PreRegistroController::class, 'verDocumento'])->name('preregistro.documentos.ver');
        Route::get('/preregistro/reiniciar', [PreRegistroController::class, 'reiniciar'])->name('preregistro.reiniciar');
        Route::get('/preregistro/editar', [PreRegistroController::class, 'editar'])->name('preregistro.editar');
        Route::post('/preregistro/editar', [PreRegistroController::class, 'actualizarDatos'])->name('preregistro.editar.store');

        Route::get('/pago', [PersonaPagoController::class, 'index'])->name('pago.index');
        Route::post('/pago/comprobante', [PersonaPagoController::class, 'subirComprobante'])->name('pago.comprobante');
        Route::get('/referencia', [PersonaReferenciaController::class, 'index'])->name('referencia.index');
        Route::post('/referencia', [PersonaReferenciaController::class, 'generar'])->name('referencia.generar');
        Route::get('/referencia/formato', [PersonaReferenciaController::class, 'formato'])->name('referencia.formato');
        Route::get('/documentos', [PreRegistroController::class, 'documentos'])->name('documentos.index');
        Route::get('/sede', [PersonaSedeController::class, 'index'])->name('sede.index');
        Route::get('/sede/disponibilidad', [PersonaSedeController::class, 'disponibilidad'])->name('sede.disponibilidad');
        Route::post('/sede', [PersonaSedeController::class, 'seleccionar'])->name('sede.seleccionar');
        Route::get('/sede/comprobante', [PersonaSedeController::class, 'comprobante'])->name('sede.comprobante');
        Route::get('/resultados', [PersonaResultadoController::class, 'resultados'])->name('resultados');
        Route::get('/resultados/demo/{estado}', [PersonaResultadoController::class, 'demo'])->name('resultados.demo');
        Route::get('/certificado', [CertificadoController::class, 'index'])->name('certificado');
        Route::get('/facturacion', [FacturacionController::class, 'index'])->name('facturacion.index');
        Route::post('/facturacion', [FacturacionController::class, 'store'])->name('facturacion.store');
    });
});

/* Toda la zona administrativa exige sesión y al menos un privilegio del
   catálogo. Dentro, cada módulo pide el suyo: un administrador de área no ve
   más que su bandeja. */
Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth', 'can:acceder-admin'],
], function () {
    /* El tablero lo abre cualquier administrador, pero sólo le pinta las
       tarjetas de lo que su privilegio permite. */
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    /* El pre-registro y la documentación son un solo trámite: la bandeja lleva
       al expediente y el expediente a la revisión documental. */
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

    /* Revertir una resolución ya notificada le toca a quien la dictó. */
    Route::middleware('can:reanudar-tramite')->group(function () {
        Route::post('/documentos/{id}/reanudar', [AdminDocumentoController::class, 'reanudar'])->name('documentos.reanudar');
        Route::post('/documentos/{id}/cancelar', [AdminDocumentoController::class, 'cancelar'])->name('documentos.cancelar');
    });

    Route::middleware('can:gestionar-pagos')->group(function () {
        Route::get('/pagos', [AdminPagoController::class, 'index'])->name('pagos.index');
        Route::get('/pagos/{id}/comprobante', [AdminPagoController::class, 'comprobante'])->name('pagos.comprobante');
        Route::post('/pagos/{id}/validar', [AdminPagoController::class, 'validar'])->name('pagos.validar');
        Route::post('/pagos/{id}/rechazar', [AdminPagoController::class, 'rechazar'])->name('pagos.rechazar');
        Route::get('/pagos/{id}/resultado', [AdminPagoController::class, 'resultado'])->name('pagos.resultado');
        Route::get('/pagos/{id}', [AdminPagoController::class, 'show'])->name('pagos.show');
    });

    Route::middleware('can:reanudar-pago')->group(function () {
        Route::post('/pagos/{id}/reanudar', [AdminPagoController::class, 'reanudar'])->name('pagos.reanudar');
    });

    /* El catálogo de referencias asigna dinero y archivos, y lo emite la DEC. */
    Route::middleware('can:gestionar-referencias')->group(function () {
        Route::get('/referencias', [AdminReferenciaController::class, 'index'])->name('referencias.index');
        Route::get('/referencias/carga', [AdminReferenciaController::class, 'carga'])->name('referencias.carga');
        Route::post('/referencias/catalogo', [AdminReferenciaController::class, 'guardarCatalogo'])->name('referencias.catalogo.store');
        Route::post('/referencias/formatos', [AdminReferenciaController::class, 'guardarFormatos'])->name('referencias.formatos.store');
        Route::get('/referencias/{id}/formato', [AdminReferenciaController::class, 'formato'])->name('referencias.formato');
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

    /* Quien administra usuarios decide quién entra al sistema: es el permiso
       más delicado del catálogo y no lo tiene ningún administrador de área. */
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::get('/administradores', [AdminAdministradorController::class, 'index'])->name('administradores.index');
        Route::get('/administradores/crear', [AdminAdministradorController::class, 'create'])->name('administradores.create');
        Route::post('/administradores', [AdminAdministradorController::class, 'store'])->name('administradores.store');
        Route::get('/administradores/{id}/editar', [AdminAdministradorController::class, 'edit'])->name('administradores.edit');
        Route::post('/administradores/{id}/reactivar', [AdminAdministradorController::class, 'reactivar'])->name('administradores.reactivar');
        Route::put('/administradores/{id}', [AdminAdministradorController::class, 'update'])->name('administradores.update');
        Route::delete('/administradores/{id}', [AdminAdministradorController::class, 'destroy'])->name('administradores.destroy');
    });

    Route::middleware('can:generar-reportes')->group(function () {
        Route::get('/resultados', [AdminResultadoController::class, 'index'])->name('resultados.index');
    });
});
