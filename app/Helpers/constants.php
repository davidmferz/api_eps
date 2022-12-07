<?php
session_start();
/*
|--------------------------------------------------------------------------
| File and Directory Modes
|--------------------------------------------------------------------------
|
| These prefs are used when checking and setting modes when working
| with the file system.  The defaults are fine on servers with proper
| security, but you may wish (or even need) to change the values in
| certain environments (Apache running a separate process for each
| user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
| always be used to set the mode correctly.
|
 */
define('FILE_READ_MODE', 0644);
define('FILE_WRITE_MODE', 0666);
define('DIR_READ_MODE', 0755);
define('DIR_WRITE_MODE', 0777);

/*
|--------------------------------------------------------------------------
| File Stream Modes
|--------------------------------------------------------------------------
|
| These modes are used when working with fopen()/popen()
|
 */

define('FOPEN_READ', 'rb');
define('FOPEN_READ_WRITE', 'r+b');
define('FOPEN_WRITE_CREATE_DESTRUCTIVE', 'wb'); // truncates existing file data, use with care
define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE', 'w+b'); // truncates existing file data, use with care
define('FOPEN_WRITE_CREATE', 'ab');
define('FOPEN_READ_WRITE_CREATE', 'a+b');
define('FOPEN_WRITE_CREATE_STRICT', 'xb');
define('FOPEN_READ_WRITE_CREATE_STRICT', 'x+b');

define('REGISTROS_POR_PAGINA', 25);
define('REGISTROS_POR_PAGINA_50', 50);
define('COLOR_TITULOS', '#cc2222');
define('COLOR_ENCABEZADOS', 'IndianRed');
define('MAX_INTENTOS_LOGIN', 5);
define('PREFIJO_CATALOGO', 'sw');

// Empresas
define('EMPRESA_UPSTER', 2);
define('EMPRESA_SPORTS', 1);
define('EMPRESA_WORLDGYM', 3);

// Log's
define('LOG_ACTIVIDAD_DEPORTIVA', '11');
define('LOG_ATENCIONCLIENTES', '29');
define('LOG_CATALOGO', '5');
define('LOG_CATEGORIA', '3');
define('LOG_CERTIFICADO_REGALO', '37');
define('LOG_CERTIFICADOS', '33');
define('LOG_COMISIONES', '25');
define('LOG_COMUNIDAD', '41');
define('LOG_CONVENIO', '8');
define('LOG_DOCUMENTO_DIGITAL', '44');
define('LOG_DOCUMENTOS', '40');
define('LOG_EMPLEADOS', '27');
define('LOG_EMPRESA', '6');
define('LOG_EVALUACIONES', '24');
define('LOG_EVENTO', '10');
define('LOG_FACTURACION', '26');
define('LOG_FACULTAMIENTO', '22');
define('LOG_FEELHEALTHY', '43');
define('LOG_FOTO', '31');
define('LOG_HOST', '42');
define('LOG_INSCRIPCION', '21');
define('LOG_LAVANDERIA', 50);
define('LOG_LOCKER', '20');
define('LOG_MANTENIMIENTO', '14');
define('LOG_MEMBRESIA', '16');
define('LOG_NOTICIAS', '39');
define('LOG_NOTIFICACIONES', '45');
define('LOG_OBJETO', '2');
define('LOG_OPERADOR', '19');
define('LOG_PAQUETE', '12');
define('LOG_PASES', '28');
define('LOG_PERMISOS', '13');
define('LOG_PERSONA', '9');
define('LOG_PRODUCTO', '4');
define('LOG_PROSPECTOS', '18');
define('LOG_PROVEEDOR', '47');
define('LOG_REFERENCIAS', '7');
define('LOG_RUTINA', '34');
define('LOG_SATISFACCION', '36');
define('LOG_SISTEMAS', '35');
define('LOG_SOCIO', '17');
define('LOG_SOCIOMANIA', '23');
define('LOG_SOPORTE', '45');
define('LOG_UNIDADNEGOCIO', '15');
define('LOG_USUARIO', '1');
define('LOG_VENTA', '7');

// Estatus de movimientos
define('TIPO_MOVIMIENTO_MANTENIMIENTO', 47);
define('TIPO_MOVIMIENTO_MEMBRESIA', 46);
define('TIPO_MOVIMIENTO_OTROSINGRESOS', 48);
define('TIPO_MOVIMIENTO_INVITADOS', 51);
//define('MOVIMIENTO_TIPO_TRASPASO', 420);

// Estatus membresia
define('ESTATUS_MEM_BENEFICIARIOS', 9);
define('ESTATUS_MEM_CESIONDERECHOS', 4);
define('ESTATUS_MEMBRESIA_ACTIVA', 27);
define('ESTATUS_MEMBRESIA_INACTIVA', 28);
define('ESTATUS_MEMBRESIA_LIBRE', 26);
define('ESTATUS_MEMBRESIA_PASIVA', 155);

define('MEM_ATRIB_ALTERNOPERMANENTE', 22);
define('MEM_ATRIB_AMPLIACION', 3);
define('MEM_ATRIB_AUSENCIA', 2);
define('MEM_ATRIB_BENEFICIARIO', 9);
define('MEM_ATRIB_CAMBIOTITULAR', 11);
define('MEM_ATRIB_CANC_ADEUDO', 16);
define('MEM_ATRIB_CANCELACION', 5);
define('MEM_ATRIB_CERTIFICADO', 8);
define('MEM_ATRIB_CESION', 4);
define('MEM_ATRIB_COMBO', 10);
define('MEM_ATRIB_INVITADOS', 14);
define('MEM_ATRIB_MULTIPLEPAGO', 17);
define('MEM_ATRIB_PRECIOESPECIAL', 13);
define('MEM_ATRIB_REACTIVACION', 1);
define('MEM_ATRIB_SEL_AMPLIAR', 15);
define('MEM_ATRIB_SOCIOMANIA', 7);
define('MEM_ATRIB_TRANSEMPRESA', 12);
define('MEM_ATRIB_TRANSFERENCIA', 6);
define('MEM_BENFICIARIO', 3);

// Estatus socio
define('ESTATUS_SOCIO_ACTIVO', 81);
define('ESTATUS_SOCIO_AUSENCIA', 83);
define('ESTATUS_SOCIO_BAJA', 82);
define('ESTATUS_SOCIO_INACTIVO', 84);
define('ESTATUS_SOCIO_INVITADO', 87);
define('ESTATUS_SOCIO_PENDIENTEVALIDACION', 88);
define('ESTATUS_SOCIO_SUSPENDIDO', 86);

// Estatus empleado
define('ESTATUS_EMPLEADO_ACTIVO', 196);
define('ESTATUS_EMPLEADO_BAJA', 197);
define('ESTATUS_EMPLEADO_SUSPENDIDO', 198);

// Estatus incripcion a evento
define('ESTATUS_CLASE_ASIGNADO', 1);
define('ESTATUS_CLASE_CANCELADO', 5);
define('ESTATUS_CLASE_DEMO', 6);
define('ESTATUS_CLASE_FALTOINSTRUCTOR', 4);
define('ESTATUS_CLASE_FALTOUSUARIO', 3);
define('ESTATUS_CLASE_IMPARTIDO', 2);

//Capacidad del evento
define('EVENTO_CAPACIDAD_CLASE', 3);
define('EVENTO_CAPACIDAD_MATUTINO', 4);
define('EVENTO_CAPACIDAD_MAXIMA', 1);
define('EVENTO_CAPACIDAD_MAXIMA_SALON', 18);
define('EVENTO_CAPACIDAD_MINIMA_SALON', 17);
define('EVENTO_CAPACIDAD_NUMEROCLASES', 6);
define('EVENTO_CAPACIDAD_PARTICIPANTES', 7);
define('EVENTO_CAPACIDAD_SEMANAS', 14);
define('EVENTO_CAPACIDAD_VESPERTINO', 5);
define('EVENTO_CAPACIDAD_CARRERA', 27);
define('DIAS_PARA_DESCUENTO', 3);

//Tipo de evento
define('EVENTO_CLASESPERSONALIZADAS', 3);
define('EVENTO_PROGRAMASDEPORIVOS', 2);

//Tipo estatus de inscripcion al evento
define('EVENTO_INSCRIPCION_ACTIVA', 1);
define('EVENTO_INSCRIPCION_CANCELADA', 2);
define('EVENTO_INSCRIPCION_SUSPENDIDA', 3);
define('EVENTO_INSCRIPCION_IMPARTIDA', 4);

// Tipos de socios
define('ROL_CLIENTE_SOCIO', 0);
define('ROL_CLIENTE_TITULAR', 1);
define('ROL_CLIENTE_COTITULAR', 2);
define('ROL_CLIENTE_HIJOMAYOR', 3);
define('ROL_CLIENTE_HIJOMENOR', 4);
define('ROL_CLIENTE_BEBE', 5);
define('ROL_CLIENTE_NINGUNO', 9);
define('ROL_CLIENTE_PADRE_MADRE', 10);
define('ROL_CLIENTE_NIETO_SOBRINO', 11);
define('ROL_CLIENTE_COTITULAR_GRUPAL', 12);
define('ROL_CLIENTE_AGREGADO', 17);
define('ROL_CLIENTE_2X1', 18);
define('ROL_CLIENTE_PT', 19);

// Tipos clientes
define('TIPO_CLIENTESOCIO', 1);
define('TIPO_CLIENTEEXTERNO', 2);
define('TIPO_CLIENTEEMPLEADO', 3);

define('INVOLUCRADO_PROPIETARIO', 1);
define('INVOLUCRADO_VENDEDOR', 2);
define('INVOLUCRADO_BENEFICIARIO', 3);
define('TIPOESTAUSSOCIOBAJA', 82);

// Precios
define('PRECIO_PUBLICOGENERAL', 9);

// Movimientos
define('MOVIMIENTO_PENDIENTE', 65);
define('MOVIMIENTO_PAGADO', 66);
define('MOVIMIENTO_CANCELADO', 67);
define('MOVIMIENTO_FACTURACANCELADA', 69);
define('MOVIMIENTO_EXCEPCION_PAGO', 70);
define('MOVIMIENTO_EN_TRAMITE', 112);
define('MOVIMIENTO_DEVOLUCIONCHEQUE', 127);
define('MOVIMIENTO_DESCUENTO_FACULTAMIENTO', 665);
define('MOVIMIENTO_ESPERANDO_REVISION', 666);
define('MOVIMIENTO_PAGO_VIA_NOMINA', 667);

