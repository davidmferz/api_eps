<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncMembresia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:membresia {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync de CRM a aws de membresias nuevas';

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
            "idUnicoMembresia" => $value->idUnicoMembresia,
            "idMembresia" => $value->idMembresia,
            "idUn" => $value->idUn,
            "idUnicoMembresia" => $value->idUnicoMembresia,
            #"idProducto" => $value->idProducto,
            "idTipoEstatusMembresia" => $value->idTipoEstatusMembresia,
            #"idConvenioDetalle" => $value->idConvenioDetalle,
            "idPeriodoMsi" => $value->idPeriodoMsi,
            "idEsquemaFormaPago" => $value->idEsquemaFormaPago,
            "idUnAlterno" => $value->idUnAlterno,
            "importe" => $value->importe,
            "descuento" => $value->descuento,
            "intransferible" => $value->intransferible,
            "invitado" => $value->invitado,
            "inicioVigencia" => $value->inicioVigencia,
            "finVigencia" => $value->finVigencia,
            "nueva" => $value->nueva,
            "fechaRegistro" => $value->fechaRegistro,
            "fechaActualizacion" => $value->fechaActualizacion,
            "fechaEliminacion" => $value->fechaEliminacion,
            "claveMembresia" => $value->claveMembresia,
            "certificado" => $value->certificado,
            "idMembresiaDescuentoMtto" => $value->idMembresiaDescuentoMtto,
            "reglaCAT" => $value->reglaCAT,
            "ventaEnLinea" => $value->ventaEnLinea,
            "fechaInicioMtto" => $value->fechaInicioMtto,
            "limiteInicioMtto" => $value->limiteInicioMtto,
            "eliminado" => $value->eliminado,
            "fechaActivacion" => $value->fechaActivacion
        ];
        return $datos;
    }

    private function membresiaNuevo()
    {
        $this->line('membresias nuevas:');
        try {
            $nuevos = DB::connection('crm')
                ->table('membresia')
                ->where('fechaRegistro', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            $this->error('Error al buscar membresias nuevas');
        }
        $bar = $this->output->createProgressBar(count($nuevos));
        foreach ($nuevos as $key => $value) {
            $encontrado = DB::connection('aws')
                ->table('membresia')
                ->where('idUnicoMembresia', '=', $value->idUnicoMembresia);
            if ($encontrado->count() === 0) {
                DB::connection('aws')
                    ->table('membresia')
                    ->insert($this->parseDatos($value));
                try {
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar membresia id: ' . $value->idUnicoMembresia);
                }
            }
            $bar->advance();
        }
        $bar->finish();
    }

    private function membresiaActualizacion()
    {
        $this->line(PHP_EOL . 'membresias actualizaciones:');
        $actualizaciones = DB::connection('crm')
            ->table('membresia')
            ->whereColumn('fechaRegistro', '!=', 'fechaActualizacion')
            ->where('fechaActualizacion', '>=', $this->getFecha())
            ->get();
        $bar2 = $this->output->createProgressBar(count($actualizaciones));
        foreach ($actualizaciones as $key => $value) {
            $encontrado = DB::connection('aws')->table('membresia')->where('idUnicoMembresia', '=', $value->idUnicoMembresia);
            if ($encontrado->count() === 0) {
                try {
                    DB::connection('aws')
                        ->table('membresia')
                        ->insert($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar membresia id: ' . $value->idUnicoMembresia);
                }
            } else {
                try {
                    DB::connection('aws')->table('membresia')
                        ->where('idUnicoMembresia', '=', $value->idUnicoMembresia)
                        ->update($this->parseDatos($value));
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al actualizar membresia id: ' . $value->idUnicoMembresia);
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
            $this->membresiaNuevo();
        } else if ($this->option('actualizaciones')) {
            $this->membresiaActualizacion();
        } else {
            $this->membresiaNuevo();
            $this->membresiaActualizacion();
        }
        $this->info(PHP_EOL . 'Sincronización completa.');
    }
}
