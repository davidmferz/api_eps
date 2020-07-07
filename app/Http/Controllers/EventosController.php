<?php

namespace API_EPS\Http\Controllers;

use API_EPS\Http\Controllers\ApiController;
use API_EPS\Http\Requests\InscribirDemoRequest;
use API_EPS\Http\Requests\InscribirProgramaRequest;
use API_EPS\Models\AgendaInbody;
use API_EPS\Models\Categoria;
use API_EPS\Models\DemoClientes;
use API_EPS\Models\Empleado;
use API_EPS\Models\Evento;
use API_EPS\Models\EventoFecha;
use API_EPS\Models\Movimiento;
use API_EPS\Models\Persona;
use API_EPS\Models\Producto;
use API_EPS\Models\Socio;
use API_EPS\Models\Un;
use Illuminate\Support\Facades\DB;

class EventosController extends ApiController
{

    /**
     * [incribir description]
     *
     * @return [type] [description]
     */
    public function inscribirDemo(InscribirDemoRequest $request)
    {
        $idPersonaCliente = $request->input('idCliente');
        $idCategoria      = $request->input('idCategoria');
        $idEntrenador     = $request->input('idEntrenador');
        $idUn             = $request->input('idUn');
        $idVendedor       = $request->input('idVendedor');
        $idUnicoMembresia = $request->input('idUnicoMembresia');
        $fecha            = $request->input('fecha');

        $idEvento   = 2152; // evento del producto demo
        $idEmpleado = Empleado::obtenIdEmpleado($idEntrenador, 1);

        $fechaClase = explode(' ', $fecha);
        $valida     = EventoFecha::ValidaHorario($idEmpleado, $fechaClase[0], $fechaClase[1]);
        $inbodys    = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $idUn, $fecha);
        if ($valida > 0 || COUNT($inbodys) > 0) {
            return $this->errorResponse('La hora ya esta ocupada.');
        }
        $whereDemos = [
            'idPersona'     => $idPersonaCliente,
            'idEmpleado'    => $idEmpleado,
            'CategoriaDemo' => $idCategoria,
        ];
        $registroDemo = DemoClientes::where($whereDemos)->first();
        if ($registroDemo != null) {
            if ($registroDemo->numDemos >= 2) {
                return $this->errorResponse('Excedio el limite de clases demo para este producto.');
            }
        } else {
            $registroDemo                = new DemoClientes();
            $registroDemo->idPersona     = $idPersonaCliente;
            $registroDemo->idEmpleado    = $idEmpleado;
            $registroDemo->categoriaDemo = $idCategoria;
            $registroDemo->numDemos      = 0;
        }

        $idSocio = Socio::obtenIdSocio($idPersonaCliente);

        if ($idSocio > 0) {
            $idUnicoMembresia = Socio::obtenUnicoMembresia($idSocio);
            $tipoCliente      = ROL_CLIENTE_SOCIO;
        }
        //valores para demo
        $totalSesiones = 1;
        $importe       = 0.00;
        $cantidad      = 1;
        $participantes = 1;

        $idIncripcion = Evento::inscripcion(
            $idEvento,
            $idUn,
            $idPersonaCliente,
            $idVendedor,
            $importe,
            0,
            $idUnicoMembresia,
            $cantidad,
            $totalSesiones,
            TIPO_CLIENTEEXTERNO,
            1,
            0,
            $participantes,
            $idEntrenador,
            0
        );
        if ($idIncripcion <= 0) {
            return $this->errorResponse('Error al registrar el demo.');

        }
        $registroDemo->numDemos = $registroDemo->numDemos + 1;
        $registroDemo->save();

        $generales      = Evento::datosGenerales($idEvento, $idUn);
        $cuenta         = Evento::ctaContable($idEvento, $idUn);
        $cuentaProducto = Evento::ctaProducto($idEvento, $idUn);

        $tipoCliente    = ROL_CLIENTE_NINGUNO;
        $nombreClub     = Un::nombre($idUn);
        $descripcionMov = Categoria::campo($idCategoria, 'nombre');
        $origen         = 'APP_WS_EVT_INS-' . str_replace(' ', '_', 'DEMO_' . $descripcionMov);

        $descripcion = 'Demo ' . $descripcionMov . ' - ' . $nombreClub . ' (Num. Inscripcion ' . $idIncripcion . ')';

        $esquemaPago                         = ESQUEMA_PAGO_CONTADO;
        $movimiento                          = new Movimiento();
        $movimiento->idPersona               = $idPersonaCliente;
        $movimiento->idTipoEstatusMovimiento = 70; //excepcion de pago
        $movimiento->idUn                    = $idUn;
        $movimiento->descripcion             = $descripcion;
        $movimiento->importe                 = $importe;
        $movimiento->importeIva              = 0;
        $movimiento->iva                     = 0;
        $movimiento->idunicoMembresia        = $idUnicoMembresia;
        $movimiento->idproducto              = $generales['idProducto'];
        $movimiento->origen                  = $origen;
        $movimiento->msi                     = 1;
        $movimiento->save();

