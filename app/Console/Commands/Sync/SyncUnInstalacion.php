<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncUnInstalacion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:uninstalacion {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización de la uninstalacion uninstalacions de CRM a AWS';

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
            "idUnInstalacion" => $value->idUnInstalacion,
            "idUn" => $value->idUn,
            "idInstalacion" => $value->idInstalacion,
            "activo" => $value->activo,
            "multipleActividad" => $value->multipleActividad,
            "rutaImagen" => $value->rutaImagen,
            "capacidadNormal" => $value->capacidadNormal,
            "capacidadMaxima" => $value->capacidadMaxima
        ];
        return $datos;
    }

    private function uninstalacionNuevo()
    {
        $this->line('uninstalacion nuevos:');
        try {
            $nuevos = DB::connection('crm')
                ->table('uninstalacion')
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar uninstalacion nuevos');
        }
        $bar = $this->output->createProgressBar(count($nuevos));
        foreach ($nuevos as $key => $value) {
            $encontrado = DB::connection('aws')
                ->table('uninstalacion')
                ->where('idUnInstalacion', '=', $value->idUnInstalacion);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('uninstalacion')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar uninstalacion id: ' . $value->idUnInstalacion);
                }
            }
            $bar->advance();
        }
        $bar->finish();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->uninstalacionNuevo();
        $this->info(PHP_EOL . 'Sincronización completa.');
    }
}
