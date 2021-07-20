<?php

namespace App\Http\Controllers;

use App\Http\Requests\EncuestaV02MaxRequest;
use App\Http\Requests\NuevoFitnessTestRequest;
use App\Models\AgendaInbody;
use App\Models\Menu;
use App\Models\PersonaInbody;
use App\Models\Vo2Max\Abdominales;
use App\Models\Vo2Max\CatFcr;
use App\Models\Vo2Max\CatImc;
use App\Models\Vo2Max\Cooper;
use App\Models\Vo2Max\Estatus;
use App\Models\Vo2Max\FitnessTest;
use App\Models\Vo2Max\Flexibilidad;
use App\Models\Vo2Max\Pushup;
use App\Models\Vo2Max\Rockport;
use App\Models\Vo2Max\SaturacionOxigeno;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FitnessTestController extends ApiController
{

    const SEMANA     = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const SEMANA_ENG = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function getEncuesta()
    {
        $abdominales = Abdominales::select(
            [
                'cat_abdominales.id',
                'cat_abdominales.edadMinima',
                'cat_abdominales.edadMaxima',
                'cat_abdominales.repeticionesMinimas',
                'cat_abdominales.repeticionesMaximas',
                'cat_abdominales.genero',
                'cat_abdominales.idEstatus',
            ]
        )
            ->get();

        $cooper = Cooper::select(
            [
                'cat_cooper.id',
                'cat_cooper.edadMinima',
                'cat_cooper.edadMaxima',
                'cat_cooper.distanciaMinima',
                'cat_cooper.distanciaMaxima',
                'cat_cooper.genero',
                'cat_cooper.idEstatus',
            ]
        )
            ->get();

        $flexibilidad = Flexibilidad::select(
            [
                'cat_flexibilidad.id',
                'cat_flexibilidad.edadMinima',
                'cat_flexibilidad.edadMaxima',
                'cat_flexibilidad.repeticionesMaximas',
                'cat_flexibilidad.repeticionesMinimas',
                'cat_flexibilidad.genero',
                'cat_flexibilidad.idEstatus',
            ]
        )
            ->get();

        $pushup = Pushup::select(
            [
                'cat_pushup.id',
                'cat_pushup.edadMinima',
                'cat_pushup.edadMaxima',
                'cat_pushup.repeticionesMaximas',
                'cat_pushup.repeticionesMinimas',
                'cat_pushup.genero',
                'cat_pushup.idEstatus',
            ]
        )
            ->get();

        $rockport = Rockport::select(
            [
                'cat_rockport.id',
                'cat_rockport.edadMinima',
                'cat_rockport.edadMaxima',
                'cat_rockport.tiempoMinimo',
                'cat_rockport.tiempoMaximo',
                'cat_rockport.genero',
                'cat_rockport.idEstatus',
            ]
        )
            ->get();

        $estatus = Estatus::select(
            [
                'id',
                'nombre',
            ]
        )
            ->get();

        return $this->successResponse(
            [
                'abdominales'  => $abdominales,
                'cooper'       => $cooper,
                'flexibilidad' => $flexibilidad,
                'pushup'       => $pushup,
                'rockport'     => $rockport,
                'estatus'      => $estatus,
            ]
        );
    }

    public function fitnessCrear(EncuestaV02MaxRequest $request)
    {
        // return $request->all();
        $semana         = Carbon::now()->addDays(7)->format('Y-m-d');
        $hoy            = Carbon::now()->format('Y-m-d');
        $encuestaActual = FitnessTest::where('idPersona', $request->idPersona)->whereRaw("date_format(created_at, 'Y%-m%-d%') between '" . $hoy . "' and '" . $semana . "'")->count();

        if ($encuestaActual) {
            $fitness                     = new FitnessTest();
            $fitness->idPersona          = $request->idPersona;
            $fitness->idPersonaEmpleado  = $request->idPersonaEmpleado;
            $fitness->idCatPushUp        = $request->idCatPushUp;
            $fitness->idCatAbdominales   = $request->idCatAbdominales;
            $fitness->idCatFlexibilidad  = $request->idCatFlexibilidad;
            $fitness->vo2Max             = $request->vo2Max;
            $fitness->nombrePruebaVo2Max = $request->nombrePruebaVo2Max;
            if ($fitness->save()) {
                return $this->successResponse([], 'Se genero el registro correctamente');
            } else {
                return $this->errorResponse('Sucedio un error al generar el registro, intenta más tarde');
            }
        } else {
            return $this->errorResponse('Tiene una evaluación no superior de una semana');
        }
    }

    public function setNuevoResgistro(NuevoFitnessTestRequest $request)
    {
        $idPersona          = $request->idPersona;
        $idPersonaEmpleado  = $request->idPersonaEmpleado;
        $peso               = $request->peso ?? 60;
        $abdominales        = $request->abdominales;
        $tiempo             = $request->tiempo ?? 10;
        $distanciaMetros    = $request->distanciaMetros;
        $frecuenciaCardiaca = $request->frecuenciaCardiaca ?? 80;
        $edadRequest        = $request->fcNacimiento != '0000-00-00' ? Carbon::now()->format('Y') - Carbon::parse($request->fcNacimiento)->format('Y') : Carbon::now()->subYears(20)->format('Y') - Carbon::now()->format('Y');
        if ($edadRequest >= 20 && $edadRequest <= 90) {
            $edad = $edadRequest;
        } else {
            $edad = 20;
        }
        // $edad = $edadRequest < 20 ? 20 : $edadRequest;
        $rockportEncuesta = $request->rockportEncuesta ?? false;
        $idNivel          = $request->idNivel ?? 0;
        $flexiones        = $request->flexiones ?? 20;
        $flexibilidad     = $request->flexibilidad ?? 2;
        $generoSexo       = $request->sexo == 13 ? 1 : 0;
        $Vo2MAX           = 0;
        $tipoCuerpo       = $request->tipoCuerpo ?? 'mesomorfo';
        $numComidas       = $request->numComidas ?? 4;
        $idUn             = $request->idUn;
        $idAgenda         = $request->idAgenda ?? null;
        $idRutina         = $request->idRutina;

        $rcc                = $request->rcc;
        $pgc                = $request->pgc;
        $imc                = $request->imc;
        $mme                = $request->mme;
        $mcg                = $request->mcg;
        $act                = $request->act;
        $minerales          = $request->minerales;
        $proteina           = $request->proteina;
        $peso               = $request->peso;
        $estatura           = $request->estatura;
        $fcresp             = $request->fcresp ?? 60;
        $observaciones      = $request->observaciones ?? 'Sin observaciones';
        $idReferenciaOrigen = $request->idReferenciaOrigen ?? 5;
        $sp02Res            = $request->ps02 ?? 90;
        // $menuPersona = Menu::whereRaw("now() between  fecha_inicio and fecha_fin")->where('idPersona', $idPersona)->whereNull('fechaCancelacion')->first();

        $calcMe = number_format(($peso) / pow(($estatura / 100), 2), 2);

        $rCFLu = $request->rCFLu ? true : false;
        $rCFMa = $request->rCFMa ? true : false;
        $rCFMi = $request->rCFMi ? true : false;
        $rCFJu = $request->rCFJu ? true : false;
        $rCFVi = $request->rCFVi ? true : false;
        $rCFSa = $request->rCFSa ? true : false;
        $rCFDo = $request->rCFDo ? true : false;

        $rCCLu = $request->rCCLu ? true : false;
        $rCCMa = $request->rCCMa ? true : false;
        $rCCMi = $request->rCCMi ? true : false;
        $rCCJu = $request->rCCJu ? true : false;
        $rCCVi = $request->rCCVi ? true : false;
        $rCCSa = $request->rCCSa ? true : false;
        $rCCDo = $request->rCCDo ? true : false;

        $rCClLu = $request->rCClLu ? true : false;
        $rCClMa = $request->rCClMa ? true : false;
        $rCClMi = $request->rCClMi ? true : false;
        $rCClJu = $request->rCClJu ? true : false;
        $rCClVi = $request->rCClVi ? true : false;
        $rCClSa = $request->rCClSa ? true : false;
        $rCClDo = $request->rCClDo ? true : false;

        $rCOpLu = $request->rCOpLu ? true : false;
        $rCOpMa = $request->rCOpMa ? true : false;
        $rCOpMi = $request->rCOpMi ? true : false;
        $rCOpJu = $request->rCOpJu ? true : false;
        $rCOpVi = $request->rCOpVi ? true : false;
        $rCOpSa = $request->rCOpSa ? true : false;
        $rCOpDo = $request->rCOpDo ? true : false;

        $fechaInicio = Carbon::now();
        $diasFor     = clone $fechaInicio;
        $fechaFin    = clone $fechaInicio;
        $fechaFin->addDays(27);

        $diasSemana = [
            "lunes"     => [
                "cardio"     => $rCCLu,
                "clases"     => $rCClLu,
                "fuerza"     => $rCFLu,
                "opcionales" => $rCOpLu,
            ],
            "martes"    => [
                "cardio"     => $rCCMa,
                "clases"     => $rCClMa,
                "fuerza"     => $rCFMa,
                "opcionales" => $rCOpMa,
            ],
            "miercoles" => [
                "cardio"     => $rCCMi,
                "clases"     => $rCClMi,
                "fuerza"     => $rCFMi,
                "opcionales" => $rCOpMi,
            ],
            "jueves"    => [
                "cardio"     => $rCCJu,
                "clases"     => $rCClJu,
                "fuerza"     => $rCFJu,
                "opcionales" => $rCOpJu,
            ],
            "viernes"   => [
                "cardio"     => $rCCVi,
                "clases"     => $rCClVi,
                "fuerza"     => $rCFVi,
                "opcionales" => $rCOpVi,
            ],
            "sabado"    => [
                "cardio"     => $rCCSa,
                "clases"     => $rCClSa,
                "fuerza"     => $rCFSa,
                "opcionales" => $rCOpSa,
            ],
            "domingo"   => [
                "cardio"     => $rCCDo,
                "clases"     => $rCClDo,
                "fuerza"     => $rCFDo,
                "opcionales" => $rCOpDo,
            ],
        ];

        foreach ($diasSemana as $key => $value) {
            if (!in_array($key, self::SEMANA)) {
                return $this->errorResponse(' valor inválido:' . $key, 422);
            }
        }

        $actividades = [];
        for ($i = 0; $i < 28; $i++) {

            $dia   = $diasFor->formatLocalized('%A');
            $index = array_search($dia, self::SEMANA_ENG);

            $actividades[$diasFor->format('Y-m-d')] = $diasSemana[self::SEMANA[$index]];
            $diasFor->addDay();
        }

        $idsaveOptativaPreferencia = null;

        $cooper         = null;
        $rock           = null;
        $imcCal         = null;
        $idFcr          = null;
        $adbominalesCom = $this->adbominales($generoSexo, $edad, $abdominales);
        $flexionesCom   = $this->flexiones($generoSexo, $edad, $flexiones);
        $imcCal         = $this->imc($calcMe);
        $idFcr          = $this->fcr($fcresp, $edad, $generoSexo);
        $pushup         = $this->pushUp($generoSexo, $edad, $flexiones);
        if ($rockportEncuesta) {
            $Vo2MAX = 132.6 - (0.17 * $peso) - (0.39 * $edad) + (6.31 * $generoSexo) - (3.27 * $tiempo) - (0.156 * $frecuenciaCardiaca);
            $rock   = Rockport::whereRaw("{$edad} between edadMinima and edadMaxima")->where("genero", $generoSexo)
                ->whereRaw("{$tiempo} between tiempoMinimo and tiempoMaximo")
                ->first();
        } else {
            $Vo2MAX = (($distanciaMetros - 540) / 45);
            $cooper = Cooper::whereRaw("{$edad} between edadMinima and edadMaxima")
                ->where("genero", $generoSexo)
                ->whereRaw("{$distanciaMetros} between distanciaMinima and distanciaMaxima")
                ->first();
        }

        $sp02 = SaturacionOxigeno::whereRaw("{$sp02Res} between so_minimo and so_maximo")->first();

        // return ['adbominalesCom' => $adbominalesCom ? $adbominalesCom->id : null, 'flexionesCom' => $flexionesCom ? $flexionesCom->id : null, 'imcCal' => $imcCal ? $imcCal->id : null, 'idFcr' => $idFcr ? $idFcr->id : null, 'pushup' => $pushup ? $pushup->id : null, 'Vo2MAX' => number_format($Vo2MAX, 2), 'cooper' => $cooper ? $cooper->id : null, 'rock' => $rock, 'requetst' => $request->all(), 'abdominales' => $abdominales, 'genero' => $generoSexo, 'flexiones' => $flexiones, 'bvd' => "132.6 - (0.17 * $peso) - (0.39 * $edad) + (6.31 *  $generoSexo) - (3.27 * $tiempo) - (0.156 * $frecuenciaCardiaca)", 'dd' => "(($distanciaMetros - 540) / 45) * $peso", 'rockportEncuesta' => $rockportEncuesta, 'sp2' => $sp02 ? $sp02->id : null];

        // return $this->successResponse(['sp02' => $sp02]);

        $fitness                     = new FitnessTest();
        $fitness->idPersona          = $idPersona;
        $fitness->idPersonaEmpleado  = $idPersonaEmpleado;
        $fitness->idCatPushUp        = $pushup ? $pushup->id : 1;
        $fitness->idCatAbdominales   = $adbominalesCom ? $adbominalesCom->id : null;
        $fitness->idCatFlexibilidad  = $flexionesCom ? $flexionesCom->id : null;
        $fitness->idCooper           = $cooper ? $cooper->id : null;
        $fitness->idRockport         = $rock ? $rock->id : null;
        $fitness->idImc              = $imcCal ? $imcCal->id : null;
        $fitness->idFcr              = $idFcr ? $idFcr->id : null;
        $fitness->vo2Max             = number_format($Vo2MAX, 2);
        $fitness->nombrePruebaVo2Max = $rock ? 'Rockport' : 'Cooper';
        $fitness->idSp02             = $sp02 ? $sp02->id : null;
        $fitnessSave                 = $fitness->save();
        $lastFitnessTest             = FitnessTest::latest()->where('idPersona', $idPersona)->first();

        AgendaInbody::whereNull('fechaCancelacion')->where('idPersona', $idPersona)->update(
            [
                'fechaCancelacion' => Carbon::now()->format('Y-m-d H:i:s'),
            ]
        );

        $menu = Menu::insertMenu($idUn, $idPersona, $idRutina, Carbon::now(), Carbon::now()->addDays(28), $observaciones, $actividades, $idPersonaEmpleado);
        //
        $agendaSave = null;
        if ($idAgenda) {
            $agendaSave = AgendaInbody::where(['idAgenda' => $idAgenda])->update(
                [
                    'idEmpleado'        => $idPersonaEmpleado,
                    'fechaConfirmacion' => Carbon::now()->format('Y-m-d H:i:s'),
                ]
            );
        } else {
            $agenda                    = new AgendaInbody();
            $agenda->idPersona         = $idPersona;
            $agenda->idEmpleado        = $idPersonaEmpleado;
            $agenda->idUn              = $idUn;
            $agenda->fechaSolicitud    = Carbon::now()->format('Y-m-d H:i:s');
            $agenda->fechaConfirmacion = Carbon::now()->format('Y-m-d H:i:s');
            $agenda->horario           = Carbon::now()->format('H:i:s');
            $agendaSave                = $agenda->save();
            $idAgendaSaved             = AgendaInbody::where('idPersona', $idPersona)->latest()->first();
            $idAgenda                  = $idAgendaSaved->id;
        }
        $agendaSave;
        $peopleIny                       = new PersonaInbody();
        $peopleIny->idPersona            = $idPersona;
        $peopleIny->idPersonaEmpleado    = $idPersonaEmpleado;
        $peopleIny->tipoCuerpo           = $tipoCuerpo;
        $peopleIny->numComidas           = null;
        $peopleIny->RCC                  = $rcc ?? 0;
        $peopleIny->PGC                  = $pgc ?? 0;
        $peopleIny->IMC                  = $calcMe ?? 0;
        $peopleIny->MME                  = $mme ?? 0;
        $peopleIny->MCG                  = $mcg ?? 0;
        $peopleIny->ACT                  = $act ?? 0;
        $peopleIny->minerales            = $minerales ?? 0;
        $peopleIny->genero               = $generoSexo ?? 13;
        $peopleIny->proteina             = $proteina ?? 0;
        $peopleIny->peso                 = number_format($peso, 2);
        $peopleIny->estatura             = $estatura ?? 160;
        $peopleIny->fcresp               = $fcresp ?? 60;
        $peopleIny->pushUp               = $pushup ? $pushup->id : null;
        $peopleIny->tiempo               = $tiempo ?? 15;
        $peopleIny->cooper               = $cooper ? $cooper->id : null;
        $peopleIny->rockport             = $rock ? $rock->id : null;
        $peopleIny->distancia            = $distanciaMetros ?? 1200;
        $peopleIny->adbominales          = $abdominales ?? 29;
        $peopleIny->vo2MAX               = number_format($Vo2MAX, 2) ?? 0;
        $peopleIny->flexibilidad         = $flexibilidad ?? 0;
        $peopleIny->idPersonaFitnessTest = $lastFitnessTest ? $lastFitnessTest->id : null;
        $peopleIny->idMenu               = $menu ? $menu : null;
        $peopleIny->edad                 = $edad ? $edad : 20;
        $peopleIny->sp02                 = $sp02Res;
        $peopleInySave                   = $peopleIny->save();

        if ($fitnessSave) {
            return $this->successResponse(['fitnessSave' => $fitnessSave, 'peopleInySave' => $peopleInySave, 'agendaSave' => $agendaSave, 'lastFitnessTest' => $lastFitnessTest, 'menu' => $menu, 'idAgenda' => $idAgenda, 'dsaveOptativaPreferencia' => $idsaveOptativaPreferencia], 'Se genero el registro correctamente');
        } else {
            return $this->errorResponse('Sucedio un error al generar el registro, intenta más tarde');
        }

        return $request->all();
    }

    public function imc($calc)
    {
        return CatImc::whereRaw("{$calc} between imcMinimo and imcMaximo ")->first();
    }

    public function fcr($fcresp, $edad, $genero)
    {
        return CatFcr::whereRaw("{$edad} between edadMinima and edadMaxima and genero = {$genero} and {$fcresp} between fcrMinimo and fcrMaximo")->first();
    }

    public function pushUp($genero, $edad, $flexiones)
    {
        return Pushup::where('genero', $genero)->whereRaw("{$edad} between edadMinima and edadMaxima")
            ->whereRaw("{$flexiones} between repeticionesMinimas and repeticionesMaximas")
            ->first();
    }

    public function flexiones($genero, $edad, $flexibilidad)
    {
        return Flexibilidad::where('genero', $genero)->whereRaw("{$edad} between edadMinima and edadMaxima")
            ->whereRaw("{$flexibilidad} between repeticionesMinimas and repeticionesMaximas")->first();
    }

    public function adbominales($genero, $edad, $repeticiones)
    {
        return Abdominales::where('genero', $genero)->whereRaw("{$edad} between edadMinima and edadMaxima")
            ->whereRaw("{$repeticiones} between repeticionesMinimas and repeticionesMaximas")->first();
    }

    public function calcularCooperRockport(Request $request)
    {
        $peso               = $request->peso;
        $tiempo             = $request->tiempo ?? 10;
        $distanciaMetros    = $request->distanciaMetros;
        $frecuenciaCardiaca = $request->frecuenciaCardiaca ?? 80;
        $edad               = $request->edad ? Carbon::now()->format('Y') - Carbon::parse($request->edad)->format('Y') : Carbon::now()->subYears(20)->format('Y') - Carbon::now()->format('Y');
        $rockportEncuesta   = $request->rockportEncuesta ?? false;
        $sexo               = $request->sexo;
        $generoSexo         = $sexo == 13 ? 1 : 0;
        $Vo2MAX             = 0;
        $rock               = null;
        $cooper             = null;
        $estatus            = null;

        if ($rockportEncuesta) {
            $rock = Rockport::whereRaw("{$edad} between edadMinima and edadMaxima")->where("genero", $generoSexo)
                ->whereRaw("{$tiempo} between tiempoMinimo and tiempoMaximo")
                ->first();
        } else {
            $cooper = Cooper::whereRaw("{$edad} between edadMinima and edadMaxima")
                ->where("genero", $generoSexo)
                ->whereRaw("{$distanciaMetros} between distanciaMinima and distanciaMaxima")
                ->first();
        }

        if ($cooper || $rock) {
            $estatus = Estatus::where('id', $cooper->idEstatus ?? $rock->idEstatus)->first();
            return $this->successResponse(['cooper' => $cooper, 'rock' => $rock, 'Vo2MAX' => $Vo2MAX, 'estatus' => $estatus]);
        } else {
            return $this->successResponse([], 'Favor de verificar ya que no se encuentra una coincidencia');
        }
    }
}