define('MOVIMIENTO_TIPO_MEMBRESIA', 46);
define('MOVIMIENTO_TIPO_MANTENIMIENTO', 47);
define('MOVIMIENTO_TIPO_OTROS_INGRESOS', 48);
define('MOVIMIENTO_TIPO_REINGRESO_SOCIO', 40);
define('MOVIMIENTO_TIPO_CITA', 663);
define('MOVIMIENTO_TIPO_CREDENCIAL', 664);
define('MOVIMIENTO_TIPO_TRASPASO', 420);
define('MOVIMIENTO_TIPO_LOCKER', 50);
define('MOVIMIENTO_TIPO_EVENTO', 100);
define('MOVIMIENTO_TIPO_CLASES_PERSONALIZADAS', 520);
define('MOVIMIENTO_TIPO_AMPLIACION_MEMBRESIA', 488);

define('ESQUEMA_PAGO_TRASPASO', 5);
define('ESQUEMA_PAGO_CONTADO', 1);
define('ESQUEMA_PAGO_CARGOAUTOMATICO', 2);
define('ESQUEMA_PAGO_ANUAL', 3);
define('ESQUEMA_PAGO_REACTIVACION', 13);
define('ESQUEMA_MENSUAL', 6);
define('ESQUEMA_PREVENTA', 9);
define('ESQUEMA_REINSIDENTE', 10);
define('ESQUEMA_DESCUENTO_ANUAL', 11);
define('ESQUEMA_ELITE', 14);

define('PERSONA_FISICA', 44);
define('PERSONA_MORAL', 45);

//Opciones membresias
define('TIPO_MESESAUSENCIA', 1);
define('TIPO_NUMEROINTEGRANTES', 2);
define('TIPO_MESESGRATIS', 3);
define('TIPO_ADULTOS', 4);
define('TIPO_AGREGADOS', 5);
define('TIPO_LIMITE_INICIO_MTTO', 10);
define('TIPO_DESC_VENTA_CA', 11);

define('MEMBRSIA_INDIVIDUAL', 1);

//Tipo de eventos
define('TIPO_EVENTO_DEPORTIVO', 1);
define('TIPO_EVENTO_PROGRAMA', 2);
define('TIPO_EVENTO_CLASE', 3);
define('TIPO_EVENTO_CURSOVERANO', 4);
define('TIPO_EVENTO_PARTYKIDZ', 6);

//Tipos de Documentos
define('CONCEPTO_CAMBIOMEMBRESIA', 'Cambio_de_Membresía');
define('CONCEPTO_CANCELACIONMEMBRESIA', 'Cancelacion_Membresía');
define('CONCEPTO_INVITADOMEMBRESIA', 'Liberacion_Responsabilidad_Invitado');
define('CONCEPTO_REACTIVACIONMEMBRESIA', 'Reactivacion_de_Membresía');
define('CONCEPTO_TRANSFERENCIAMEMBRESIA', 'Transferencia_Clubes');
define('CONCEPTO_TRASPASOMEMBRESIA', 'Traspaso_Membresía');
define('TIPO_ACUERDO_USO_LCOKER', 8);
define('TIPO_ANALISISFEELHEALTHY', 720);
define('TIPO_AUTORIZACION_MEDICA', 21);
define('TIPO_CAMBIOCUOTAMTTO', 713);
define('TIPO_CAMBIOMEMBRESIA', 10);
define('TIPO_CANCELACIONMEMBRESIA', 13);
define('TIPO_CLUB_ALTERNO', 23);
define('TIPO_CONFIRMACION_REQUISITOS_MEMBRESIA', 739);
define('TIPO_COTIZACIONMEMBRESIA', 7);
define('TIPO_DOCUMENTO_ACEPTACION_INTERESADO', 736);
define('TIPO_DOCUMENTO_ACUERDO_DAC', 700);
define('TIPO_DOCUMENTO_ACUSE_RECIBO_REGLAMENTO', 731);
define('TIPO_DOCUMENTO_ADMISION_TARJETA_PREPAGO', 717);
define('TIPO_DOCUMENTO_ADMISIONMEMBRESIA', 1);
define('TIPO_DOCUMENTO_ALTA_CARGO_AUTOMATICO', 17);
define('TIPO_DOCUMENTO_ALTA_INTEGRANTE', 12);
define('TIPO_DOCUMENTO_AMONESTACION_MEMBRESIA', 732);
define('TIPO_DOCUMENTO_ANEXO_BENEFICIARIOS_MAPFRE', 721);
define('TIPO_DOCUMENTO_ANEXO_PART_TIME', 4);
define('TIPO_DOCUMENTO_AUSENCIA', 22);
define('TIPO_DOCUMENTO_AUTORIZACION_DIGITAL', 723);
define('TIPO_DOCUMENTO_BAJA_CARGO_AUTOMATICO', 18);
define('TIPO_DOCUMENTO_BAJA_MEMBRESIA', 733);
define('TIPO_DOCUMENTO_BAJASOCIO', 9);
define('TIPO_DOCUMENTO_CARTA_CONFIRMACION', 739);
define('TIPO_DOCUMENTO_CONTRATO_LAVANDERIA', 745);
define('TIPO_DOCUMENTO_DAC', 730);
define('TIPO_DOCUMENTO_DAC_TARJETA_PREPAGO', 718);
define('TIPO_DOCUMENTO_FIRMADO', 707);
define('TIPO_DOCUMENTO_FOLLETO_IRL', 744);
define('TIPO_DOCUMENTO_FOLLETO_MAPFRE', 722);
define('TIPO_DOCUMENTO_FUNDADOR', 710);
define('TIPO_DOCUMENTO_IRL', 742);
define('TIPO_DOCUMENTO_LIBERACION_CLASE_PRUEBA', 737);
define('TIPO_DOCUMENTO_LIBERACION_RESPONSABILIDAD', 711);
define('TIPO_DOCUMENTO_LIBERACION_RESPONSABILIDAD_DOCUMENTOS_FALTANTES', 740);
define('TIPO_DOCUMENTO_PENDIENTE', 706);
define('TIPO_DOCUMENTO_RECIBOCREDENCIAL', 708);
define('TIPO_DOCUMENTO_REGLAMENTO_INSTALACIONES', 2);
define('TIPO_DOCUMENTO_REGRESOAUSENCIA', 6);
define('TIPO_DOCUMENTO_REINGRESO_INTEGRANTE', 14);
define('TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD', 714);
define('TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_GRUPAL', 716);
define('TIPO_DOCUMENTO_RESPONSIVA_MENORES_EDAD_SOBRINO', 715);
define('TIPO_DOCUMENTO_SOCIOMANIA', 703);
define('TIPO_DOCUMENTO_USOALTERNO', 23);
define('TIPO_EXAMENMEDICOFEELHEALTHY', 719);
define('TIPO_INVITADOMEMBRESIA', 19);
define('TIPO_MTTOANUAL', 712);
define('TIPO_REACTIVACIONMEMBRESIA', 15);
define('TIPO_RECIBOCREDENCIAL', 708);
define('TIPO_TRANSFERENCIAMEMBRESIA', 16);
define('TIPO_TRASPASOMEMBRESIA', 11);

