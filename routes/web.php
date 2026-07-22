<?php

/*
|--------------------------------------------------------------------------
| Web Routes — SUIF
|--------------------------------------------------------------------------
| Migrado desde: config/routes.php
|
| Todas las rutas web del sistema. Se aplica el middleware 'web' de forma
| automática (definido en app/Http/Kernel.php), que incluye:
|   - EncryptCookies, AddQueuedCookiesToResponse
|   - StartSession, ShareErrorsFromSession
|   - VerifyCsrfToken   ← protección CSRF nativa (reemplaza core/Csrf.php)
|   - SubstituteBindings
|
| Para proteger rutas que requieren autenticación usar middleware 'auth':
|   Route::group(['middleware' => 'auth'], function () { ... });
|
*/

use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Rutas públicas — Landing
// ---------------------------------------------------------------------------

Route::get('/', 'HomeController@index')->name('home');

// ---------------------------------------------------------------------------
// Autenticación (login / logout)
// ---------------------------------------------------------------------------

Route::get('/login', 'AuthController@showLogin')->name('login');
Route::post('/login', 'AuthController@login')->name('login.post');
Route::post('/logout', 'AuthController@logout')->name('logout');

/* Vista de desarrollo sin autenticación. No se registra fuera del entorno local. */
if (app()->environment('local')) {
    Route::get('/participante/dashboard/demo/{escenario?}', 'Participante\DashboardController@demo')
        ->where('escenario', '[a-z-]+')
        ->name('participante.dashboard.demo');
}

// ---------------------------------------------------------------------------
// Panel del Participante (requiere autenticación)
// ---------------------------------------------------------------------------

Route::group(['middleware' => 'auth', 'prefix' => 'participante', 'as' => 'participante.', 'namespace' => 'Participante'], function () {

    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');

    // Pre-registro
    Route::get('/preregistro', 'PreRegistroController@create')->name('preregistro.create');
    Route::post('/preregistro', 'PreRegistroController@store')->name('preregistro.store');

    // Pago y comprobante
    Route::get('/pago', 'PagoController@index')->name('pago.index');
    Route::post('/pago/comprobante', 'PagoController@subirComprobante')->name('pago.comprobante');

    // Referencia bancaria
    Route::get('/referencia', 'ReferenciaController@index')->name('referencia.index');

    // Documentación
    Route::get('/documentos', 'DocumentoController@index')->name('documentos.index');
    Route::post('/documentos', 'DocumentoController@store')->name('documentos.store');

    // Sede
    Route::get('/sede', 'SedeController@index')->name('sede.index');
    Route::post('/sede', 'SedeController@seleccionar')->name('sede.seleccionar');

    // Resultados
    Route::get('/resultados', 'ResultadoController@resultados')->name('resultados');

    // Certificado
    Route::get('/certificado', 'CertificadoController@index')->name('certificado');

    // Facturación
    Route::get('/facturacion', 'FacturacionController@index')->name('facturacion.index');
    Route::post('/facturacion', 'FacturacionController@store')->name('facturacion.store');
});

// ---------------------------------------------------------------------------
// Panel Administrativo (requiere autenticación + rol admin)
// ---------------------------------------------------------------------------

Route::group(['middleware' => ['auth'], 'prefix' => 'admin', 'as' => 'admin.', 'namespace' => 'Admin'], function () {

    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');

    // Participantes
    Route::get('/participantes', 'ParticipanteController@index')->name('participantes.index');
    Route::get('/participantes/{id}', 'ParticipanteController@show')->name('participantes.show');

    // Pagos
    Route::get('/pagos', 'PagoController@index')->name('pagos.index');
    Route::post('/pagos/{id}/validar', 'PagoController@validar')->name('pagos.validar');
    Route::post('/pagos/{id}/rechazar', 'PagoController@rechazar')->name('pagos.rechazar');

    // Referencias
    Route::get('/referencias', 'ReferenciaController@index')->name('referencias.index');

    // Documentación
    Route::get('/documentos', 'DocumentoController@index')->name('documentos.index');
    Route::post('/documentos/{id}/validar', 'DocumentoController@validar')->name('documentos.validar');

    // Sedes
    Route::get('/sedes', 'SedeController@index')->name('sedes.index');
    Route::post('/sedes', 'SedeController@store')->name('sedes.store');
    Route::put('/sedes/{id}', 'SedeController@update')->name('sedes.update');
    Route::delete('/sedes/{id}', 'SedeController@destroy')->name('sedes.destroy');

    // Resultados
    Route::get('/resultados', 'ResultadoController@index')->name('resultados.index');
});
