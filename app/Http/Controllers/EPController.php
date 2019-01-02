<?php

namespace API_EPS\Http\Controllers;

use Illuminate\Http\Request;
use API_EPS\Http\Requests;
use API_EPS\Models\Anualidad;
use API_EPS\Models\Categoria;
use API_EPS\Models\Comision;
use API_EPS\Models\Empleado;
use API_EPS\Models\EP;
use API_EPS\Models\Evento;
use API_EPS\Models\Movimiento;
use API_EPS\Models\Objeto;
use API_EPS\Models\Persona;
use API_EPS\Models\Producto;
use API_EPS\Models\Socio;
use API_EPS\Models\UN;
use Illuminate\Support\Facades\Log;

/**
* Extraído desde el controller /crm/system/application/controllers/ep.php
* y desde el model /crm/system/application/models/ep_model.php
*/
class EPController extends Controller
{
    
    public function login(Request $request)
    {
        // session_destroy();
        $email = $request->input('email');
        $password = $request->input('password');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $res = EP::login($email, $password);
            $res_array = array_map(function($x){return (array)$x;},$res);
            
            if (isset($res['response'])) {
                foreach($res['response'] as $k => $v) {
                    $_SESSION[$k] = $v;
                }
            }
            
            session_write_close(); //En el modelo guardamos unos datos de sesion...
            if ($res['status']==200) {
                return response()->json($res['response'], 200);
            } else {
                return response()->json($res, 400);
            }
        } else {
            $error['status'] = 400;
            $error['message'] = 'Correo invalido';
            $error['code'] = '1001';
            $error['more_info'] = 'http://localhost/docs/error/1001';
            // $this->output->set_status_header('400');
            // $this->output->set_output(json_encode($error));
            return response()->json($error, 400);
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
                'status' => 'OK',
                'data' => $datos,
                'code' => 200,
                'message' => 'OK'
            );
        } else {
            $retval = array(
                'status' => 'Error',
                'data' => 'No se encontro información',
                'code' => 400,
                'message' => 'Error'
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
    public function alta($idInscripcion, $idEntrenador, $timestamp, $empleado = null)
    {
        $idEmpleado = ($empleado != null) ? $empleado : Empleado::obtenIdEmpleado($idEntrenador) ;
        $fecha = substr($timestamp, 0, 10);
        $hora = substr($timestamp, 11, 8);
        $idClase = Evento::insertaClase($idInscripcion, $idEmpleado, $idEntrenador, $fecha, $hora);
        $retval = array();
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
            $error['status'] = 200;
            $error['message'] = 'Clase eliminada';
            $error['code'] = '0';
            $error['more_info'] = 'Ok';
        } else {
            $error['status'] = 400;
            $error['message'] = 'Error al cancelar clase';
            $error['code'] = '1009';
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
        if ($_SESSION['idPersona']) {
            $out =
                array(
                    'idPersona' => $_SESSION['idPersona'],
                    'nombre' => $_SESSION['nombre'],
                    'idEmpleado' => $_SESSION['idEmpleado'],
                    'idTipoEstatusEmpleado' => $_SESSION['idTipoEstatusEmpleado'],
                    'idUn' => $_SESSION['idUn'],
                    'unNombre' => $_SESSION['unNombre'],
                    'idPuesto' => $_SESSION['idPuesto'],
                    'puestoNombre' => $_SESSION['puestoNombre'],
                    'entrenadores' => $_SESSION['entrenadores'],
                    'perfil_ep' => $_SESSION['perfil_ep'],
                    'calificacion' => $_SESSION['calificacion'],
                    'version' => Objeto::obtenerObjeto(953)['descripcion'],
                    'clubs' => $_SESSION['clubs']
                );
        return response()->json($out, 200);
        } else {
            return response()->json(null, 402);
        }
    }
    
    /**
     * [plantrabajo description]
     * @return [type] [description]
     */
    public function plantrabajo($idPersona = 0)
    {
        // dd($idPersona);
        $idPersona = $idPersona === 0 ? $_SESSION['idPersona'] : $idPersona;
        session_write_close();
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') { // por POST recibimos datos para guardar
            
                $request = json_decode(trim(file_get_contents('php://input')), true);
                /*
                 * Vamos a validar que tengamos datos como para procesar...
                 */
                $arr = ['idEventoParticipante','idCategoria','participantes','clases'];
                foreach ($arr as $v) {
                    if (!$request[$v] || !is_numeric($request[$v])) {
                        throw new \RuntimeException('El valor de '.$v.' no es válido');
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
                $idProducto = $this->evento_model->datosGenerales($idEvento, $this->session->userData('idUn'));
                if (!isset($idProducto['idProducto'])) {
                    throw new \RuntimeException('No se pudo encontrar el producto.');
                } else {
                    $idProducto = $idProducto['idProducto'];
                }
                $programas = [3219,3220];
                $esquema = in_array($idProducto, $programas)? 1 : TIPO_ESQUEMA_PAGO_EVENTO_PAQUETE;
                $p = $this->producto_model->precio(
                    $idProducto,
                    $this->session->userData('idUn'),
                    $this->socio_model->obtenIdSocio($request['idPersona']) > 0 ? ROL_CLIENTE_SOCIO : ROL_CLIENTE_NINGUNO,
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
            if (count($result) == 0) {
                $result = [];
            }
            $retval = [
                'status' => 'success',
                'data' => $result,
                'code' => 200,
                'message' => 'OK'
            ];
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            // header('Bad Request', true, 400);
            $retval = array(
                'status' => 'error',
                'data' => array(),
                'code' => 400,
                'message' => $ex->getMessage()
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
            if (!is_int($idPersona))
                throw new \RuntimeException('El parametro idPersona no es numerico');
            $retval = array(
                'status' => 'success',
                'data' => EP::meta_venta($idPersona),
                'code' => 200,
                'message' => 'OK'
            );
            return response()->json($retval, $retval['code']);
        } catch (\RuntimeException $ex) {
            header('Bad Request', true, 400);
            $retval = array(
                'status' => 'error',
                'data' => array(),
                'code' => 400,
                'message' => $ex->getMessage()
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

        $datos = EP::clase($idEmpleado,$idUn);

        $retval = array();
        if (is_array($datos)) {
            $retval = array(
                'status' => 'OK',
                'data' => $datos,
                'code' => 200,
                'message' => 'OK'
            );
            return response()->json($retval, $retval['code']);
        } else {
            $retval = array(
                'status' => 'Error',
                'data' => 'No se encontraron datos',
                'code' => 400,
                'message' => 'Error'
            );
            return response()->json($retval, $retval['code']);
        }
    }
    
    
    

}
