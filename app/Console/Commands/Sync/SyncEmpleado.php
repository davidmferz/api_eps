<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncEmpleado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:empleado {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización de la tabla empleados de CRM a AWS';

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
        if ($this->option('nuevos')) {
            $this->empleadoNuevo();
        } else if ($this->option('actualizaciones')) {
            $this->empleadoActualizacion();
        } else {
            $this->empleadoNuevo();
            $this->empleadoActualizacion();
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
            "idEmpleado" => $value->idEmpleado,
            "idPersona" => $value->idPersona,
            "idTipoEstatusEmpleado" => $value->idTipoEstatusEmpleado,
            "idArea" => $value->idArea,
            "imss" => (string)$value->imss,
            "rfc" => (string)$value->rfc,
            "credencial" => $value->credencial,
            "alias" => (string)$value->alias,
            "idOperador" => $value->idOperador,
            "fechaContratacion" => (string)$value->fechaContratacion,
            "fechaEliminacion" => (string)$value->fechaEliminacion,
            "fechaActualizacion" => (string)$value->fechaActualizacion,
            "fechaRegistro" => (string)$value->fechaRegistro,
            "perfil_ep" => (string)$value->perfil_ep
        ];
        return $datos;
    }

    private function empleadoNuevo()
    {
        $this->line('empleados nuevos:');
        try {
            $nuevos = DB::connection('crm')
                ->table('empleado')
                ->where('fechaRegistro', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            Log::alert($e);
            $this->error('Error al buscar empleados nuevos');
        }

        $bar = $this->output->createProgressBar(count($nuevos));
        // dd('nuevos', $nuevos, $bar);
        foreach ($nuevos as $key => $value) {
            $this->info($value->idEmpleado);
            $encontrado = DB::connection('aws')
                ->table('empleado')
                ->where('idEmpleado', '=', $value->idEmpleado);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('empleado')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    Log::alert($e);
                    dd(1, $this->parseDatos($value));
                    $this->error(PHP_EOL . 'Error al insertar empleado id: ' . $value->idEmpleado);
                }
            }
            $bar->advance();
        }
        $bar->finish();
        #Log::info('sincronización tabla empleado nuevos');
    }

    private function empleadoActualizacion()
    {
        $this->line(PHP_EOL . 'empleados actualizaciones:');
        $actualizaciones = DB::connection('crm')
            ->table('empleado')
            ->whereColumn('fechaRegistro', '!=', 'fechaActualizacion')
            ->where('fechaActualizacion', '>=', $this->getFecha())
            ->get();

        $bar2 = $this->output->createProgressBar(count($actualizaciones));
        //  dd('actualizaciones', $actualizaciones);

        foreach ($actualizaciones as $key => $value) {
            $this->info($value->idEmpleado);
            $encontrado = DB::connection('aws')->table('empleado')->where('idEmpleado', '=', $value->idEmpleado);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('empleado')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    Log::alert($e);
                    dd(2, $this->parseDatos($value));
                    $this->error(PHP_EOL . 'Error al insertar empleado id: ' . $value->idEmpleado);
                }
            } else {
                try {
                    DB::connection('aws')->table('empleado')
                        ->where('idEmpleado', '=', $value->idEmpleado)
                        ->update($this->parseDatos($value));
                } catch (\Exception $e) {
                    Log::alert($e);
                    dd(3, $this->parseDatos($value));
                    $this->error(PHP_EOL . 'Error al actualizar empleado id: ' . $value->idEmpleado);
                }
            }
            $bar2->advance();
        }
        $bar2->finish();
        #Log::info('sincronización tabla empleado actualizaciones');
    }
}