//TABLAS
define('TBL_ACCESOPERSONAHOST', 'accesopersonahost');
define('TBL_ACTIVIDADASISTENCIA', 'actividadasistencia');
define('TBL_ACTIVIDADASISTENCIAHISTORICO', 'actividadasistenciahistorico');
define('TBL_ACTIVIDADDEPORTIVA', 'actividaddeportiva');
define('TBL_ACTIVIDADDEPORTIVAENTORNO', 'actividaddeportivaentorno');
define('TBL_ACTIVIDADDEPORTIVAEQUIPAMIENTO', 'actividaddeportivaequipamiento');
define('TBL_ACTIVIDADDEPORTIVAFOTOGRAFIA', 'actividaddeportivafotografia');
define('TBL_ACTIVIDADDEPORTIVALESION', 'actividaddeportivalesion');
define('TBL_ACTIVIDADDEPORTIVAMUSCULO', 'actividaddeportivamusculo');
define('TBL_ACTIVIDADDEPORTIVAPROVEDOR', 'actividaddeportivaprovedor');
define('TBL_ACTIVIDADDEPORTIVAVIDEO', 'actividaddeportivavideo');
define('TBL_AGENDA', 'agenda');
define('TBL_AGENDAACTIVIDAD', 'agendaactividad');
define('TBL_AGENDAACTIVIDADPARTICIPANTE', 'agendaactividadparticipante');
define('TBL_APLICACION', 'aplicacion');
define('TBL_BANCO', 'banco');
define('TBL_CATALOGO_ENFERMEDADES', 'catalogoenfermedades');
define('TBL_CATALOGOS', 'catalogos');
define('TBL_CATEGORIA', 'categoria');
define('TBL_CATEGORIAAPP', 'categoriasapp');
define('TBL_CATEGORIADEPORTIVA', 'categoriadeportiva');
define('TBL_CATEGORIAPRODUCTONOCONFORME', 'categoriaproductonoconforme');
define('TBL_CATEGORIARESPONSABLE', 'categoriaresponsable');
define('TBL_CATEGORIAREVISOR', 'categoriarevisor');
define('TBL_CATEGORIATIPOPRODUCTO', 'categoriatipoproducto');
define('TBL_CERTIFICADO', 'certficado');
define('TBL_CERTIFICADOPERSONA', 'certificadopersona');
define('TBL_CERTIFICADOPRODUCTO', 'certificadoproducto');
define('TBL_CIRCUITO', 'circuito');
define('TBL_CITA', 'cita');
define('TBL_CITAGLUCOSA', 'citaglucosa');
define('TBL_CLIENTECOMPROBANTE', 'clientecomprobante');
define('TBL_CODIGOPOSTAL', 'codigopostal');
define('TBL_COLONIA', 'colonia');
define('TBL_COMISION', 'comision');
define('TBL_COMISIONCONCEPTO', 'comisionconcepto');
define('TBL_COMISIONDETALLE', 'comisiondetalle');
define('TBL_COMISIONMOVIMIENTO', 'comisionmovimiento');
define('TBL_COMISIONNETO', 'comisionneto');
define('TBL_COMISIONPERIODO', 'comisionperiodo');
define('TBL_COMISIONREGLA', 'comisionregla');
define('TBL_COMISIONREGLADETALLE', 'comisionregladetalle');
define('TBL_COMPROBANTEDOCUMENTO', 'comprobantedocumento');
define('TBL_COMUNIDAD', 'comunidad');
define('TBL_COMUNIDADASISTENCIA', 'comunidadasistencia');
define('TBL_COMUNIDADEVENTO', 'comunidadevento');
define('TBL_COMUNIDADFOTOGRAFIA', 'comunidadfotografia');
define('TBL_COMUNIDADNOTICIAS', 'comunidadnoticias');
define('TBL_COMUNIDADNOTICIASTIPO', 'comunidadnoticiastipo');
define('TBL_COMUNIDADREGISTRO', 'comunidadregistro');
define('TBL_COMUNIDADSESTATUSASISTENCIA', 'comunidadestatusasistencia');
define('TBL_COMUNIDADVIDEO', 'comunidadvideo');
define('TBL_CONSULTORIO', 'consultorio');
define('TBL_CONSULTORIODIA', 'consultoriodia');
define('TBL_CONSULTORIOEVALUACION', 'consultorioevaluacion');
define('TBL_CONSULTORIOHORARIO', 'consultoriohorario');
define('TBL_CONSULTORIORESPONSABLE', 'consultorioresponsable');
define('TBL_CONSULTORIOUN', 'consultorioun');
define('TBL_CONTACTO', 'contacto');
define('TBL_CONVENIO', 'convenio');
define('TBL_CONVENIOCONTACTO', 'conveniocontacto');
define('TBL_CONVENIODATOSTARJETA', 'conveniodatostarjeta');
define('TBL_CONVENIODETALLE', 'conveniodetalle');
define('TBL_CONVENIODETALLERESPONSABLE', 'conveniodetalleresponsable');
define('TBL_CONVENIOUN', 'convenioun');
define('TBL_CONVENIOUNMANTENIMIENTO', 'conveniounmantenimiento');
define('TBL_CONVENIOUNMEMBRESIA', 'conveniounmembresia');
define('TBL_COTIZACION', 'cotizacion');
define('TBL_CRMESTADISTICAS', 'crm_estadisticas.estadistica');
define('TBL_CUENTACONTABLE', 'cuentacontable');
define('TBL_CUENTAPRODUCTO', 'cuentaproducto');
define('TBL_CUESTIONARIO', 'cuestionario');
define('TBL_CUESTIONARIOPREGUNTA', 'cuestionariopregunta');
define('TBL_DESCUENTOMTTOPROMOCION', 'descuentomttopromocion');
define('TBL_DISPOSITIVOAPLICACION', 'dispositivoAplicacion');
define('TBL_DOCUMENTO', 'documento');
define('TBL_DOCUMENTOESTATUS', 'documentoestatus');
define('TBL_DOCUMENTOLIBERACION', 'membresiadocumentoliberacion');
define('TBL_DOCUMENTOPERSONA', 'documentopersona');
define('TBL_DOMICILIO', 'domicilio');
define('TBL_EMPLEADO', 'empleado');
define('TBL_EMPLEADOACTIVIDAD', 'empleadoactividad');
define('TBL_EMPLEADOBLOQUEADO', 'empleadobloqueado');
define('TBL_EMPLEADOPUESTO', 'empleadopuesto');
define('TBL_EMPRESA', 'empresa');
define('TBL_EMPRESAGRUPO', 'empresagrupo');
define('TBL_ENTORNO', 'entorno');
define('TBL_ENVIAMAILDOCUMENTO', 'enviamaildocumento');
define('TBL_ENVIAMAILDOCUMENTOESTATUS', 'enviamaildocumentoestatus');
define('TBL_ENVIAMAILDOCUMENTOORIGEN', 'enviamaildocumentoorigen');
define('TBL_EQUIPAMIENTO', 'equipamiento');
define('TBL_ESQUEMAFORMAPAGO', 'esquemaformapago');
define('TBL_ESQUEMAPAGO', 'esquemapago');
define('TBL_ESTADISTICAS', 'crm_estadisticas.estadisticatemporal');
define('TBL_ESTADO', 'estado');
define('TBL_EVALUACION', 'evaluacion');
define('TBL_EVALUACIONCUESTIONARIO', 'evaluacioncuestionario');
define('TBL_EVALUACIONPREVIA', 'evaluacionprevia');
define('TBL_EVENTO', 'evento');
define('TBL_EVENTOCALIFICACION', 'eventocalificacion');
define('TBL_EVENTOCATEGORIA', 'eventocategoria');
define('TBL_EVENTOCOMUNIDAD', 'eventocomunidad');
define('TBL_EVENTOFECHA', 'eventofecha');
define('TBL_EVENTOFECHACOMISION', 'eventofechacomision');
define('TBL_EVENTOGRUPO', 'eventogrupo');
define('TBL_EVENTOGRUPOCATEGORIA', 'eventogrupocategoria');
define('TBL_EVENTOGRUPOFOTOGRAFIA', 'eventogrupofotografia');
define('TBL_EVENTOGRUPORESPONSABLE', 'eventogruporesponsable');
define('TBL_EVENTOINSCRIPCION', 'eventoinscripcion');
define('TBL_EVENTOINVOLUCRADO', 'eventoinvolucrado');
define('TBL_EVENTOMOVIMIENTO', 'eventomovimiento');
define('TBL_EVENTOPARTICIPANTE', 'eventoparticipante');
define('TBL_EVENTOPERSONACANCELAR', 'eventopersonacancelar');
define('TBL_EVENTOPUESTOCOMISION', 'eventopuestocomision');
define('TBL_EVENTOUN', 'eventoun');
define('TBL_EVENTOUNCAPACIDAD', 'eventouncapacidad');
define('TBL_EVENTOUNCATEGORIA', 'eventouncategoria');
define('TBL_EVENTOUNCOORDINADOR', 'eventouncoordinador');
define('TBL_EVENTOUNENTREGA', 'eventounentrega');
define('TBL_EVENTOUNPUESTOEXCEPCION', 'eventounpuestoexcepcion');
define('TBL_EVENTOUNTALLA', 'eventountalla');
define('TBL_EXPEDIENTESVALIDADOS', 'expedientesvalidados');
define('TBL_FACTURA', 'factura');
define('TBL_FACTURACFDI', 'facturaCFDi');
define('TBL_FACTURACFDIURL', 'facturacfdiurl');
define('TBL_FACTURACORTECAJA', 'facturacortecaja');
define('TBL_FACTURADETALLE', 'facturadetalle');
define('TBL_FACTURAMOVIMIENTO', 'facturamovimiento');
define('TBL_FACTURANOTACREDITO', 'finanzasnotacredito');
define('TBL_FACTURANOTACREDITOCFDI', 'finanzasNotaCreditoCFDi');
define('TBL_FACTURANOTACREDITOCONCEPTO', 'finanzasnotacreditoconcepto');
define('TBL_FACTURANOTACREDITODETALLE', 'finanzasnotacreditodetalle');
define('TBL_FACTURANOTACREDITOMOVIMIENTOS', 'finanzasnotacreditomovimientos');
define('TBL_FACTURANOTACREDITOTIPO', 'finanzasnotacreditotipo');
define('TBL_FACTURAVALIDACION', 'facturavalidacion');
define('TBL_FACULTAMIENTO', 'facultamiento');
define('TBL_FACULTAMIENTOMOVIMIENTO', 'facultamientomovimiento');
define('TBL_FACULTAMIENTOPERIODO', 'facultamientoperiodo');
define('TBL_FINANZACONCESIONARIOCARGO', 'finanzasconcesionarioscargos');
define('TBL_FINANZACONCESIONARIOHISTORICO', 'finanzasconcesionarioshistorico');
define('TBL_FINANZASCONCESIONARIOSUN', 'finanzasconcesionariosun');
define('TBL_FINANZASDEPOSITOSREFERENCIADOS', 'finanzasdepositosreferenciados');
define('TBL_FINANZASNOTACREDITO', 'finanzasnotacredito');
define('TBL_FINANZASNOTACREDITOCFDI', 'finanzasnotacreditocfdi');
define('TBL_FINANZASNOTACREDITOCFDIURL', 'finanzasnotacreditocfdiurl');
define('TBL_FINANZASRECIBOPROVISIONALCORTECAJA', 'finanzasreciboprovisionalcortecaja');
define('TBL_FINANZASREFERENCIASEXTERNAS', 'finanzasreferenciasexternas');
define('TBL_FORMAPAGO', 'formapago');
define('TBL_FORMULA', 'formula');
define('TBL_FORMULAANALISIS', 'formulaanalisis');
define('TBL_FOTO', 'foto');
define('TBL_HEALTHYAUTORIZACIONMEDICA', 'healthyautorizacionmedica');
define('TBL_HEALTHYEQUIVALENTES', 'healthyequivalentes');
define('TBL_HEALTHYGLUCEMIACONFIGURACION', 'healthyglucemiaconfiguracion');
define('TBL_HEALTHYGLUCOSASOCIO', 'healthyglucosasocio');
define('TBL_HEALTHYPROTOCOLO', 'healthyprotocolo');
define('TBL_HISTORICOPASSWORD', 'historicopassword');
define('TBL_HORARIOSENLACE', 'horariosenlace');
define('TBL_HOSTACTIVIDAD', 'hostactividad');
define('TBL_HOSTCATALOGOACTIVIDAD', 'hostcatalogoactividades');
define('TBL_HOSTCONFIGURACION', 'hostconfiguracion');
define('TBL_HOSTEVENTOACTIVIDADPERSONA', 'hosteventoactividadpersona');
define('TBL_HOSTEVENTOUNACTIVIDAD', 'hosteventounactividad');
define('TBL_HOSTPERSONA', 'hostpersona');
define('TBL_HOSTPERSONACANCELAR', 'hostpersonacancelar');
define('TBL_HOSTPERSONACOMENTARIO', 'hostpersonacomentario');
define('TBL_HOSTPERSONAESTATUS', 'hostpersonaestatus');
define('TBL_HUELLA', 'huella');
define('TBL_INSTALACION', 'instalacion');
define('TBL_INSTALACIONACTIVIDAD', 'instalacionactividad');
define('TBL_INSTALACIONACTIVIDADPROGRAMADA', 'instalacionactividadprogramada');
define('TBL_INSTALACIONACTIVIDADPROGRAMADACOMISION', 'instalacionactividadprogramadacomision');
define('TBL_INSTALACIONEQUIPAMIENTO', 'instalacionequipamiento');
define('TBL_INVITADO', 'invitado');
define('TBL_INVITADOCONFIGURACION', 'invitadoconfiguracion');
define('TBL_INVITADOESPECIAL', 'invitadoespecial');
define('TBL_INVITADOESPECIALUN', 'invitadoespecialun');
define('TBL_INVITADOPERSONA', 'invitadopersona');
define('TBL_ACCESOALTACRM', 'accesoaltacrm');
define('TBL_KIOSCOAUDITORIABUSQUEDA', 'kioscoauditoriabusqueda');
define('TBL_KIOSCOBITACORA', 'kioscobitacora');
define('TBL_KIOSCOCATEGORIA', 'kioscocategoria');
define('TBL_KIOSCOCATEGORIAEMPLEADO', 'kioscocategoriaempleado');
define('TBL_KIOSCOCATEGORIAEMPRESA', 'kioscocategoriaempresa');
define('TBL_KIOSCOCATEGORIAPUESTO', 'kioscocategoriapuesto');
define('TBL_KIOSCOCATEGORIAUNBEEPER', 'kioscocategoriaunbeeper');
define('TBL_KIOSCOGENERAL', 'kioscogeneral');
define('TBL_KIOSCOGENERALDOMINIOSVALIDOS', 'kioscogeneraldominiosinvalidos');
define('TBL_KIOSCOPERSONA', 'kioscopersona');
define('TBL_KIOSCOQUEJAEMPLEADO', 'kioscoquejaempleado');
define('TBL_KIOSCOTIPOCATEGORIA', 'kioscotipocategoria');
define('TBL_LAVANDERIA', 'lavanderia');
define('TBL_LAVANDERIADETALLE', 'lavanderiadetalle');
define('TBL_LAVANDERIAMOVIMIENTO', 'lavanderiamovimiento');
define('TBL_LEALTADCANJE', 'lealtadcanje');
define('TBL_LEALTADDETALLE', 'lealtaddetalle');
define('TBL_LEALTADRESUMEN', 'lealtadresumen');
define('TBL_LEALTADTIPOEVENTO', 'lealtadtipoevento');
define('TBL_LESION', 'lesion');
define('TBL_LESIONMUSCULO', 'lesionmusculo');
define('TBL_LOCALIDAD', 'localidad');
define('TBL_LOCKER', 'locker');
define('TBL_LOCKERESPERA', 'lockerespera');
define('TBL_LOCKERLLAVE', 'lockerllave');
define('TBL_LOCKERMOVIMIENTO', 'lockermovimiento');
define('TBL_LOCKERPERSONA', 'lockerpersona');
define('TBL_LOG', 'log');
define('TBL_LOGAGENDAACTIVIDAD', 'logagendaactividad');
define('TBL_LOGCATEGORIA', 'logcategoria');
define('TBL_LOGMAILUSUARIOSINACTIVOS', 'logmailusuariosinactivos');
define('TBL_LOGPROCESODIARIO', 'logprocesodiario');
define('TBL_LOGPROCESODIARIOMONITOR', 'logprocesodiariomonitor');
define('TBL_MAIL', 'mail');
define('TBL_MAIL_CODIGO', 'mail_codigo');
define('TBL_MANTENIMIENTO', 'mantenimiento');
define('TBL_MANTENIMIENTOCLIENTE', 'mantenimientocliente');
define('TBL_MANTENIMIENTOCONVERSION', 'mantenimientoconversion');
define('TBL_MANTENIMIENTOHORARIO', 'mantenimientohorario');
define('TBL_MANTENIMIENTOUNACCESO', 'mantenimientounacceso');
define('TBL_MANTENIMIENTOUNADICIONAL', 'mantenimientounadicional');
define('TBL_MANTENIMIENTOUNADICIONALOPCIONES', 'mantenimientounadicionalopciones');
define('TBL_MANTENIMIENTOUNHORARIO', 'mantenimientounhorario');
define('TBL_MANTENIMIENTOUNPERMITIDO', 'mantenimientounpermitido');
define('TBL_MEDICOUNIVERSITARIO', 'medicouniversitario');
define('TBL_MEMBRESIA', 'membresia');
define('TBL_MEMBRESIAAMPLIACION', 'membresiaampliacion');
define('TBL_MEMBRESIAATRIBUTOS', 'membresiaatributos');
define('TBL_MEMBRESIACANCELACION', 'membresiacancelacion');
define('TBL_MEMBRESIACATEGORIA', 'membresiacategoria');
define('TBL_MEMBRESIACESION', 'membresiacesion');
define('TBL_MEMBRESIACESIONPARTICIPANTE', 'membresiacesionparticipante');
define('TBL_MEMBRESIACONFIGMTTO', 'membresiaconfigmtto');
define('TBL_MEMBRESIACONFIGURACION', 'membresiaconfiguracion');
define('TBL_MEMBRESIACONFIGURACIONREACTIVACION', 'membresiaconfiguracionreactivacion');
define('TBL_MEMBRESIACONFIGURACIONTIPOCONVENIO', 'membresiaconfiguraciontipoconvenio');
define('TBL_MEMBRESIADESCUENTOMTTO', 'membresiadescuentomtto');
define('TBL_MEMBRESIADESCUENTOMTTOHISTORICO', 'membresiadescuentomttohistorico');
define('TBL_MEMBRESIADIGITAL', 'membresiadigital');
define('TBL_MEMBRESIADOCUMENTO', 'membresiadocumento');
define('TBL_MEMBRESIAEXTRAS', 'membresiaextras');
define('TBL_MEMBRESIAFIDELIDAD', 'membresiafidelidad');
define('TBL_MEMBRESIAINVOLUCRADO', 'membresiainvolucrado');
define('TBL_MEMBRESIAOPCIONES', 'membresiaopciones');
define('TBL_MEMBRESIAPROMOMTTO', 'membresiapromomtto');
define('TBL_MEMBRESIAREACTIVACION', 'membresiareactivacion');
define('TBL_MEMBRESIAREACTIVACIONMOVIMIENTO', 'membresiareactivacionmovimiento');
define('TBL_MEMBRESIAREACTIVACIONPARTICIPANTES', 'membresiareactivacionparticipantes');
define('TBL_MEMBRESIATIPOSOCIO', 'membresiatiposocio');
define('TBL_MEMBRESIATRASPASO', 'membresiatraspaso');
define('TBL_MEMBRESIAUNALTERNO', 'membresiaunalterno');
define('TBL_MEMBRESIAVENTA', 'membresiaventa');
define('TBL_METAACCION', 'metaaccion');
define('TBL_METACOMPROMISO', 'metacompromiso');
define('TBL_METAPRESUPUESTO', 'metapresupuesto');
define('TBL_METAPROSPECTOS', 'metaprospectos');
define('TBL_MOTIVOCORRECCION', 'motivocorreccion');
define('TBL_MOVIMIENTO', 'movimiento');
define('TBL_MOVIMIENTOAJUSTE', 'movimientoajuste');
define('TBL_MOVIMIENTOAJUSTERESPUESTA', 'movimientoajusterespuesta');
define('TBL_MOVIMIENTOCTACONTABLE', 'movimientoctacontable');
define('TBL_MOVIMIENTODESCUENTOMTTO', 'movimientodescuentomtto');
define('TBL_MOVIMIENTODEVENGADO', 'movimientodevengado');
define('TBL_MOVIMIENTOPARCIALIDADES', 'movimientoparcialidades');
define('TBL_MOVIMIENTOPRORRATEO', 'movimientoprorrateo');
define('TBL_MTTODISPONIBLE', 'mttodisponible');
define('TBL_MUNICIPIO', 'municipio');
define('TBL_MUSCULO', 'musculo');
define('TBL_NOMBREDISPOSITIVO', 'nombredispositivo');
define('TBL_NOTICIAS', 'noticias');
define('TBL_NOTICIASCATEGORIA', 'noticiascategoria');
define('TBL_NOTICIASCATEGORIARESPONSABLE', 'noticiascategoriareponsable');
define('TBL_NOTICIASFOTOGRAFIA', 'noticiasfotografia');
define('TBL_NOTICIASUN', 'noticiasun');
define('TBL_NOTIFICACIONESCLUBES', 'notificacionesclubes');
define('TBL_NOTIFICACIONESCOMUNIDADES', 'notificacionescomunidades');
define('TBL_NOTIFICACIONESNOTICIAS', 'notificacionesnoticias');
define('TBL_NOTIFICACIONESTOKENSDISPOSITIVO', 'notificacionestokendispositivo');
define('TBL_OBJETO', 'objeto');
define('TBL_OPERADOR', 'operador');
define('TBL_ORIGENPROSPECTO', 'origenprospecto');
define('TBL_ORIGENTRAMITE', 'origentramite');
define('TBL_PAQUETE', 'paquete');
define('TBL_PAQUETEAPLICAFIDELIDAD', 'paqueteaplicafidelidad');
define('TBL_PAQUETEAPLICAMTTO', 'paqueteaplicamtto');
define('TBL_PAQUETEAPLICADESCUENTOMTTO', 'paqueteaplicadescuentomtto');
define('TBL_PAQUETEBANCO', 'paquetebanco');
define('TBL_PAQUETECONFIGURACION', 'paqueteconfiguracion');
define('TBL_PAQUETEFORMAPAGO', 'paqueteformapago');
define('TBL_PAQUETEIMPACTO', 'paqueteimpacto');
define('TBL_PAQUETEMANTENIMIENTO', 'paquetemantenimiento');
define('TBL_PAQUETEMSI', 'paquetemsi');
define('TBL_PAQUETEPERIODO', 'paqueteperiodo');
define('TBL_PAQUETEPORCENTAJE', 'paqueteporcentaje');
define('TBL_PAQUETEPRODUCTO', 'paqueteproducto');
define('TBL_PAQUETERANGO', 'paqueterango');
define('TBL_PAQUETEUN', 'paqueteun');
define('TBL_PAQUETEVENDEDOR', 'paquetevendedor');
define('TBL_PARAMETRO', 'parametro');
define('TBL_PASE', 'pase');
define('TBL_PASECOMISION', 'pasecomision');
define('TBL_PASECONFIGURACION', 'paseconfiguracion');
define('TBL_PASECONFIGURACIONUN', 'paseconfiguracionun');
define('TBL_PASECONFIGURACIONUNVISITA', 'paseconfiguracionunvisita');
define('TBL_PASECONFIGURACIONUNVISITAHORARIO', 'paseconfiguracionunvisitahorario');
define('TBL_PASEINVOLUCRADO', 'paseinvolucrado');
define('TBL_PASEMOVIMIENTO', 'pasemovimiento');
define('TBL_PERIODOMSI', 'periodomsi');
define('TBL_PERIODOVACACIONAL', 'periodovacacional');
define('TBL_PERMISO_UN', 'permisoun');
define('TBL_PERMISOAPLICAPUESTOS', 'permisoaplicapuestos');
define('TBL_PERMISOPUESTO', 'permisopuesto');
define('TBL_PERMISOUSARIO', 'permisousuario');
define('TBL_PERSONA', 'persona');
define('TBL_PERSONABLOQUEO', 'personabloqueo');
define('TBL_PERSONACATEGORIA5W', 'personacategoria5w');
define('TBL_PERSONACONCESIONARIO', 'personaconcesionario');
define('TBL_PERSONADOCUMENTO', 'personadocumento');
define('TBL_PERSONAENFERMEDAD', 'personaenfermedad');
define('TBL_PERSONAENTRENADOR', 'personaentrenador');
define('TBL_PERSONAHEALTHY', 'personahealthy');
define('TBL_PERSONAHEALTHYCANCELAR', 'personahealthycancelar');
define('TBL_PREGUNTA', 'pregunta');
define('TBL_PREGUNTAOPCION', 'preguntaopcion');
define('TBL_PREGUNTARELACIONADA', 'preguntarelacionada');
define('TBL_PRODUCTO', 'producto');
define('TBL_PRODUCTOACTIVIDADDEPORTIVA', 'productoactividaddeportiva');
define('TBL_PRODUCTOCLIENTE', 'productocliente');
define('TBL_PRODUCTODESCUENTO', 'productodescuento');
define('TBL_PRODUCTOESQUEMAPAGO', 'productoesquemapago');
define('TBL_PRODUCTOGRUPO', 'productogrupo');
define('TBL_PRODUCTOGRUPOALIAS', 'productogrupoalias');
define('TBL_PRODUCTOLEALTADDISPONIBILIDAD', 'productolealtaddisponibilidad');
define('TBL_PRODUCTOMANTENIMIENTO', 'productomantenimiento');
define('TBL_PRODUCTOPRECIO', 'productoprecio');
define('TBL_PRODUCTOPUNTOS', 'productopuntos');
define('TBL_PRODUCTOUN', 'productoun');
define('TBL_PROGRAMA', 'programa');
define('TBL_PROMOMTTOUN', 'promomttoun');
define('TBL_PROMOMTTOUNROLCLIENTE', 'promomttounrolcliente');
define('TBL_PROSPECTOACTIVIDAD', 'prospectoactividad');
define('TBL_PROSPECTOEMPLEADO', 'prospectoempleado');
define('TBL_PROSPECTOFOLIO', 'prospectofolio');
define('TBL_PROSPECTOPAQUETE', 'prospectopaquete');
define('TBL_PROSPECTOREFERENCIA', 'prospectoreferencia');
define('TBL_PROSPECTOVENDEDOR', 'prospectovendedor');
define('TBL_PROSPECTOVENDEDORHISTORICO', 'prospectovendedorhistorico');
define('TBL_PROVEDOR', 'provedor');
define('TBL_PROVEEDOR', 'proveedor');
define('TBL_PROVEEDOREVALUADOREMPLEADO', "proveedorevaluadorempleado");
define('TBL_PROVEEDOREVALUADORPUESTO', "proveedorevaluadorpuesto");
define('TBL_PROVEEDORSATISFACCION', "proveedorsatisfaccion");
define('TBL_PROVEEDORSATISFACCIONEVALUACION', "proveedorsatisfaccionevaluacion");
define('TBL_PUESTO', 'puesto');
define('TBL_REFERENCIAORIGEN', 'referenciaorigen');
define('TBL_REFERENCIAORIGENUN', 'referenciaorigenun');
define('TBL_REFERENCIASEXTERNAS', 'finanzasbusquedareferencias');
define('TBL_REGION', 'region');
define('TBL_REGISTROACCESO', 'registroacceso');
define('TBL_RESPUESTA', 'respuesta');
define('TBL_RUTINA', 'rutina');
define('TBL_RUTINAACTIVIDADDEPORTIVA', 'rutinaactividaddeportiva');
define('TBL_RUTINADIA', 'rutinadia');
define('TBL_RUTINASEMANA', 'rutinasemana');
define('TBL_RUTINASERIE', 'rutinaserie');
define('TBL_RUTINASERIEACTIVIDAD', 'rutinaserieactividad');
define('TBL_SATISFACCIONATENCION', 'satisfaccionatencion');
define('TBL_SATISFACCIONATENCIONEVALUACION', 'satisfaccionatencionevaluacion');
define('TBL_SATISFACCIONCONFIGURACION', 'satisfaccionconfiguracion');
define('TBL_SATISFACCIONEMPLEADOEVALUACION', 'satisfaccionempleadoevaluacion');
define('TBL_SATISFACCIONEVALUACION', 'satisfaccionevaluacion');
define('TBL_SATISFACCIONEVALUACIONAPLICADA', 'satisfaccionunevaluacionaplicada');
define('TBL_SATISFACCIONEVALUACIONCATEGORIA', 'satisfaccionevaluacioncategoria');
define('TBL_SATISFACCIONEVALUACIONPUESTO', 'satisfaccionevaluacionpuesto');
define('TBL_SATISFACCIONEVCATRESPONSABLE', 'satisfaccionevcatresponsable');
define('TBL_SATISFACCIONNIVELCALIFICACION', 'satisfaccionnivelcalificacion');
define('TBL_SATISFACCIONPREGUNTA', 'satisfaccionpregunta');
define('TBL_SATISFACCIONPREGUNTACATEGORIA', 'satisfaccionpreguntacategoria');
define('TBL_SATISFACCIONPREGUNTAEVALUACION', 'satisfaccionpreguntaevaluacion');
define('TBL_SATISFACCIONPREGUNTATIPO', 'satisfaccionpreguntatipo');
define('TBL_SATISFACCIONRESPUESTA', 'satisfaccionrespuesta');
define('TBL_SATISFACCIONUBICACION', 'satisfaccionubicacion');
define('TBL_SATISFACCIONUN', 'satisfaccionun');
define('TBL_SELECCIONUNIDADNEGOCIOMOVIMIENTO', 'seleccionunidadnegociomovimientos');
define('TBL_SOCIO', 'socio');
define('TBL_SOCIOAUSENCIA', 'socioausencia');
define('TBL_SOCIOBAJA', 'sociobaja');
define('TBL_SOCIODATOSTARJETA', 'sociodatostarjeta');
define('TBL_SOCIOEXPEDIENTE', 'socioexpediente');
define('TBL_SOCIOMANIA', 'sociomania');
define('TBL_SOCIOMANIAINVOLUCRADO', 'sociomaniainvolucrado');
define('TBL_SOCIOMANTENIMIENTO', 'sociomantenimiento');
define('TBL_SOCIOMENSAJE', 'sociomensaje');
define('TBL_SOCIOMENSAJEOPCION', 'sociomensajeopcion');
define('TBL_SOCIOPAGOMTTO', 'sociopagomtto');
define('TBL_SOCIOPRECIOMTTO', 'sociopreciomtto');
define('TBL_SOCIOUNEXTRA', 'sociounextra');
define('TBL_SOPORTEMTTOCONFIGURACION', 'soportemttoconfiguracion');
define('TBL_SOPORTEMTTORESPONSABLE', 'soportemttoresponsable');
define('TBL_TAGKIDZ', 'tagkidz');
define('TBL_TAGKIDZHISTORICO', 'tagkidzhistorico');
define('TBL_TALLA', 'talla');
define('TBL_TELEFONO', 'telefono');
define('TBL_TELEFONOINVALIDO', 'telefonoinvalido');
define('TBL_TIPO_INVITADOESPECIAL', 'tipoinvitadoespecial');
define('TBL_TIPOACCESO', 'tipoacceso');
define('TBL_TIPOACTIVIDAD', 'tipoactividad');
define('TBL_TIPOACTIVIDADESTATUS', 'tipoactividadestatus');
define('TBL_TIPOALTURALOCKER', 'tipoalturalocker');
define('TBL_TIPOAPLICACION', 'tipoaplicacion');
define('TBL_TIPOCLIENTE', 'tipocliente');
define('TBL_TIPOCOMISION', 'tipocomision');
define('TBL_TIPOCOMISIONPUESTOAUTORIZA', 'tipocomisionpuestoautoriza');
define('TBL_TIPOCOMPROBANTE', 'tipocomprobante');
define('TBL_TIPOCONTACTO', 'tipocontacto');
define('TBL_TIPOCONVENIO', 'tipoconvenio');
define('TBL_TIPODESCUENTO', 'tipodescuento');
define('TBL_TIPODEVENGADOPRODUCTO', 'tipodevengadoproducto');
define('TBL_TIPODIA', 'tipodia');
define('TBL_TIPODIASREACTIVACION', 'tipodiasreactivacion');
define('TBL_TIPODOCUMENTO', 'tipodocumento');
define('TBL_TIPODOCUMENTOEMPRESA', 'tipodocumentoempresa');
define('TBL_TIPODOMICILIO', 'tipodomicilio');
define('TBL_TIPOENTORNO', 'tipoentorno');
define('TBL_TIPOEQUIPAMIENTO', 'tipoequipamiento');
define('TBL_TIPOESTADOCIVIL', 'tipoestadocivil');
define('TBL_TIPOESTATUSCOMISION', 'tipoestatuscomision');
define('TBL_TIPOESTATUSEMPLEADO', 'tipoestatusempleado');
define('TBL_TIPOESTATUSEVENTOFECHA', 'tipoestatuseventofecha');
define('TBL_TIPOESTATUSFACTURA', 'tipoestatusfactura');
define('TBL_TIPOESTATUSINSCRIPCION', 'tipoestatusinscripcion');
define('TBL_TIPOESTATUSLOCKER', 'tipoestatuslocker');
define('TBL_TIPOESTATUSMEMBRESIA', 'tipoestatusmembresia');
define('TBL_TIPOESTATUSMOVIMIENTO', 'tipoestatusmovimiento');
define('TBL_TIPOESTATUSPASE', 'tipoestatuspase');
define('TBL_TIPOESTATUSSOCIO', 'tipoestatussocio');
define('TBL_TIPOESTATUSSOCIOMANIA', 'tipoestatussociomania');
define('TBL_TIPOEVENTO', 'tipoevento');
define('TBL_TIPOEVENTOCAPACIDAD', 'tipoeventocapacidad');
define('TBL_TIPOFIDELIDAD', 'tipofidelidad');
define('TBL_TIPOGLUCOSA', 'tipoglucosa');
define('TBL_TIPOHORARIO', 'tipohorario');
define('TBL_TIPOINVITADOESPECIAL', 'tipoinvitadoespecial');
define('TBL_TIPOINVOLUCRADO', 'tipoinvolucrado');
define('TBL_TIPOKIOSCOCATEGORIA', 'tipokioscocategoria');
define('TBL_TIPOKIOSCOESTATUS', 'tipokioscoestatus');
define('TBL_TIPOKIOSCOORIGEN', 'tipokioscoorigen');
define('TBL_TIPOKIOSCOPRIORIDAD', 'tipokioscoprioridad');
define('TBL_TIPOLOCKER', 'tipolocker');
define('TBL_TIPOMAIL', 'tipomail');
define('TBL_TIPOMEMBRESIA', 'tipomembresia');
define('TBL_TIPOMEMBRESIAATRIBUTO', 'tipomembresiaatributo');
define('TBL_TIPOMEMBRESIAEXTRAS', 'tipomembresiaextras');
define('TBL_TIPOMEMBRESIAOPCION', 'tipomembresiaopcion');
define('TBL_TIPOMES', 'tipomes');
define('TBL_TIPOMOVIMIENTO', 'tipomovimiento');
define('TBL_TIPOMTTOCONFIGURACION', 'tipomttoconfiguracion');
define('TBL_TIPONOTACREDITO', 'tiponotacredito');
define('TBL_TIPONOTACREDITOCONCEPTOS', 'tiponotacreditoconceptos');
define('TBL_TIPOPAQUETE', 'tipopaquete');
define('TBL_TIPOPASE', 'tipopase');
define('TBL_TIPOPASEACCESO', 'tipopaseacceso');
define('TBL_TIPOPASEINVOLUCRADO', 'tipopaseinvolucrado');
define('TBL_TIPOPRODUCTO', 'tipoproducto');
define('TBL_TIPOPROFESION', 'tipoprofesion');
define('TBL_TIPOPROMOMTTO', 'tipopromomtto');
define('TBL_TIPOQUEMAEMPLEADO', 'tipoquejaempleado');
define('TBL_TIPOQUINCENA', 'tipoquincena');
define('TBL_TIPOROLCLIENTE', 'tiporolcliente');
define('TBL_TIPORUTINANIVEL', 'tiporutinanivel');
define('TBL_TIPOSEXO', 'tiposexo');
define('TBL_TIPOTELEFONO', 'tipotelefono');
define('TBL_TIPOTITULOPERSONA', 'tipotitulopersona');
define('TBL_TIPOUNCAPACIDAD', 'tipouncapacidad');
define('TBL_TIPOUNIDADCAPACIDAD', 'tipounidadcapacidad');
define('TBL_TIPOUNIDADESCORPORATIVO', 'tipounidadescorporativo');
define('TBL_TIPOUNIDADMEDIDA', 'tipounidadmedida');
define('TBL_TMPACCESOFOTO', 'tmpaccesofoto');
define('TBL_TMPADEUDOMEMBRESIAS', 'tmpadeudomembresias');
define('TBL_TMPANUALIDADES', 'tmpanualidades');
define('TBL_TMPRESPUESTASSATISFACCION', 'tmprespuestassatisfaccion');
define('TBL_UN', 'un');
define('TBL_UNACCESORESUMEN', 'unaccesoresumen');
define('TBL_UNAFILIACION', 'unafiliacion');
define('TBL_UNCAPACIDAD', 'uncapacidad');
define('TBL_UNCONFIGURACIONFINANZAS', 'unconfiguracionfinanzas');
define('TBL_UNGERENTE', 'ungerente');
define('TBL_UNINSTALACION', 'uninstalacion');
define('TBL_UNSERVIDOR', 'unservidor');
define('TBL_UNSERVIDORLOCAL', 'unservidorlocal');
define('TBL_UNTELEFONO', 'untelefono');
define('TBL_UNTEMPORALIDADSEMANA', 'untemporalidadsemana');
define('TBL_UNTIPO', 'untipo');
define('TBL_USUARIOMENU', 'usuariomenu');
define('TBL_USUARIOS', 'usuarios');
define('TBL_USUARIOWEB', 'socios.usuarioweb');
define('TBL_VERIFICACIONRESPUESTA', 'verificacionrespuesta');
define('TBL_VIGENCIAREGISTRARAUTORIZRAAFORO', 'vigenciaregistrarautorizaraforo');
define('TBL_ZONAHORARIA', 'zonahoraria');
define('TBL_ZONAMUSCULAR', 'zonamuscular');

