<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CatRutinas extends Model
{

    use SoftDeletes;

    protected $connection = 'aws';
    protected $table      = "piso.cat_rutinas";
    protected $primaryKey = "id";

    const CREATED_AT  = 'fechaRegistro';
    const UPDATED_AT  = 'fechaActualizacion';
    const DELETED_AT  = 'fechaEliminacion';
    protected $hidden = [
        'fechaRegistro', 'fechaActualizacion', 'fechaEliminacion',
    ];
    public static function read_rutina_circuitos($rutina_id)
    {

        $sql = "SELECT rc.id ,rc.num_dia,che.id as idCircuito,che.ejercicio_id,che.orden
        FROM piso.rutina_circuitos AS rc
        INNER JOIN piso.circuitos_has_ejercicios AS che ON rc.id = che.circuito_id
        WHERE rc.rutina_id = {$rutina_id}
        ORDER BY rc.num_dia,orden";
        $res = DB::connection('aws')->select($sql);
        $rutina = [];
        if (count($res) > 0) {
            foreach ($res as $circuito) {
                if (isset($rutina[$circuito->id])) {
                    $rutina[$circuito->id]['ejercicios'][] = [
                        'id'           => $circuito->idCircuito,
                        'ejercicio_id' => $circuito->ejercicio_id,
                        'orden'        => $circuito->orden,
                    ];
                } else {

                    $rutina[$circuito->id] = [
                        'id'         => $circuito->id,
                        'num_dia'    => $circuito->num_dia,
                        'ejercicios' => [
                            [
                                'id'           => $circuito->idCircuito,
                                'ejercicio_id' => $circuito->ejercicio_id,
                                'orden'        => $circuito->orden,
                            ],
                        ],
                    ];

                }
            }

        }
        return array_values($rutina);
    }
    public static function read_rutina_cardios($rutina_id)
    {
        $res = DB::connection('aws')->table('piso.rutina_cardios')
            ->select('id', 'num_semana', 'num_semana', 'equipo_id', 'tiempo', 'intensidad')
            ->where('rutina_id', '=', $rutina_id)->get();

        $arr_return = [];
        if (count($res) > 0) {
            // Se obtiene detalle de circuitos
            $res_arr1 = $res->toArray();
            $res_arr1 = array_map(function ($x) {return (array) $x;}, $res_arr1);
            $arr_return = $res_arr1;
        }
        return $arr_return;
    }
    public static function getMiRutina($idPersona)
    {
        $sql = "SELECT
                        m.id as idRutina,cr.nivel,cr.rutina, m.fecha_inicio,m.Fecha_fin
                    FROM piso.menu as m
                    JOIN  piso.cat_rutinas as cr on m.idRutina=cr.id
                    WHERE
                        m.idPersona = {$idPersona}
                            AND (m.fechaEliminacion = '0000-00-00 00:00:00' || m.fechaEliminacion IS NULL)
                            AND m.fecha_fin > NOW()
                            AND m.fechaCancelacion IS NULL
                    ORDER BY m.fechaRegistro DESC LIMIT 1";
        $res = DB::connection('aws')->select($sql);

        if (count($res) > 0) {
            return ['estatus' => true, 'data' => $res[0]];
        } else {
            return ['estatus' => false, 'mensaje' => 'Acércate a un entrenador para que genere tu rutina'];
        }
    }

    public static function getFullCatalog()
    {

        // Se obtienen rutinas
        $rutinas = DB::connection("aws")->table('piso.cat_rutinas')->select(
            'id',
            'nivel',
            'rutina',
            'premisa_fuerza',
            'premisa_cardio',
            'premisa_clase',
            'premisa_optativa')
            ->where('fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->orderByRaw('rutina ASC , id ASC')->get()->toArray();
        $ids = array_column($rutinas, 'id');
        // Se obtiene Circuitos
        $circuitos = DB::connection("aws")->table('piso.cat_circuitos')
            ->select('rutina_id', 'id', 'num_dia')
            ->whereIn('rutina_id', $ids)
            ->get()->toArray();
        $idsCircuitos = array_column($circuitos, 'id');

        $ejercicios = DB::connection("aws")->table('piso.circuitos_has_ejercicios')
            ->select('circuito_id', 'piso.cat_ejercicios.nombre', 'piso.cat_ejercicios.descripcion', 'orden')
            ->join('piso.cat_ejercicios', 'piso.cat_ejercicios.id', '=', 'piso.circuitos_has_ejercicios.ejercicio_id')
            ->whereIn('circuito_id', $idsCircuitos)
            ->get()->toArray();
        // Se obtienen Cardios
        $cardios = DB::connection("aws")->table('piso.rutina_cardios')
            ->select('rutina_id', 'num_semana', 'equipo_id', 'tiempo', 'intensidad', 'cat_equipos.nombre as equipo')
            ->join('piso.cat_equipos', 'equipo_id', '=', 'cat_equipos.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Fuerzas
        $fuerzas = DB::connection("aws")->table('piso.rutina_fuerzas')
            ->select('rutina_id', 'id', 'num_semana', 'series', 'repeticiones', 'descanso')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Clases
        $clases = DB::connection("aws")->table('piso.cat_clases')
            ->select('rutina_id', 'piso.cat_clases.id', 'nombre', 'descripcion')
            ->join('piso.rutinas_has_clases', 'piso.rutinas_has_clases.clase_id', '=', 'piso.cat_clases.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Optativas
        $optativas = DB::connection("aws")->table('piso.cat_optativas')
            ->select('rutina_id', 'piso.cat_optativas.id', 'nombre')
            ->join('piso.rutinas_has_optativas', 'piso.rutinas_has_optativas.optativa_id', '=', 'piso.cat_optativas.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        $send = [];
        foreach ($rutinas as $k1 => $rutina) {

            $cardioArray = [];
            foreach ($cardios as $keyCardio => $cardio) {
                if ($cardio->rutina_id == $rutina->id) {
                    $cardioArray[] = $cardio;
                    unset($cardios[$keyCardio]);
                }
            }
            $fuerzaArray = [];
            foreach ($fuerzas as $keyFuerza => $fuerza) {
                if ($fuerza->rutina_id == $rutina->id) {
                    $fuerzaArray[] = $fuerza;
                    unset($fuerzas[$keyFuerza]);
                }
            }
            $clasesArray = [];
            foreach ($clases as $keyclases => $clase) {
                if ($clase->rutina_id == $rutina->id) {
                    $clasesArray[] = $clase;
                    unset($clases[$keyclases]);
                }
            }

            $optativasArray = [];
            foreach ($optativas as $keyoptativas => $optativa) {
                if ($optativa->rutina_id == $rutina->id) {
                    $optativasArray[] = $optativa;
                    unset($optativas[$keyoptativas]);
                }
            }
            $circuitosArray = [];
            foreach ($circuitos as $keycircuitos => $circuito) {
                if ($circuito->rutina_id == $rutina->id) {

                    $ejerciciosArray = [];
                    foreach ($ejercicios as $keyejercicios => $ejercicio) {
                        if ($ejercicio->circuito_id == $circuito->id) {
                            $ejerciciosArray[] = [
                                'orden'       => $ejercicio->orden,
                                'descripcion' => $ejercicio->descripcion,
                                'nombre'      => $ejercicio->nombre,
                            ];
                            unset($ejercicios[$keyejercicios]);
                        }
                    }
                    $circuitos[$keycircuitos]->ejercicios = $ejerciciosArray;
                    $circuitosArray[]                     = $circuito;
                    unset($circuitos[$keycircuitos]);
                }
            }

            $send[$rutina->rutina][] = [
                'cardio'           => $cardioArray,
                'fuerza'           => $fuerzaArray,
                'clase'            => $clasesArray,
                'optativa'         => $optativasArray,
                'circuito'         => $circuitosArray,
                'id'               => $rutina->id,
                'nivel'            => $rutina->nivel,
                'premisa_cardio'   => $rutina->premisa_cardio,
                'premisa_clase'    => $rutina->premisa_clase,
                'premisa_fuerza'   => $rutina->premisa_fuerza,
                'premisa_optativa' => $rutina->premisa_optativa,
                'rutina'           => $rutina->rutina,
            ];

        }

        $retval = [
            'code'    => 200,
            'message' => 'ok',
            'data'    => $send,
        ];

        return $retval;

    }

    public static function getFullCatalogv2()
    {

        // Se obtienen rutinas
        $rutinas = DB::connection("aws")->table('piso.cat_rutinas')->select(
            'id',
            'nivel',
            'rutina',
            'premisa_fuerza',
            'premisa_cardio',
            'premisa_clase',
            'premisa_optativa')
            ->where('fechaEliminacion', '=', '0000-00-00 00:00:00')
            ->orderByRaw('rutina ASC , id ASC')->get()->toArray();
        $ids = array_column($rutinas, 'id');
        // Se obtiene Circuitos
        $circuitos = DB::connection("aws")->table('piso.cat_circuitos')
            ->select('rutina_id', 'id', 'num_dia')
            ->whereIn('rutina_id', $ids)
            ->get()->toArray();
        $idsCircuitos = array_column($circuitos, 'id');

        $ejercicios = DB::connection("aws")->table('piso.circuitos_has_ejercicios')
            ->select('circuito_id', 'piso.cat_ejercicios.nombre', 'piso.cat_ejercicios.descripcion', 'orden')
            ->join('piso.cat_ejercicios', 'piso.cat_ejercicios.id', '=', 'piso.circuitos_has_ejercicios.ejercicio_id')
            ->whereIn('circuito_id', $idsCircuitos)
            ->get()->toArray();
        // Se obtienen Cardios
        $cardios = DB::connection("aws")->table('piso.rutina_cardios')
            ->select('rutina_id', 'num_semana', 'equipo_id', 'tiempo', 'intensidad', 'cat_equipos.nombre as equipo')
            ->join('piso.cat_equipos', 'equipo_id', '=', 'cat_equipos.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Fuerzas
        $fuerzas = DB::connection("aws")->table('piso.rutina_fuerzas')
            ->select('rutina_id', 'id', 'num_semana', 'series', 'repeticiones', 'descanso')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Clases
        $clases = DB::connection("aws")->table('piso.cat_clases')
            ->select('rutina_id', 'piso.cat_clases.id', 'nombre', 'descripcion')
            ->join('piso.rutinas_has_clases', 'piso.rutinas_has_clases.clase_id', '=', 'piso.cat_clases.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        // Se obtienen Optativas
        $optativas = DB::connection("aws")->table('piso.cat_optativas')
            ->select('rutina_id', 'piso.cat_optativas.id', 'nombre')
            ->join('piso.rutinas_has_optativas', 'piso.rutinas_has_optativas.optativa_id', '=', 'piso.cat_optativas.id')
            ->whereIn('rutina_id', $ids)
            ->orderBy('rutina_id', 'ASC')
            ->get()->toArray();

        $send = [];
        foreach ($rutinas as $k1 => $rutina) {

            $cardioArray = [];
            foreach ($cardios as $keyCardio => $cardio) {
                if ($cardio->rutina_id == $rutina->id) {
                    $cardioArray[] = $cardio;
                    unset($cardios[$keyCardio]);
                }
            }
            $fuerzaArray = [];
            foreach ($fuerzas as $keyFuerza => $fuerza) {
                if ($fuerza->rutina_id == $rutina->id) {
                    $fuerzaArray[] = $fuerza;
                    unset($fuerzas[$keyFuerza]);
                }
            }
            $clasesArray = [];
            foreach ($clases as $keyclases => $clase) {
                if ($clase->rutina_id == $rutina->id) {
                    $clasesArray[] = $clase;
                    unset($clases[$keyclases]);
                }
            }

            $optativasArray = [];
            foreach ($optativas as $keyoptativas => $optativa) {
                if ($optativa->rutina_id == $rutina->id) {
                    $optativasArray[] = $optativa;
                    unset($optativas[$keyoptativas]);
                }
            }
            $circuitosArray = [];
            foreach ($circuitos as $keycircuitos => $circuito) {
                if ($circuito->rutina_id == $rutina->id) {

                    $ejerciciosArray = [];
                    foreach ($ejercicios as $keyejercicios => $ejercicio) {
                        if ($ejercicio->circuito_id == $circuito->id) {
                            $ejerciciosArray[] = [
                                'orden'       => $ejercicio->orden,
                                'descripcion' => $ejercicio->descripcion,
                                'nombre'      => $ejercicio->nombre,
                            ];
                            unset($ejercicios[$keyejercicios]);
                        }
                    }
                    $circuitos[$keycircuitos]->ejercicios = $ejerciciosArray;
                    $circuitosArray[]                     = $circuito;
                    unset($circuitos[$keycircuitos]);
                }
            }

            $send[$rutina->rutina][] = [
                'cardio'           => $cardioArray,
                'fuerza'           => $fuerzaArray,
                'clase'            => $clasesArray,
                'optativa'         => $optativasArray,
                'circuito'         => $circuitosArray,
                'id'               => $rutina->id,
                'nivel'            => $rutina->nivel,
                'premisa_cardio'   => $rutina->premisa_cardio,
                'premisa_clase'    => $rutina->premisa_clase,
                'premisa_fuerza'   => $rutina->premisa_fuerza,
                'premisa_optativa' => $rutina->premisa_optativa,
                'rutina'           => $rutina->rutina,
            ];

        }

        return $send;

    }

}
