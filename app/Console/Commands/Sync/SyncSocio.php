<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncSocio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:socio {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización de la tabla socio de CRM a AWS';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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
            "idSocio" => $value->idSocio,
            "idUnicoMembresia" => $value->idUnicoMembresia,
            "idPersona" => $value->idPersona,
            "idTipoRolCliente" => $value->idTipoRolCliente,
            "idTipoEstatusSocio" => $value->idTipoEstatusSocio,
            "idMantenimiento" => $value->idMantenimiento,
            "idEsquemaPago" => $value->idEsquemaPago,
            "credencial" => $value->credencial,
            "nuevo" => $value->nuevo,
            "numeroAusencias" => $value->numeroAusencias,
            "fechaRegistro" => $value->fechaRegistro,
            "fechaActualizacion" => $value->fechaActualizacion,
            "fechaEliminacion" => $value->fechaEliminacion,
            "mesesConsecutivos" => $value->mesesConsecutivos,
            "asignarLealtad" => $value->asignarLealtad,
            "eliminado" => $value->eliminado
        ];
        return $datos;
    }

    private function socioNuevo()
    {
        $this->line('socio nuevos:');
        try {
            $nuevos = DB::connection('crm')
                ->table('socio')
                ->where('fechaRegistro', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar socio nuevos');
        }
        $bar = $this->output->createProgressBar(count($nuevos));
        foreach ($nuevos as $key => $value) {
            $encontrado = DB::connection('aws')
                ->table('socio')
                ->where('idSocio', '=', $value->idSocio);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('socio')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar socio id: ' . $value->idSocio);
                }
            }
            $bar->advance();
        }
        $bar->finish();
    }

    private function socioActualizacion()
    {
        $this->line(PHP_EOL . 'socio actualizaciones:');
        try {
            $actualizaciones = DB::connection('crm')
                ->table('socio')
                ->whereColumn('fechaRegistro', '!=', 'fechaActualizacion')
                ->where('fechaActualizacion', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar socio actualizadas');
        }
        $bar2 = $this->output->createProgressBar(count($actualizaciones));
        foreach ($actualizaciones as $key => $value) {
            $encontrado = DB::connection('aws')->table('socio')->where('idSocio', '=', $value->idSocio);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('socio')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar socio id: ' . $value->idSocio);
                }
            } else {
                try {
                    DB::connection('aws')->table('socio')
                        ->where('idSocio', '=', $value->idSocio)
                        ->update($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al actualizar socio id: ' . $value->idSocio);
                }
            }
            $bar2->advance();
        }
        $bar2->finish();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('nuevos')) {
            $this->socioNuevo();
        } else if ($this->option('actualizaciones')) {
            $this->socioActualizacion();
        } else {
            $this->socioNuevo();
            $this->socioActualizacion();
        }
        $this->info(PHP_EOL . 'Sincronización completa.');
    }
}