define('MAGIC_WORD', '#D4nt3h3ll2017$');
define('MAGIC_WORD_WG', '#3ll1s1um2017$');
define('MAGIC_WORD_AFC', '#1m4g3dyn4m1cs$');

//categorias de ip
define('IP_CATEGORIA_PUBLICA', 1);

//tipo de servicio para ip
define('IP_TIPO_ACCESO', 2);
define('IP_TIPO_ACCESO_INVITADO', 7);

//clave para encriptar y desencriptar
define('CLAVE', 'p4ss0rd$');

// Tipo Producto
define('TIPO_PRODUCTO_MEMBRESIA', 1);
define('TIPO_PRODUCTO_MTTO', 2);
define('TIPO_PRODUCTO_EVENTO', 5);
define('TIPO_PRODUCTO_INVITADO', 7);
define('TIPO_PRODUCTO_PREMIO', 11);

//Periodo de facultamiento
define('FAC_MENSUAL', 1);
define('FAC_BIMESTRAL', 2);
define('FAC_SEMESTRAL', 3);
define('FAC_ANUAL', 4);

// Estatus Sociomania
define('ESTATUS_SOCIOMANIA_ACTIVA', 285);
define('ESTATUS_SOCIOMANIA_APLICADO', 284);
define('ESTATUS_SOCIOMANIA_CANCELADA', 286);
define('TIPO_SOCIOMANIA_NUEVO', 'Nuevo');
define('TIPO_SOCIOMANIA_APLICADO', 'Referido');
define('DESCUENTO_SOCIOMANIA', 288);

