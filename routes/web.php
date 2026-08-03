<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentoController as AdminDocumentoController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\ParticipanteController as AdminParticipanteController;
use App\Http\Controllers\Admin\ReferenciaController as AdminReferenciaController;
use App\Http\Controllers\Admin\ResultadoController as AdminResultadoController;
use App\Http\Controllers\Admin\SedeController as AdminSedeController;
use App\Http\Controllers\Participante\CertificadoController;
use App\Http\Controllers\Participante\DashboardController as ParticipanteDashboardController;
use App\Http\Controllers\Participante\DocumentoController as ParticipanteDocumentoController;
use App\Http\Controllers\Participante\FacturacionController;
use App\Http\Controllers\Participante\PagoController as ParticipantePagoController;
use App\Http\Controllers\Participante\PreRegistroController;
use App\Http\Controllers\Participante\ReferenciaController as ParticipanteReferenciaController;
use App\Http\Controllers\Participante\ResultadoController as ParticipanteResultadoController;
use App\Http\Controllers\Participante\SedeController as ParticipanteSedeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Ajuste temporal: se eliminó 'middleware' => 'auth' (va al inicio de route::group)

Route::group(['prefix' => 'participante', 'as' => 'participante.'], function () {
    Route::get('/dashboard', [ParticipanteDashboardController::class, 'index'])->name('dashboard');

    Route::get('/preregistro', [PreRegistroController::class, 'index'])->name('preregistro.index');
    Route::post('/preregistro/datos', [PreRegistroController::class, 'guardarDatos'])->name('preregistro.datos.store');
    Route::post('/preregistro/avanzar', [PreRegistroController::class, 'avanzar'])->name('preregistro.avanzar');
    Route::get('/preregistro/formatos/{documento}', [PreRegistroController::class, 'formato'])->name('preregistro.formatos.ver');
    Route::get('/preregistro/formatos/{documento}/descargar', function ($documento) {
        return app(PreRegistroController::class)->formato($documento, true);
    })->name('preregistro.formatos.descargar');
    Route::post('/preregistro/documentos/{documento}', [PreRegistroController::class, 'subirDocumento'])->name('preregistro.documentos.store');
    Route::get('/preregistro/documentos/{documento}', [PreRegistroController::class, 'verDocumento'])->name('preregistro.documentos.ver');
    Route::post('/preregistro/documentos/enviar', [PreRegistroController::class, 'enviarRevision'])->name('preregistro.documentos.enviar');
    Route::post('/preregistro/finalizar', [PreRegistroController::class, 'finalizar'])->name('preregistro.finalizar');
    Route::get('/preregistro/completado', [PreRegistroController::class, 'completado'])->name('preregistro.completado');
    Route::get('/preregistro/demo/{estado}', [PreRegistroController::class, 'demo'])->name('preregistro.demo');
    Route::get('/preregistro/reiniciar', [PreRegistroController::class, 'reiniciar'])->name('preregistro.reiniciar');

    Route::get('/pago', [ParticipantePagoController::class, 'index'])->name('pago.index');
    Route::post('/pago/comprobante', [ParticipantePagoController::class, 'subirComprobante'])->name('pago.comprobante');
    Route::get('/pago/demo/{estado}', [ParticipantePagoController::class, 'demo'])->name('pago.demo');
    Route::get('/referencia', [ParticipanteReferenciaController::class, 'index'])->name('referencia.index');
    Route::get('/documentos', [ParticipanteDocumentoController::class, 'index'])->name('documentos.index');
    Route::post('/documentos', [ParticipanteDocumentoController::class, 'store'])->name('documentos.store');
    Route::get('/sede', [ParticipanteSedeController::class, 'index'])->name('sede.index');
    Route::post('/sede', [ParticipanteSedeController::class, 'seleccionar'])->name('sede.seleccionar');
    Route::get('/sede/reiniciar', [ParticipanteSedeController::class, 'reiniciar'])->name('sede.reiniciar');
    Route::get('/resultados', [ParticipanteResultadoController::class, 'resultados'])->name('resultados');
    Route::get('/resultados/demo/{estado}', [ParticipanteResultadoController::class, 'demo'])->name('resultados.demo');
    Route::get('/certificado', [CertificadoController::class, 'index'])->name('certificado');
    Route::get('/facturacion', [FacturacionController::class, 'index'])->name('facturacion.index');
    Route::post('/facturacion', [FacturacionController::class, 'store'])->name('facturacion.store');
});

// Acceso temporal sin autenticación para desarrollar los módulos administrativos.
// Restaurar el middleware 'auth' antes de desplegar el proyecto.
Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/participantes', [AdminParticipanteController::class, 'index'])->name('participantes.index');
    Route::get('/participantes/{id}', [AdminParticipanteController::class, 'show'])->name('participantes.show');
    Route::post('/participantes/{id}/aceptar-preregistro', [AdminParticipanteController::class, 'aceptarPreRegistro'])->name('participantes.preregistro.aceptar');
    Route::post('/participantes/{id}/rechazar-preregistro', [AdminParticipanteController::class, 'rechazarPreRegistro'])->name('participantes.preregistro.rechazar');
    Route::get('/participantes/{id}/resultado', [AdminParticipanteController::class, 'resultado'])->name('participantes.resultado');
    Route::get('/pagos', [AdminPagoController::class, 'index'])->name('pagos.index');
    Route::get('/pagos/{id}/comprobante', [AdminPagoController::class, 'comprobante'])->name('pagos.comprobante');
    Route::match(['get', 'post'], '/pagos/{id}/validar', [AdminPagoController::class, 'validar'])->name('pagos.validar');
    Route::post('/pagos/{id}/rechazar', [AdminPagoController::class, 'rechazar'])->name('pagos.rechazar');
    Route::get('/pagos/{id}/resultado', [AdminPagoController::class, 'resultado'])->name('pagos.resultado');
    Route::get('/pagos/{id}', [AdminPagoController::class, 'show'])->name('pagos.show');
    Route::get('/referencias', [AdminReferenciaController::class, 'index'])->name('referencias.index');
    Route::get('/documentos', [AdminDocumentoController::class, 'index'])->name('documentos.index');
    Route::get('/documentos/{id}/resultado', [AdminDocumentoController::class, 'resultado'])->name('documentos.resultado');
    Route::get('/documentos/{id}', [AdminDocumentoController::class, 'show'])->name('documentos.show');
    Route::post('/documentos/{id}/validar', [AdminDocumentoController::class, 'validar'])->name('documentos.validar');
    Route::post('/documentos/{id}/interrumpir', [AdminDocumentoController::class, 'interrumpir'])->name('documentos.interrumpir');
    Route::get('/sedes', [AdminSedeController::class, 'index'])->name('sedes.index');
    Route::post('/sedes', [AdminSedeController::class, 'store'])->name('sedes.store');
    Route::put('/sedes/{id}', [AdminSedeController::class, 'update'])->name('sedes.update');
    Route::delete('/sedes/{id}', [AdminSedeController::class, 'destroy'])->name('sedes.destroy');
    Route::get('/resultados', [AdminResultadoController::class, 'index'])->name('resultados.index');
});
