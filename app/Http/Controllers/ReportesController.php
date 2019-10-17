<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Models\AgendaInbody;
use API_EPS\Models\Menu;
use API_EPS\Models\Un;
use Carbon\Carbon;

class ReportesController extends ApiController
{

    public function getRegiones()
    {
        $catalogo = Un::GetClubsRegiones();
        $clubs    = [];
        foreach ($catalogo['clubs'] as $key => $value) {
            $clubs[$key] = array_column($value, 'value');
        }
        $inbodysAgendamientos = AgendaInbody::getReporteInbodysRegion($clubs);
        $rutinasClientes      = Menu::getConteoRutinasRegion($clubs);
        $response             = [
            'catalogos'            => $catalogo,
            'inbodysAgendamientos' => $inbodysAgendamientos,
            'rutinasClientes'      => $rutinasClientes,
        ];
        return $this->successResponse($response, 'catalogo de regiones y datos.');
    }

    public function getReporteRegion($idRegion)
    {
        $clubs    = Un::GetClubsRegiones($idRegion);
        $clubs    = $clubs['clubs'][$idRegion];
        $idsClubs = implode(',', array_column($clubs, 'value'));

        $inbodysAgendamientos = AgendaInbody::getReporteInbodysClub($idsClubs);

        $rutinasClientes = Menu::getConteoRutinasClub($idsClubs);
        $response        = [
            'inbodysAgendamientos' => $inbodysAgendamientos,
            'rutinasClientes'      => $rutinasClientes,
        ];
        return $this->successResponse($response, 'catalogo de regiones y datos.');
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