//Tipo comision
define('TIPO_COMISION_CLASEPERSONALIZADA', 6);
define('TIPO_COMISION_GERENTE_VENTAS', 2);
define('TIPO_COMISION_GERENTENACIONAL', 10);
define('TIPO_COMISION_PROGRAMADEPORTIVO', 9);
define('TIPO_COMISION_VENTA_VENDEDOR', 1);
define('TIPO_COMISION_VENTASCORPORATIVAS', 3);
define('TIPO_COMISION_CURSODEVERANO', 17);
define('TIPO_ESTATUSCOMISION_SINFACTURAR', 6);
define('TIPO_EVENTO_COMISIONEXTERNA', 10);
define('TIPO_EVENTO_COMISIONINTERNA', 9);

//Mantenimiento Default
define('MTTO_ALL_CLUBS', 62);
define('MTTO_ALLCLUB2_PROMOCION', 179);
define('MTTO_ALLCLUB_2X1', 135);
define('MTTO_ALLCLUB2_2X1', 223);
define('MTTO_ALLCLUB_2X1_NUEVA', 146);
define('MTTO_DEFAULT', 64);
define('MTTO_FUNDADOR', 81);
define('MTTO_ORO_UNICLUB', 86);

define('MTTO_PAREJA', 168);
define('MTTO_PAREJA2', 244);
define('MTTO_PAREJA3', 250);
define('MTTO_PAREJA_PROMO', 201);
define('MTTO_PAREJA_PROMO2', 247);
define('MTTO_PAREJA_PROMO_CORP', 215);
define('MTTO_PAREJA_PROMO_CORP2', 240);
define('MTTO_PAREJA_TRICLUB', 245);
define('MTTO_PAREJA_UNICLUB2', 196);

