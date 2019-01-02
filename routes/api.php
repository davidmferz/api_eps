<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1/'], function () {
    Route::middleware(['cors'])->group(function () {
        Route::match(['get', 'options'], 'perrito/{id}', 'EpsController@perrito');
        Route::match(['post', 'options'], 'login', 'EPController@login');
        Route::match(['get', 'options'], 'agenda/{idEntrenador}/{idUn}', 'EPController@agenda');
        Route::match(['get', 'options'], 'alta/{idInscripcion}/{idEntrenador}/{timestamp}/{empleado}', 'EPController@alta');
        Route::match(['get', 'options'], 'cancelar/{idClase}', 'EPController@cancelar');
        Route::match(['get', 'options'], 'getSesion', 'EPController@getSesion');
        Route::match(['post', 'options'], 'plantrabajo/{idPersona}', 'EPController@plantrabajo');
        Route::match(['get', 'options'], 'meta/{idPersona}', 'EPController@meta');
        Route::match(['get', 'options'], 'clase/{idEntrenador}/{idUn}', 'EPController@clase');
        Route::match(['get', 'options'], 'general/{idUn}', 'EPController@general');
        Route::match(['post', 'options'], 'inscribir', 'EPController@inscribir');
        Route::match(['get', 'options'], 'reAgendar/{idEventoFecha}/{delay}', 'EPController@reAgendar');
        Route::match(['get', 'options'], 'buscar/{value}', 'EPController@buscar');
        Route::match(['get', 'options'], 'persona/{idPersona}', 'EPController@persona');
        Route::match(['get', 'options'], 'sexo', 'EPController@sexo');
        
        
        
    });
});
