<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Http\Requests\InscripcionRequest;
use App\Models\AgendaInbody;
use App\Models\Anualidad;
use App\Models\Categoria;
use App\Models\CatRutinas;
use App\Models\Comision;
use App\Models\ComisionMovimiento;
use App\Models\Empleado;
use App\Models\EP;
use App\Models\Evento;
use App\Models\EventoFecha;
use App\Models\EventoInscripcion;
use App\Models\Membresia;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\PromocionVisa;
use App\Models\Socio;
use App\Models\Tipocliente;
use App\Models\Token;
use App\Models\Un;
use App\Models\UsuariosMigracion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Extraído desde el controller /crm/system/application/controllers/ep.php
 * y desde el model /crm/system/application/models/ep_model.php
 */
class EPController extends ApiController
{
    public function getFullCatalog()
    {
        $data = CatRutinas::getFullCatalog();
        return $this->successResponse($data);

    }
    public function catalogoPaquetes($idUn)
    {
        $datos = Producto::getPaquetes($idUn);
        if ($datos) {
            $entrenadores = EP::arrayEntrenadores($datos['idsCategorias'], $idUn, 'lista_cat');
            $datos        = array_merge($datos, ['entrenadores' => $entrenadores]);
            $auxCat       = [];
            foreach ($datos['categorias'] as $key => $value) {
                $auxCat[] = ['id' => $key, 'value' => $value];
            }
            $datos['categorias'] = $auxCat;
            $auxProd             = [];
            foreach ($datos['productos'] as $keyCat => $categorias) {
                foreach ($categorias as $key => $producto) {
                    $auxProd[$keyCat][] = ['id' => $key, 'value' => $producto];
                }
            }
            $datos['categorias'] = $auxCat;
            $datos['productos']  = $auxProd;
            return response()->json($datos, 200);
        } else {
            return response()->json($datos, 400);

        }
    }