define('MTTO_PLATINO_ORO', 84);
define('MTTO_PLATINO_UNICLUB', 85);
define('MTTO_UNICLUB', 64);
define('MTTO_UNICLUB2_PROMOCION', 180);
define('MTTO_UNICLUB_2X1', 137);
define('MTTO_UNICLUB_2X1_FORANEO', 158);
define('MTTO_UNICLUB_2X1_ZONACENTRO', 141);
define('MTTO_UNICLUB_AGREGADO', 114);
define('MTTO_UNICLUB_GRUPAL_8', 156);
define('MTTO_UNICLUB_V2', 154);
define('MTTO_WEEKEND', 105);
define('MTTO_EVEN_ALL_FULL', 203);
define('MTTO_EVEN_ALL_MEDIO', 206);
define('MTTO_EVEN_UNI_FORANEO_FULL', 205);
define('MTTO_EVEN_UNI_FORANEO_MEDIO', 208);
define('MTTO_EVEN_UNI_FULL', 204);
define('MTTO_EVEN_UNI_MEDIO', 207);

//Estatus de Citas
define('ESTATUS_CITA_ATENDIDA', 3);
define('ESTATUS_CITA_CANCELADA', 2);
define('ESTATUS_CITA_NOASISTIO', 6);
define('ESTATUS_CITA_NOINTERESADO', 5);
define('ESTATUS_CITA_PENDIENTE', 1);
define('ESTATUS_CITA_VENCIDA', 4);
define('ESTATUS_CITAATENDIDA', 'Atendida');
define('EVALUACION_DAC', 10);
define('EVALUACION_ENCUESTA_INFORMATIVA', 23); //23
define('EVALUACION_ENCUESTA_INICIAL', 30); //36
define('EVALUACION_ENCUESTA_POST', 31);
define('EVALUACION_FEEL_HEALTHY', 22);
define('EVALUACION_FITNESSTEST', 4);
define('EVALUACION_NOPUBLICA', 0);
define('EVALUACION_PUBLICA', 1);
define('EVALUACION_SEGUIMIENTO_1', 28); //38
define('EVALUACION_SEGUIMIENTO_2', 29); //29
define('EVALUACION_VALORACION_NUTRICIONAL', 21);
define('TIPO_CITA_NOPROGRAMADA', 0);

//Tipo Domicilio
define('DOMICILIO_CASA', 42);
define('DOMICILIO_EMPRESA', 41);
define('DOMICILIO_OFICINA', 43);

//Tipo credencial
define('TIPO_EMPLEADO', 1);
define('TIPO_SOCIO', 2);
define('REPOSICION_CREDENCIAL_EMPLEADO', 172);
define('REPOSICION_CREDENCIAL_SOCIO', 171);

//Tipo estatus locker
define('ESTATUS_LOCKER_ACTIVO', 1);
define('ESTATUS_LOCKER_ASIGNADO', 116);
define('ESTATUS_LOCKER_CORTESIA', 487);
define('ESTATUS_LOCKER_ESPERA', 95);
define('ESTATUS_LOCKER_INACTIVO', 0);
define('ESTATUS_LOCKER_LIBRE', 94);
define('ESTATUS_LOCKER_ROTATIVO', 96);
define('ESTATUSLOCKERASIGNADO', 'Asignado');
define('ESTATUSLOCKERCANCELADO', 'Cancelado');
define('ESTATUSLOCKERLIBRE', 'Libre');
define('TIPO_LOCKER_VENCIDO', 20);

