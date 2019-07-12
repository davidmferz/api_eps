<?php

namespace API_EPS\Console\Commands;

use API_EPS\Mail\SendEncuestaEvaluacion;
use API_EPS\Models\ConteoMails;
use API_EPS\Models\EventoInscripcion;
use API_EPS\Models\TokenEncuestas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClasesFin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clases:fin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'comando para validar cuando una clase termino';

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
     * @return mixed
     */
    public function handle()
    {
        $idEventoInscripciones = EventoInscripcion::FindClasesTerminadas();
        Log::debug($idEventoInscripciones);
        if (count($idEventoInscripciones) > 0) {
            $conteo             = new ConteoMails();
            $conteo->numCorreos = count($idEventoInscripciones);
            $conteo->save();

            $informacionCorreos = EventoInscripcion::getNombresEmail($idEventoInscripciones);
            foreach ($informacionCorreos as $key => $value) {
                $strToken     = sha1($value->mail . rand());
                $token        = new TokenEncuestas();
                $token->token = $strToken;
                $token->save();
                $value->token = $strToken;
                Mail::to($value->mail)->send(new SendEncuestaEvaluacion($value));
            }

        }
    }
}