    public function inscripcionEvento(InscripcionRequest $request)
    {
        $permiso = new Permiso;

        $idPersona        = $request->input('idCliente');
        $idCategoria      = $request->input('idCategoria');
        $idProducto       = $request->input('idProducto');
        $idTipoCliente    = $request->input('idTipoCliente');
        $idUnicoMembresia = $request->input('idUnicoMembresia');
        $idEsquemaPago    = $request->input('idEsquemaPago');

        $idEntrenador  = $request->input('idEntrenador');
        $idUn          = $request->input('idUn');
        $idVendedor    = $request->input('idVendedor');
        $participantes = $request->input('participantes');
        $clases        = $request->input('clases');
        $formaPago     = $request->input('formaPago');
        $importe       = $request->input('importe');
        $tipo          = $request->input('tipo');
        $demo          = $request->input('demo');
        $cantidad      = $request->input('cantidad');
        $fecha         = $request->input('fecha');

        $empleado = Empleado::where('idPersona', $idEntrenador)->where('idTipoEstatusEmpleado', 196)->first();

        if ($empleado == null) {

            return $this->errorResponse('Empleado esta  dado de baja o no existe', 404);
        }
        $idEmpleado = $empleado->idEmpleado;

        $inscripcion = Evento::inscripcionV2($idUn, $idCategoria, $idPersona, $idVendedor, $idEntrenador, $idTipoCliente, $demo, $idProducto, $cantidad, $importe, $idEsquemaPago);
        Log::debug($inscripcion);
        /* $inscripcion = [
        "estatus"             => true,
        "idEventoInscripcion" => 556163349,
        "cuentaProducto"      => "",
        "numCuenta"           => "4093",
        "idTipoEvento"        => 3,
        ];*/
        if ($inscripcion['estatus']) {

            $esquemaPago = TIPO_ESQUEMA_PAGO_EVENTO_PAQUETE;
            if ($inscripcion['idTipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                $esquemaPago = ESQUEMA_PAGO_CONTADO;
            }
            $desc_extra = '';
            //SOCIO
            if ($idTipoCliente == 1) {
                if ($idUnicoMembresia > 0) {
                    $dias      = Membresia::diasRegistro($idUnicoMembresia);
                    $hoy       = Carbon::now();
                    $anualidad = Anualidad::anualidadPagada($idUnicoMembresia, $hoy->format('Y'));
                    $notUn     = [85, 88, 76];
                    if ($dias >= 0 && $dias <= 7 && !in_array($idUn, $notUn)) {
                        $especial = Evento::precioPrimerSemana($idProducto, $idUn);
                        if ($especial > 0) {
                            $importe    = $especial;
                            $desc_extra = '-1ER_SEMANA';
                        } else {
                            $descuento = Evento::descuentoAnualidad($idProducto, $idUn);
                            if ($descuento > 0 && $anualidad == true) {
                                $importe    = (int) ($importe * ((100 - $descuento) / 100));
                                $desc_extra = '-DESC_ANUAL';
                            }
                        }
                    } else {
                        $descuento = Evento::descuentoAnualidad($idProducto, $idUn);
                        if ($descuento > 0 && $anualidad == true) {
                            $importe    = (int) ($importe * ((100 - $descuento) / 100));
                            $desc_extra = '-DESC_ANUAL';
                        }
                    }
                }
            }

            $descripcionMov = Categoria::campo($idCategoria, 'nombre');
            $nombreClub     = Un::nombre($idUn);

            $descripcion = '';
            if ($inscripcion['idTipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                $descripcion = $inscripcion['productoNombre'] . ' - ' . $nombreClub . ' (Num. Inscripcion ' . $inscripcion['idEventoInscripcion'] . ')';
            } else {
                $descripcion = $descripcionMov . ' ' . $clases * $cantidad . ' Clase(s) ' .
                    $participantes . ' Participante(s) - ' . $nombreClub . ' (Num. Inscripcion ' . $inscripcion['idEventoInscripcion'] . ')';
            }

            $tipoCliente = Tipocliente::find($idTipoCliente);
            $movimiento  = new Movimiento();

            $movimiento->idPersona               = $idPersona;
            $movimiento->idTipoEstatusMovimiento = MOVIMIENTO_PENDIENTE;
            $movimiento->idUn                    = $idUn;
            $movimiento->descripcion             = $descripcion;
            $movimiento->importe                 = $importe;
            $movimiento->iva                     = 16;
            $movimiento->idUnicoMembresia        = $idUnicoMembresia;
            $movimiento->idProducto              = $idProducto;
            $movimiento->origen                  = 'APP_WS_EVT_INS-' . str_replace(' ', '_', strtoupper($tipoCliente->descripcion) . $desc_extra);
            $movimiento->msi                     = $formaPago;
            $movimiento->save();
            Log::debug($movimiento);
            //$movimiento->fecha = date('Y-m-d');
            //$movimiento->tipo                    = MOVIMIENTO_TIPO_EVENTO;
            $idMovimiento = $movimiento->idMovimiento;
            if ($idMovimiento > 0) {
                $numeroCuenta = $inscripcion['numCuenta'];
                if ($numeroCuenta == '' || $numeroCuenta == '0') {
                    $query = DB::connection('crm')->table(TBL_MOVIMIENTO)
                        ->delete(array('idMovimiento' => $movimiento));
                    return (-7);
                } else {
                    $cta = array(
                        'idMovimiento'        => $idMovimiento,
                        'numeroCuenta'        => $numeroCuenta,
                        'cuentaProducto'      => $inscripcion['cuentaProducto'],
                        'idPromocion'         => '0',
                        'fechaAplica'         => date('Y-m-d'),
                        'importe'             => number_format($importe, 2, '.', ''),
                        'idTipoMovimiento'    => MOVIMIENTO_TIPO_EVENTO,
                        'idUn'                => $idUn,
                        'cveProductoServicio' => Producto::cveProducto($idProducto),
                        'cveUnidad'           => Producto::cveUnidad($idProducto),
                        'cantidad'            => $cantidad,
                    );
                    $movimiento_cta = DB::connection('crm')->table(TBL_MOVIMIENTOCTACONTABLE)
                        ->insertGetId($cta);

                    $total = $movimiento_cta;
                    if ($total == 0) {
                        $sql = 'UPDATE movimiento
                            SET idTipoEstatusMovimiento=' . MOVIMIENTO_CANCELADO . ', fechaEliminacion=NOW()
                            WHERE idMovimiento=' . $idMovimiento;
                        $query = DB::connection('crm')->select($sql);
                        return $this->errorResponse('Error al crear el movimiento', 404);
                    }
                    $permiso->log(utf8_decode('Se inserto Movimiento Cta. Contable(' . $movimiento_cta . ') con cuenta (' . $numeroCuenta . ') y movimiento (' . $movimiento . ') (' . date('Y-m-d') . ')'), LOG_SISTEMAS, $idUnicoMembresia);
                }

                Evento::inscripcionMovimiento($inscripcion['idEventoInscripcion'], $idMovimiento);
                Evento::modificaMonto($inscripcion['idEventoInscripcion'], $importe);
                //Se valida si el movimiento se va a devengar en 60 20 20
                $movDevengado = Evento::movientoDevengado($idMovimiento);
                //aplicar Devengado 60 20 20 a Movimiento contable
                //Log::debug($movDevengado);
                if ($movDevengado !== null) {
                    if (0 != $movDevengado->activo && 0 != $movDevengado->autorizado) {
                        $registroContable = Evento::devengarMovimientoContable($idMovimiento);
                        Log::debug($registroContable);
                    }
                }

                $comisionar = Evento::generarComisionVenta($inscripcion['idEvento'], $idUn);
                Log::debug($comisionar);
                if ($comisionar == true) {
                    $descripcionTipoEvento = 'VENTA';
                    $montoComision         = 0;
                    $porcentaje            = 0;

                    $idTipoComision = 13;
                    if ($inscripcion['idTipoEvento'] == TIPO_EVENTO_CLASE) {
                        $descripcionTipoEvento = 'VENTA DE CLASE PERSONALIZADA DE';
                        $idTipoComision        = TIPO_COMISION_CLASEPERSONALIZADA;
                        $porcentaje            = Comision::obtenCapacidad($idUn);
                        $porcentaje            = ($porcentaje == '') ? 0 : $porcentaje;

                    } elseif ($inscripcion['idTipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                        $descripcionTipoEvento = 'VENTA DE PROGRAMA DEPORTIVO DE';
                        $idTipoComision        = TIPO_COMISION_PROGRAMADEPORTIVO;
                        //$isSocio=Socio::where('fechaEliminacion','=','0000-00-00 00:00:00')->where('idPersona','=',$jsonData['idCliente'])->get()->toArray();
                        //SOCIO
                        if ($idTipoCliente != 1) {
                            #Externo
                            $montoComision = Comision::obtenCapacidad($idUn, TIPO_EVENTO_COMISIONEXTERNA);
                        } else {
                            #Socio
                            $montoComision = Comision::obtenCapacidad($idUn, TIPO_EVENTO_COMISIONINTERNA);
                        }
                    } elseif ($inscripcion['idTipoEvento'] == TIPO_EVENTO_CURSOVERANO) {
                        $idTipoComision = TIPO_COMISION_CURSODEVERANO;
                    }

                    $comision                        = new Comision();
                    $comision->idTipoEstatusComision = TIPO_ESTATUSCOMISION_SINFACTURAR;
                    $comision->idUn                  = $idUn;
                    $comision->idTipoComision        = $idTipoComision;
                    $comision->idPersona             = $empleado->idPersona;
                    $comision->importe               = $importe;
                    $comision->descripcion           = $descripcionTipoEvento . ' ' . strtoupper($descripcion);
                    $comision->montoComision         = $montoComision;
                    $comision->porcentaje            = $porcentaje;
                    $comision->manual                = 0;
                    Log::debug(print_r($comision, true));
                    $comision->save();
                    $comisionMovimiento = new ComisionMovimiento();

                    $comisionMovimiento->idComision   = $comision->idComision;
                    $comisionMovimiento->idMovimiento = $movimiento->idMovimiento;
                    $comisionMovimiento->save();
                }
                return $this->successResponse($inscripcion);

            } else {

                return $this->errorResponse('Error al crear el movimiento', 404);
            }
        } else {
            return $this->errorResponse('Error al realizar la inscripcion', 404);
        }

    }

    public function datosPersona($idPersona, $idSocio, $token)
    {
        if (!Token::ValidaToken($token)) {
            return $this->errorResponse('Token Invalido  ');

        }
        $idPersona = intval($idPersona);
        $idSocio   = intval($idSocio);
        if ($idSocio > 0 && $idPersona > 0) {
            $result = Persona::DatosPersona($idPersona, $idSocio);
            if (count($result) > 0) {

                return $this->successResponse($result, 'Cliente encontrado  ');
            } else {
                return $this->errorResponse('Cliente no encontrado ');

            }
        } else {
            return $this->errorResponse('error de datos ');
        }
    }

    /**
     * [agenda description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public function agenda($idEntrenador, $idUn)
    {
        session_write_close();
        $datos = EP::agenda($idEntrenador, $idUn);

        if (is_array($datos)) {
            $retval = array(
                'status'  => 'OK',
                'data'    => $datos,
                'code'    => 200,
                'message' => 'OK',
            );
        } else {
            $retval = array(
                'status'  => 'Error',
                'data'    => 'No se encontro información',
                'code'    => 400,
                'message' => 'Error',
            );
        }
        return response()->json($retval, $retval['code']);
    }

    /**
     * [alta description]
     *
     * @param  [type] $idInscripcion [id de tabla EVENTOINSCRIPCION]
     * @param  [type] $idEntrenador  [idPersona de la persona logueada]
     * @param  [type] $timestamp     [fecha para la clase]
     * @param  [type] $empleado      [idEmpleado 'para cuando un gerente hace una asignacion']
     *
     * @return [Json] $retval        [respuesta para FRONT]
     */
    public function altaPost(Request $request)
    {

        $clases = $request->input('clases');
        if (COUNT($clases) <= 0) {
            return $this->errorResponse('Selecciona minimo una clase');

        }
        $send = [];
        foreach ($clases as $key => $value) {

            $idEmpleado    = ($value['empleado'] != null) ? $value['empleado'] : Empleado::obtenIdEmpleado($value['idPersona']);
            $datosEmpleado = Empleado::DatosEmpleadoPuesto($idEmpleado);
            $fechaClase    = explode(' ', $value['timestamp']);

            $valida  = EventoFecha::ValidaHorario($idEmpleado, $fechaClase[0], $fechaClase[1]);
            $inbodys = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $datosEmpleado['idUn']);
            if ($valida > 0 && COUNT($inbodys) > 0) {
                $send[] = [
                    'estatus'   => 'error',
                    'mensaje'   => 'clase ocupada para la fecha ' . $fechaClase[0] . ' y hora ' . $fechaClase[1],
                    'timestamp' => $value['timestamp'],
                ];
                continue;
            }

            $fecha   = substr($value['timestamp'], 0, 10);
            $hora    = substr($value['timestamp'], 11, 8);
            $idClase = Evento::insertaClase($value['idInscripcion'], $idEmpleado, $value['idPersona'], $fecha, $hora);
            $retval  = array();
            if ($idClase > 0) {
                $send[] = [
                    'estatus' => 'ok',
                    'mensaje' => 'clase registrada',
                    'idClase' => $idClase,
                ];

            } else {
                $send[] = [
                    'estatus' => 'error',
                    'mensaje' => 'error para inscribir a la fecha ' . $fechaClase[0] . ' y hora ' . $fechaClase[1],
                ];
            }
        }
        return $this->successResponse($send, 'clases estatus inscripcion ');

    }

    /**
     * [alta description]
     *
     * @param  [type] $idInscripcion [id de tabla EVENTOINSCRIPCION]
     * @param  [type] $idEntrenador  [idPersona de la persona logueada]
     * @param  [type] $timestamp     [fecha para la clase]
     * @param  [type] $empleado      [idEmpleado 'para cuando un gerente hace una asignacion']
     *
     * @return [Json] $retval        [respuesta para FRONT]
     */
    public function alta($idInscripcion, $idEntrenador, $timestamp, $empleado = null)
    {
        $idEmpleado    = ($empleado != null) ? $empleado : Empleado::obtenIdEmpleado($idEntrenador);
        $datosEmpelado = Empleado::DatosEmpleadoPuesto($idEmpleado);
        $fechaClase    = explode(' ', $timestamp);

        $valida  = EventoFecha::ValidaHorario($idEmpleado, $fechaClase[0], $fechaClase[1]);
        $inbodys = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $datosEmpelado['idUn']);
        if ($valida > 0 && COUNT($inbodys) > 0) {
            $error['status']    = 400;
            $error['message']   = 'La hora ya esta ocupada ';
            $error['code']      = '1010';
            $error['more_info'] = 'http://localhost/docs/error/1010';
            return response()->json($error, $error['status']);
        }

        $fecha   = substr($timestamp, 0, 10);
        $hora    = substr($timestamp, 11, 8);
        $idClase = Evento::insertaClase($idInscripcion, $idEmpleado, $idEntrenador, $fecha, $hora);
        $retval  = array();
        if ($idClase > 0) {
            $clase['idClase']  = $idClase;
            $retval['status']  = 'OK';
            $retval['data']    = $clase;
            $retval['code']    = 200;
            $retval['message'] = 'OK';
        } else {
            $retval['status']  = 'ERROR';
            $retval['data']    = array();
            $retval['code']    = 400;
            $retval['message'] = 'Error al registrar la clase';
        }
        return response()->json($retval, $retval['code']);
    }

    /**
     * [cancelar description]
     *
     * @param  [type] $idClase [description]
     *
     * @return [type]          [description]
     */
    public function cancelar($idClase)
    {
        session_write_close();
        settype($idClase, 'integer');

        $estatus = Evento::eliminarClase($idClase);
        if ($estatus == true) {
            $error['status']    = 200;
            $error['message']   = 'Clase eliminada';
            $error['code']      = '0';
            $error['more_info'] = 'Ok';
        } else {
            $error['status']    = 400;
            $error['message']   = 'Error al cancelar clase';
            $error['code']      = '1009';
            $error['more_info'] = 'http://localhost/docs/error/1009';
        }
        $retval = $error;
        return response()->json($retval, $retval['status']);
    }

    /**
     * getSesion - Obtener datos de la sesión (si existe)
     * @return json/array
     */
    public function getSesion(Request $request)
    {
        if (isset($_SESSION['idPersona'])) {
            $out =
            array(
                'idPersona'             => $_SESSION['idPersona'],
                'nombre'                => $_SESSION['nombre'],
                'idEmpleado'            => $_SESSION['idEmpleado'],
                'idTipoEstatusEmpleado' => $_SESSION['idTipoEstatusEmpleado'],
                'idUn'                  => $_SESSION['idUn'],
                'unNombre'              => $_SESSION['unNombre'],
                'idPuesto'              => $_SESSION['idPuesto'],
                'puestoNombre'          => $_SESSION['puestoNombre'],
                'entrenadores'          => $_SESSION['entrenadores'],

                'NumSeguroSocial'       => $_SESSION['NumSeguroSocial'],
                'razonSocial'           => $_SESSION['razonSocial'],

                'perfil_ep'             => $_SESSION['perfil_ep'],
                'calificacion'          => $_SESSION['calificacion'],
                'version'               => '3.0.5', // Objeto::obtenerObjeto(953)['descripcion'],
                'clubs'                 => $_SESSION['clubs'],
            );
            return response()->json($out, 200);
        } else {
            return response()->json(null, 402);
        }
    }

    public function getPlanesDeTrabajoEmpleados(Request $request)
    {
        session_write_close();
        $entrenadores = $request->input('entrenadores');
        if (count($entrenadores) > 0) {
            $send = [];

            $meta = EP::metaVentaArray($entrenadores);
            $plan = EP::renovacionesArray($entrenadores);
            foreach ($entrenadores as $key => $value) {

                // isset($plan[$value]) ? dd($plan[$value]) : [];
                //$meta         = EP::meta_venta($value);
                $send[$value] = [
                    'plan' => isset($plan[$value]) ? $plan[$value] : [],
                    'meta' => isset($meta[$value]) ? $meta[$value] : [],
                ];
            }
            $retval = array(
                'data'    => $send,
                'code'    => 200,
                'message' => 'OK',
            );

        } else {
            $retval = array(
                'data'    => [],
                'code'    => 200,
                'message' => 'OK',
            );
        }
        return response()->json($retval, $retval['code']);

    }

    /**
     * [plantrabajo description]
     * @return [type] [description]
     */
    public function plantrabajo($idPersona = 0)
    {

        try {
            if (!isset($_SESSION['idPersona'])) {
                $retval = array(
                    'status'  => 'error',
                    'data'    => array(),
                    'code'    => 400,
                    'message' => 'session terminada',
                );
                return response()->json($retval, $retval['code']);
            }
            $idPersona = $idPersona === 0 ? $_SESSION['idPersona'] : $idPersona;
            session_write_close();
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // por POST recibimos datos para guardar

                $request = json_decode(trim(file_get_contents('php://input')), true);
                /*
                 * Vamos a validar que tengamos datos como para procesar...
                 */
                $arr = ['idEventoParticipante', 'idCategoria', 'participantes', 'clases'];
                foreach ($arr as $v) {
                    if (!$request[$v] || !is_numeric($request[$v])) {
                        throw new \RuntimeException('El valor de ' . $v . ' no es válido');
                    }
                }
                if (!strtotime($request['fechaVenta'])) {
                    throw new \RuntimeException('El valor de fechaVenta no es válido');
                }
                $idEvento = EP::obtenerEvento(
                    $request['idCategoria'],
                    $_SESSION['idUn'],
                    $request['participantes'],
                    $request['clases'],
                    0
                );
                if ($idEvento == 0) {
                    throw new \RuntimeException('Error al identificar evento');
                }

                /*
                 * Obtener precio
                 */
                $idProducto = Evento::datosGenerales($idEvento, $_SESSION['idUn']);
                if (!isset($idProducto['idProducto'])) {
                    throw new \RuntimeException('No se pudo encontrar el producto.');
                } else {
                    $idProducto = $idProducto['idProducto'];
                }
                $programas = [3219, 3220];
                $esquema   = in_array($idProducto, $programas) ? 1 : TIPO_ESQUEMA_PAGO_EVENTO_PAQUETE;
                $p         = Producto::precio(
                    $idProducto,
                    $_SESSION['idUn'],
                    $idSocio = Socio::obtenIdSocio($request['idPersona']) > 0 ? ROL_CLIENTE_SOCIO : ROL_CLIENTE_NINGUNO,
                    $esquema
                );
                if ($p == 0) {
                    throw new \RuntimeException('No se pudo obtener el precio');
                }
                if (!EP::actualizaEventoFecha($request['idEventoParticipante'], $request['fechaVenta'], $idProducto, $p['monto'])) {
                    throw new \RuntimeException('No se pudo actualizar el registro en la base de datos.');
                }
            }
            /*
             * En cualquier caso siempre respondemos con la tablita de las renovaciones,
             * ya sea que hayamos insertado o no
             */
            $result = EP::renovaciones($idPersona);
            if ($result == 0 || count($result) == 0) {
                $result = [];
            }
            $retval = [
                'status'  => 'success',
                'data'    => $result,
                'code'    => 200,
                'message' => 'OK',
            ];
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            // header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [meta description]
     * @param  [type] $idPersona [description]
     * @return [type]            [description]
     */
    public function meta($idPersona)
    {
        try {
            settype($idPersona, 'int');
            if (!is_int($idPersona)) {
                throw new \RuntimeException('El parametro idPersona no es numerico');
            }

            $retval = array(
                'status'  => 'success',
                'data'    => EP::metaVenta($idPersona),
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [clase description]
     *
     * @param  [type] $idEntrenador [description]
     * @param  [type] $idUn         [description]
     *
     * @return [type]               [description]
     */
    public function clase($idEntrenador, $idUn)
    {
        session_write_close();
        $idEmpleado = Empleado::obtenIdEmpleado($idEntrenador);

        $datos = EP::clase($idEmpleado, $idUn);

        $retval = array();
        if (is_array($datos)) {
            $retval = array(
                'status'  => 'OK',
                'data'    => $datos,
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        } else {
            $retval = array(
                'status'  => 'Error',
                'data'    => 'No se encontraron datos',
                'code'    => 400,
                'message' => 'Error',
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [incribir description]
     *
     * @return [type] [description]
     */
    public function inscribir()
    {

        session_write_close();
        $jsonData = json_decode(trim(file_get_contents('php://input')), true);
        $fail     = 0;
        $visa     = 0;
        $error    = array();

        if (!isset($jsonData['idCategoria'])) {
            $fail = 1;
        }
        if (!isset($jsonData['idUn'])) {
            $fail = 1;
        }
        if (!isset($jsonData['idVendedor'])) {
            $fail = 1;
        }
        if (!isset($jsonData['idEntrenador'])) {
            $fail = 1;
        }
        if (!isset($jsonData['idCliente'])) {
            $fail = 1;
        }
        if (!isset($jsonData['participantes'])) {
            $fail = 1;
        }
        if (!isset($jsonData['clases'])) {
            $fail = 1;
        }
        if (!isset($jsonData['formaPago'])) {
            $fail = 1;
        }
        if (!isset($jsonData['importe'])) {
            $fail = 1;
        }
        if (!isset($jsonData['idUnicoMembresia'])) {
            $fail = 1;
        }
        if (!isset($jsonData['tipo'])) {
            $fail = 1;
        }
        $demo = 0;
        if (isset($jsonData['demo'])) {
            $demo = $jsonData['demo'];
        }

        if (array_key_exists('visa', $jsonData)) {
            $visa = $jsonData['visa'];
        }

        $cantidad = 1;
        if (isset($jsonData['cantidad'])) {
            $cantidad = $jsonData['cantidad'];
            if ($cantidad <= 0) {
                $cantidad = 1;
            }
        }
        if ($demo == 1) {
            if (!isset($jsonData['fecha'])) {
                $fail = 1;
            }
        }
        $idEmpleado = Empleado::obtenIdEmpleado($jsonData['idEntrenador'], 1);

        if ($fail == 0) {
            if ($demo == 1) {
                $fechaClase = explode(' ', $jsonData['fecha']);

                $valida  = EventoFecha::ValidaHorario($idEmpleado, $fechaClase[0], $fechaClase[1]);
                $inbodys = AgendaInbody::ConsultaInbodyEmpleado($idEmpleado, $jsonData['idUn']);
                if ($valida > 0 && COUNT($inbodys) > 0) {
                    $error['status']    = 400;
                    $error['message']   = 'La hora ya esta ocupada ';
                    $error['code']      = '1010';
                    $error['more_info'] = 'http://localhost/docs/error/1010';
                    return response()->json($error, $error['status']);
                }
                $totalDemos = EP::totalDemos($jsonData['idCategoria'], $jsonData['idCliente'], $idEmpleado);
                if ($totalDemos >= 2) {
                    $error['status']    = 400;
                    $error['message']   = 'Excedio el limite de clases demo para este producto';
                    $error['code']      = '1010';
                    $error['more_info'] = 'http://localhost/docs/error/1010';
                    return response()->json($error, $error['status']);
                    // $this->output->set_status_header('400');
                    // $this->output->set_output(json_encode($error));
                    return;
                }
            }
            if ($demo == 1) {
                $idEvento = 2152;
            } else {
                $idEvento = EP::obtenerEvento(
                    $jsonData['idCategoria'],
                    $jsonData['idUn'],
                    $jsonData['participantes'],
                    $jsonData['clases'],
                    $demo
                );
            }

            if ($idEvento > 0) {
                $totalSesiones = $jsonData['clases'] * $cantidad;
                $importe       = $jsonData['importe'];
                if ($demo == 1 || $visa == 1) {
                    $totalSesiones             = 1;
                    $importe                   = 0.00;
                    $cantidad                  = 1;
                    $jsonData['participantes'] = 1;
                }
                $idIncripcion['idIncripcion'] = Evento::inscripcion(
                    $idEvento, $jsonData['idUn'], $jsonData['idCliente'], $jsonData['idVendedor'],
                    $importe, 0, $jsonData['idUnicoMembresia'], $cantidad,
                    $totalSesiones, TIPO_CLIENTEEXTERNO,
                    1, 0, $jsonData['participantes'], $jsonData['idEntrenador'], $visa
                );
                // inserta registros VISA
                if ($visa == 1) {
                    $valid = PromocionVisa::validaCliente($jsonData['idCliente']);
                    $sql   = "INSERT INTO crm.visaeventoinscripcion (idpromocionvisa, idPersona, idEventoInscripcion)
                    VALUES (" . $valid['id'] . "," . $jsonData['idCliente'] . "," . $idIncripcion['idIncripcion'] . ")";
                    $resultado = DB::connection('crm')->select($sql);
                }

                $generales      = Evento::datosGenerales($idEvento, $jsonData['idUn']);
                $cuenta         = Evento::ctaContable($idEvento, $jsonData['idUn']);
                $cuentaProducto = Evento::ctaProducto($idEvento, $jsonData['idUn']);

                $tipoCliente = ROL_CLIENTE_NINGUNO;

                $idUnicoMembresia = 0;
                $idSocio          = Socio::obtenIdSocio($jsonData['idCliente']);

                if ($idSocio > 0) {
                    $idUnicoMembresia = Socio::obtenUnicoMembresia($idSocio);
                    $tipoCliente      = ROL_CLIENTE_SOCIO;
                }

                $esquemaPago = TIPO_ESQUEMA_PAGO_EVENTO_PAQUETE;
                if ($generales['tipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                    $esquemaPago = ESQUEMA_PAGO_CONTADO;
                }

                $p = Producto::precio(
                    $generales['idProducto'],
                    $jsonData['idUn'],
                    $tipoCliente,
                    $esquemaPago
                );
                if ($p['numCuentaProducto'] != '') {
                    $cuentaProducto = $p['numCuentaProducto'];
                }

                if ($p['monto'] == 0.00 && $demo == 0) {
                    $error['status']    = 400;
                    $error['message']   = 'Error no se enconntro el precio del producto';
                    $error['code']      = '1010';
                    $error['more_info'] = 'http://localhost/docs/error/1010';
                    return response()->json($error, $error['status']);
                    return;
                }

                $desc_extra = '';
                if ($jsonData['tipo'] == 'Socio') {
                    if ($jsonData['importe'] == $p['monto']) {
                        if ($idUnicoMembresia > 0) {
                            $dias      = Membresia::diasRegistro($idUnicoMembresia);
                            $anualidad = Anualidad::anualidadPagada($idUnicoMembresia, '2018');
                            $notUn     = [85, 88, 76];
                            if ($dias >= 0 && $dias <= 7 && !in_array($jsonData['idUn'], $notUn)) {
                                $especial = Evento::precioPrimerSemana($generales['idProducto'], $jsonData['idUn']);
                                if ($especial > 0) {
                                    $p['monto'] = $especial;
                                    $desc_extra = '-1ER_SEMANA';
                                } else {
                                    $descuento = Evento::descuentoAnualidad($generales['idProducto'], $jsonData['idUn']);
                                    if ($descuento > 0 && $anualidad == true) {
                                        $p['monto'] = (int) ($p['monto'] * ((100 - $descuento) / 100));
                                        $desc_extra = '-DESC_ANUAL';
                                    }
                                }
                            } else {
                                $descuento = Evento::descuentoAnualidad($generales['idProducto'], $jsonData['idUn']);
                                if ($descuento > 0 && $anualidad == true) {
                                    $p['monto'] = (int) ($p['monto'] * ((100 - $descuento) / 100));
                                    $desc_extra = '-DESC_ANUAL';
                                }
                            }
                        }
                    } else if ($jsonData['importe'] < $p['monto']) {
                        $p['monto'] = $jsonData['importe'];
                    }
                } else {
                    $p['monto'] = $jsonData['importe'];
                }

                if ($cantidad > 1) {
                    $p['monto'] = $p['monto'] * $cantidad;
                }
                $datos['importe']   = $p['monto'];
                $datos['fecha']     = date('Y-m-d');
                $datos['tipo']      = MOVIMIENTO_TIPO_EVENTO;
                $datos['iva']       = 16;
                $datos['idUn']      = $jsonData['idUn'];
                $datos['membresia'] = $idUnicoMembresia;

                $datos['producto']                = $generales['idProducto'];
                $datos['persona']                 = $jsonData['idCliente'];
                $datos['origen']                  = 'APP_WS_EVT_INS-' . str_replace(' ', '_', strtoupper($jsonData['tipo']) . $desc_extra);
                $datos['numeroCuenta']            = $cuenta;
                $datos['cuentaProducto']          = $cuentaProducto;
                $datos['msi']                     = $jsonData['formaPago'];
                $datos['cantidad']                = $cantidad;
                $datos['cveProductoServicio']     = Producto::cveProducto($generales['idProducto']);
                $datos['cveUnidad']               = Producto::cveUnidad($generales['idProducto']);
                $datos['cuentaProducto']          = Producto::ctaProducto($generales['idProducto'], $jsonData['idUn']);
                $datos['idTipoEstatusMovimiento'] = MOVIMIENTO_PENDIENTE;

                if ($demo == 1 || $visa == 1) {
                    $datos['importe']                 = 0;
                    $datos['idTipoEstatusMovimiento'] = MOVIMIENTO_EXCEPCION_PAGO;
                }
                $descripcionMov = Categoria::campo($jsonData['idCategoria'], 'nombre');

                $nombreClub           = Un::nombre($jsonData['idUn']);
                $datos['descripcion'] = '';
                if ($generales['tipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                    $datos['descripcion'] = $descripcionMov . ' - ' . $nombreClub . ' (Num. Inscripcion ' . $idIncripcion['idIncripcion'] . ')';
                } else {
                    $datos['descripcion'] = $descripcionMov . ' ' . $jsonData['clases'] . ' Clase(s) ' .
                        $jsonData['participantes'] . ' Participante(s) - ' . $nombreClub . ' (Num. Inscripcion ' . $idIncripcion['idIncripcion'] . ')';
                }

                if ($demo == 1) {
                    $datos['descripcion'] = 'Demo ' . $descripcionMov . ' - ' . $nombreClub . ' (Num. Inscripcion ' . $idIncripcion['idIncripcion'] . ')';
                }

                if ($visa == 1) {
                    $datos['descripcion'] = 'VISA ' . $descripcionMov . ' - ' . $nombreClub . ' (Num. Inscripcion ' . $idIncripcion['idIncripcion'] . ')';
                }

                $idMovimiento = Movimiento::inserta($datos);

                if ($idMovimiento > 0) {
                    Evento::inscripcionMovimiento($idIncripcion['idIncripcion'], $idMovimiento);
                    Evento::modificaMonto($idIncripcion['idIncripcion'], $datos['importe']);
                    //Se valida si el movimiento se va a devengar en 60 20 20
                    $movDevengado = Evento::movientoDevengado($idMovimiento);
                    //aplicar Devengado 60 20 20 a Movimiento contable
                    if ($movDevengado !== null) {
                        if (0 != $movDevengado->activo && 0 != $movDevengado->autorizado) {
                            $registroContable = Evento::devengarMovimientoContable($idMovimiento);
                        }
                    }
                    if ($idIncripcion['idIncripcion'] > 0) {
                        if ($demo == 1) {
                            Evento::insertaClase(
                                $idIncripcion['idIncripcion'],
                                $idEmpleado,
                                $jsonData['idEntrenador'],
                                $fechaClase[0],
                                $fechaClase[1],
                                $demo
                            );
                        } else {
                            $comisionar = Evento::generarComisionVenta($idEvento, $jsonData['idUn']);

                            if ($comisionar == true) {
                                $descripcionTipoEvento = 'VENTA';
                                $montoComision         = 0;
                                $porcentaje            = 0;

                                $idTipoComision = 13;
                                if ($generales['tipoEvento'] == TIPO_EVENTO_CLASE) {
                                    $descripcionTipoEvento = 'VENTA DE CLASE PERSONALIZADA DE';
                                    $idTipoComision        = TIPO_COMISION_CLASEPERSONALIZADA;
                                    $porcentaje            = Comision::obtenCapacidad($jsonData['idUn']);
                                    $porcentaje            = ($porcentaje == '') ? 0 : $porcentaje;

                                } elseif ($generales['tipoEvento'] == TIPO_EVENTO_PROGRAMA) {
                                    $descripcionTipoEvento = 'VENTA DE PROGRAMA DEPORTIVO DE';
                                    $idTipoComision        = TIPO_COMISION_PROGRAMADEPORTIVO;
                                    //$isSocio=Socio::where('fechaEliminacion','=','0000-00-00 00:00:00')->where('idPersona','=',$jsonData['idCliente'])->get()->toArray();
                                    if ($jsonData['tipo'] != 'Socio') {
                                        #Externo
                                        $montoComision = Comision::obtenCapacidad($jsonData['idUn'], TIPO_EVENTO_COMISIONEXTERNA);
                                    } else {
                                        #Socio
                                        $montoComision = Comision::obtenCapacidad($jsonData['idUn'], TIPO_EVENTO_COMISIONINTERNA);
                                    }
                                } elseif ($generales['tipoEvento'] == TIPO_EVENTO_CURSOVERANO) {
                                    $idTipoComision = TIPO_COMISION_CURSODEVERANO;
                                }
                                $opciones['idTipoEstatusComision'] = TIPO_ESTATUSCOMISION_SINFACTURAR;
                                $opciones['idUn']                  = $jsonData['idUn'];
                                $opciones['idTipoComision']        = $idTipoComision;
                                $opciones['idPersona']             = $jsonData['idEntrenador'];
                                $opciones['importe']               = $datos['importe'];
                                $opciones['descripcion']           = $descripcionTipoEvento . ' ' . strtoupper($datos['descripcion']);
                                $opciones['montoComision']         = $montoComision;
                                $opciones['porcentaje']            = $porcentaje;
                                $opciones['manual']                = 0;
                                $opciones['movimiento']            = $idMovimiento;
                                $idcomision                        = Comision::guardaComision($opciones);
                            }
                        }
                        return response()->json($idIncripcion, 200);
                    } else {
                        $error['status']    = 400;
                        $error['message']   = 'Error al generar inscripcion al evento';
                        $error['code']      = '1009';
                        $error['more_info'] = 'http://localhost/docs/error/1009';
                        return response()->json($error, $error['status']);
                    }
                } else {
                    $error['status']    = 400;
                    $error['message']   = 'Error no se genero correctamente el cargo al cliente';
                    $error['code']      = '1009';
                    $error['more_info'] = 'http://localhost/docs/error/1009';
                    return response()->json($error, $error['status']);
                }
            } else {
                $error['status']    = 400;
                $error['message']   = 'Error al identificar evento';
                $error['code']      = '1009';
                $error['more_info'] = 'http://localhost/docs/error/1009';
                return response()->json($error, $error['status']);
            }
        } else {
            $error['status']    = 400;
            $error['message']   = 'Datos incompletos para generar inscripcion';
            $error['code']      = '1009';
            $error['more_info'] = 'http://localhost/docs/error/1009';
            $this->output->set_output(json_encode($error));
        }
    }

    public function loginOkta(Request $request)
    {

        // session_destroy();
        $email = $request->input('email');
        // $password = $request->input('password');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $res       = EP::loginOkta($email);
            $res_array = array_map(function ($x) {return (array) $x;}, $res);
            if (isset($res['response'])) {
                foreach ($res['response'] as $k => $v) {
                    $_SESSION[$k] = $v;
                }
            }

            session_write_close(); //En el modelo guardamos unos datos de sesion...
            if ($res['status'] == 200) {
                return response()->json($res['response'], 200);
            } else {
                return response()->json($res, 400);
            }
        } else {
            $error['status']    = 400;
            $error['message']   = 'Correo invalido';
            $error['code']      = '1001';
            $error['more_info'] = 'http://localhost/docs/error/1001';
            // $this->output->set_status_header('400');
            // $this->output->set_output(json_encode($error));
            return response()->json($error, 400);
        }
    }

    public function login(Request $request)
    {

        // session_destroy();
        $email    = $request->input('email');
        $password = $request->input('password');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $res       = EP::login($email, $password);
            $res_array = array_map(function ($x) {return (array) $x;}, $res);
            if (isset($res['response'])) {
                foreach ($res['response'] as $k => $v) {
                    $_SESSION[$k] = $v;
                }
            }

            session_write_close(); //En el modelo guardamos unos datos de sesion...
            if ($res['status'] == 200) {
                return response()->json($res['response'], 200);
            } else {
                return response()->json($res, 400);
            }
        } else {
            $error['status']    = 400;
            $error['message']   = 'Correo invalido';
            $error['code']      = '1001';
            $error['more_info'] = 'http://localhost/docs/error/1001';
            // $this->output->set_status_header('400');
            // $this->output->set_output(json_encode($error));
            return response()->json($error, 400);
        }
    }

    /**
     * [general description]
     *
     * @param  [type] $idUn [description]
     *
     * @return [type]       [description]
     */
    public function general($idUn)
    {
        header("Content-Type: application/json");
        session_write_close();
        //12 horas
        Cache::flush();
        $datos = Cache::remember('productos-' . $idUn, 43200, function () use ($idUn) {
            $ep = new EP;
            return $ep->general($idUn);
        });

        return response()->json($datos, 200);
    }

    /**
     * [reAgendar description]
     *
     * @param  [type] $idEventoFecha [description]
     * @param  [type] $delay         [description]
     *
     * @return [type]                [description]
     */
    public function reAgendar($idEventoFecha, $delay)
    {
        session_write_close();
        $delay = $delay * 1000;
        try {
            $fechaNueva = EP::getNewEventoFecha($idEventoFecha, $delay);
            $retval     = array(
                'status'  => 'OK',
                'data'    => $fechaNueva,
                'code'    => 200,
                'message' => 'OK',
            );
        } catch (\RuntimeException $ex) {
            $retval = array(
                'status'  => 'Error',
                'data'    => $ex->getMessage(),
                'code'    => 400,
                'message' => 'Error',
            );
        }
        echo json_encode($retval);
    }

    /**
     * [buscar description]
     *
     * @return [type] [description]
     */
    public function buscar($value = null)
    {
        session_write_close();
        // $criterio = $this->input->get('value');
        $criterio = $value;
        $arr      = Persona::listaPersonas($criterio, 10);
        if (count($arr) > 10) {
            $retval = array(
                'status'  => 'error',
                'data'    => [],
                'code'    => 413,
                'message' => 'Request Entity Too Large'
            );
            return response()->json($retval, $retval['code']);
            // header(413, true, 413);
        } else {
            $a = [];
            $r = [];
            foreach ($arr as $row) {
                $r['idPersona']   = $row['idPersona'];
                $r['nombre']      = utf8_encode($row['nombre']);
                $r['paterno']     = utf8_encode($row['paterno']);
                $r['materno']     = utf8_encode($row['materno']);
                $r['idMembresia'] = $row['idMembresia'];
                if ($row['idMembresia'] > 0) {
                    $r['materno'] = utf8_encode($row['materno']) . ' [' . $row['clave'] . ' - ' . $row['idMembresia'] . ']';
                }
                $r['clave'] = $row['clave'];
                $a[]        = $r;
            }

            $retval = array(
                'status'  => 'success',
                'data'    => $a,
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [persona description]
     * @return [type] [description]
     */
    public function persona($idPersona)
    {
        session_write_close();

        $retval                    = Persona::datosGenerales($idPersona);
        $retval['nombre']          = mb_strtoupper(utf8_encode($retval['nombre']));
        $retval['paterno']         = mb_strtoupper(utf8_encode($retval['paterno']));
        $retval['materno']         = mb_strtoupper(utf8_encode($retval['materno']));
        $retval['idTipoSexo']      = $retval['sexo'];
        $retval['tipo']            = $retval['tipoCliente'];
        $retval['sexo']            = Persona::sexo($idPersona);
        $retval['fechaNacimiento'] = $retval['fecha'];
        unset($retval['fecha']);
        $retval['estadoCivil']       = utf8_encode(Persona::obtenerEstadoCivil($idPersona));
        $retval['idTipoEstadoCivil'] = $retval['civil'];
        unset($retval['civil']);
        $retval['idEstado'] = $retval['estado'];
        unset($retval['estado']);

        $retval = array(
            'status'  => 'success',
            'data'    => $retval,
            'code'    => 200,
            'message' => 'OK',
        );
        return response()->json($retval, $retval['code']);
    }

    /**
     * [sexo description]
     * @return [type] [description]
     */
    public function sexo()
    {
        session_write_close();
        $query = DB::connection('crm')->table(TBL_TIPOSEXO)
            ->select('idTipoSexo', 'descripcion')
            ->where('activo', '1');

        $out = [];
        if ($query->count() > 0) {
            $out = $query->get()->toArray();
        }

        $retval = array(
            'status'  => 'success',
            'data'    => $out,
            'code'    => 200,
            'message' => 'OK',
        );
        return response()->json($retval, $retval['code']);
    }

    /**
     * [estadocivil description]
     * @return [type] [description]
     */
    public function estadocivil()
    {
        session_write_close();
        $query = DB::connection('crm')->table(TBL_TIPOESTADOCIVIL)
            ->select('idTipoEstadoCivil', 'descripcion')
            ->where('activo', '1');

        if ($query->count() == 0) {
            $retval = array(
                'status'  => 'failure',
                'data'    => [],
                'code'    => 200,
                'message' => 'FAIL'
            );
            return response()->json($retval, $retval['code']);
        }

        $query  = $query->get()->toArray();
        $retval = [];
        foreach ($query as $k => $v) {
            foreach ($v as $k2 => $v2) {
                $retval[$k][$k2] = utf8_encode($v2);
            }
        }
        $retval = array(
            'status'  => 'success',
            'data'    => $retval,
            'code'    => 200,
            'message' => 'OK',
        );
        return response()->json($retval, $retval['code']);
    }

    /**
     * [estado description]
     * @return [type] [description]
     */
    public function estado()
    {
        session_write_close();
        $query  = DB::connection('crm')->table(TBL_ESTADO)->get()->toArray();
        $retval = [];
        foreach ($query as $k => $v) {
            foreach ($v as $k2 => $v2) {
                $retval[$k][$k2] = utf8_encode($v2);
            }
        }
        $retval = array(
            'status'  => 'success',
            'data'    => $retval,
            'code'    => 200,
            'message' => 'OK',
        );
        return response()->json($retval, $retval['code']);
    }

    /**
     * [datosCliente description]
     * @return [type] [description]
     */
    public function datosCliente()
    {
        $ip = EP::getRealIP();
        return response($ip, 200);
    }

    /**
     * [nuevosClientes description]
     * @return [type] [description]
     */
    public function nuevosClientes()
    {
        session_write_close();

        try {
            $retval = array();
            $datos  = EP::getNuevosClientes($_SESSION['idUn'], mktime(0, 0, 0, date('n'), 1, date('Y')));
            foreach ($datos as $k => $v) {
                $retval[] = array(
                    'nombre'      => $v['nombre'],
                    'idMembresia' => $v['idMembresia'],
                    'idPersona'   => $v['idPersona'],
                    'mail'        => explode(',', $v['mail']),
                    'telefono'    => explode(',', $v['telefonos']),
                );
            }

            $retval = array(
                'status'  => 'success',
                'data'    => $retval,
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            header('Internal Server Error', true, 500);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 500,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [comisiones description]
     * @param  integer $idPersona [description]
     * @return [type]             [description]
     */
    public function comisiones($idPersona = 0)
    {
        session_write_close();

        if ($idPersona == 0) {
            $idPersona = $_SESSION['idPersona'];
        }

        try {
            $retval = EP::getComisiones($idPersona);

            foreach ($retval as &$fila) {
                foreach ($fila as &$value) {
                    $value = utf8_encode($value);
                }
            }

            $retval = array(
                'status'  => 'success',
                'data'    => $retval,
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        } catch (RuntimeException $ex) {
            header('Internal Server Error', true, 500);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 500,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    public function logout()
    {
        session_destroy();
        $retval = array(
            'status'  => 'success',
            'data'    => [],
            'code'    => 200,
            'message' => 'Se destruyó la sesión'
        );
        return response()->json($retval, $retval['code']);
    }
    public function editarPerfil(Request $request, $idPersona)
    {

        $perfil_ep  = $request->input('perfil_ep');
        $clubs      = $request->input('clubs');
        $diciplinas = $request->input('diciplinas');
        $empleado   = Empleado::where('idPersona', $idPersona)
            ->where('idTipoEstatusEmpleado', ESTATUS_EMPLEADO_ACTIVO)
            ->where('fechaEliminacion', 0)->first();
        $empleado->perfil_ep  = $perfil_ep;
        $empleado->clubes     = $clubs;
        $empleado->diciplinas = $diciplinas;
        $empleado->save();

        $usuarioMigracion             = UsuariosMigracion::find($idPersona);
        $usuarioMigracion->actualizar = 1;
        $usuarioMigracion->coach      = 1;
        $usuarioMigracion->save();

        $retval = array(
            'status'  => 'success',
            'data'    => $empleado,
            'code'    => 200,
            'message' => 'Actualizado',
        );
        return response()->json($retval, $retval['code']);
    }

    public function perfil($idPersona)
    {
        /*
         * El idPerson viene en el GET y (en el caso que venga) el perfil nuevo en el POST
         * La columna perfil_ep de la tabla empleado...fyi
         */
        try {
            if (is_null($idPersona)) {
                throw new \RuntimeException('idPersona inválido');
            }

            $retval  = json_decode(trim(file_get_contents('php://input')), true);
            $code    = 200;
            $message = 'OK';
            if (!is_null($retval)) {
                if (!isset($retval['perfil'])) {
                    throw new \RuntimeException('El perfil debe ser entre 1 y 512 caracteres');
                }

                settype($retval['perfil'], 'string');
                if (strlen($retval['perfil']) > 512) {
                    throw new \RuntimeException('El perfil debe ser menor que 513 caracteres');
                }

                if (strlen($retval['perfil']) < 1) {
                    throw new \RuntimeException('El perfil debe ser mayor que 0 caracteres');
                }

                $code    = 201;
                $message = 'Created';
            } else {
                $retval = array(
                    'perfil' => null,
                );
            }
            $retval = array(
                'status'  => 'success',
                'data'    => EP::perfil($idPersona),
                'code'    => $code,
                'message' => $message,
            );
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    /**
     * [obtiene idPersona, nombre completo, idPuesto y descripcion del puesto de los Entrenadores]
     *
     * @param  [int] $idUn         [id de club]
     *
     * @return [JSON] $retval      [arreglo con entrenadores pertenecientes al club]
     */
    public function getEntrenadores($idUn)
    {
        session_write_close();
        $datos  = EP::obtenEntrenadores($idUn);
        $retval = [];
        if (is_array($datos)) {
            $retval = array(
                'status'  => 'OK',
                'data'    => $datos,
                'code'    => 200,
                'message' => 'OK',
            );
            return response()->json($retval, $retval['code']);
        } else {
            $retval = array(
                'status'  => 'Error',
                'data'    => 'No se encontraron datos',
                'code'    => 400,
                'message' => 'Error',
            );
            return response()->json($retval, $retval['code']);
        }
    }

    public function verifyVisa($idPersona, $categoria, $participantes)
    {
        // productos validos
        $categorias = array(108, 109, 111);

        try {
            // se valida que el producto a vender
            if (in_array($categoria, $categorias) && $participantes == 1) {
                // se valida que el cliente este registrado en la promocion
                $valid = PromocionVisa::validaCliente($idPersona);
                if ($valid->count() > 0) {
                    if ($valid && now()->format('Y-m-d') > $valid->inicio && now()->format('Y-m-d') < $valid->final) {
                        // se obtienen rango de fechas dependiendo de tarjeta
                        $fechas = PromocionVisa::getFechas($valid->tipo);
                        // se obtienen las clases impartidas dentro del rango de fechas
                        $clases = PromocionVisa::validaEvento($idPersona, $fechas, $valid->inicio, $valid->final);
                        // validacion para comprobar si el cliente puede tomar clase gratuita
                        if ($clases <= $fechas['days']) {
                            $retval = array(
                                'status'  => 'ok',
                                'data'    => $clases,
                                'code'    => 200,
                                'message' => 'response',
                            );
                            return response()->json($retval, $retval['code']);
                        } else {
                            $retval = array(
                                'status'  => 'error',
                                'data'    => array(),
                                'code'    => 201,
                                'message' => 'Ya no tiene clases',
                            );
                            return response()->json($retval, $retval['code']);
                        }
                    } else {
                        $retval = array(
                            'status'  => 'error',
                            'data'    => array(),
                            'code'    => 201,
                            'message' => "El cliente no entro en la promocion visa",
                        );
                        return response()->json($retval, $retval['code']);
                    }
                } else {
                    $retval = array(
                        'status'  => 'error',
                        'data'    => array(),
                        'code'    => 202,
                        'message' => "El cliente no entro en la promocion visa",
                    );
                    return response()->json($retval, $retval['code']);
                }
            } else {
                $retval = array(
                    'status'  => 'error',
                    'data'    => array(),
                    'code'    => 203,
                    'message' => "Producto o capacidad no valido en promociopn visa",
                );
                return response()->json($retval, $retval['code']);
            }
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }

    public function dataVisa($idPersona)
    {
        try {
            $valid  = PromocionVisa::validaCliente($idPersona);
            $retval = array(
                'status'  => 'ok',
                'data'    => $valid,
                'code'    => 200,
                'message' => 'response',
            );
            return response()->json($retval);
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status'  => 'error',
                'data'    => array(),
                'code'    => 400,
                'message' => $ex->getMessage(),
            );
            return response()->json($retval, $retval['code']);
        }
    }
}