//Periodo de renta de lockers
define('PERIODO_ANUAL', 3);
define('PERIODO_SEMESTRAL', 4);

// Tipo de categoria
define('CATEGORIA_CARRERAS', 78);
define('CATEGORIA_EVTEMATICOS', 56);
define('CATEGORIA_LOCKER', 19);
define('CATEGORIA_MTTO', 2);
define('CATEGORIA_PROGRAMASDEP', 12);
define('CATEGORIA_SUMMERCAMP', 32);

//Estatus de pregunta en cuestionariopregunta
define('ESTATUS_PREGUNTA_ACTVO', 1);
define('ESTATUS_PREGUNTA_INACTVO', 0);
define('EVALUACION_AREASINTERES', 17);
define('TIPO_PREGUNTA_CALCULO', 'Calculo');

//Estatus de Factura
define('ESTATUS_FACTURA_CANCELADA', 110);
define('ESTATUS_FACTURA_DEVOLUCIONCHEQUE', 129);
define('ESTATUS_FACTURA_INTERCAMBIO', 30);
define('ESTATUS_FACTURA_PAGADA', 109);

//Tipo nota Credito
define('CAMBIO_CONCEPTO', 2);
define('CAMBIO_DATOSFISCALES', 1);
define('COBRANZA_ERRONEA', 3);

//Tipo concepto
define('MANTENIMIENTO', 1);
define('MEMBRESIA', 2);
define('LOCKER', 3);
define('PROGRAMA_DEPORTIVO', 4);
define('NUTRICION', 5);

//Rfc generico
define('RFC_GENERICO', 'XAXX010101000');
define('RFC_EXTRANJERO', 'XEXX010101000');

define('PRODUCTO_MEMBRESIACOLLEGE', 15);

//Tipo Referencia
define('REFERENCIA_TESORERIA', 1);
define('REFERENCIA_COBROSLIBRES', 2);

//Tipo Cargo concesionario
define('CARGO_CONCESIONARIO_FIJO', 2);

//Zona horaria
define('ID_ZONA_HORARIA_DEFAULT', 1);
define('ZONA_HORARIA_DEFAULT', 'America/Mexico_City');

//Tipo producto mantenimiento
define('TIPOPRODUCTO_MANTENIMIENTO', 2);

//Tipo estatus pase
define('TIPO_ESTATUS_PASE_GENERADO', 1);
define('TIPO_ESTATUS_PASE_AUTORIZADO', 2);
define('TIPO_ESTATUS_PASE_CANCELADO', 3);

//Tipo pase involucrado
define('TIPO_PASE_INVOLUCRADO_GENERO', 1);
define('TIPO_PASE_INVOLUCRADO_AUTORIZO', 2);
define('TIPO_PASE_INVOLUCRADO_CANCELO', 3);

//Tipo pase
define('TIPO_PASE_CORTESIA', 1);
define('TIPO_PASE_INVITADO', 2);
define('TIPO_PASE_PROMOCION', 3);

//Pregunta de sexo y edad para cita
define('PREGUNTA_EDAD', 276);
define('PREGUNTA_SEXO', 277);

//tipo de  responsable queja
define('TIPO_RESPONSABLE_QUEJA', 2);
define('TIPO_SOLICITANTE_QUEJA', 1);
define('TIPO_GESTOR_QUEJA', 3);
define('TIPO_QUEJA_SINCATEGORIZAR', 0);
define('TIPO_QUEJA_ESTATUS_ABIERTA', 145);
define('ESTATUS_DIVISION_QUEJA', 464);
define('ESTATUS_RESUELTA_QUEJA', 146);
define('ESTATUS_RESUELTACONFIRMADA_QUEJA', 473);
define('ESTATUS_CANCELADA_QUEJA', 474);

//Estatus comision
define('ESTATUS_COMISION_AUTORIZADA', 1);
define('ESTATUS_COMISION_NOAUTORIZADA', 5);
define('ESTATUS_COMISION_PAGADA', 2);
define('ESTATUS_COMISION_PENDIENTE', 0);
define('ESTATUS_COMISION_SINFACTURAR', 6);

//Tipo mail
define('MAIL_TIPO_EMPLEADO', 37);
define('MAIL_TIPO_FACTURA', 'Factura');
define('MAIL_TIPO_NOTACREDITO', 'Nota de Credito');
define('MAIL_TIPO_PERSONAL', 34);

//Tipo Convenio
define('TIPO_CONVENIO_INVITADOESPECIAL', 4);
define('TIPO_CONVENIO_PREMIER', 7);
define('TIPO_CONVENIO_ALIANZA_COMERCIAL', 8);

// tipo invitado espciela
define('MEDICOS_UNIVERSITARIOS', 5);
define('INTERCAMBIO_AUTORIZACION', 3);
define('INTERCAMBIO_COMERCIAL', 1);
define('INTERCAMBIO_FAMILIAR', 2);

// log para invitado especial
define('LOG_INVITADOESPECIAL', 30);

// tipo reporte forma pafo
define('TIPO_FORMA_PAGO', 1);
define('TIPO_CUENTA_CONTABLE', 2);

//Tipo involucrado
define('TIPO_INVOLUCRADO_PROPIETARIO', 1);
define('TIPO_INVOLUCRADO_VENDEDOR', 2);
define('TIPO_INVOLUCRADO_BENEFICIARIO', 3);

//Tipo contacto
define('TIPO_CONTACTO_NINGUNO', 0);

//Tipo telefono
define('TIPO_TELEFONO_CASA', 30);

// Tipo de evento Capacidad
define('TIPO_NUMERO_CLASES', 6);
define('TIPO_NUMERO_PARTICIPANTES', 7);

//Tipo esquema pago
define('TIPO_ESQUEMA_PAGO_EVENTO_PAQUETE', 8);
define('TIPO_ESQUEMA_PAGO_EVENTO_CLASE', 7);

// Tipo comprobante
define('TIPO_COMPROBANTE_AMONESTACION', 8);
define('TIPO_COMPROBANTE_BAJA', 9);
define('TIPO_COMPROBANTE_DAC', 11);
define('TIPO_COMPROBANTE_DOMICILIO', 2);
define('TIPO_COMPROBANTE_EMPLEADO', 3);
define('TIPO_COMPROBANTE_ESTUDIOS', 4);
define('TIPO_COMPROBANTE_IDENTIFICACION', 1);
define('TIPO_COMPROBANTE_RECIBO_CREDENCIAL', 6);
define('TIPO_COMPROBANTE_RECIBO_REGLAMENTO', 7);

//Tipo mail
define('TIPO_MAIL_FISCAL', 3);
define('TIPO_MAIL_WEB', 36);
define('TIPO_MAIL_EMPLEADO', 37);

//Tipo Invitado
define('INVITADO_POR_1_DIA', 442);

//Tipo mantenimiento
define('SINGLE_CLUB', 591);

//Puestos
define('CODIGO_GERENTE_GENERAL', 'GTGRAL');
define('PUESTO_ANFITRION', 156);
define('PUESTO_ANFITRION_4HRS', 538);
define('PUESTO_ANFITRION_TUM', 554);
define('PUESTO_COORDINADOR_DEPORTIVO', 229);
define('PUESTO_COORDINADOR_FITKIDZ', 198);
define('PUESTO_COORDINADOR_VENTAS', 583);
define('PUESTO_ENTRENADOR_FH', 779);
define('PUESTO_GERENTE_GENERAL', 74);
define('PUESTO_GERENTE_VENTAS', 77);
define('PUESTO_HOST', 789);
define('PUESTO_HOST_4HR', 790);
define('PUESTO_PROSPECTADOR', 773);

// Puestos para Call Center
define('PUESTO_ANALISTA_CC', 791);
define('PUESTO_OPERADOR_CC', 815);
define('PUESTO_SUPERVISOR_CC', 788);

//Tipo Cuenta contable
define('CUENTA_CONTALE_OTROSINGRESOS', 4003);

//Limite de dias para regenerar documentos
define('LIMITE_DIAS_REGENERA_DOC', 90);

//Tipos de membresia
define('TIPO_MEMBRESIA_INDIVIDUAL', 1);
define('TIPO_MEMBRESIA_GRUPAL', 2);
define('TIPO_MEMBRESIA_FAMILIAR', 3);

//Cuenta contable
define('CUENTA_INGRESOS_MANTTOS_INDIVIDUALES', 44);
define('CUENTA_INGRESOS_MANTTOS_GRUPALES', 45);
define('CUENTA_INGRESOS_MANTTOS_FAMILIARES', 46);

//Forma pago
define('FORMA_PAGO_EFECTIVO', 1);
define('FORMA_PAGO_TARJETACREDITO', 3);

//Membresia Opciones
define('MEM_OPC_ADULTOS', 4);
define('MEM_OPC_INTEGRANTES', 2);
define('MEM_OPC_AGREGADOSADULTOS', 9);

//Convenio detalle base corporativo
define('CONVENIO_DETALLE_BASE', 1755);
define('CONVENIO_DETALLE_SAMS_CLUB', 1824);
define('CONVENIO_DETALLE_LARIN', 1946);
define('CONVENIO_DETALLE_NUTRISA', 2057);
define('CONVENIO_DETALLE_RAAM', 2360);

//Generar Excel
define('GENERAR_EXCEL', 573);

//Tipos de grupo
define('TIPO_GRUPO_NINGUNO', 0);
define('TIPO_GRUPO_A', 1);
define('TIPO_GRUPO_B', 2);
define('TIPO_GRUPO_C', 3);
define('TIPO_GRUPO_D', 4);
define('TIPO_GRUPO_E', 5);

//Operadores
define('OPERADOR_DEFAULT', 1);

//Referencias origen
define('REFERENCIA_ALIANZA_COMERCIAL', 290);
define('REFERENCIA_ORIGEN_FRIDAYPASS', 321);
define('REFERENCIA_ORIGEN_GRAN_PASO', 280);
define('REFERENCIA_ORIGEN_RADIO', 281);
define('REFERENCIA_ORIGEN_VENTA_EN_LINEA', 279);
define('REFERENCIA_ORIGEN_WEB', 278);

//Estatus de actividad
define('ESTATUS_ACTIVIDAD_TERMINADA', 1);
define('ESTATUS_ACTIVIDAD_EN_PROCESO', 2);
define('ESTATUS_ACTIVIDAD_CANCELADA', 3);
define('ESTATUS_ACTIVIDAD_EN_ESPERA', 4);

