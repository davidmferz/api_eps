<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Anualidad extends Model
{
    use SoftDeletes;
    protected $connection = 'crm';
    protected $table      = 'crm.anualidadescodigosviajes';
    protected $primaryKey = 'idAnualidadesCodigosViajes';

    const CREATED_AT = 'fechaRegistro';
    const UPDATED_AT = 'fechaActualizacion';
    const DELETED_AT = 'fechaEliminacion';

    /**
     * [actualizaClubPremier description]
     *
     * @param  integer $idMovimiento [description]
     * @param  string  $clubPremier  [description]
     * @param  string  $nombre       [description]
     * @param  string  $apellido     [description]
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function actualizaClubPremier($idMovimiento, $clubPremier, $nombre, $apellido, $opcion)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento > 0) {
            $ci = &get_instance();
            $ci->load->model('movimientos_model');

            $t                = '';
            $idUnicoMembresia = $ci->movimientos_model->unicoMembresia($idMovimiento);

            if ($opcion == 1) {
                $t = ' (No. Cliente Premier)';
            }
            if ($opcion == 2) {
                $t = ' (Nombre(s))';
            }
            if ($opcion == 3) {
                $t = ' (Apellido(s))';
            }

            $set = array(
                'numSocioClubPremier' => $clubPremier,
                'nombre'              => utf8_decode($nombre),
                'apellido'            => utf8_decode($apellido),
            );
            $where = array(
                'idMovimiento' => $idMovimiento,
                'enviado'      => 0,
            );

            $this->db->update('clubpremieranualidad', $set, $where);

            $total = $this->db->affected_rows();
            if ($total > 0) {
                $this->permisos_model->log('Actualiza beneficiario para ClubPremier' . $t, LOG_MEMBRESIA, $idUnicoMembresia);

                return true;
            }
        }

        return false;
    }

    /**
     * [aplicaDescuentoSantender description]
     *
     * @param  [type] $idMovimiento [description]
     *
     * @return [type]               [description]
     */
    public function aplicaDescuentoSantander($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento == 9838817) {
            return true;
        }

        if ($idMovimiento > 0) {
            $sql = "SELECT mpm.idMovimiento
                FROM membresiaPromoMTTOMSI mpm
                WHERE mpm.idPromoMTTOMSI IN (4068, 4069, 4070, 4071)
                    AND mpm.idMovimiento=" . $idMovimiento;
            $query2 = $this->db->query($sql);
            if ($query2->num_rows > 0) {
                return false;
            }

            $sql = "SELECT mpm.idMovimiento
                FROM membresiaPromoMTTOMSI mpm
                WHERE mpm.idPromoMTTOMSI IN (4426, 4427, 4428, 4429)
                    AND mpm.idMovimiento=" . $idMovimiento;
            $query2 = $this->db->query($sql);
            if ($query2->num_rows > 0) {
                return false;
            }

            $sql = "SELECT mov.importe, mov.idUnicoMembresia,
                (PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1) as meses
                FROM movimiento mov
                INNER JOIN membresia mem ON mem.idUnicoMembresia=mov.idUnicoMembresia
                INNER JOIN un u ON u.idUn=mem.idUn
                INNER JOIN empresa e ON e.idEmpresa=u.idEmpresa AND e.idEmpresaGrupo=1
                INNER JOIN sociopagomtto spm ON spm.idMovimiento=mov.idMovimiento
                    AND (PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1) IN (10, 11,12)
                    AND spm.eliminado=0
                INNER JOIN mantenimiento mt ON mt.idMantenimiento=spm.idMantenimiento
                    AND mt.descuentoSantander=1
                WHERE mov.idMovimiento=$idMovimiento AND mov.idTipoEstatusMovimiento=65 AND mov.msi=12
                    AND mov.eliminado=0
                    AND mov.origen NOT LIKE '%DescBinSantander%'
                GROUP BY mov.idMovimiento";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                $fila             = $query->row_array();
                $meses            = $fila['meses'];
                $idUnicoMembresia = $fila['idUnicoMembresia'];

                $sql = "SELECT idMovimientoCtaContable, importe
                    FROM movimientoctacontable
                    WHERE idMovimiento=$idMovimiento AND numeroCuenta<>'4090'
                        AND eliminado=0";
                $query2 = $this->db->query($sql);
                if ($query2->num_rows == 1) {
                    $fila2                   = $query2->row_array();
                    $idMovimientoCtaContable = $fila2['idMovimientoCtaContable'];
                    $importe                 = $fila2['importe'];

                    $importeOriginal = $importe;
                    $descuento       = $importe / $meses;
                    $importe         = round($importe - $descuento);

                    $this->permisos_model->log(
                        utf8_decode("Aplica descuento Santader por Anualidad al movimiento (" . $idMovimiento . ") de $" . number_format($importeOriginal, 2) . " a $" . number_format($importe, 2)),
                        LOG_MEMBRESIA,
                        $idUnicoMembresia
                    );

                    $ci = &get_instance();
                    $ci->load->model('movimientos_model');

                    $ci->movimientos_model->actualizaImporteCtaContable($idMovimientoCtaContable, $importe, $idUnicoMembresia);

                    $ci->movimientos_model->agregarOrigen($idMovimiento, 'DescBinSantander');

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * [aplicaDescuentoSantender description]
     *
     * @param  [type] $idMovimiento [description]
     *
     * @return [type]               [description]
     */

    public function aplicaDescuentoHSBC($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        if ($idMovimiento > 0) {
            $sql = "SELECT mov.importe, mov.idUnicoMembresia,
                (PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1) as meses
                FROM movimiento mov
                INNER JOIN membresia mem ON mem.idUnicoMembresia=mov.idUnicoMembresia
                INNER JOIN un u ON u.idUn=mem.idUn
                INNER JOIN empresa e ON e.idEmpresa=u.idEmpresa AND e.idEmpresaGrupo=1
                INNER JOIN sociopagomtto spm ON spm.idMovimiento=mov.idMovimiento
                    AND spm.fechaFin='2018-12-31'
                    AND (PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1) IN (11,12)
                    AND spm.eliminado=0
                INNER JOIN mantenimiento mt ON mt.idMantenimiento=spm.idMantenimiento
                    AND mt.descuentoSantander=1
                WHERE mov.idMovimiento=$idMovimiento AND mov.idTipoEstatusMovimiento=65 AND mov.msi=12
                    AND mov.eliminado=0
                    AND mov.origen NOT LIKE '%DescBinHSBC%'
                GROUP BY mov.idMovimiento";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                $fila             = $query->row_array();
                $meses            = $fila['meses'];
                $idUnicoMembresia = $fila['idUnicoMembresia'];

                $sql = "SELECT idMovimientoCtaContable, importe
                    FROM movimientoctacontable
                    WHERE idMovimiento=$idMovimiento AND numeroCuenta<>'4090'
                        AND eliminado=0";
                $query2 = $this->db->query($sql);
                if ($query2->num_rows == 1) {
                    $fila2                   = $query2->row_array();
                    $idMovimientoCtaContable = $fila2['idMovimientoCtaContable'];
                    $importe                 = $fila2['importe'];

                    $importeOriginal = $importe;
                    //$descuento = round(($importe*0.07),2);
                    $importe = round($importe * 0.93);

                    $this->permisos_model->log(
                        utf8_decode("Aplica descuento HSBC por Anualidad al movimiento (" . $idMovimiento . ") de $" . number_format($importeOriginal, 2) . " a $" . number_format($importe, 2)),
                        LOG_MEMBRESIA,
                        $idUnicoMembresia
                    );

                    $ci = &get_instance();
                    $ci->load->model('movimientos_model');

                    $ci->movimientos_model->actualizaImporteCtaContable($idMovimientoCtaContable, $importe, $idUnicoMembresia);

                    $ci->movimientos_model->agregarOrigen($idMovimiento, 'DescBinHSBC');

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica si pago anualidad para el año indicado
     *
     * @param  integer $idUnicoMembresia [description]
     * @param  integer $anio             [description]
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public static function anualidadPagada($idUnicoMembresia, $anio)
    {
        settype($idUnicoMembresia, 'integer');
        settype($anio, 'integer');

        if ($idUnicoMembresia == 0 || $anio == 0) {
            return false;
        }

        $sql = "SELECT spm.idUnicoMembresia
            FROM sociopagomtto spm
            WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.activo=1 AND spm.eliminado=0
                AND spm.idMovimiento>0 AND DATE(CONCAT('$anio','-12-31')) BETWEEN spm.fechaInicio AND spm.fechaFin
                AND DATEDIFF(spm.fechaFin, spm.fechaInicio)>320";
        $query = DB::connection('crm')->select($sql);

        if (count($query) > 0) {
            return true;
        }
        return false;
    }

    /**
     * [infoClubPremier description]
     *
     * @param  integer $idMovimiento [description]
     *
     * @return $res
     */
    public function infoClubPremier($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $res = array();

        $sql = "SELECT c.numSocioClubPremier, UPPER(c.nombre) AS nombre, UPPER(c.apellido) AS apellido, c.enviado
            FROM tmpanualidades t
            INNER JOIN movimiento m ON m.idMovimiento=t.idMovimiento
                AND m.eliminado=0
            LEFT JOIN clubpremieranualidad c ON c.idMovimiento=m.idMovimiento
            WHERE t.idMovimiento=$idMovimiento
            GROUP BY t.idMovimiento";
        $query = $this->db->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $res['numSocioClubPremier'] = $fila->numSocioClubPremier;
                $res['nombre']              = utf8_encode($fila->nombre);
                $res['apellido']            = utf8_encode($fila->apellido);
                $res['enviado']             = $fila->enviado;
            }
        }

        return $res;
    }

    /*
     * Divide el cargo de anualidad contablemente para mandar ingresos a credecial cuando corresponda
     *
     * @author Jorge Cruz
     *
     */
    public function divideIngresoCredencial($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        if ($idUnicoMembresia > 0) {
            $sql = "SELECT spm.idMovimiento
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.fechaFin='2017-12-31'
                    AND spm.eliminado=0 AND DATE(spm.fechaRegistro)=DATE(NOW())
                GROUP BY spm.idMovimiento
                ORDER BY spm.idMovimiento DESC
                LIMIT 1";
            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                $fila         = $query->row_array();
                $idMovimiento = $fila['idMovimiento'];

                if ($idMovimiento > 0) {
                    $sql = "SELECT mcc.idMovimientoCtaContable, mcc.idUn
                        FROM movimientoctacontable mcc
                        WHERE mcc.idMovimiento=$idMovimiento AND mcc.eliminado=0";
                    $query2 = $this->db->query($sql);

                    if ($query2->num_rows == 1) {
                        $fila2                   = $query2->row_array();
                        $idMovimientoCtaContable = $fila2['idMovimientoCtaContable'];
                        $idUn                    = $fila2['idUn'];

                        $sql = "SELECT s.idPersona
                            FROM socio s
                            LEFT JOIN movimiento m ON m.idPersona=s.idPersona
                                AND m.idTipoEstatusMovimiento IN (66, 70)
                                AND m.idProducto=390 AND m.eliminado=0
                                AND m.descripcion NOT LIKE '%InBody%'
                            WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.idTipoEstatusSocio<>82
                                AND s.eliminado=0 AND m.idMovimiento IS NULL
                            GROUP BY s.idPersona";
                        $query3 = $this->db->query($sql);
                        $total  = $query3->num_rows;

                        $noSW = array(7, 11, 26, 35);
                        if ($total > 0 && !in_array($idUn, $noSW)) {
                            $importeCredenciales = 120 * $total;

                            $sql = "INSERT INTO movimientoctacontable
                                (idMovimiento, idTipoMovimiento, idUn, numeroCuenta,
                                cuentaProducto, idPromocion, fechaAplica, importe) VALUES (
                                $idMovimiento, 48, $idUn, '4090',
                                '', 0, DATE(NOW()), $importeCredenciales)";
                            $this->db->query($sql);

                            $sql = "UPDATE movimientoctacontable m
                                SET m.importe = m.importe - $importeCredenciales
                                WHERE m.idMovimientoCtaContable=$idMovimientoCtaContable";
                            $this->db->query($sql);

                            $sql = "UPDATE movimiento m
                                SET m.descripcion = CONCAT('Promocion ', m.descripcion)
                                WHERE m.idMovimiento=$idMovimiento AND m.idTipoEstatusMovimiento=65";
                            $this->db->query($sql);
                        }
                    }
                }
            }
        }
    }

    /**
     * Valida si el codigo de club premier ya se encuentra registrado en alguna otra membresia
     *
     * @param  integer $idUnicoMembresia Identificador de membresia
     * @param  string  $clubPremier      No de cta en club premier
     *
     *  @author Jorge Cruz
     *
     * @return boolean
     */
    public function duplicaClubPremier($idUnicoMembresia, $clubPremier)
    {
        settype($idUnicoMembresia, 'integer');

        $clubPremier = trim($clubPremier);
        if ($clubPremier == '') {
            return false;
        }

        $sql = "SELECT m.idMovimiento
            FROM clubpremieranualidad c
            INNER JOIN movimiento m ON m.idMovimiento=c.idMovimiento
                AND m.eliminado=0 AND m.idTipoEstatusMovimiento=66
            WHERE c.numSocioClubPremier='$clubPremier'
                AND m.idUnicoMembresia=$idUnicoMembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return true;
        }

        return false;
    }

    /**
     * [esCargoAnualidad2018 description]
     *
     * @param  integer $idMovimiento [description]
     *
     * @return boolean
     */
    public function esCargoAnualidad2018($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $res = false;

        if ($idMovimiento <= 0) {
            return $res;
        }

        $sql = "SELECT m.idMovimiento
            FROM tmpanualidades t
            INNER JOIN movimiento m ON m.idMovimiento=t.idMovimiento
                AND m.eliminado=0
            INNER JOIN sociopagomtto spm ON spm.idMovimiento=m.idMovimiento
                AND spm.eliminado=0
                AND '2018-12-31' BETWEEN spm.fechaInicio AND spm.fechaFin
            WHERE t.idMovimiento=$idMovimiento";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            $res = true;
        }

        return $res;
    }

    /**
     * [evaluaInicio description]
     *
     * @param  [type] $fechaInicio   [description]
     * @param  [type] $meses         [description]
     * @param  [type] $proximoInicio [description]
     * @param  [type] $tipo          [description]
     *
     * @return [type]                [description]
     */
    public function evaluaInicio($fechaInicio, $meses, $proximoInicio, $tipo)
    {
        $sql = "CALL spMTTOMSIEvaluaInicio('$fechaInicio', $meses, '$proximoInicio', $tipo, @resultado)";
        $this->db->query($sql);

        $sql2   = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row    = $query2->row();
        $a      = explode('|', $row->resp);

        $r['fechaInicio'] = $a[0];
        $r['fechaFin']    = $a[1];
        $r['meses']       = $a[2];

        return $r;
    }

    /**
     *
     * @author Gustavo Bonilla
     */
    public function formatoAnualidad($idMovimientoAnualidad = 0)
    {
        settype($idMovimientoAnualidad, 'integer');

        $ci = &get_instance();

        $ci->load->model('un_model');
        $ci->load->model('membresia_model');
        $ci->load->model('movimientos_model');
        $ci->load->model('persona_model');
        $ci->load->model('socio_model');
        $ci->load->model('catalogos_model');
        $ci->load->model('mantenimientos_model');
        $ci->load->model('digital_model');
        $ci->load->model('operadores_model');
        $ci->load->model('finanzas_model');

        if ($idMovimientoAnualidad <= 0) {
            return false;
        }

        $idUnicoMembresia = $ci->movimientos_model->unicoMembresia($idMovimientoAnualidad);

        $sql = "SELECT dp.idDocumentoPersona
            FROM documentopersona dp
            INNER JOIN documento d ON d.idDocumento=dp.idDocumento AND d.fechaEliminacion='0000-00-00 00:00:00'
            WHERE dp.idUnicoMembresia=$idUnicoMembresia AND dp.idMovimiento=$idMovimientoAnualidad";
        $query2 = $this->db->query($sql);
        if ($query2->num_rows > 0) {
            return false;
        }

        $sql = "SELECT idFactura FROM facturamovimiento
            WHERE idMovimiento=$idMovimientoAnualidad";
        $query = $this->db->query($sql);
        if ($query->num_rows != 1) {
            return false;
        }
        $row       = $query->row_array();
        $idFactura = $row['idFactura'];

        $datosFactura = $ci->finanzas_model->obtenerDatosFactura($idFactura);

        if ($datosFactura != 0) {
            $datos['fechaHoy']  = date('Y-m-d');
            $datos['fecha_hoy'] = formatoFecha($datos['fechaHoy']);
            $detalleFactura     = $ci->finanzas_model->obtenerDetalleFactura($idFactura);

            if ($idMovimientoAnualidad) {
                $idMovimiento = $idMovimientoAnualidad;
            } else {
                $idMovimiento = $detalleFactura[0]->idMovimiento;
            }
            $datosMovimiento        = $ci->movimientos_model->datosGral($idMovimiento);
            $datos['Membresia']     = $ci->membresia_model->obtenerTipoMembresia($datosMovimiento['idUnicoMembresia']);
            $datosMantenimiento     = $ci->mantenimientos_model->obtenerMantenimientoCliente($datosMovimiento['idUnicoMembresia'], $datosFactura['idUn'], ROL_CLIENTE_TITULAR);
            $idMantenimiento        = $datosMantenimiento['idMantenimiento'];
            $datos['tipoMembresia'] = $datos['Membresia']['nombre'];

            $datos['mantenimiento'] = $datos['Membresia']['nombre'];

            $datosTitular         = $ci->membresia_model->obtenerTitular($datosMovimiento['idUnicoMembresia']);
            $datos['titular']     = $ci->persona_model->nombre($datosTitular['idPersona']);
            $datosMembresia       = $ci->membresia_model->obtenerDatosGeneralesMem($datosMovimiento['idUnicoMembresia']);
            $datos['idMembresia'] = $datosMembresia[0]->idMembresia;
            $datos['club']        = $ci->un_model->nombre($datosMembresia[0]->idUn);

            $datosUn                   = $ci->un_model->obtenDatosUn($datosMembresia[0]->idUn);
            $datosOperador             = $ci->operadores_model->obtenOperadorInfo($datosUn['idOperador']);
            $datos['logo']             = $datosOperador[0]['logo'];
            $datos['razonSocial']      = $datosOperador[0]['razonSocial'];
            $datos['clubes']           = $datosOperador[0]['clubes'];
            $datos['responsable']      = $datosOperador[0]['responsable'];
            $datos['firmaResponsable'] = $datosOperador[0]['firmaResponsable'];

            $datos['digital']       = $ci->digital_model->validaAutorizacionDigital($datosMembresia[0]->idUnicoMembresia) ? 1 : 0;
            $datos['fechaRegistro'] = date('Y-m-d');

            $domicilio          = $ci->persona_model->listaDomicilios($datosTitular['idPersona']);
            $datos['domicilio'] = $domicilio[0]['calle'] . ' ' . $domicilio[0]['numero'] . ' ' . $domicilio[0]['colonia'];
            $telefonos          = $ci->persona_model->listaTelefonos($datosTitular['idPersona']);
            if (count($telefonos) > 0) {
                for ($i = 0; $i < count($telefonos); $i++) {
                    if ($i == 0) {
                        $datos['telefonos'] = $telefonos[$i]['telefono'];
                    } else {
                        $datos['telefonos'] .= ' - ' . $telefonos[$i]['telefono'];
                    }
                }
            }
            $correos = $ci->persona_model->listaMails($datosTitular['idPersona']);
            if (count($correos) > 0) {
                for ($i = 0; $i < count($correos); $i++) {
                    if ($i == 0) {
                        $datos['correos'] = $correos[$i]['mail'];
                    } else {
                        $datos['correos'] .= " / " . $correos[$i]['mail'];
                    }
                }
            }

            $datos['datos']       = $ci->socio_model->obtenSocios($datosMovimiento['idUnicoMembresia']);
            $datos['integrantes'] = '<table width="100%" bordercolor="#FFFFFF" style="border: solid ;" >' .
                '<tr><td width="40%">TIPO INTEGRANTE</td><td width="80%">NOMBRE COMPLETO</td><td width="40%">FECHA DE NACIMIENTO</td></tr>';
            $informacionTarjeta   = '';
            $fechaInicio          = '';
            $fechaFin             = '';
            $idMantenimientoNuevo = 0;
            foreach ($datos['datos'] as $fila) {
                $datosMtto = $ci->membresia_model->datosSocioPagoMtto($fila->idPersona, $datosMovimiento['idUnicoMembresia'], '', 1, $idMovimiento);

                if ($datosMtto != null) {
                    $data['rolCliente'] = $ci->membresia_model->obtnerRolCliente($fila->idPersona);

                    $data['titulo'] = $data['rolCliente']['descripcion'];
                    $datosGenerales = $ci->persona_model->datosGenerales($fila->idPersona);
                    $datos['integrantes'] .= '<tr>';
                    $datos['integrantes'] .= '<td >' . $data['titulo'] . '</td>';
                    $datos['integrantes'] .= '<td >' . $ci->persona_model->nombre($fila->idPersona) . '</td>';
                    $datos['integrantes'] .= '<td >' . $datosGenerales['fecha'] . '</td>';
                    $datos['integrantes'] .= '</tr>';
                    if ($data['rolCliente']['idTipoRolCliente'] == ROL_CLIENTE_TITULAR) {
                        $fechaInicio          = $datosMtto[0]['fechaInicio'];
                        $fechaFin             = $datosMtto[0]['fechaFin'];
                        $idMantenimientoNuevo = $datosMtto[0]['idMantenimiento'];
                    }
                }
            }
            $datos['integrantes'] .= '</table>';

            $datosFormasPago     = $ci->finanzas_model->listaFormasPago($idFactura);
            $datos['formasPago'] = '<table width="100%" bordercolor="#FFFFFF" style="border: solid ;" >';

            if (is_array($datosFormasPago)) {
                foreach ($datosFormasPago as $fila) {
                    $datos['formasPago'] .= '<tr>';
                    switch ($fila["idFormaPago"]) {
                        case 1:
                            $datos['formasPago'] .= '<td>' . strtoupper($fila['formaPago']) . ': $' . number_format($fila["importe"], 3) . '</td>';
                            $datos['formasPago'] .= '<td>NO. FACTURA: ' . $fila['factura'] . '</td>';
                            break;
                        case 2:
                            $datos['formasPago'] .= '<td>' . strtoupper($fila['formaPago']) . ': $' . number_format($fila["importe"], 3) . '</td>';
                            $datos['formasPago'] .= '<td>BANCO EMISOR: ' . $fila["banco"] . '</td>';
                            $datos['formasPago'] .= '<td>NO: CHEQUE: ' . $fila["autorizacion"] . '</td>';
                            $datos['formasPago'] .= '<td>NO: CUENTA: ' . $fila["cuenta"] . '</td>';
                            $datos['formasPago'] .= '<td>NO. FACTURA: ' . $fila['factura'] . '</td>';
                            break;
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 13:
                            $datos['formasPago'] .= '<td>' . strtoupper($fila['formaPago']) . ': $' . number_format($fila["importe"], 3) . '</td>';
                            $datos['formasPago'] .= '<td>BANCO EMISOR: ' . $fila["banco"] . '</td>';
                            $datos['formasPago'] .= '<td>AUTORIZACION: ' . $fila["autorizacion"] . '</td>';
                            $datos['formasPago'] .= '<td>NO. FACTURA: ' . $fila['factura'] . '</td>';
                            break;
                        default:
                            $datos['formasPago'] .= '<td></td>';
                            break;
                    }

                    if ($fila['factura'] != '') {
                        if (strpos($datos['formasPago'], 'NO. FACTURA') === false) {
                            $datos['formasPago'] .= '<td>NO. FACTURA: ' . $fila['factura'] . '</td>';
                        }
                    }
                    $datos['formasPago'] .= '</tr>';
                }
            } else {
                $xxx = $ci->finanzas_model->obtenerDatosFactura($idFactura);
                $datos['formasPago'] .= '<td>' . strtoupper($datosMovimiento['importe']) . ': $' . number_format($datosMovimiento['importe'], 3) . '</td>';
                $datos['formasPago'] .= '<td>NO. FACTURA: ' . $xxx['prefijoFactura'] . $xxx['folioFactura'] . '</td>';
            }

            $datos['formasPago'] .= '</table>';
            $datos['anualidad'] = $datosMovimiento['importe'];
            $datos['periodo']   = 'Del ' . formatoFecha($fechaInicio) . ' al ' . formatoFecha($fechaFin);
            $datos['empleado']  = $ci->un_model->obtenNombreGteAdmin($datosFactura['idUn']);
            $ci->load->model('documentos_model');
            $tipoDocumento = TIPO_MTTOANUAL;

            $datos['meses'] = meses();

            if (file_exists(verificaRuta(RUTA_LOCAL . '/system/application/views/finanzas/html/mttoAnual_' .
                $datosUn['idOperador'] . '.php'))) {
                $html        = $ci->load->view('finanzas/html/mttoAnual_' . $datosUn['idOperador'], $datos, true);
                $idDocumento = $ci->documentos_model->insertaHTML2($html, $datosTitular['idPersona'], $tipoDocumento, $datosMovimiento['idUnicoMembresia'], $idMovimiento, $datos['digital']);
            }

            /////////DATOS HTML
            $idMantenimientoActual = $idMantenimiento;

            if ($idMantenimientoActual != 0 && $idMantenimientoNuevo != 0) {
                $socios        = $ci->socio_model->obtenSocios($datosMovimiento['idUnicoMembresia']);
                $cuentaMayores = 0;
                $cuentaMenores = 0;
                foreach ($socios as $id => $socio) {
                    if ($ci->persona_model->edad($socio->idPersona) >= 18) {
                        $cuentaMayores++;
                    } else {
                        $cuentaMenores++;
                    }
                }

                $meses = array('Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo',
                    'Junio', 'Julio', 'Agosto', 'Septiembre',
                    'Octubre', 'Noviembre', 'Diciembre');

                $datos['fecha_hoy']   = date('d') . ' de ' . $meses[date('n') - 1] . ' de ' . date('Y');
                $datosTitular         = $ci->membresia_model->obtenerTitular($datosMovimiento['idUnicoMembresia']);
                $datos['nombre']      = $ci->persona_model->nombre($datosTitular['idPersona']);
                $datos['idMembresia'] = $datosMembresia[0]->idMembresia;
                $datos['club']        = $ci->un_model->nombre($datosMembresia[0]->idUn);

                $nombreMttoActual              = $ci->mantenimientos_model->obtenMantenimientoNombre($idMantenimientoActual);
                $datos['tipoMembresiaActual']  = $nombreMttoActual;
                $datos['Membresia']['nombre2'] = $ci->mantenimientos_model->obtenMantenimientoNombre($idMantenimientoNuevo);

                $datos['tipoMembresiaNueva'] = $datos['Membresia']['nombre2'];

                $datos['formaPago'] = $ci->socio_model->obtenerEsquemaFormaPago($datosTitular['idPersona']);
                if ($idMantenimientoNuevo == 63) {
                    $datos['horario'] = 'PART TIME';
                } else {
                    $datos['horario'] = 'COMPLETO';
                }
                $datos['integrantesMantenimiento'] = $ci->membresia_model->obtenerMantenimientoUnHorario($idMantenimientoNuevo, $datosMembresia[0]->idProducto, $datosMembresia[0]->idUn);

                $datos['integrantes'] = $cuentaMayores . ' adultos , ' . $cuentaMenores . ' menores';
                $datos['vendedor']    = $ci->un_model->obtenNombreGteAdmin($datosFactura['idUn']);

                $ci->load->model('documentos_model');
                $tipoDocumento = TIPO_CAMBIOCUOTAMTTO;

                if (file_exists(verificaRuta(RUTA_LOCAL . '/system/application/views/membresia/HTML/CambioCuotaMtto_' . $datosUn['idOperador'] . '.php'))) {
                    $html         = $ci->load->view('membresia/HTML/CambioCuotaMtto_' . $datosUn['idOperador'], $datos, true);
                    $idDocumento2 = $ci->documentos_model->insertaHTML2($html, $datosTitular['idPersona'], $tipoDocumento, $datosMovimiento['idUnicoMembresia'], $idMovimiento, $datos['digital']);
                    # Agregar LOG
                    $this->permisos_model->log('Se genera formato por pago de Mantenimiento Anual' .
                        " [" . $ci->session->userdata('idUn') . "-" . $ci->session->userdata('idUsuario') . "-" .
                        $ci->session->userdata('usuario') . "-" . $ci->session->userdata('NombreUsuario') . "] .",
                        LOG_FACTURACION, $datosMovimiento['idUnicoMembresia'], 0, false);

                    return true;
                }
            }
        }
        return false;
    }

    public function logPromoSantander($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $sql = "INSERT INTO logpromosantander (idMovimiento, fechaRegistro)
			VALUES ($idMovimiento, NOW())";
        $this->db->query($sql);
    }

    /**
     * [permiteAnualidad2018 description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function permiteAnualidad2018($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        // Validamos que solo tenga un cargo, no division, no cambio cuota mtto
        $sql = "SELECT spm.idMantenimiento
            FROM sociopagomtto spm
            WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.eliminado=0 AND spm.activo=1
                AND spm.fechaFin='2017-12-31' AND spm.fechaInicio<='2017-03-31'
            GROUP BY spm.idMovimiento";
        $query = $this->db->query($sql);
        if ($query->num_rows != 1) {
            return false;
        }
        $row             = $query->row_array();
        $idMantenimiento = $row['idMantenimiento'];

        $ci = &get_instance();

        $ci->load->model('membresia_model');

        return true;
    }

    /**
     * Valida si se permite descuento extra de 8% para anualidad
     *
     * @param  integer $idUnicoMembresia [description]
     *
     * @return boolean
     */
    public function permiteDescuento8Extra($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $res = false;

        $anual2017 = false;
        $anual2018 = false;

        $sql = "SELECT spm.idMovimiento
            FROM sociopagomtto spm
            INNER JOIN movimiento m ON m.idMovimiento=spm.idMovimiento
                AND m.eliminado=0
            INNER JOIN membresia mem ON mem.idUnicoMembresia=m.idUnicoMembresia
            INNER JOIN un u ON u.idUn=mem.idUn
            INNER JOIN empresa e ON e.idEmpresa=u.idEmpresa
                AND e.idEmpresaGrupo=1
            INNER JOIN facturamovimiento fm ON fm.idMovimiento=m.idMovimiento
            WHERE spm.idUnicoMembresia=$idUnicoMembresia
                AND spm.idMovimiento>0 AND spm.eliminado=0
                AND '2017-12-31' BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.fechaRegistro<'2017-05-01 00:00:00'
            GROUP BY spm.idMovimiento";
        $query = $this->db->query($sql);
        if ($query->num_rows >= 1) {
            $anual2017 = true;
        }

        $sql = "SELECT spm.idMovimiento
            FROM sociopagomtto spm
            INNER JOIN movimiento m ON m.idMovimiento=spm.idMovimiento
                AND m.eliminado=0
            INNER JOIN membresia mem ON mem.idUnicoMembresia=m.idUnicoMembresia
            INNER JOIN un u ON u.idUn=mem.idUn
            INNER JOIN empresa e ON e.idEmpresa=u.idEmpresa
                AND e.idEmpresaGrupo=1
            INNER JOIN facturamovimiento fm ON fm.idMovimiento=m.idMovimiento
            WHERE spm.idUnicoMembresia=$idUnicoMembresia
                AND spm.idMovimiento>0 AND spm.eliminado=0
                AND '2018-12-31' BETWEEN spm.fechaInicio AND spm.fechaFin
                AND spm.fechaRegistro<'2018-04-01 00:00:00'
            GROUP BY spm.idMovimiento";
        $query = $this->db->query($sql);
        if ($query->num_rows >= 1) {
            $anual2018 = true;
        }

        /*
        $sql = "SELECT m.idUnicoMembresia
        FROM membresiamayoabril m";
        $query2 = $this->db->query($sql);
        if ($query2->num_rows>=1) {
        return true;
        }
         */

        if ($anual2017 == true && $anual2018 == true) {
            $res = true;
        }

        return $res;
    }

    /**
     * Valida descuento por contigencia septiembre en Xola
     *
     * @param  integer $idUnicoMembresia Identificado de membresia
     *
     * @return boolean
     */
    public function permiteDescuentoXola($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT spm.idMovimiento
            FROM sociopagomtto spm
            INNER JOIN movimiento m ON m.idMovimiento=spm.idMovimiento
                AND m.eliminado=0
            INNER JOIN membresia mem ON mem.idUnicoMembresia=m.idUnicoMembresia
                AND mem.idUn=64 AND mem.eliminado=0
            INNER JOIN facturamovimiento fm ON fm.idMovimiento=m.idMovimiento
            WHERE spm.idUnicoMembresia=$idUnicoMembresia
                AND spm.idMovimiento>0 AND spm.eliminado=0
                AND '2017-12-31' BETWEEN spm.fechaInicio AND spm.fechaFin
                AND '2017-09-19' BETWEEN spm.fechaInicio AND spm.fechaFin
            GROUP BY spm.idMovimiento";
        $query = $this->db->query($sql);
        if ($query->num_rows >= 1) {
            return true;
        }

        return false;
    }

    /**permiteAnualidad2018
     * [spGuardaCargoPromocionAnualidad description]
     *
     * @param  [type] $datos [description]
     *
     * @return [type]        [description]
     */
    public function spGuardaCargoPromocionAnualidad($datos)
    {
        $cp = $this->duplicaClubPremier($datos['idUnicoMembresia'], $datos['clubPremier']);

        if ($cp == true) {
            return 'La cuenta de club premier ya tiene asignada una anualidad';
        }

        $sql1 = "CALL crm.spMTTOMSIGuardaCargo(" .
            $datos['idUnicoMembresia'] .
            ", '" . $datos['fechaInicio'] . "'" .
            ", " . $datos['idMantenimiento'] .
            ", " . $datos['importe'] .
            ", " . $datos['mesesMTTO'] .
            ", " . $datos['mesesMSI'] .
            ", '" . $datos['origenAAsignar'] . "'" .
            ", " . $datos['idPersonaResponsableVenta'] .
            ", " . $datos['idPromoMTTOMSI'] .
            ", '" . $datos['lista_personas'] . "'" .
            ", '" . $datos['clubPremier'] . "'" .
            ", '" . $datos['nombrePremier'] . "'" .
            ", '" . $datos['apellidoPremier'] . "'" .
            ", @resultado)";
        $query1 = $this->db->query($sql1);

        $sql2   = "SELECT @resultado AS resp";
        $query2 = $this->db->query($sql2);
        $row    = $query2->row();
        return $row->resp;
    }

    /**
     * [spObtenCargosPromocionAnualidadTiposAnualidades description]
     *
     * @return [type] [description]
     */
    public function spObtenCargosPromocionAnualidadTiposAnualidades()
    {
        $datos = array();

        $sql1  = "SELECT * FROM crm.tipoAnualidad;";
        $query = $this->db->query($sql1);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $datos[$fila->idTipoAnualidad]['idTipoAnualidad'] = $fila->idTipoAnualidad;
                $datos[$fila->idTipoAnualidad]['descripcion']     = utf8_decode($fila->descripcion);
                $datos[$fila->idTipoAnualidad]['origen']          = $fila->origen;
            }
        }

        return $datos;
    }

    /**
     * [spObtenPorcentajesDescuentoAnualidad description]
     *
     * @param  integer $idUn            [description]
     * @param  integer $idProducto      [description]
     * @param  integer $idMantenimiento [description]
     * @param  integer $fidelidad       [description]
     * @param  integer $corporativa     [description]
     * @param  string  $rango           [description]
     *
     * @return [type]                   [description]
     */
    public function spObtenPorcentajesDescuentoAnualidad($idUn = 0, $idProducto = 0, $idMantenimiento = 0, $fidelidad = 0, $corporativa = 0, $rango = '', $fecha = '0000-00-00')
    {
        settype($idUn, 'integer');
        settype($idProducto, 'integer');
        settype($idMantenimiento, 'integer');
        settype($fidelidad, 'integer');
        settype($corporativa, 'integer');

        $datos = array();

        $datos['respuesta'] = 0;

        $datos['combinaciones']        = $corporativa == 0 ? 4 : 8;
        $datos['combinacionesDigitos'] = $corporativa == 0 ? 3 : 4;
        $datos['combinacionesTotal']   = ($datos['combinaciones'] * 2) - 1;
        for ($combinacionesCont = 0, $combinacionesId = $datos['combinacionesTotal']; $combinacionesCont <= $datos['combinacionesTotal']; $combinacionesCont++, $combinacionesId--) {
            $datos['combinacionesArr'][$combinacionesId] = str_split(str_repeat('0', $datos['combinacionesDigitos'] - strlen(decbin($combinacionesCont))) . decbin($combinacionesCont));
        }
        ksort($datos['combinacionesArr']);

        $datos['fidelidad'] = array($fidelidad, 0);

        foreach ($datos['fidelidad'] as $idF => $nivelFidelidad) {
            if ($corporativa == 0) {
                foreach ($datos['combinacionesArr'] as $combinacionId => $combinacion) {
                    $datos['combinacionF'][0] = $combinacion[0] == 1 ? $idUn : 0;
                    $datos['combinacionF'][1] = $combinacion[1] == 1 ? $idProducto : 0;
                    $datos['combinacionF'][2] = $combinacion[2] == 1 ? $idMantenimiento : 0;

                    $sql   = "CALL crm.spMTTOMSIObtenPorcentajesDescuento(" . implode(",", $datos['combinacionF']) . ", '', 0, " . $nivelFidelidad . ", '$fecha', @resultado)";
                    $query = $this->db->query($sql);

                    $sqlr   = 'SELECT @resultado AS resp';
                    $queryr = $this->db->query($sqlr);
                    $row    = $queryr->row();

                    $datos['combinacionesDatos'][$combinacionId] = array($sql, $row->resp);

                    if ($row->resp != '' && $row->resp != null) {
                        $datos['respuesta'] = $row->resp;
                        break;
                    }
                }
            } else {
                foreach ($datos['combinacionesArr'] as $combinacionId => $combinacion) {
                    $datos['combinacionF'][0] = $combinacion[1] == 1 ? $idUn : 0;
                    $datos['combinacionF'][1] = $combinacion[2] == 1 ? $idProducto : 0;
                    $datos['combinacionF'][2] = $combinacion[3] == 1 ? $idMantenimiento : 0;
                    $datos['combinacionF'][3] = $combinacion[0] == 1 ? "'" . $rango . "'" : "''";

                    $sql   = "CALL crm.spMTTOMSIObtenPorcentajesDescuento(" . implode(',', $datos['combinacionF']) . ', 1, ' . $nivelFidelidad . ", '$fecha', @resultado)";
                    $query = $this->db->query($sql);

                    $sqlr   = 'SELECT @resultado AS resp';
                    $queryr = $this->db->query($sqlr);
                    $row    = $queryr->row();

                    $datos['combinacionesDatos'][$combinacionId] = array($sql, $row->resp);

                    if ($row->resp != '' && $row->resp != null) {
                        $datos['respuesta'] = $row->resp;
                        break;
                    }
                }
            }

            if ($datos['respuesta'] != '' && $datos['respuesta'] != null) {
                break;
            }
        }
        return $datos['respuesta'];
    }

    /**
     * Reporte de anualidades pendientes de envio a club premier
     *
     * @return array
     */
    public function reporteClubPremier()
    {
        $datos = array();

        $w = '';
        if ($this->session->userdata('idUn') != 1) {
            $idUn = $this->session->userdata('idUn');
            $w    = "AND (f.idUn=$idUn OR mem.idUn=$idUn)";
        }

        $sql = "SELECT mem.idMembresia, u.nombre AS club,
                CONCAT(f.prefijoFactura, f.folioFactura) AS factura,
                DATE(f.fecha) AS fecha, c.numSocioClubPremier,
                UPPER(c.nombre) AS nombre, UPPER(c.apellido) AS apellido,
                c.idMovimiento, c.mensaje
            FROM clubpremieranualidad c
            INNER JOIN movimiento m ON m.idMovimiento=c.idMovimiento
            INNER JOIN facturamovimiento fm ON fm.idMovimiento=m.idMovimiento
            INNER JOIN factura f ON f.idFactura=fm.idFactura
            INNER JOIN membresia mem ON mem.idUnicoMembresia=m.idUnicoMembresia
            INNER JOIN un u ON u.idUn=mem.idUn
            WHERE c.enviado=0 $w
            ORDER BY u.nombre, mem.idMembresia";
        $query = $this->db->query($sql);

        if ($query->num_rows) {
            foreach ($query->result() as $fila) {
                $cp['idMembresia']  = $fila->idMembresia;
                $cp['club']         = $fila->club;
                $cp['factura']      = $fila->factura;
                $cp['fecha']        = $fila->fecha;
                $cp['numPremier']   = $fila->numSocioClubPremier;
                $cp['nombre']       = utf8_encode($fila->nombre);
                $cp['apellido']     = utf8_encode($fila->apellido);
                $cp['idMovimiento'] = $fila->idMovimiento;
                $cp['mensaje']      = $fila->mensaje;
                $datos[]            = $cp;
            }
        }

        return $datos;
    }

    /**
     * [ultimoPago description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function ultimoPago($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT MAX(DATE(a.fecha)) AS fecha
            FROM  (
                SELECT IFNULL(MAX(spm.fechaFin), DATE_SUB(DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')), INTERVAL 1 DAY)) AS fecha
                FROM sociopagomtto spm
                WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.eliminado=0 AND spm.activo=1
                UNION ALL
                SELECT DATE_SUB(CONCAT(SUBSTRING(MAX(mr.fechaRegistro), 1, 8),'01'), INTERVAL 1 DAY) AS fecha
                FROM membresiareactivacion mr
                WHERE mr.idUnicoMembresia=$idUnicoMembresia AND mr.fechaEliminacion='0000-00-00 00:00:00'
            ) a";
        $query = $this->db->query($sql);

        $row = $query->row_array();
        return $row['fecha'];
    }

    /**
     * [pagoMensual2018 description]
     *
     * @param  [type] $idUnicoMembresia [description]
     * @param  [type] $idMantenimiento  [description]
     *
     * @return [type]                   [description]
     */
    public function pagoMensual2018($idUnicoMembresia, $idMantenimiento)
    {
        settype($idUnicoMembresia, 'integer');
        settype($idMantenimiento, 'integer');

        $sql = "SELECT final AS importe
            FROM anualprebase2019
            WHERE idUnicoMembresia=$idUnicoMembresia AND idMantenimiento=$idMantenimiento";

        /*$sql = "SELECT a.idMovimiento, a.meses, ROUND(a.importe, 8) as importe
        FROM (
        SELECT
        spm.idMovimiento,
        PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1 AS meses,
        ((m.importe/((PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1)-(IF(m.origen LIKE '%DescBinSantander%', 1.0, 0)))) /
        (if(m.msi=1, 0.88, if(m.msi=6, 0.92, if(m.msi=12, 0.94, 1))))) * 1.07 AS importe,
        COUNT(*) AS total
        FROM sociopagomtto spm
        INNER JOIN productomantenimiento pm ON pm.idMantenimiento=spm.idMantenimiento
        INNER JOIN movimiento m ON m.idMovimiento=spm.idMovimiento
        AND DATE(m.fechaRegistro) BETWEEN '2016-09-01' AND '2016-10-31'
        INNER JOIN membresia mem ON mem.idUnicoMembresia=spm.idUnicoMembresia
        AND mem.idUn NOT IN (7, 11, 26, 35, 70, 79, 85, 82, 83, 84, 88, 89, 86, 87)
        WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.eliminado=0 AND spm.activo=1
        AND spm.fechaFin='2017-12-31' AND spm.idMantenimiento=$idMantenimiento
        GROUP BY spm.idMovimiento
        HAVING meses >= 10
        ) a";
         */
        $query = $this->db->query($sql);

        if ($query->num_rows != 1) {
            return 0.00;
        }

        $row = $query->row_array();
        return $row['importe'];
    }

    /**
     * Valida si la membresia ya tiene reigstrado algun cargo en programa de Club Premier
     *
     * @param  integer $idUnicoMembresia Identificado unico de membresia
     *
     * @author Jorge Cruz
     *
     * @return boolean
     */
    public function validaClubPremier($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT m.idMovimiento
            FROM clubpremieranualidad c
            INNER JOIN movimiento m ON m.idMovimiento=c.idMovimiento
                AND m.eliminado=0 AND m.idTipoEstatusMovimiento NOT IN (67, 65)
            WHERE m.idUnicoMembresia=$idUnicoMembresia AND c.numSocioClubPremier <> ''";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return true;
        }

        return false;
    }

    /**
     * [validaEnvioClubPremier description]
     *
     * @param  [type] $idMovimiento [description]
     *
     * @return [type]                   [description]
     */
    public function validaEnvioClubPremier($idMovimiento)
    {
        settype($idMovimiento, 'integer');

        $sql = "SELECT c.idMovimiento
            FROM clubpremieranualidad c
            WHERE c.idMovimiento=$idMovimiento AND c.enviado=1";
        $query = $this->db->query($sql);

        if ($query->num_rows > 0) {
            return true;
        }

        return false;
    }

    public function validadVentaReactivacionMesActual($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');
        $res = false;

        if ($idUnicoMembresia > 0) {
            $sql = "SELECT m.idUnicoMembresia
                FROM membresia m
                WHERE m.idUnicoMembresia=$idUnicoMembresia AND m.eliminado=0
                    AND DATE(m.fechaRegistro) BETWEEN DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')) AND LAST_DAY(DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')))
                UNION ALL
                SELECT mr.idUnicoMembresia FROM membresiareactivacion mr
                WHERE mr.idUnicoMembresia=$idUnicoMembresia AND mr.fechaEliminacion='0000-00-00 00:00:00'
                    AND DATE(mr.fechaRegistro) BETWEEN DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')) AND LAST_DAY(DATE(CONCAT(YEAR(NOW()),'-',MONTH(NOW()),'-01')))";
            $query = $this->db->query($sql);

            if ($query->num_rows == 1) {
                return true;
            }
        }

        return $res;
    }

    /**
     * [validaPromo2018 description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function validaIntegrantes2019($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT * FROM (
                SELECT spm.idMovimiento, spm.idUnicoMembresia,
                    PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1 AS meses,
                    COUNT(*) AS total
                FROM sociopagomtto spm
                INNER JOIN socio s ON s.idSocio AND s.idSocio=spm.idSocio AND s.idTipoRolCliente NOT IN (17, 18, 19)
                WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.eliminado=0 AND spm.activo=1
                    AND spm.fechaFin='2018-12-31'
                GROUP BY spm.idMovimiento
                HAVING meses>=10
            ) a
            INNER JOIN (
                SELECT s.idUnicoMembresia, COUNT(*) AS integrantes
                FROM socio s
                WHERE s.idUnicoMembresia=$idUnicoMembresia AND s.eliminado=0 AND s.idTipoEstatusSocio=81
                    AND s.idTipoRolCliente NOT IN (17, 18, 19)
            ) b ON b.idUnicoMembresia=a.idUnicoMembresia
            WHERE a.total=b.integrantes";
        $query = $this->db->query($sql);

        if ($query->num_rows == 1) {
            return true;
        }

        return false;
    }

    /**
     * [validaIntegrantes2018 description]
     *
     * @param  [type] $idUnicoMembresia [description]
     *
     * @return [type]                   [description]
     */
    public function habilitaPromo2018($idUnicoMembresia)
    {
        settype($idUnicoMembresia, 'integer');

        $sql = "SELECT PERIOD_DIFF(DATE_FORMAT(spm.fechaFin, '%Y%m'), DATE_FORMAT(spm.fechaInicio, '%Y%m'))+1 AS meses
            FROM sociopagomtto spm
            INNER JOIN socio s ON s.idSocio AND s.idSocio=spm.idSocio AND s.idTipoRolCliente NOT IN (17, 18)
            WHERE spm.idUnicoMembresia=$idUnicoMembresia AND spm.eliminado=0 AND spm.activo=1
                AND spm.fechaFin='2017-12-31'
            GROUP BY spm.idMovimiento
            HAVING meses>=10";
        $query = $this->db->query($sql);

        if ($query->num_rows >= 1) {
            return true;
        }

        return false;
    }
}
