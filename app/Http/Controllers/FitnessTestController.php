<?php

namespace App\Http\Controllers;

use App\Models\Vo2Max\Abdominales;
use App\Models\Vo2Max\Cooper;
use App\Models\Vo2Max\Estatus;
use App\Models\Vo2Max\FitnessTest;
use App\Models\Vo2Max\Flexibilidad;
use App\Models\Vo2Max\Pushup;
use App\Models\Vo2Max\Rockport;

class FitnessTestController extends ApiController
{
    public function getEncuesta()
    {
       return $abdominales = Abdominales::select(
            [
                'cat_abdominales.id',
                'cat_estatus.nombre',
                'cat_abdominales.edadMinima',
                'cat_abdominales.edadMaxima',
                'cat_abdominales.repeticionesMinimas',
                'cat_abdominales.repeticionesMaximas',
                'cat_abdominales.genero'
            ]
        )
            ->join('cat_estatus', 'cat_estatus.id', '=', 'cat_abdominales.idEstatus')
            ->get();

        $cooper = Cooper::select(
            [
                'cat_cooper.id',
                'cat_estatus.nombre',
                'cat_cooper.edadMinima',
                'cat_cooper.edadMaxima',
                'cat_cooper.distanciaMinima',
                'cat_cooper.distanciaMaxima',
                'cat_cooper.genero'
            ]
        )
            ->join('cat_estatus', 'cat_estatus.id', '=', 'cat_abdominales.idEstatus')
            ->get();

        $flexibilidad = Flexibilidad::select(
            [
                'cat_flexibilidad.id',
                'cat_estatus.nombre',
                'cat_flexibilidad.edadMinima',
                'cat_flexibilidad.edadMaxima',
                'cat_flexibilidad.repeticionesMaximas',
                'cat_flexibilidad.repeticionesMinimas',
                'cat_flexibilidad.genero'
            ]
        )
            ->join('cat_estatus', 'cat_estatus.id', '=', 'cat_abdominales.idEstatus')
            ->get();

        $pushup = Pushup::select(
            [
                'cat_pushup.id',
                'cat_estatus.nombre',
                'cat_pushup.edadMinima',
                'cat_pushup.edadMaxima',
                'cat_pushup.repeticionesMaximas',
                'cat_pushup.repeticionesMinimas',
                'cat_pushup.genero'
            ]
        )
            ->join('cat_estatus', 'cat_estatus.id', '=', 'cat_abdominales.idEstatus')
            ->get();

        $rockport = Rockport::select(
            [
                'cat_rockport.id',
                'cat_estatus.nombre',
                'cat_rockport.edadMinima',
                'cat_rockport.edadMaxima',
                'cat_rockport.repeticionesMaximas',
                'cat_rockport.repeticionesMinimas',
                'cat_rockport.genero'
            ]
        )
            ->join('cat_estatus', 'cat_estatus.id', '=', 'cat_abdominales.idEstatus')
            ->get();

        return $this->successResponse(
            [
                'abdominales' => $abdominales,
                'cooper' => $cooper,
                'flexibilidad' => $flexibilidad,
                'pushup' => $pushup,
                'rockport' => $rockport
            ]
        );
    }
}
