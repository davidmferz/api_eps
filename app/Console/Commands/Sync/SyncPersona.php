<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncPersona extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:persona {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización de la tabla persona de CRM a AWS';

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
    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        if ($this->option('nuevos')) {
            $this->personaNuevo();
        } else if ($this->option('actualizaciones')) {
            $this->personaActualizacion();
        } else {
            $this->personaNuevo();
            $this->personaActualizacion();
        }
        $this->info(PHP_EOL . 'Sincronización completa.');
    }

    private function validFecha($fecha)
    {
        $val = preg_match("/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/", $fecha);
        if ($val) {
            return true;
        } else {
            $this->error('Formato incorrecto de fecha');
            exit;
        }
    }

    private function getFecha()
    {
        $argumento = $this->argument('fecha');
        if ($argumento !== null) {
            if ($this->validFecha($argumento)) {
                $fecha = $argumento;
            }
        } else {
            $fecha = Carbon::now()->subHour()->format('Y-m-d H:i:s');
        }
        return $fecha;
    }

    private function parseDatos($value)
    {
        $datos = [
            "idPersona"           => $value->idPersona,
            "nombre"              => $value->nombre,
            "nombreCorto"         => $value->nombreCorto,
            "paterno"             => $value->paterno,
            "materno"             => $value->materno,
            "idTipoPersona"       => $value->idTipoPersona,
            "fechaNacimiento"     => $value->fechaNacimiento,
            "idTipoSexo"          => $value->idTipoSexo,
            "idTipoEstadoCivil"   => $value->idTipoEstadoCivil,
            "RFC"                 => $value->RFC,
            "CURP"                => $value->CURP,
            "idTipoTituloPersona" => $value->idTipoTituloPersona,
            "fallecido"           => $value->fallecido,
            "idEstado"            => $value->idEstado,
            "idTipoProfesion"     => $value->idTipoProfesion,
            "bloqueoMail"         => $value->bloqueoMail,
            "fechaTour"           => $value->fechaTour,
            "fechaRegistro"       => $value->fechaRegistro,
            "fechaActualizacion"  => $value->fechaActualizacion,
            "fechaEliminacion"    => $value->fechaEliminacion,
            "concesionario"       => $value->concesionario,
            "producto"            => $value->producto,
            "concesionarioValido" => $value->concesionarioValido,
            "idTipoEscolaridad"   => $value->idTipoEscolaridad,
            "idTipoNivelIngresos" => $value->idTipoNivelIngresos,
            "hijos"               => $value->hijos,
            "bloqueoCallCenter"   => $value->bloqueoCallCenter,
            "demo"                => $value->demo,
            "edad"                => $value->edad,
            "bloqueo"             => $value->bloqueo,
            "idEmpresaGrupo"      => $value->idEmpresaGrupo,
        ];
        return $datos;
    }

    private function personaNuevo()
    {
        $this->line('personas nuevas:');
        try {
            $nuevos = DB::connection('crm')
                ->table('persona')
                //->where('idPersona', '=', 2444230)
                ->where('fechaRegistro', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar personas nuevas');
        }
        $bar = $this->output->createProgressBar(count($nuevos));
        foreach ($nuevos as $key => $value) {
            $encontrado = DB::connection('aws')
                ->table('persona')
                ->where('idPersona', '=', $value->idPersona);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('persona')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar persona id: ' . $value->idPersona);
                }
            }
            $bar->advance();
        }
        $bar->finish();
    }

    private function personaActualizacion()
    {
        $this->line(PHP_EOL . 'personas actualizaciones:');
        try {
            $actualizaciones = DB::connection('crm')
                ->table('persona')
                ->whereColumn('fechaRegistro', '!=', 'fechaActualizacion')
                ->where('fechaActualizacion', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar personas actualizadas');
        }
        $bar2 = $this->output->createProgressBar(count($actualizaciones));
        foreach ($actualizaciones as $key => $value) {
            $encontrado = DB::connection('aws')->table('persona')->where('idPersona', '=', $value->idPersona);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('persona')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar persona id: ' . $value->idPersona);
                }
            } else {
                try {
                    DB::connection('aws')->table('persona')
                        ->where('idPersona', '=', $value->idPersona)
                        ->update($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al actualizar persona id: ' . $value->idPersona);
                }
            }
            $bar2->advance();
        }
        $bar2->finish();
    }
}
