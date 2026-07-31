<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index')->name('home');

Route::get('/login', 'AuthController@showLogin')->name('login');
Route::post('/login', 'AuthController@login')->name('login.post');
Route::post('/logout', 'AuthController@logout')->name('logout');

// Ajuste temporal: se eliminó 'middleware' => 'auth' (va al inicio de route::group)

Route::group(['prefix' => 'participante', 'as' => 'participante.', 'namespace' => 'Participante'], function () {
    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');

    Route::get('/preregistro', 'PreRegistroController@index')->name('preregistro.index');
    Route::post('/preregistro/datos', 'PreRegistroController@guardarDatos')->name('preregistro.datos.store');
    Route::post('/preregistro/avanzar', 'PreRegistroController@avanzar')->name('preregistro.avanzar');
    Route::get('/preregistro/formatos/{documento}', 'PreRegistroController@formato')->name('preregistro.formatos.ver');
    Route::get('/preregistro/formatos/{documento}/descargar', function ($documento) {
        return app('App\\Http\\Controllers\\Participante\\PreRegistroController')->formato($documento, true);
    })->name('preregistro.formatos.descargar');
    Route::post('/preregistro/documentos/{documento}', 'PreRegistroController@subirDocumento')->name('preregistro.documentos.store');
    Route::get('/preregistro/documentos/{documento}', 'PreRegistroController@verDocumento')->name('preregistro.documentos.ver');
    Route::post('/preregistro/documentos/enviar', 'PreRegistroController@enviarRevision')->name('preregistro.documentos.enviar');
    Route::post('/preregistro/finalizar', 'PreRegistroController@finalizar')->name('preregistro.finalizar');
    Route::get('/preregistro/completado', 'PreRegistroController@completado')->name('preregistro.completado');
    Route::get('/preregistro/demo/{estado}', 'PreRegistroController@demo')->name('preregistro.demo');
    Route::get('/preregistro/reiniciar', 'PreRegistroController@reiniciar')->name('preregistro.reiniciar');

    Route::get('/pago', 'PagoController@index')->name('pago.index');
    Route::post('/pago/comprobante', 'PagoController@subirComprobante')->name('pago.comprobante');
    Route::get('/pago/demo/{estado}', 'PagoController@demo')->name('pago.demo');
    Route::get('/referencia', 'ReferenciaController@index')->name('referencia.index');
    Route::get('/documentos', 'DocumentoController@index')->name('documentos.index');
    Route::post('/documentos', 'DocumentoController@store')->name('documentos.store');
    Route::get('/sede', 'SedeController@index')->name('sede.index');
    Route::post('/sede', 'SedeController@seleccionar')->name('sede.seleccionar');
    Route::get('/sede/reiniciar', 'SedeController@reiniciar')->name('sede.reiniciar');
    Route::get('/resultados', 'ResultadoController@resultados')->name('resultados');
    Route::get('/certificado', 'CertificadoController@index')->name('certificado');
    Route::get('/facturacion', 'FacturacionController@index')->name('facturacion.index');
    Route::post('/facturacion', 'FacturacionController@store')->name('facturacion.store');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'admin', 'as' => 'admin.', 'namespace' => 'Admin'], function () {
    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');
    Route::get('/participantes', 'ParticipanteController@index')->name('participantes.index');
    Route::get('/participantes/{id}', 'ParticipanteController@show')->name('participantes.show');
    Route::get('/pagos', 'PagoController@index')->name('pagos.index');
    Route::post('/pagos/{id}/validar', 'PagoController@validar')->name('pagos.validar');
    Route::post('/pagos/{id}/rechazar', 'PagoController@rechazar')->name('pagos.rechazar');
    Route::get('/referencias', 'ReferenciaController@index')->name('referencias.index');
    Route::get('/documentos', 'DocumentoController@index')->name('documentos.index');
    Route::post('/documentos/{id}/validar', 'DocumentoController@validar')->name('documentos.validar');
    Route::get('/sedes', 'SedeController@index')->name('sedes.index');
    Route::post('/sedes', 'SedeController@store')->name('sedes.store');
    Route::put('/sedes/{id}', 'SedeController@update')->name('sedes.update');
    Route::delete('/sedes/{id}', 'SedeController@destroy')->name('sedes.destroy');
    Route::get('/resultados', 'ResultadoController@index')->name('resultados.index');
});