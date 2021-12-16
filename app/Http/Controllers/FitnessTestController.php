<?php

namespace App\Http\Controllers;

use App\Http\Requests\EncuestaV02MaxRequest;
use App\Http\Requests\NuevoFitnessTestRequest;
use App\Models\AgendaInbody;
use App\Models\BD_App\Cuestionario;
use App\Models\BD_App\UsuarioPlan;
use App\Models\Menu;
use App\Models\PersonaInbody;
use App\Models\portal_socios\PersonaRewardBitacora;
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
        $abdominales        = intval($request->abdominales);
        $tiempo             = intval($request->tiempo ?? 10);
        $distanciaMetros    = intval($request->distanciaMetros);
        $frecuenciaCardiaca = intval($request->frecuenciaCardiaca ?? 80);
        $edadRequest        = $request->fcNacimiento != '0000-00-00' ? Carbon::now()->format('Y') - Carbon::parse($request->fcNacimiento)->format('Y') : Carbon::now()->subYears(20)->format('Y') - Carbon::now()->format('Y');
        if ($edadRequest >= 20 && $edadRequest <= 90) {
            $edad = $edadRequest;
        } else {
            $edad = 20;
        }
        $usuarioPlan = UsuarioPlan::where('ID_USUARIO', $idPersona)
            ->whereRaw('CURRENT_DATE() BETWEEN FECHA_INICIO AND FECHA_FIN')
            ->where('ESTATUS', 'EN_PROCESO')
            ->first();

        $cuestionario = Cuestionario::find($usuarioPlan->ID_CUESTIONARIO);

        if ($usuarioPlan == null) {
            return $this->errorResponse('Error al identificar el plan del usuario');

        }

        $rockportEncuesta = $request->rockportEncuesta ?? false;
        $flexiones        = intval($request->flexiones ?? 20);
        $flexibilidad     = intval($request->flexibilidad ?? 2);
        $generoSexo       = $request->sexo == 13 ? 1 : 0;
        $Vo2MAX           = 0;
        $tipoCuerpo       = $request->tipoCuerpo ?? 'mesomorfo';
        $idUn             = $request->idUn;
        $idAgenda         = $request->idAgenda ?? null;

        $rcc       = $request->rcc;
        $pgc       = $request->pgc;
        $mme       = $request->mme;
        $mcg       = $request->mcg;
        $act       = $request->act;
        $minerales = $request->minerales;
        $proteina  = $request->proteina;
        $peso      = $request->peso;
        $estatura  = $request->estatura;
        $fcresp    = $request->fcresp ?? 60;

        $sp02Res = $request->ps02 ?? 90;

        $calcMe = number_format(($peso) / pow(($estatura / 100), 2), 2);

        $cooper         = null;
        $rock           = null;
        $imcCal         = null;
        $idFcr          = null;
        $adbominalesCom = $this->adbominales($generoSexo, $edad, $abdominales);
        $flexionesCom   = $this->flexiones($generoSexo, $edad, $flexiones);
        $imcCal         = $this->imc($calcMe);

        $idFcr  = $this->fcr($fcresp, $edad, $generoSexo);
        $pushup = $this->pushUp($generoSexo, $edad, $flexiones);
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

        $menu = Menu::whereRaw('CURRENT_DATE() BETWEEN fecha_inicio AND fecha_fin')
            ->whereNull('fechaCancelacion')
            ->orderBy('id', 'DESC')
            ->first();

        $menu->observaciones = 'REWARD';
        $menu->idPlan        = $usuarioPlan->ID_USUARIO_PLAN;
        $menu->save();

        $bitacora = PersonaRewardBitacora::validaEstatusReward($idPersona);
        if ($bitacora != null) {
            if ($bitacora->idMenu1 == null) {
                $bitacora->idMenu1 = $menu->id;
            } elseif ($bitacora->idMenu2 == null) {
                $bitacora->idMenu2 = $menu->id;
            } else {
                $bitacora->idMenu3 = $menu->id;
            }
            $bitacora->save();
        }

        $cuestionario->ID_MENU  = $menu->id;
        $cuestionario->PESO     = $peso;
        $cuestionario->ESTATURA = $estatura;
        $cuestionario->EDAD     = $edad;
        $cuestionario->IMC      = $calcMe;
        $cuestionario->save();

        $agendaSave = null;
        if ($idAgenda) {
            $agendaSave = AgendaInbody::where(['idAgenda' => $idAgenda])
                ->update(
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
        $peopleIny->idMenu               = $menu->id ? $menu->id : null;
        $peopleIny->edad                 = $edad ? $edad : 20;
        $peopleIny->sp02                 = $sp02Res;
        $peopleInySave                   = $peopleIny->save();

        if ($fitnessSave) {
            return $this->successResponse([
                'fitnessSave'     => $fitnessSave,
                'peopleInySave'   => $peopleInySave,
                'agendaSave'      => $agendaSave,
                'lastFitnessTest' => $lastFitnessTest,
                'menu'            => $menu,
                'idAgenda'        => $idAgenda,
            ], 'Se genero el registro correctamente');
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
