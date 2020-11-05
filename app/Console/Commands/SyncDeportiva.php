<?php

namespace App\Console\Commands;

use App\Models\Deportiva\PersonaDeportiva;
use App\Models\Persona;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDeportiva extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncDep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizacion de tablas con el crm';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::debug('inicio');
        $personas = Persona::where('idPersona', '>=', 43961)->where('idPersona', '<', 80000)->orderBy('idPersona', 'ASC')->get();
        foreach ($personas as $key => $value) {
            $personaDep = PersonaDeportiva::find($value->idPersona);
            if ($personaDep == null) {
                Log::debug('creando persona ' . $value->idPersona);

                $personaDep = new PersonaDeportiva();
            } else {
                Log::debug('Actualizando persona ' . $value->idPersona);

            }

            $personaDep->idPersona           = $value->idPersona;
            $personaDep->nombre              = $value->nombre;
            $personaDep->nombreCorto         = $value->nombreCorto;
            $personaDep->paterno             = $value->paterno;
            $personaDep->materno             = $value->materno;
            $personaDep->idTipoPersona       = $value->idTipoPersona;
            $personaDep->fechaNacimiento     = $value->fechaNacimiento;
            $personaDep->idTipoSexo          = $value->idTipoSexo;
            $personaDep->idTipoEstadoCivil   = $value->idTipoEstadoCivil;
            $personaDep->RFC                 = $value->RFC;
            $personaDep->CURP                = $value->CURP;
            $personaDep->idTipoTituloPersona = $value->idTipoTituloPersona;
            $personaDep->fallecido           = $value->fallecido;
            $personaDep->idEstado            = $value->idEstado;
            $personaDep->tour                = $value->tour;
            $personaDep->idTipoProfesion     = $value->idTipoProfesion;
            $personaDep->bloqueoMail         = $value->bloqueoMail;
            $personaDep->fechaTour           = $value->fechaTour;
            $personaDep->fechaRegistro       = $value->fechaRegistro;
            $personaDep->fechaEliminacion    = $value->fechaEliminacion;
            $personaDep->concesionario       = $value->concesionario;
            $personaDep->producto            = $value->producto;
            $personaDep->concesionarioValido = $value->concesionarioValido;
            $personaDep->idTipoEscolaridad   = $value->idTipoEscolaridad;
            $personaDep->idTipoNivelIngresos = $value->idTipoNivelIngresos;
            $personaDep->hijos               = $value->hijos;
            $personaDep->bloqueoCallCenter   = $value->bloqueoCallCenter;
            $personaDep->demo                = $value->demo;
            $personaDep->edad                = $value->edad;
            $personaDep->bloqueo             = $value->bloqueo;
            $personaDep->idEmpresaGrupo      = $value->idEmpresaGrupo;
            $personaDep->save();
        }
        return 0;
    }
}
