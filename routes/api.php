<?php

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

        //Route::match(['get', 'options'], 'pruebaMetodo', 'EPController@pruebaMetodo');
        Route::match(['get', 'options'], 'perrito/{id}', 'EpsController@perrito');
        Route::match(['post', 'options'], 'login', 'EPController@login');
        Route::match(['post', 'options'], 'loginOkta', 'EPController@loginOkta');
        Route::match(['get', 'options'], 'agenda/{idEntrenador}/{idUn}', 'EPController@agenda');
        Route::match(['get', 'options'], 'alta/{idInscripcion}/{idEntrenador}/{timestamp}/{empleado?}', 'EPController@alta');
        Route::match(['post', 'options'], 'altaPost', 'EPController@altaPost');
        Route::match(['get', 'options'], 'datosPersona/{idPersona}/{idSocio}/{token}', 'EPController@datosPersona');

        Route::match(['get', 'options'], 'cancelar/{idClase}', 'EPController@cancelar');
        Route::match(['get', 'options'], 'getSesion', 'EPController@getSesion');
        Route::match(['post', 'options'], 'plantrabajo/{idPersona?}', 'EPController@plantrabajo');
        Route::match(['get', 'options'], 'plantrabajo/{idPersona?}', 'EPController@plantrabajo');
        Route::match(['get', 'options'], 'meta/{idPersona}', 'EPController@meta');

        Route::match(['get', 'options'], 'clase/{idEntrenador}/{idUn}', 'EPController@clase');

        Route::match(['get', 'options'], 'general/{idUn}', 'EPController@general');
        Route::match(['post', 'options'], 'inscribir', 'EPController@inscribir');
        Route::match(['get', 'options'], 'reAgendar/{idEventoFecha}/{delay}', 'EPController@reAgendar');
        Route::match(['get', 'options'], 'buscar/{value}', 'EPController@buscar');
        Route::match(['get', 'options'], 'persona/{idPersona}', 'EPController@persona');
        Route::match(['get', 'options'], 'sexo', 'EPController@sexo');
        Route::match(['get', 'options'], 'estadocivil', 'EPController@estadocivil');
        Route::match(['get', 'options'], 'estado', 'EPController@estado');
        Route::match(['get', 'options'], 'datosCliente', 'EPController@datosCliente');
        Route::match(['get', 'options'], 'nuevosClientes', 'EPController@nuevosClientes');
        Route::match(['get', 'options'], 'comisiones/{idPersona?}', 'EPController@comisiones');
        Route::match(['get', 'options'], 'logout', 'EPController@logout');
        Route::match(['get', 'options'], 'perfil/{idPersona}', 'EPController@perfil');
        Route::match(['get', 'options'], 'getEntrenadores/{idUn}', 'EPController@getEntrenadores');

        Route::match(['post', 'options'], 'editarPerfil/{idPersona}', 'EPController@editarPerfil');
        Route::match(['post', 'options'], 'getPlanesDeTrabajoEmpleados', 'EPController@getPlanesDeTrabajoEmpleados');
        Route::match(['post', 'options'], 'buscaPersona', 'EPController@buscaPersona');

        // Promo VISA
        Route::match(['get', 'options'], 'verifyVisa/{idPersona}/{categoria}/{participantes}', 'EPController@verifyVisa');
        Route::match(['get', 'options'], 'dataVisa/{idPersona}', 'EPController@dataVisa');

        // Inbody
        Route::match(['get', 'options'], 'agendaEps/{idEntrenador}/{idUn}', 'InbodyController@agendaEps');
        Route::match(['get', 'options'], 'asignaEntrenador/{idEmpleado}/{idUn}/{idAgenda}/{nombreCoordinador}', 'InbodyController@asignaEntrenador');
        Route::match(['post', 'options'], 'inbody/{idPersona?}/{cantidad?}', 'InbodyController@inbody');
        Route::match(['post', 'options'], 'agendaInbodyCoordinador', 'InbodyController@agendaInbodyCoordinador');

        //calificacion encuestas
        Route::match(['post', 'options'], 'setCalificacion', 'Calificacion@setCalificacion');
        Route::match(['get', 'options'], 'getInfoCalificacion/{idEventoInscripcion}/{token}', 'Calificacion@getInfoCalificacion');
        Route::match(['get', 'options'], 'getCalificacionEntrenadores/{idUn}', 'Calificacion@getCalificacionEntrenadores');

        // RUTINAS ENTRENADORES
        Route::match(['post', 'options'], 'getRutinasEntrenadores', 'RutinasController@getRutinasEntrenadores');

        Route::match(['get', 'options'], 'hola', 'EPController@hola');

        Route::group(['prefix' => 'reporte/'], function () {

            Route::match(['get', 'options'], 'getRegiones', 'ReportesController@getRegiones');
            Route::match(['get', 'options'], 'getReporteRegion/{idRegion}', 'ReportesController@getReporteRegion');

            Route::match(['get', 'options'], 'getEstadisticasEntrenadores', 'ReportesController@getEstadisticasEntrenadores');

        });
    });

});
