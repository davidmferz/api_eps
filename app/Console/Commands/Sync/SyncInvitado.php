<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncInvitado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:invitado {fecha?} {--nuevos} {--actualizaciones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización de la tabla invitadoespecial de CRM a AWS';

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
            "idInvitadoEspecial" => $value->idInvitadoEspecial,
            "idPersona" => $value->idPersona,
            "idEmpleado" => $value->idEmpleado,
            #"idConvenio" => $value->idConvenio,
            #"idConvenioDetalle" => $value->idConvenioDetalle,
            "idTipoInvitadoEspecial" => $value->idTipoInvitadoEspecial,
            "fechaInicio" => $value->fechaInicio,
            "fechaFin" => $value->fechaFin,
            "fechaRegistro" => $value->fechaRegistro,
            "fechaActualizacion" => $value->fechaActualizacion,
            "fechaEliminacion" => $value->fechaEliminacion,
            "horaInicio" => $value->horaInicio,
            "horaFin" => $value->horaFin,
            "activo" => $value->activo,
            "comentario" => $value->comentario,
            "credencial" => $value->credencial,
            "vip" => $value->vip,
            "eliminado" => $value->eliminado,
            "idEmpresaGrupo" => $value->idEmpresaGrupo
        ];
        return $datos;
    }

    private function invitadoNuevo()
    {
        $this->line('invitados nuevos:');
        try {
            //dd($this->getFecha());
            $nuevos = DB::connection('crm')
                ->table('invitadoespecial')
                ->where('fechaRegistro', '>=', $this->getFecha())
                ->get();
        } catch (\Exception $e) {
            Log::error($e);
            $this->error('Error al buscar invitados nuevos');
            exit;
        }
        $bar = $this->output->createProgressBar(count($nuevos));
        foreach ($nuevos as $key => $value) {
            $encontrado = DB::connection('aws')
                ->table('invitadoespecial')
                ->where('idInvitadoEspecial', '=', $value->idInvitadoEspecial);
            if ($encontrado->count() === 0) {
                DB::connection('aws')
                    ->table('invitadoespecial')
                    ->insert($this->parseDatos($value));
                try {
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar invitado id: ' . $value->idInvitadoEspecial);
                }
            }
            $bar->advance();
        }
        $bar->finish();
        #Log::info('sincronización tabla invitado nuevos ');
    }

    private function invitadoActualizacion()
    {
        $this->line(PHP_EOL . 'invitados actualizaciones:');
        $actualizaciones = DB::connection('crm')
            ->table('invitadoespecial')
            ->whereColumn('fechaRegistro', '!=', 'fechaActualizacion')
            ->where('fechaActualizacion', '>=', $this->getFecha())
            ->get();
        $bar2 = $this->output->createProgressBar(count($actualizaciones));
        foreach ($actualizaciones as $key => $value) {
            $encontrado = DB::connection('aws')->table('invitadoespecial')->where('idInvitadoEspecial', '=', $value->idInvitadoEspecial);
            if ($encontrado->count() === 0) {
                DB::connection('aws')
                    ->table('invitadoespecial')
                    ->insert($this->parseDatos($value));
                try {
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al insertar invitado id: ' . $value->idInvitadoEspecial);
                }
            } else {
                DB::connection('aws')->table('invitadoespecial')
                    ->where('idInvitadoEspecial', '=', $value->idInvitadoEspecial)
                    ->update($this->parseDatos($value));
                try {
                } catch (\Exception $e) {
                    $this->error(PHP_EOL . 'Error al actualizar invitado id: ' . $value->idInvitadoEspecial);
                }
            }
            $bar2->advance();
        }
        $bar2->finish();
        #Log::info('sincronización tabla invitado actualizaciones ');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('nuevos')) {
            $this->invitadoNuevo();
        } else if ($this->option('actualizaciones')) {
            $this->invitadoActualizacion();
        } else {
            $this->invitadoNuevo();
            $this->invitadoActualizacion();
        }
        $this->info(PHP_EOL . 'Sincronización completa.');
    }
}
