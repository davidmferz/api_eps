<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Models\AgendaInbody;
use API_EPS\Models\EventoInscripcion;
use API_EPS\Models\Menu;
use API_EPS\Models\Un;
use Carbon\Carbon;

class ReportesController extends ApiController
{

    public function getRegiones()
    {
        EventoInscripcion::FindClasesTerminadas();
        $catalogo = Un::GetClubsRegiones();
        return $this->successResponse($catalogo, 'catalogo de regiones y clubs.');

    }
    public function getEstadisticasEntrenadores()
    {
        $fecha      = Carbon::now();
        $fecha->day = 1;
        $ini        = $fecha->format('Y-m-d');
        $fin        = $fecha->endOfMonth()->format('Y-m-d');
        $inbodys    = AgendaInbody::GetConteoAgendaPorFecha($ini, $fin);
        $rutinas    = Menu::RutinasClub($ini, $fin);
        dd($rutinas);

    }

}