        if ($movimiento->idMovimiento > 0) {

            $cveProductoServicio = Producto::cveProducto($generales['idProducto']);
            $cveUnidad           = Producto::cveUnidad($generales['idProducto']);
            $cta                 = array(
                'idMovimiento'        => $movimiento->idMovimiento,
                'numeroCuenta'        => $cuenta,
                'cuentaProducto'      => $cuentaProducto,
                'idPromocion'         => '0',
                'fechaAplica'         => $fechaClase[0],
                'importe'             => $importe,
                'idTipoMovimiento'    => 100,
                'idUn'                => $idUn,
                'cveProductoServicio' => $cveProductoServicio,
                'cveUnidad'           => $cveUnidad,
                'cantidad'            => $cantidad,
            );
            $movimiento_cta = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
                ->insertGetId($cta);

            Evento::inscripcionMovimiento($idIncripcion, $movimiento->idMovimiento);
            Evento::insertaClase(
                $idIncripcion,
                $idEmpleado,
                $idPersonaCliente,
                $fechaClase[0],
                $fechaClase[1],
                1// demo
            );

        }
        return $this->successResponse($idIncripcion, 'Registro exitoso.');

    }

    public function inscribirProgramaDeportivo(InscribirProgramaRequest $request)
    {
        $idCliente        = $request->input('idCliente');
        $idCategoria      = $request->input('idCategoria');
        $idProducto       = $request->input('idProducto');
        $idTipoCliente    = $request->input('idTipoCliente');
        $idEsquemaPago    = $request->input('idEsquemaPago');
        $idUn             = $request->input('idUn');
        $idVendedor       = $request->input('idVendedor');
        $formaPago        = $request->input('formaPago');
        $importe          = $request->input('importe');
        $tipo             = $request->input('tipo');
        $idUnicoMembresia = $request->input('idUnicoMembresia');
        $demo             = $request->input('demo');
        $cantidad         = $request->input('cantidad');

        $empleado = Empleado::where('idPersona', $idEntrenador)->where('idTipoEstatusEmpleado', 196)->first();
        $query    = DB::connection('crm')->table(TBL_EVENTOUN)
            ->select('idEventoUn', 'edadMinima', 'edadMaxima')
            ->where('idUn', $idUn)
            ->where('idEvento', $idEvento)
            ->where('activo', 1)
            ->where('fechaEliminacion', '0000-00-00 00:00:00')
            ->get()
            ->toArray();
        if (count($query) > 0) {
            $fila       = $query[0];
            $idEventoUn = $fila->idEventoUn;
            $edadMinima = $fila->edadMinima;
            $edadMaxima = $fila->edadMaxima;
        } else {
            return (-1);
        }
        $reg = [
            'idEventoUn'               => $idEventoUn,
            'idPersona'                => $idPersona,
            'idUn'                     => $unSession,
            'idEmpleado'               => $empleadoSession,
            'idTipoEstatusInscripcion' => 1,
            'monto'                    => $monto,
            'pagado'                   => $pagado,
            'cantidad'                 => $cantidad,
            'totalSesiones'            => $totalSesion,
            'idTipoCliente'            => $idTipoCliente,
            'descQuincenas'            => $descQuincenas,
            'informativo'              => $informativo,
            'participantes'            => $participantes,
            'visa'                     => $visa,
        ];

        $inscripcion = DB::connection('crm')->table(TBL_EVENTOINSCRIPCION)->insertGetId($reg);
        $permiso     = new Permiso;
        $permiso->log(
            'Se realiza incripcion al evento ' . $nombre . ' (Num. Inscripcion ' . $inscripcion . ')',
            LOG_EVENTO,
            $membresia,
            $idPersona
        );

        $edad = Persona::edad($idPersona);

        if ((($edadMinima == 0 && $edadMaxima == 0) || ($edad >= $edadMinima && $edad <= $edadMaxima)) && $inscripcion > 0) {
            if ($idCategoria == CATEGORIA_CARRERAS) {
                $aux->guardaParticipante($inscripcion, $idPersona, $idEvento);
            } else {
                if ($idCategoria != CATEGORIA_SUMMERCAMP) {
                    $aux->guardaParticipante($inscripcion, $idPersona);
                }
            }
        }

        $set = array(
            'idEventoInscripcion' => $inscripcion,
            'idPersona'           => $_SESSION['idPersona'],
            'tipo'                => 1,
        );
        $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

        if ($res) {
            $set = array(
                'idEventoInscripcion' => $inscripcion,
                'idPersona'           => $idPersonaRespVta,
                'tipo'                => 2,
            );
            $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);

            if ($idPersonaRespVta1 > 0) {
                $set = array(
                    'idEventoInscripcion' => $inscripcion,
                    'idPersona'           => $idPersonaRespVta1,
                    'tipo'                => 3,
                );
                $res = DB::connection('crm')->table(TBL_EVENTOINVOLUCRADO)->insert($set);
            }
        }

        $eventoInscripcion = EventoInscripcion();
    }

    public function getTipoCliente($idPersona)
    {
        $tipo = Persona::buscaTipoCliente($idPersona);
        return $this->successResponse($tipo, 'tipo cliente.');

    }
}