//Pases
define('PASE_CONFIG_GRAN_PASO', 3);
define('PASE_EDAD_MINIMA', 18);
define('PASE_PREPAGO', 20);

//Estatus kiosco
define('TIPO_KIOSCO_ESTATUS_ABIERTO', 145);

//Objeto tipo actividad
define('TIPO_ACTIVIDAD_CITA', 4);
define('TIPO_ACTIVIDAD_LLAMADA', 5);
define('TIPO_ACTIVIDAD_MENSAJE', 7);

//Tipo aplicacion
define('TIPO_QUIOSCO', 'Quiosco');
define('TIPO_BEEPER', 'Beeper');

//Tipo paquete
define('TIPO_PAQUETE_VACACIONES', 3);
define('TIPO_PAQUETE_PROX_MTTO_GRATIS', 4);
define('TIPO_PAQUETE_PROPORCIONAL_GRATIS', 5);
define('TIPO_PAQUETE_LOCKER_MTTO_GRATIS', 6);
define('TIPO_PAQUETE_NOV_DIC_GRATIS', 7);
define('TIPO_PAQUETE_CERTIFICADO_REGALO', 10);

//Cuestionario
define('CUESTIONARIO_LESIONES', 55);
define('CUESTIONARIO_OBJETIVOS', 54);
define('CUESTIONARIO_PERFIL', 53);

//Definicion de permisos
define('PER_AUSENCIA_FUERAFECHA', 476);
define('PER_COMISION_BUSCAREMPLEADO', 428);
define('PER_COMISION_BUSCAREMPLEADOSINACTIVOS', 648);
define('PER_COMISION_BUSCARNUMEROEMPLEADO', 646);
define('PER_COMISION_SOLOJECUTIVOS', 647);
define('PER_COMISION_TODOSCLUBS', 496);
define('PER_PASE_RESTRINGIDOS', 660);
define('PER_REPORTEVENTAMEMBRESIA_CLUBS', 703);
define('PER_REPORTEVENTAMEMBRESIA_CONVENIO', 710);
define('PER_SOCIOS_CORRECIONMTTOS', 215);
define('PER_SOCIOS_MTTOSRESTRINGIDOS', 656);
define('PER_SUPERUSUARIO', 398);

//Tipo membresia opción
define('TIPO_MEMBRESIA_OPCION_LIMITEINICIOMTTO', 10);

//Tipo de unidad de medida para ejercicios de rutina
define('TIPO_UNIDADMEDIDA_MIN', '2'); //cambiar en produccion

//Tipo tarjetas
define('TIPO_TARJETA_AMEX', 3);

//Puestos
define('ASESOR_VENTAS', 100025);
define('EJECUTIVO_VTAS_CORPORATIVO', 97);
define('EJECUTIVO_VENTAS', 96);
define('EJECUTIVO_VENTAS_JR', 439);
define('EJECUTIVO_VENTAS_SR', 771);
define('VENDEDOR_SR', 100065);
define('VENDEDOR', 100066);
define('GERENTE_GENERAL', 74);
define('GERENTE_REGIONAL', 441);

//Tamaño de imagen para entrenamiento
//Verticales
define('ALTURA', 170);
define('ANCHO', 117);
//Vertcales Pequeñas
define('ALTURA_MIN', 130);
define('ANCHO_MIN', 77);
define('ESCALA_MAX', 70);
define('ESCALA_MIN', 30);

//Tipo descuentos
define('TIPO_DESCUENTO_PRIMERA_QUINCENA', 1);
define('TIPO_DESCUENTO_SEGUNDA_QUINCENA', 2);

//Catalogo de meses
define('CATALOGO_MESES', 47);
define('CATALOGO_DISPOSITIVOS_IPAD', 48);

//Descuentos de mtto
define('DESCUENTO_ESPECIAL', 6);
define('DESCUENTO_VACACIONAL', 4);
define('DESCUENTO_VACACIONAL_2', 5);
define('DESCUENTO_VACACIONAL_3', 14);
define('DESCUENTO_VACACIONAL_4', 15);

//Origen tramite de la tabla origentramite
define('ORIGEN_CRM', 1);
define('ORIGEN_PORTAL', 2);
define('ORIGEN_QUIOSCO', 3);

//Edad minima mtto
define('MTTO_EDAD_DEFAULT', 18);
define('MTTO_EDAD_MEM_INDIVIDUAL', 14);

//Tipo kiosco categoria
define('KIOSCO_CATEGORIA_BITACORA', 465);
define('KIOSCO_CATEGORIA_INFORMACION', 462);
define('KIOSCO_CATEGORIA_QUEJA', 140);

//Tipo unidad capacidad
define('TIPO_UNIDAD_CAPACIDAD_EQUIPAMIENTO', 2);

//Tipo estatus estadistica
define('ESTATUSESTADISTICA_ACTIVO', 1);
define('ESTATUSESTADISTICA_INACTIVO', 5);
define('ESTATUSESTADISTICA_SOCIOMANIA', 15);
define('ESTATUSESTADISTICA_PROPORCIONAL', 17);

//Tipo extra
define('TIPO_EXTRA_FECHAACTUALIZACIONDATOS', 1);

//Tipo interes prospecto
define('PROSPECTO_INTERESADO', 'si');
define('PROSPECTO_NO_INTERESADO', 'no');
define('PROSPECTO_POSIBLE_INTERESADO', 'posiblemente');

//Origen Mail documento
define('ORIGEN_MAIL_DOCUMENTO_CRM_GF', '1');
define('ORIGEN_MAIL_DOCUMENTO_CRM_HS', '3');
define('ORIGEN_MAIL_DOCUMENTO_CRM_DIG', 4);
define('ORIGEN_MAIL_DOCUMENTO_CRM_BP', 5);

//Estatus Mail documento
define('ESTATUS_MAIL_ESPERA', 1);

//Categoria Producto
define('CATEGORIA_PREMIO', 80);

// Tipo de promocion
define('PROMOCION_CAMPAIN', 103);
define('PROMOCION_CORPORATIVA', 102);
define('PROMOCION_MEMBRESIA', 100);
define('PROMOCION_REACTIVACION', 101);

//Convenio detalle Empleado SW
define('EMPLEADO_SW', 2124);
define('BECADOS_FEELHEALTHY', 2284);

//Variable para publicar un archivo directo en excel
define('EXCEL_MICROSOFT', 'data:application/vnd.ms-excel;charset=utf-8,');

//Identificador de DIV para el icono EXCEL
define('XLSX', 'xlsx');

//Evaluacion
define('EVALUACION_ENTRENAMIENTO', 19);

//Actividades Host
define('CITADAC', 7);
define('CITANUTRICION', 3);
define('ENCUESTAINFORMATIVA', 1);
define('FITKIDZ', 5);
define('FITNESSTEST', 10);
define('SATISFACCION', 6);
define('SATISFACCION1', 9);

//Cuestionarios Encuesta Informativa
define('CUESTIONARIO_GENERAL', 73);
define('CUESTIONARIO_OBJETIVOS_ASISTENCIA', 74);

//Consultorio
define('CONSULTORIO_NUTRICION', '2');
define('CONSULTORIO_MEDICO', '1');

//PREGUNTA para identificar la aplicacion de feel healthy en DAC
define('PREGUNTA_FEEL_HEALTHY_DAC', 654);

//ENFERMEDAD
define('ENFERMEDAD_DIABETES2', 1);
define('DIABETES_MELLITUS_ID', 315);
define('OBESIDAD_I_ID', 1028);
define('OBESIDAD_II_ID', 1028);
define('HIPERTENSION_ID', 389);
define('PARENTESCO_PREGUNTA_ID', 1036);

//Programa
define('PROGRAMA_HAND_TO_HAND', 1);
define('PROGRAMA_FEEL_HEALTHY', 2);
define('PROGRAMA_NINGUNO', 0);

//Usuarios
define('USUARIO_SISTEMA', 1000);
//SEXO
define('SEXO_MASCULINO', 13);
define('SEXO_FEMENINO', 12);

//Unidad como repeticiones, min, hr para entrenamiento
define('UNIDAD_REPETICIONES', 5);

//Descanso
define('DESCANSO_ACTIVO', 213);

//Categoria Ejercicios
define('ACONDICIONAMIENTO_FISICO', 10);
define('EJERCICIOS_VARIOS', 6);

//Preguntas de Peso y Estatura
define('PESO', 253); //253 375
define('ESTATURA', 547); //547 748

//Fidelidad
define('FIDELIDAD_STARTER', 1);
define('FIDELIDAD_NEW_STARTER', 6);
define('FIDELIDAD_NEW_STARTER2015', 7);

//Eventos
define('EVENTO_HAND_TO_HAND', 448); //456
define('EVENTO_FEEL_HEALTHY', 449); //455

//Niveles de entrenamiento
define('NIVEL_PRINCIPIANTE', 998);
define('NIVEL_MEDIO', 999);
define('NIVEL_AVANZADO', 1000);

//Noticias categoria
define('NOTICIA_CATEGORIA_COMUNIDAD', 7);

//Categoria encuesta
define('RECURSOS_HUMANOS', 2);

//Operador
define('OPERADOR_HUMMAN_ACCESS', 7);

//Tipo Mtto Preventivo
define('MTTO_PRE_SOPORTE_TECNIVO', 1);
define('MTTO_PRE_MTTO', 2);

// Expedientes
define('EXPEDIENTES_APROBAR', 927);
define('EXPEDIENTES_GENERAR', 937);
define('EXPEDIENTES_REVISAR', 928);

// Certificados de Regalo
define('CERTIFICADO_GROUPON', 397);
define('CERTIFICADO_GROUPON2', 396);
define('CERTIFICADO_GROUPON2B', 431);

define('CERTIFICADO_GROUPON_UPSTER_A', 620);
define('CERTIFICADO_GROUPON_UPSTER_B', 621);
define('CERTIFICADO_GROUPON_UPSTER_C', 622);

// Lavanderia mensual
define('LAVANDERIA_MENSUAL', 2376);
define('LAVANDERIA_RESPUESTO', 2433);

define('CARGO_CREDENCIAL', 390);
define('CARGO_CREDENCIAL_PREMIER', 3671);

// Ruta de API trajetas de prepago
define('API_PREPAGO', 'https://crm.sportsworld.com.mx/apiprepago/public/api/v1/prepago/');

//if (gethostname()=='vswmex09') {
//    include("/respaldos/rsw/variables_archivoGeneralConexionesExternas.php");
//}

// include("/respaldos/rsw/variables_archivoGeneralConexionesExternas.php");

/* End of file constants.php */
/* Location: ./system/application/config/constants.php */
