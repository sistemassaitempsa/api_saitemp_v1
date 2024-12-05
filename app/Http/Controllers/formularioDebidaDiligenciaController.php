<?php

namespace App\Http\Controllers;

use Exception;
use App\Events\NotificacionSeiya;
use App\Http\Controllers\HorarioLaboralController;
use App\Http\Controllers\EstadosFirmaController;
use App\Models\ResponsablesEstadosModel;
use App\Models\ClientesSeguimientoGuardado;
use App\Models\HistoricoContratosDDModel;
use App\Models\ClientesSeguimientoEstado;
use App\Models\UsuarioPermiso;
use App\Models\cliente;
use App\Models\ActividadCiiu;
use App\Models\Cargos;
use App\Models\Accionista;
use App\Models\RepresentanteLegal;
use App\Models\MiembroJunta;
use App\Models\CalidadTributaria;
use App\Models\Contador;
use App\Models\Tesorero;
use App\Models\DatoFinanciero;
use App\Models\OperacionIternacional;
use App\Models\ReferenciaBancaria;
use App\Models\ReferenciaComercial;
use App\Models\CargoRequisito;
use App\Models\CargoExamen;
use App\Models\Documento;
use App\Models\DocumentoCliente;
use App\Models\PersonasExpuestas;
use App\Models\OrigenFondo;
use App\Models\CargoCliente;
use App\Models\Cargo2;
use App\Models\Cargo2Examen;
use App\Models\Cargo2Recomendacion;
use App\Models\ClienteEpp;
use App\Models\RegistroCambio;
use App\Models\ClienteOtroSi;
use App\Models\ClienteConvenioBanco;
use App\Models\ClienteTipoContrato;
use App\Models\ClienteLaboratorio;
use App\Models\HistoricoOperacionesDDModel;
use App\Models\VersionFormularioDD;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\enviarCorreoDDController;
// use App\Events\EventoPrueba2;





class formularioDebidaDiligenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $result = cliente::select()
            ->get();
        return response()->json($result);
    }

    public function empresascliente()
    {
        $result = cliente::select(
            'id',
            'razon_social as nombre',
            DB::raw('COALESCE(nit, numero_identificacion) as nit')
        )->get();

        return response()->json($result);
    }
    public function consultacliente($cantidad)
    {
        $permisos = $this->validaPermiso();

        $user = auth()->user();
        $year_actual = date('Y');
        $result = cliente::join('gen_vendedor as ven', 'ven.cod_ven', '=', 'usr_app_clientes.vendedor_id')
            ->leftJoin('usr_app_estados_firma as estf', 'estf.id', '=', 'usr_app_clientes.estado_firma_id')
            ->whereYear('usr_app_clientes.created_at', $year_actual)
            ->when(!in_array('39', $permisos), function ($query) use ($user) {
                return $query->where('usr_app_clientes.vendedor_id', $user->vendedor_id);
            })
            ->select(
                'usr_app_clientes.id',
                DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
                'usr_app_clientes.razon_social',
                'usr_app_clientes.numero_identificacion',
                'usr_app_clientes.nit',
                'ven.nom_ven as vendedor',
                'usr_app_clientes.telefono_empresa',
                'usr_app_clientes.created_at',
                'estf.nombre as nombre_estado_firma',
                'estf.color as color_estado_firma',
                /* 'estf.id as estado_firma_id', */
                'usr_app_clientes.responsable'

            )
            ->orderby('usr_app_clientes.numero_radicado', 'DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }

    public function clientesactivos()
    {
        $result = cliente::select()
            ->get();
        return response()->json(count($result));
    }

    public function existbyid($id, $tipo_id)
    {
        if ($tipo_id == 1) {
            $result = Cliente::where('usr_app_clientes.numero_identificacion', '=', $id)
                ->select(
                    'numero_identificacion'
                )
                ->first();
            return $result;
        } else if ($tipo_id == 2) {
            $result = Cliente::where('usr_app_clientes.nit', '=', $id)
                ->select(
                    'nit',
                )
                ->first();
            return $result;
        }
    }


    public function getbyid($id, $asJson = true)
    {
        try {
            $result = Cliente::leftJoin('usr_app_actividades_ciiu as ac', 'ac.id', '=', 'usr_app_clientes.actividad_ciiu_id')
                ->leftJoin('usr_app_codigos_ciiu as cc', 'cc.id', '=', 'ac.codigo_ciiu_id')
                ->leftJoin('usr_app_tipos_persona as tp', 'tp.id', '=', 'usr_app_clientes.tipo_persona_id')
                ->leftJoin('usr_app_operaciones as op', 'op.id', '=', 'usr_app_clientes.operacion_id')
                ->leftJoin('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_clientes.tipo_identificacion_id')
                ->leftJoin('usr_app_estratos as est', 'est.id', '=', 'usr_app_clientes.estrato_id')
                ->leftJoin('usr_app_municipios as mun1', 'mun1.id', '=', 'usr_app_clientes.municipio_id')
                ->leftJoin('usr_app_municipios as mun2', 'mun2.id', '=', 'usr_app_clientes.municipio_prestacion_servicio_id')
                ->leftJoin('usr_app_municipios as mun3', 'mun3.id', '=', 'usr_app_clientes.municipio_rut_id')
                ->leftJoin('usr_app_departamentos as dep1', 'dep1.id', '=', 'mun1.departamento_id')
                ->leftJoin('usr_app_departamentos as dep2', 'dep2.id', '=', 'mun2.departamento_id')
                ->leftJoin('usr_app_departamentos as dep3', 'dep3.id', '=', 'mun3.departamento_id')
                ->leftJoin('usr_app_paises as pais', 'pais.id', '=', 'dep1.pais_id')
                ->leftJoin('usr_app_paises as pais2', 'pais2.id', '=', 'dep2.pais_id')
                ->leftJoin('usr_app_sociedades_comerciales as sc', 'sc.id', '=', 'usr_app_clientes.sociedad_comercial_id')
                ->leftJoin('usr_app_jornadas_laborales as jl', 'jl.id', '=', 'usr_app_clientes.jornada_laboral_id')
                ->leftJoin('usr_app_rotaciones_personal as rp', 'rp.id', '=', 'usr_app_clientes.rotacion_personal_id')
                ->leftJoin('usr_app_riesgos_laborales as rl', 'rl.id', '=', 'usr_app_clientes.riesgo_cliente_id')
                ->leftJoin('gen_sucursal as scf', 'scf.cod_suc', '=', 'usr_app_clientes.sucursal_facturacion_id')
                ->leftJoin('gen_vendedor as ven', 'ven.cod_ven', '=', 'usr_app_clientes.vendedor_id')
                ->leftJoin('usr_app_periodicidad_liquidacion_nominas as pl', 'pl.id', '=', 'usr_app_clientes.periodicidad_liquidacion_id')
                ->leftJoin('usr_app_datos_contador as con', 'con.cliente_id', '=', 'usr_app_clientes.id')
                ->leftJoin('gen_tipide as ti2', 'ti2.cod_tip', '=', 'con.tipo_identificacion_id')
                ->leftJoin('usr_app_datos_tesoreria as tes', 'tes.cliente_id', '=', 'usr_app_clientes.id')
                ->leftJoin('usr_app_datos_financieros as fin', 'fin.cliente_id', '=', 'usr_app_clientes.id')
                ->leftJoin('usr_app_operaciones_internacionales as opi', 'opi.cliente_id', '=', 'usr_app_clientes.id')
                ->leftJoin('usr_app_tipo_operaciones_internacionales as topi', 'topi.id', '=', 'opi.tipo_operaciones_id')
                ->leftJoin('usr_app_tipo_proveedor as tpro', 'tpro.id', '=', 'usr_app_clientes.tipo_proveedor_id')
                ->leftJoin('usr_app_tipo_cliente as tcli', 'tcli.id', '=', 'usr_app_clientes.tipo_cliente_id')
                ->leftJoin('usr_app_estados_firma as estf', 'estf.id', '=', 'usr_app_clientes.estado_firma_id')
                ->leftJoin('usr_app_usuarios as usuario', 'usuario.id', '=', 'usr_app_clientes.usuario_corregir_id')
                ->leftJoin('usr_app_observacion_estado as novedad', 'novedad.id', '=', 'usr_app_clientes.novedad_servicio')
                ->select(
                    DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
                    'ac.codigo_actividad as codigo_actividad_ciiu',
                    'ac.id as codigo_actividad_ciiu_id',
                    'cc.codigo as codigo_ciiu',
                    'cc.id as codigo_ciiu_id',
                    'ac.descripcion as actividad_ciiu_descripcion',
                    'tp.nombre as tipo_persona',
                    'usr_app_clientes.tipo_persona_id',
                    'op.nombre as tipo_operacion',
                    'usr_app_clientes.operacion_id',
                    'ti.des_tip as tipo_identificacion',
                    'usr_app_clientes.tipo_identificacion_id',
                    'usr_app_clientes.contratacion_directa',
                    'usr_app_clientes.atraccion_seleccion',
                    'usr_app_clientes.numero_identificacion',
                    'usr_app_clientes.fecha_exp_documento',
                    'usr_app_clientes.nit',
                    'usr_app_clientes.digito_verificacion',
                    'usr_app_clientes.razon_social',
                    'usr_app_clientes.fecha_constitucion',
                    'usr_app_clientes.junta_directiva',
                    'est.nombre as estrato',
                    'est.id as estrato_id',
                    'mun1.nombre as municipio',
                    'mun1.id as municipio_id',
                    'mun2.nombre as municipio_prestacion_servicio',
                    'mun2.id as municipio_prestacion_servicio_id',
                    'mun3.nombre as municipio_rut',
                    'mun3.id as municipio_rut_id',
                    'dep1.nombre as departamento',
                    'dep1.id as departamento_id',
                    'dep2.nombre as departamento_prestacion_servicio',
                    'dep2.id as departamento_prestacion_servicio_id',
                    'dep3.nombre as departamento_rut',
                    'dep3.id as departamento_rut_id',
                    'pais.nombre as pais',
                    'pais.id as pais_id',
                    'pais2.nombre as pais_prestacion_servicio',
                    'pais2.id as pais_prestacion_servicio_id',
                    'usr_app_clientes.direccion_empresa',
                    'usr_app_clientes.contacto_empresa',
                    'usr_app_clientes.correo_empresa',
                    'usr_app_clientes.telefono_empresa',
                    'usr_app_clientes.celular_empresa',
                    'sc.nombre as sociedad_comercial',
                    'sc.id as sociedad_comercial_id',
                    'usr_app_clientes.otra',
                    'usr_app_clientes.aiu_negociado',
                    'usr_app_clientes.plazo_pago',
                    'usr_app_clientes.acuerdo_comercial',
                    'usr_app_clientes.numero_empleados',
                    'jl.nombre as jornada_laboral',
                    'jl.id as jornada_laboral_id',
                    'rp.nombre as rotacion_personal',
                    'rp.id as rotacion_personal_id',
                    'rl.nombre as riesgo_cliente',
                    'rl.id as riesgo_cliente_id',
                    'usr_app_clientes.responsable_inpuesto_ventas',
                    'usr_app_clientes.correo_facturacion_electronica',
                    'usr_app_clientes.declaraciones_autorizaciones',
                    'usr_app_clientes.tratamiento_datos_personales',
                    'usr_app_clientes.operaciones_internacionales',
                    'scf.nom_suc as sucursal_facturacion',
                    'scf.cod_suc as sucursal_facturacion_id',
                    'ven.nom_ven as vendedor',
                    'ven.cod_ven as vendedor_id',
                    'pl.id as periodicidad_liquidacion_id',
                    'pl.nombre as periodicidad_liquidacion',
                    'con.id as contador_id',
                    'con.nombre as nombre_contador',
                    'con.identificacion as identificacion_contador',
                    'con.telefono as telefono_contador',
                    'con.tipo_identificacion_id as tipo_identificacion_id_contador',
                    'ti2.des_tip as tipo_identificacion_contador',
                    'tes.nombre as nombre_tesorero',
                    'tes.telefono as telefono_tesorero',
                    'tes.correo as correo_tesorero',
                    'fin.ingreso_mensual as ingreso_mensual',
                    'fin.otros_ingresos as otros_ingresos',
                    'fin.total_ingresos as total_ingresos',
                    'fin.costos_gastos_mensual as costos_gastos_mensual',
                    'fin.detalle_otros_ingresos as detalle_otros_ingresos',
                    'fin.reintegro_costos_gastos as reintegro_costos_gastos',
                    'fin.activos as activos',
                    'fin.pasivos as pasivos',
                    'fin.patrimonio as patrimonio',
                    'opi.tipo_operaciones_id as tipo_operacion_internacional_id',
                    'topi.nombre as tipo_operacion_internacional',
                    'tpro.nombre as tipo_proveedor',
                    'usr_app_clientes.tipo_proveedor_id as tipo_proveedor_id',
                    'tcli.nombre as tipo_cliente',
                    'usr_app_clientes.tipo_cliente_id as tipo_cliente_id',
                    'usr_app_clientes.empresa_extranjera',
                    'usr_app_clientes.empresa_en_exterior',
                    'usr_app_clientes.vinculos_empresa',
                    'usr_app_clientes.numero_empleados_directos',
                    'usr_app_clientes.vinculado_empresa_temporal',
                    'usr_app_clientes.visita_presencial',
                    'usr_app_clientes.facturacion_contacto',
                    'usr_app_clientes.facturacion_cargo',
                    'usr_app_clientes.facturacion_telefono',
                    'usr_app_clientes.facturacion_celular',
                    'usr_app_clientes.facturacion_correo',
                    'usr_app_clientes.facturacion_factura_unica',
                    'usr_app_clientes.facturacion_fecha_corte',
                    'usr_app_clientes.facturacion_encargado_factura',
                    'usr_app_clientes.requiere_anexo_factura',
                    'usr_app_clientes.trabajo_alto_riesgo',
                    'usr_app_clientes.accidentalidad',
                    'usr_app_clientes.encargado_sst',
                    'usr_app_clientes.nombre_encargado_sst',
                    'usr_app_clientes.cargo_encargado_sst',
                    'usr_app_clientes.induccion_entrenamiento',
                    'usr_app_clientes.entrega_dotacion',
                    'usr_app_clientes.evaluado_arl',
                    'usr_app_clientes.entrega_epp',
                    'usr_app_clientes.contratacion_contacto',
                    'usr_app_clientes.contratacion_cargo',
                    'usr_app_clientes.contratacion_telefono',
                    'usr_app_clientes.contratacion_celular',
                    'usr_app_clientes.contratacion_correo',
                    'usr_app_clientes.contratacion_hora_ingreso',
                    'usr_app_clientes.contratacion_manipulacion_alimentos',
                    'usr_app_clientes.contratacion_hora_confirmacion',
                    'usr_app_clientes.contratacion_tallas_uniforme',
                    'usr_app_clientes.contratacion_suministra_transporte',
                    'usr_app_clientes.contratacion_suministra_alimentacion',
                    'usr_app_clientes.contratacion_pago_efectivo',
                    'usr_app_clientes.contratacion_carnet_corporativo',
                    'usr_app_clientes.contratacion_pagos_31',
                    'usr_app_clientes.contratacion_observacion',
                    'usr_app_clientes.responsable_id',
                    'usr_app_clientes.responsable',
                    'usr_app_clientes.estado_firma_id',
                    'estf.nombre as nombre_estado_firma',
                    'usr_app_clientes.novedad_servicio',
                    'usr_app_clientes.afectacion_servicio',
                    'usr_app_clientes.usuario_corregir_id',
                    'usr_app_clientes.dirección_rut',
                    'novedad.nombre as nombre_novedad_servicio',
                    DB::raw("CONCAT(usuario.nombres,' ',usuario.apellidos)  AS nombre_usuario_corregir"),

                )
                ->where('usr_app_clientes.id', '=', $id)
                ->first();


            $seguimiento = ClientesSeguimientoGuardado::join('usr_app_estados_firma as ei', 'ei.id', '=', 'usr_app_clientes_seguimiento_guardado.estado_firma_id')
                ->where('usr_app_clientes_seguimiento_guardado.cliente_id', $id)
                ->select(
                    'usr_app_clientes_seguimiento_guardado.usuario',
                    'ei.nombre as estado',
                    'usr_app_clientes_seguimiento_guardado.created_at',

                )
                ->orderby('usr_app_clientes_seguimiento_guardado.id', 'desc')
                ->get();
            $result['seguimiento'] = $seguimiento;

            $contrato = HistoricoContratosDDModel::join('usr_app_usuarios as usuario', 'usuario.id', '=', 'usr_app_historico_contratos_dd.usuario_envia')
                ->where('usr_app_historico_contratos_dd.cliente_id', $id)->where('usr_app_historico_contratos_dd.activo', '=', 1)
                ->select(
                    'usr_app_historico_contratos_dd.cliente_id',
                    'usr_app_historico_contratos_dd.firmado_cliente',
                    'usr_app_historico_contratos_dd.firmado_empresa',
                    'usr_app_historico_contratos_dd.ruta_contrato',
                    'usr_app_historico_contratos_dd.contrato_firma_id',
                    'usr_app_historico_contratos_dd.transaccion_id',
                    'usr_app_historico_contratos_dd.correo_enviado_cliente',
                    'usr_app_historico_contratos_dd.correo_enviado_empresa',
                    'usr_app_historico_contratos_dd.activo',
                    'usr_app_historico_contratos_dd.estado_contrato',
                    'usr_app_historico_contratos_dd.usuario_envia',
                    'usr_app_historico_contratos_dd.created_at',
                    DB::raw("CONCAT(usuario.nombres,' ',usuario.apellidos)  AS nombre_usuario_envia"),
                )
                ->get();
            $result['contrato'] = $contrato;
            $seguimiento_estados = ClientesSeguimientoEstado::join('usr_app_estados_firma as ei', 'ei.id', '=', 'usr_app_clientes_seguimiento_estado.estados_firma_inicial')
                ->join('usr_app_estados_firma as ef', 'ef.id', '=', 'usr_app_clientes_seguimiento_estado.estados_firma_final')
                ->where('usr_app_clientes_seguimiento_estado.cliente_id', $id)
                ->select(
                    'usr_app_clientes_seguimiento_estado.responsable_inicial',
                    'usr_app_clientes_seguimiento_estado.responsable_final',
                    'usr_app_clientes_seguimiento_estado.oportuno',
                    'ei.nombre as estados_firma_inicial',
                    'ef.nombre as estados_firma_final',
                    'usr_app_clientes_seguimiento_estado.actualiza_registro',
                    'usr_app_clientes_seguimiento_estado.created_at',
                    'usr_app_clientes_seguimiento_estado.updated_at',
                )
                ->orderby('usr_app_clientes_seguimiento_estado.id', 'desc')
                ->get();
            $result['seguimiento_estados'] = $seguimiento_estados;

            $cargos = Cargos::join('usr_app_riesgos_laborales as rl2', 'rl2.id', '=', 'usr_app_cargos.riesgo_laboral_id')
                ->join('usr_app_cargos_requisitos as cr', 'cr.cargo_id', '=', 'usr_app_cargos.id')
                ->join('usr_app_requisitos as requ', 'requ.id', '=', 'cr.requisito_id')
                ->join('usr_app_cargos_examenes as cx', 'cx.cargo_id', '=', 'usr_app_cargos.id')
                ->join('usr_app_examenes as exam', 'exam.id', '=', 'cx.examen_id')
                ->select(
                    'usr_app_cargos.id as id_cargo',
                    'usr_app_cargos.nombre as cargo',
                    'usr_app_cargos.riesgo_laboral_id',
                    'rl2.nombre as riesgo_laboral',
                    'cr.requisito_id',
                    'requ.nombre as requisito',
                    'cx.examen_id',
                    'exam.nombre as examen',
                )
                ->where('cliente_id', '=', $id)
                ->distinct('requ.nombre as requisito')
                ->get();

            // Array para almacenar los resultados
            $resultados = [];

            // Recorrer el array original
            foreach ($cargos as $objeto) {
                // Extraer los datos del objeto
                $idCargo = $objeto['id_cargo'];
                $cargo = $objeto['cargo'];
                $idRiesgoLaboral = $objeto['riesgo_laboral_id'];
                $riesgoLaboral = $objeto['riesgo_laboral'];
                $idRequisito = $objeto['requisito_id'];
                $requisito = $objeto['requisito'];
                $idExamen = $objeto['examen_id'];
                $examen = $objeto['examen'];

                // Verificar si el cargo ya existe en los resultados
                if (!isset($resultados[$idCargo])) {
                    // Si no existe, crear un nuevo objeto para el cargo
                    $resultados[$idCargo] = [
                        'id_cargo' => $idCargo,
                        'cargo' => $cargo,
                        'riesgo_laboral_id' => $idRiesgoLaboral,
                        'riesgo_laboral' => $riesgoLaboral,
                        'examenes' => [],
                        'requisitos' => [],
                    ];
                }

                // Verificar si el examen ya existe en los resultados del cargo
                if (!in_array($idExamen, array_column($resultados[$idCargo]['examenes'], 'id'))) {
                    $resultados[$idCargo]['examenes'][] = [
                        'id' => $idExamen,
                        'nombre' => $examen,
                    ];
                }

                // Verificar si el requisito ya existe en los resultados del cargo
                if (!in_array($idRequisito, array_column($resultados[$idCargo]['requisitos'], 'id'))) {
                    $resultados[$idCargo]['requisitos'][] = [
                        'id' => $idRequisito,
                        'nombre' => $requisito,
                    ];
                }
            }

            // Resultado: Array final con cargos, exámenes y requisitos sin duplicados
            $resultados = array_values($resultados);
            $result['cargos'] = $resultados;


            // **************************************************************************************
            $cargos = Cargo2::join('usr_app_riesgos_laborales as rl2', 'rl2.id', '=', 'usr_app_cargos2.riesgo_laboral_id')
                ->join('usr_app_lista_cargos as lc', 'lc.id', '=', 'usr_app_cargos2.cargo_id')
                ->join('usr_app_subcategoria_cargos as sc', 'sc.id', '=', 'lc.subcategoria_cargo_id')
                ->join('usr_app_categoria_cargos as cc', 'cc.id', '=', 'sc.categoria_cargo_id')
                ->join('usr_app_cargos2_recomendaciones as cr', 'cr.cargo_id', '=', 'usr_app_cargos2.id')
                ->join('usr_app_lista_recomendaciones as recom', 'recom.id', '=', 'cr.recomendacion_id')
                ->join('usr_app_cargos2_examenes as cx', 'cx.cargo_id', '=', 'usr_app_cargos2.id')
                ->join('usr_app_lista_examenes as exam', 'exam.id', '=', 'cx.examen_id')
                ->select(
                    'usr_app_cargos2.cargo_id as id_cargo',
                    'lc.nombre as cargo',
                    'lc.subcategoria_cargo_id as categoria_cargo_id',
                    'sc.categoria_cargo_id as tipo_cargo_id',
                    'sc.nombre as categoria',
                    'cc.nombre as tipo_cargo',
                    'usr_app_cargos2.funcion_cargo as funcion_cargo',
                    'usr_app_cargos2.riesgo_laboral_id',
                    'rl2.nombre as riesgo_laboral',
                    'cr.recomendacion_id',
                    'recom.recomendacion1 as recomendacion1',
                    'recom.recomendacion2 as recomendacion2',
                    'cx.examen_id',
                    'exam.nombre as examen',

                )
                ->where('cliente_id', '=', $id)
                ->distinct('exam.nombre as examen')
                ->get();

            // Array para almacenar los resultados
            $resultados = [];

            // Recorrer el array original
            foreach ($cargos as $objeto) {
                // Extraer los datos del objeto

                $funcion_cargo = $objeto['funcion_cargo'];
                $idCargo = $objeto['id_cargo'];
                $cargo = $objeto['cargo'];
                $idSubcategoria = $objeto['categoria_cargo_id'];
                $subcategoria = $objeto['categoria'];
                $idCategoria = $objeto['tipo_cargo_id'];
                $categoria = $objeto['tipo_cargo'];
                $idRiesgoLaboral = $objeto['riesgo_laboral_id'];
                $riesgoLaboral = $objeto['riesgo_laboral'];
                $idRequisito = $objeto['recomendacion_id'];
                $recomendacion1 = $objeto['recomendacion1'];
                $recomendacion2 = $objeto['recomendacion2'];
                $idExamen = $objeto['examen_id'];
                $examen = $objeto['examen'];

                // Verificar si el cargo ya existe en los resultados
                if (!isset($resultados[$idCargo])) {
                    // Si no existe, crear un nuevo objeto para el cargo
                    $resultados[$idCargo] = [
                        'id_cargo' => $idCargo,
                        'cargo' => $cargo,
                        'riesgo_laboral_id' => $idRiesgoLaboral,
                        'riesgo_laboral' => $riesgoLaboral,
                        'examenes' => [],
                        'recomendaciones' => [],
                        'categoria_cargo_id' => $idSubcategoria,
                        'categoria' => $subcategoria,
                        'tipo_cargo_id' => $idCategoria,
                        'tipo_cargo' => $categoria,
                        'funcion_cargo' => $funcion_cargo
                    ];
                }

                // Verificar si el examen ya existe en los resultados del cargo
                if (!in_array($idExamen, array_column($resultados[$idCargo]['examenes'], 'id'))) {
                    $resultados[$idCargo]['examenes'][] = [
                        'id' => $idExamen,
                        'nombre' => $examen,
                    ];
                }

                // Verificar si el requisito ya existe en los resultados del cargo
                if (!in_array($idRequisito, array_column($resultados[$idCargo]['recomendaciones'], 'id'))) {
                    $resultados[$idCargo]['recomendaciones'][] = [
                        'id' => $idRequisito,
                        'recomendacion1' => $recomendacion1,
                        'recomendacion2' => $recomendacion2,
                    ];
                }
            }

            // Resultado: Array final con cargos, exámenes y requisitos sin duplicados
            $resultados = array_values($resultados);
            $result['cargos2'] = $resultados;
            // **************************************************************************************

            $clientes_epps = ClienteEpp::where('cliente_id', $id)
                ->select('epp_id')
                ->get();
            $result['clientes_epps'] = $clientes_epps;

            $accionistas = Accionista::join('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_accionistas.tipo_identificacion_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_accionistas.id',
                    'usr_app_accionistas.tipo_identificacion_id',
                    'usr_app_accionistas.identificacion',
                    'usr_app_accionistas.accionista as socio',
                    'usr_app_accionistas.participacion',
                    'ti.des_tip',
                )
                ->get();
            $result['accionistas'] = $accionistas;


            $RepresentanteLegal = RepresentanteLegal::join('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_representantes_legales.tipo_identificacion_id')
                ->join('usr_app_municipios as mun', 'mun.id', '=', 'usr_app_representantes_legales.municipio_expedicion_id')
                ->join('usr_app_departamentos as dep', 'dep.id', '=', 'mun.departamento_id')
                ->join('usr_app_paises as pais', 'pais.id', '=', 'dep.pais_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_representantes_legales.id',
                    'usr_app_representantes_legales.nombre',
                    'usr_app_representantes_legales.identificacion',
                    'usr_app_representantes_legales.correo_electronico as correo',
                    'usr_app_representantes_legales.telefono',
                    'usr_app_representantes_legales.tipo_identificacion_id as tipo_identificacion',
                    'ti.des_tip',
                    'mun.nombre as ciudad_expedicion',
                    'mun.id as municipio_id',
                    'dep.nombre as departamento',
                    'dep.id as departamento_id',
                    'pais.nombre as pais',
                    'pais.id as pais_id',
                )
                ->get();
            $result['representantes_legales'] = $RepresentanteLegal;

            $miembrosjunta = MiembroJunta::join('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_juntas_directivas.tipo_identificacion_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_juntas_directivas.id',
                    'usr_app_juntas_directivas.nombre',
                    'usr_app_juntas_directivas.tipo_identificacion_id',
                    'usr_app_juntas_directivas.identificacion',
                    'ti.des_tip',
                )
                ->get();

            $result['junta_directiva'] = $miembrosjunta;

            $calidadTributaria = CalidadTributaria::where('cliente_id', '=', $id)
                ->select(
                    'usr_app_calidad_tributaria.id',
                    'usr_app_calidad_tributaria.gran_contribuyente',
                    'usr_app_calidad_tributaria.resolucion_gran_contribuyente',
                    'usr_app_calidad_tributaria.fecha_gran_contribuyente',
                    'usr_app_calidad_tributaria.auto_retenedor',
                    'usr_app_calidad_tributaria.resolucion_auto_retenedor',
                    'usr_app_calidad_tributaria.fecha_auto_retenedor',
                    'usr_app_calidad_tributaria.exento_impuesto_rent',
                    'usr_app_calidad_tributaria.resolucion_exento_impuesto_rent',
                    'usr_app_calidad_tributaria.fecha_exento_impuesto_rent',
                )
                ->get();
            $result['calidad_tributaria'] = $calidadTributaria;


            $result['junta_directiva'] = $miembrosjunta;

            $referenciaBancaria = ReferenciaBancaria::join('gen_bancos as ban', 'ban.cod_ban', '=', 'usr_app_referencias_bancarias.banco_id')
                ->join('usr_app_tipos_cuenta_banco as tc', 'tc.id', '=', 'usr_app_referencias_bancarias.tipo_cuenta_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'ban.cod_ban as banco_id',
                    'ban.nom_ban as banco',
                    'usr_app_referencias_bancarias.id',
                    'usr_app_referencias_bancarias.numero_cuenta',
                    'tc.id as tipo_cuenta_banco',
                    'tc.nombre as tipo_cuenta',
                    'usr_app_referencias_bancarias.sucursal',
                    'usr_app_referencias_bancarias.telefono',
                    'usr_app_referencias_bancarias.contacto',
                )
                ->get();

            $result['referencia_bancaria'] = $referenciaBancaria;

            $referenciaComercial = ReferenciaComercial::where('cliente_id', '=', $id)
                ->select(
                    'razon_social as nombre',
                    'contacto',
                    'telefono',
                )
                ->get();
            $result['referencia_comercial'] = $referenciaComercial;

            $personasExpuestas = PersonasExpuestas::join('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_personas_expuestas_politica.tipo_identificacion_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_personas_expuestas_politica.nombre',
                    'usr_app_personas_expuestas_politica.numero_identificacion as identificacion',
                    'usr_app_personas_expuestas_politica.parentesco',
                    'usr_app_personas_expuestas_politica.tipo_identificacion_id',
                    'ti.des_tip',
                )
                ->get();
            $result['personas_expuestas'] = $personasExpuestas;

            $origenFondo = OrigenFondo::join('usr_app_tipos_origen_fondos as of', 'of.id', '=', 'usr_app_origenes_fondos.tipo_origen_fondos_id')
                ->join('usr_app_tipos_origen_medios as om', 'om.id', '=', 'usr_app_origenes_fondos.tipo_origen_medios_id')
                ->join('usr_app_tipos_origen_medios as om2', 'om2.id', '=', 'usr_app_origenes_fondos.tipo_origen_medios2_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'tipo_origen_fondos_id',
                    'otro_origen',
                    'tipo_origen_medios_id',
                    'tipo_origen_medios2_id',
                    'alto_manejo_efectivo',
                    'of.nombre as origen_fondos',
                    'om.nombre as origen_medios',
                    'om2.nombre as origen_medios2',
                    'usr_app_origenes_fondos.alto_manejo_efectivo',
                )

                ->first();
            $result['origen_fondos'] = $origenFondo;

            $documentoCliente = DocumentoCliente::join('usr_app_tipos_documento as td', 'td.id', '=', 'usr_app_documentos_cliente.tipo_documento_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_documentos_cliente.id',
                    'usr_app_documentos_cliente.tipo_documento_id',
                    'usr_app_documentos_cliente.ruta',
                    'usr_app_documentos_cliente.descripcion',
                    'td.nombre',
                    'td.tipo_archivo'
                )

                ->get();
            $result['documentos_adjuntos'] = $documentoCliente;

            $cliente_otrosi = ClienteOtroSi::join('usr_app_otros_si as ots', 'ots.id', '=', 'usr_app_cliente_otrosi.otro_si_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'ots.id',
                    'ots.nombre as nombre',
                )
                ->get();

            $result['otrosi'] = $cliente_otrosi;

            $cliente_convenio_banco = ClienteConvenioBanco::join('gen_bancos as ban', 'ban.cod_ban', '=', 'usr_app_cliente_convenio_bancos.convenio_banco_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_cliente_convenio_bancos.convenio_banco_id as id',
                    'ban.nom_ban as nombre',
                )
                ->get();

            $result['convenios_banco'] = $cliente_convenio_banco;

            $cliente_tipo_contrato = ClienteTipoContrato::join('rhh_tipcon as tcon', 'tcon.tip_con', '=', 'usr_app_cliente_tipos_contrato.tipo_contrato_id')
                ->where('cliente_id', '=', $id)
                ->select(
                    'usr_app_cliente_tipos_contrato.tipo_contrato_id as id',
                    'tcon.nom_con as nombre',
                )
                ->get();
            $result['tipos_contrato'] = $cliente_tipo_contrato;


            $cliente_laboratorio = ClienteLaboratorio::join('usr_app_ciudad_laboraorio as ciulab', 'ciulab.id', '=', 'usr_app_cliente_laboraorio.laboratorio_id')
                ->join('usr_app_municipios as mun', 'mun.id', '=', 'ciulab.ciudad_id')
                ->join('usr_app_departamentos as dep', 'dep.id', '=', 'mun.departamento_id')
                ->join('usr_app_paises as pais', 'pais.id', '=', 'dep.pais_id')
                ->where('usr_app_cliente_laboraorio.cliente_id', '=', $id)
                ->select(
                    'ciulab.id',
                    'ciulab.ciudad_id',
                    'ciulab.laboratorio as nombre',
                    'mun.id as municipio_id',
                    'mun.nombre as municipio',
                    'dep.id as departamento_id',
                    'dep.nombre as departamento',
                    'pais.id as pais_id',
                    'pais.nombre as pais',
                )
                ->get();
            $ubicacion_laboratorio = [];

            $cliente_laboratorio = $cliente_laboratorio->map(function ($item) use (&$ubicacion_laboratorio) {
                $pais = $item['pais'];
                $departamento = $item['departamento'];
                $municipio = $item['municipio'];
                $pais_id = $item['pais_id'];
                $departamento_id = $item['departamento_id'];
                $municipio_id = $item['municipio_id'];

                $ubicacion_laboratorio[] = [
                    'pais' => $pais,
                    'departamento' => $departamento,
                    'municipio' => $municipio,
                    'pais_id' => $pais_id,
                    'departamento_id' => $departamento_id,
                    'municipio_id' => $municipio_id,
                ];

                unset($item['pais'], $item['departamento'], $item['municipio'], $item['pais_id'], $item['departamento_id'], $item['municipio_id']);
                return $item;
            });

            $result['laboratorios_agregados'] = $cliente_laboratorio;
            $result['ubicacion_laboratorio'] = $ubicacion_laboratorio;

            if (!$asJson) {
                return $result;
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return $e;
        }
    }


    public function filtro($cadena)
    {
        // $objeto = (object) [
        //     'mensaje' => 'Filtrando empresas',
        //     'componente' => 'navbar/debida-diligencia/clientes'
        // ];
        // event(new EventoPrueba2($objeto));
        try {
            $consulta = base64_decode($cadena);
            $valores = explode("/", $consulta);
            $campo = $valores[0];
            $operador = $valores[1];
            $valor = $valores[2];
            $valor2 = isset($valores[3]) ? $valores[3] : null;

            // return $campo."".$valor;

            $query = cliente::join('gen_vendedor as ven', 'ven.cod_ven', '=', 'usr_app_clientes.vendedor_id')
                ->leftJoin('usr_app_estados_firma as estf', 'estf.id', '=', 'usr_app_clientes.estado_firma_id')
                ->select(
                    'usr_app_clientes.id',
                    DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
                    'usr_app_clientes.razon_social as nombre',
                    'usr_app_clientes.numero_identificacion',
                    'usr_app_clientes.nit',
                    'ven.nom_ven as vendedor',
                    'usr_app_clientes.telefono_empresa',
                    'usr_app_clientes.created_at',
                    'estf.nombre as nombre_estado_firma',
                    'estf.color as color_estado_firma'
                )
                ->orderby('id', 'DESC');


            switch ($operador) {
                case 'Contiene':
                    if ($campo == "vendedor") {
                        $query->where('ven.nom_ven', 'like', '%' . $valor . '%');
                    } else if ($campo == "nombre_estado_firma") {
                        $query->where('estf.nombre', 'like', '%' . $valor . '%');
                    } else {
                        $query->where($campo, 'like', '%' . $valor . '%');
                    }
                    break;
                case 'Igual a':
                    if ($campo == "vendedor") {
                        $query->where('ven.nom_ven', '=', $valor);
                    } else if ($campo == "nombre_estado_firma") {
                        $query->where('estf.id', '=', $valor);
                    } else {
                        $query->where($campo, '=', $valor);
                    }
                    break;
                case 'Igual a fecha':
                    $query->whereDate("usr_app_clientes." . $campo, '=', $valor);
                    break;
                case 'Entre':
                    $query->whereDate("usr_app_clientes." . $campo, '>=', $valor)
                        ->whereDate("usr_app_clientes." . $campo, '<=', $valor2);
                    break;
            }

            $result = $query->paginate();
            return response()->json($result);
        } catch (\Exception $e) {
            return $e;
        }
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        DB::beginTransaction();

        try {
            // $request = $request[0];
            $actividad_ciiu = $this->actividades_ciiu($request['actividad_ciiu']);
            $cliente = new cliente;

            // encabezado paraa el formato del contrato
            $cliente->codigo_documento = 'FEGC-01-02';
            $cliente->fecha_documento = '21/05/2024';
            $cliente->version_documento = '21';
            // fin encabezado paraa el formato del contrato
            $cliente->operacion_id = $request['operacion'] == '' ? 1 : $request['operacion'];
            $cliente->tipo_persona_id = $request['tipo_persona'];
            $cliente->digito_verificacion = $request['digito_verificacion'];
            $cliente->razon_social = $request['razon_social'];
            $cliente->periodicidad_liquidacion_id = $request['periodicidad_liquidacion_id'];
            $cliente->tipo_identificacion_id = $request['tipo_identificacion'] == '' ? 0 : $request['tipo_identificacion'];
            $cliente->numero_identificacion = $request['numero_identificacion'];
            $cliente->fecha_exp_documento = $request['fecha_expedicion'];
            $cliente->contratacion_directa = $request['contratacion_directa'];
            $cliente->atraccion_seleccion = $request['atraccion_seleccion'];
            $cliente->nit = $request['nit'];
            $cliente->fecha_constitucion = $request['fecha_constitucion'];
            $cliente->actividad_ciiu_id = $actividad_ciiu->id;
            $cliente->estrato_id = $request['estrato'];
            $cliente->municipio_id = $request['municipio'];
            $cliente->municipio_rut_id = $request['municipio_rut'];
            $cliente->dirección_rut = $request['direccion_rut'];
            $cliente->direccion_empresa = $request['direccion_empresa'];
            $cliente->contacto_empresa = $request['contacto_empresa'];
            $cliente->correo_empresa = $request['correo_electronico'];
            $cliente->telefono_empresa = $request['telefono_empresa'];
            $cliente->celular_empresa = $request['numero_celular'];
            $cliente->sociedad_comercial_id = $request['sociedad_comercial'];
            $cliente->otra = $request['otra_cual'];
            $cliente->acuerdo_comercial = $request['acuerdo_comercial'];
            $cliente->aiu_negociado = $request['aiu_negociado'];
            $cliente->plazo_pago = $request['plazo_pago'];
            $cliente->vendedor_id = $request['vendedor'] == '' ? "0  " : $request['vendedor'];
            $cliente->numero_empleados = $request['empleados_empresa'];
            $cliente->jornada_laboral_id = $request['jornada_laboral'] == '' ? 1 : $request['jornada_laboral'];
            $cliente->rotacion_personal_id = $request['rotacion_personal'] == '' ? 1 : $request['rotacion_personal'];
            $cliente->riesgo_cliente_id = $request['riesgo_cliente'] == '' ? 1 : $request['riesgo_cliente'];
            $cliente->junta_directiva = $request['junta_directiva'];
            $cliente->responsable_inpuesto_ventas = $request['responsable_inpuesto_ventas'];
            $cliente->correo_facturacion_electronica = $request['correo_factura_electronica'];
            $cliente->sucursal_facturacion_id = $request['sucursal_facturacion'] == '' ? '0' : $request['sucursal_facturacion'];
            $cliente->declaraciones_autorizaciones = $request['declaraciones_autorizaciones']  == 0 ? 0 : 1;
            $cliente->tratamiento_datos_personales = $request['tratamiento_datos_personales'];
            $cliente->operaciones_internacionales = $request['operaciones_internacionales'];
            $cliente->tipo_cliente_id = $request['tipo_cliente_id'];
            $cliente->tipo_proveedor_id = $request['tipo_proveedor_id'] == '' ? 1 : $request['tipo_proveedor_id'];
            $cliente->municipio_prestacion_servicio_id = $request['municipio_prestacion_servicio'];
            $cliente->empresa_extranjera = $request['empresa_extranjera'];
            $cliente->empresa_en_exterior = $request['empresa_exterior'];
            $cliente->vinculos_empresa = $request['vinculos_empresa'];
            $cliente->numero_empleados_directos = $request['numero_empleados_directos'];
            $cliente->vinculado_empresa_temporal = $request['personal_vinculado_temporal'];
            $cliente->visita_presencial = $request['visita_presencial'];
            $cliente->facturacion_contacto = $request['facturacion_contacto'];
            $cliente->facturacion_cargo = $request['facturacion_cargo'];
            $cliente->facturacion_telefono = $request['facturacion_telefono'];
            $cliente->facturacion_celular = $request['facturacion_celular'];
            $cliente->facturacion_correo = $request['facturacion_correo'];
            $cliente->facturacion_factura_unica = $request['facturacion_factura'];
            $cliente->facturacion_fecha_corte = $request['facturacion_fecha_corte'];
            $cliente->facturacion_encargado_factura = $request['facturacion_encargado_factura'];
            $cliente->requiere_anexo_factura = $request['anexo_factura'];
            $cliente->trabajo_alto_riesgo = $request['trabajo_alto_riesgo'];
            $cliente->accidentalidad = $request['accidentalidad'];
            $cliente->encargado_sst = $request['encargado_sst'];
            $cliente->nombre_encargado_sst = $request['nombre_encargado_sst'];
            $cliente->cargo_encargado_sst = $request['cargo_encargado_sst'];
            $cliente->induccion_entrenamiento = $request['induccion_entrenamiento'];
            $cliente->entrega_dotacion = $request['entrega_dotacion'];
            $cliente->evaluado_arl = $request['evaluado_arl'];
            $cliente->entrega_epp = $request['entrega_epp'];
            $cliente->contratacion_contacto = $request['contratacion_contacto'];
            $cliente->contratacion_cargo = $request['contratacion_cargo'];
            $cliente->contratacion_telefono = $request['contratacion_telefono'];
            $cliente->contratacion_celular = $request['contratacion_celular'];
            $cliente->contratacion_correo = $request['contratacion_correo_electronico'];
            $cliente->contratacion_hora_ingreso = $request['contratacion_hora_ingreso'];
            $cliente->contratacion_manipulacion_alimentos = $request['contratacion_manipulacion_alimentos'];
            $cliente->contratacion_hora_confirmacion = $request['contratacion_confirma_ingreso'];
            $cliente->contratacion_tallas_uniforme = $request['contratacion_tallas_uniforme'];
            $cliente->contratacion_suministra_transporte = $request['contratacion_suministra_transporte'];
            $cliente->contratacion_suministra_alimentacion = $request['contratacion_suministra_alimentacion'];
            $cliente->contratacion_pago_efectivo = $request['contratacion_pago_efectivo'];
            $cliente->contratacion_carnet_corporativo = $request['contratacion_carnet_corporativo'];
            $cliente->contratacion_pagos_31 = $request['contratacion_pagos_31'];
            if (
                $request->estado_firma_id
                == ''
            ) {
                $cliente->estado_firma_id = 1;
            } else {
                $cliente->estado_firma_id = $request->estado_firma_id;
            }
            $cliente->responsable = $request->responsable;
            $cliente->responsable_id = $request->responsable_id;
            $cliente->contratacion_observacion = $request['contratacion_observacion'];
            $cliente->save();


            $seguimiento_estado = new ClientesSeguimientoEstado;
            $seguimiento_estado->responsable_inicial =  $user->nombres . ' ' . $user->apellidos;
            $seguimiento_estado->responsable_final = $cliente->responsable = $request->responsable;
            $seguimiento_estado->estados_firma_inicial =  $cliente->estado_firma_id;
            $seguimiento_estado->estados_firma_final =    $cliente->estado_firma_id;
            $seguimiento_estado->cliente_id =   $cliente->id;
            $seguimiento_estado->actualiza_registro =   $user->nombres . ' ' .  $user->apellidos;
            $seguimiento_estado->oportuno = "2";
            $seguimiento_estado->save();
            $this->eventoSocket($request->responsable_id);

            $operacion = new HistoricoOperacionesDDModel();
            $operacion->cliente_id =  $cliente->id;
            $operacion->tipo_operacion_id = $cliente->operacion_id;
            $operacion->nombre_usuario_actualiza = $user->nombres . ' ' . $user->apellidos;
            $operacion->save();

            $seguimiento = new ClientesSeguimientoGuardado();
            $seguimiento->estado_firma_id = $cliente->estado_firma_id;
            $seguimiento->usuario = $user->nombres . ' ' . $user->apellidos;
            $seguimiento->cliente_id = $cliente->id;
            $seguimiento->save();

            $contador = 0;
            foreach ($request['cargos2'] as $item) {
                if ($item['cargo_id'] != '' || $item['riesgo_laboral_id'] != '') {
                    $cargo = new cargo2;
                    $cargo->cargo_id = $item['cargo_id'];
                    $cargo->riesgo_laboral_id = $item['riesgo_laboral_id'];
                    $cargo->funcion_cargo = $item['funcion_cargo'];
                    $cargo->cliente_id = $cliente->id;
                    $cargo->save();


                    foreach ($request['cargos2'][$contador]['examenes'] as $item) {
                        if ($item['id'] != '') {
                            $cargoExamen = new Cargo2Examen;
                            $cargoExamen->examen_id = $item['id'];
                            $cargoExamen->cargo_id = $cargo->id;
                            $cargoExamen->save();
                        }
                    }

                    // Se eliminan los requisitos del formulario
                    foreach ($request['cargos2'][$contador]['recomendaciones'] as $item) {
                        if ($item['id'] != '') {
                            $cargoRecomendacion = new Cargo2Recomendacion;
                            $cargoRecomendacion->recomendacion_id = $item['id'];
                            $cargoRecomendacion->cargo_id = $cargo->id;
                            $cargoRecomendacion->save();
                        }
                    }
                    $contador++;
                }
            }



            foreach ($request['accionistas'] as $item) {
                // if ($item['socio'] != '' || $item['tipo_identificacion'] != '' || $item['identificacion'] != '' || $item['participacion'] != '') {
                $accionista = new Accionista;
                $accionista->accionista = $item['socio'];
                $accionista->tipo_identificacion_id = $item['tipo_identificacion_id'];
                $accionista->identificacion = $item['identificacion'];
                $accionista->participacion = $item['participacion'];
                $accionista->cliente_id = $cliente->id;
                $accionista->save();
                // }
            }

            foreach ($request['representantes_legales'] as $item) {
                if ($item['nombre'] != '' || $item['tipo_identificacion'] != '' || $item['identificacion'] != '' || $item['correo'] != '' || $item['telefono'] != '' || $item['ciudad_expedicion'] != '') {
                    $RepresentanteLegal = new RepresentanteLegal;
                    $RepresentanteLegal->nombre = $item['nombre'];
                    $RepresentanteLegal->tipo_identificacion_id = $item['tipo_identificacion'];
                    $RepresentanteLegal->identificacion = $item['identificacion'];
                    $RepresentanteLegal->correo_electronico = $item['correo'];
                    $RepresentanteLegal->telefono = $item['telefono'];
                    $RepresentanteLegal->municipio_expedicion_id = $item['municipio_id'];
                    $RepresentanteLegal->cliente_id = $cliente->id;
                    $RepresentanteLegal->save();
                }
            }

            foreach ($request['miembros_Junta'] as $item) {
                if ($item['nombre'] != '' || $item['tipo_identificacion_id'] != '' || $item['identificacion']) {
                    $MiembroJunta = new MiembroJunta;
                    $MiembroJunta->nombre = $item['nombre'];
                    $MiembroJunta->tipo_identificacion_id = $item['tipo_identificacion_id'];
                    $MiembroJunta->identificacion = $item['identificacion'];
                    $MiembroJunta->cliente_id = $cliente->id;
                    $MiembroJunta->save();
                }
            }

            if ($request['calidad_tributaria'][0]['opcion'] != '' ||  $request['calidad_tributaria'][1]['opcion'] != '' || $request['calidad_tributaria'][2]['opcion'] != '') {
                $CalidadTributaria = new CalidadTributaria;
                $CalidadTributaria->gran_contribuyente = $request['calidad_tributaria'][0]['opcion'];
                $CalidadTributaria->resolucion_gran_contribuyente = $request['calidad_tributaria'][0]['numero_resolucion'];
                $CalidadTributaria->fecha_gran_contribuyente = $request['calidad_tributaria'][0]['fecha'];
                $CalidadTributaria->auto_retenedor = $request['calidad_tributaria'][1]['opcion'];
                $CalidadTributaria->resolucion_auto_retenedor = $request['calidad_tributaria'][1]['numero_resolucion'];
                $CalidadTributaria->fecha_auto_retenedor = $request['calidad_tributaria'][1]['fecha'];
                $CalidadTributaria->exento_impuesto_rent = $request['calidad_tributaria'][2]['opcion'];
                $CalidadTributaria->resolucion_exento_impuesto_rent = $request['calidad_tributaria'][2]['numero_resolucion'];
                $CalidadTributaria->fecha_exento_impuesto_rent = $request['calidad_tributaria'][2]['fecha'];
                $CalidadTributaria->cliente_id = $cliente->id;
                $CalidadTributaria->save();
            }

            // if ($request['nombre_completo_contador'] != '' || $request['tipo_identificacion_contador'] != '' || $request['identificacion_contador'] != '' || $request['telefono_contador'] != '') {
            $Contador = new Contador;
            $Contador->nombre = $request['nombre_completo_contador'];
            $Contador->tipo_identificacion_id = $request['tipo_identificacion_contador'];
            $Contador->identificacion = $request['identificacion_contador'];
            $Contador->telefono = $request['telefono_contador'];
            $Contador->cliente_id = $cliente->id;
            $Contador->save();
            // }

            // if ($request['nombre_completo_tesorero'] != '' || $request['telefono_tesorero'] != '' || $request['correo_tesorero'] != '') {
            $Tesorero = new Tesorero;
            $Tesorero->nombre = $request['nombre_completo_tesorero'];
            $Tesorero->telefono = $request['telefono_tesorero'];
            $Tesorero->correo = $request['correo_tesorero'];
            $Tesorero->cliente_id = $cliente->id;
            $Tesorero->save();
            // }

            if ($request['ingreso_mensual'] != '' || $request['otros_ingresos'] != '' || $request['total_ingresos'] != '' || $request['costos_gastos'] != '' || $request['detalle_otros_ingresos'] != '' || $request['reintegro_costos'] != '' || $request['activos'] != '' || $request['pasivos'] != '' || $request['patrimonio'] != '') {
                $DatoFinanciero = new DatoFinanciero;
                $DatoFinanciero->ingreso_mensual = $request['ingreso_mensual'];
                $DatoFinanciero->otros_ingresos = $request['otros_ingresos'];
                $DatoFinanciero->total_ingresos = $request['total_ingresos'];
                $DatoFinanciero->costos_gastos_mensual = $request['costos_gastos'];
                $DatoFinanciero->detalle_otros_ingresos = $request['detalle_otros_ingresos'];
                $DatoFinanciero->reintegro_costos_gastos = $request['reintegro_costos'];
                $DatoFinanciero->activos = $request['activos'];
                $DatoFinanciero->pasivos = $request['pasivos'];
                $DatoFinanciero->patrimonio = $request['patrimonio'];
                $DatoFinanciero->cliente_id = $cliente->id;
                $DatoFinanciero->save();
            }


            $origenFondo = new OrigenFondo();
            $origenFondo->tipo_origen_fondos_id = $request['tipo_origen_fondo'];
            $origenFondo->otro_origen = $request['otro_tipo_origen_fondos'];
            $origenFondo->tipo_origen_medios_id = $request['tipo_origen_medios'];
            $origenFondo->tipo_origen_medios2_id = $request['otro_tipo_origen_medios'];
            $origenFondo->alto_manejo_efectivo = $request['alto_manejo_efectivo'];
            $origenFondo->cliente_id = $cliente->id;
            $origenFondo->save();


            if ($request['tipo_operacion_internacional'] != '') {
                $OperacionIternacional = new OperacionIternacional;
                $OperacionIternacional->tipo_operaciones_id = $request['tipo_operacion_internacional'];
                $OperacionIternacional->cliente_id = $cliente->id;
                $OperacionIternacional->save();
            }

            foreach ($request['referencias_bancarias'] as $item) {
                // if ($item['numero_cuenta'] != '' || $item['tipo_cuenta'] != '' ||  $item['sucursal'] != '' || $item['telefono'] != '' || $item['contacto'] != '' || $item['banco'] != '') {
                $ReferenciaBancaria = new ReferenciaBancaria;
                $ReferenciaBancaria->numero_cuenta = $item['numero_cuenta'];
                $ReferenciaBancaria->tipo_cuenta_id = $item['tipo_cuenta'];
                $ReferenciaBancaria->sucursal = $item['sucursal'];
                $ReferenciaBancaria->telefono = $item['telefono'];
                $ReferenciaBancaria->contacto = $item['contacto'];
                $ReferenciaBancaria->banco_id = $item['banco_id'];
                $ReferenciaBancaria->cliente_id = $cliente->id;
                $ReferenciaBancaria->save();
                // }
            }

            foreach ($request['personas_expuestas'] as $item) {
                $personasExpuestas = new PersonasExpuestas;
                $personasExpuestas->nombre = $item['nombre'];
                $personasExpuestas->numero_identificacion = $item['identificacion'];
                $personasExpuestas->tipo_identificacion_id = $item['tipo_identificacion_id'];
                $personasExpuestas->parentesco = $item['parentesco'];
                $personasExpuestas->cliente_id = $cliente->id;
                $personasExpuestas->save();
            }

            foreach ($request['referencias_comerciales'] as $item) {
                $ReferenciaComercial = new ReferenciaComercial;
                $ReferenciaComercial->razon_social = $item['nombre'];
                $ReferenciaComercial->contacto = $item['contacto'];
                $ReferenciaComercial->telefono = $item['telefono'];
                $ReferenciaComercial->cliente_id = $cliente->id;
                $ReferenciaComercial->save();
            }

            foreach ($request['elementos_epp'] as $index => $item) {
                if ($item == true) {
                    $cliente_epp = new ClienteEpp;
                    $cliente_epp->epp_id = $index;
                    $cliente_epp->cliente_id = $cliente->id;
                    $cliente_epp->save();
                }
            }

            foreach ($request['otros_si_agregados'] as $item) {
                $Cliente_otrosi = new ClienteOtroSi;
                $Cliente_otrosi->otro_si_id = $item['id'];
                $Cliente_otrosi->cliente_id = $cliente->id;
                $Cliente_otrosi->save();
            }

            foreach ($request['tipos_contratos_agregados'] as $item) {
                $Cliente_tipo_contrato = new ClienteTipoContrato;
                $Cliente_tipo_contrato->tipo_contrato_id = $item['id'];
                $Cliente_tipo_contrato->cliente_id = $cliente->id;
                $Cliente_tipo_contrato->save();
            }

            foreach ($request['bancos_agregados'] as $item) {
                $Cliente_convenio_banco = new ClienteConvenioBanco;
                $Cliente_convenio_banco->convenio_banco_id = $item['id'];
                $Cliente_convenio_banco->cliente_id = $cliente->id;
                $Cliente_convenio_banco->save();
            }

            foreach ($request['laboratorios_medicos'] as $item) {
                $cliente_laboratorio = new ClienteLaboratorio;
                $cliente_laboratorio->laboratorio_id = $item['id'];
                $cliente_laboratorio->cliente_id = $cliente->id;
                $cliente_laboratorio->save();
            }

            DB::commit();
            return response()->json(['status' => '200', 'message' => 'ok', 'client' => $cliente->id]);
        } catch (\Exception $e) {
            // Revertir la transacción si se produce alguna excepción
            DB::rollback();
            return $e;
            return response()->json(['status' => 'error', 'message' => 'Error al guardar formulario, por favor verifique el llenado de todos los campos e intente nuevamente']);
        }
    }

    public function actividades_ciiu($codigo)
    {
        $result = ActividadCiiu::select(
            'id'
        )
            ->where('codigo_actividad', '=', $codigo)
            ->first();
        return $result;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $id_cliente)
    {

        try {
            $result = DocumentoCliente::where('cliente_id', '=', $id_cliente)
                ->get();
            $documentos = $request->all();
            $value = '';
            $id = '';
            $ids = [];
            $rutas = [];

            $directorio = public_path('upload/');
            $archivos = glob($directorio . '*');
            foreach ($archivos as $archivo) {
                $nombreArchivo = basename($archivo);

                if (strpos($nombreArchivo, '_' . $id_cliente . '_') !== false) {
                    unlink($archivo);
                }
            }
            foreach ($result as $item) {
                $archivo = DocumentoCliente::find($item->id);
                $archivo->delete();
            }

            foreach ($documentos as $item) {
                $contador = 0;
                if (!is_numeric($item)) {
                    $nombreArchivoOriginal = $item->getClientOriginalName();
                    $nuevoNombre = '_' . $id_cliente . "_" . $nombreArchivoOriginal;

                    $carpetaDestino = './upload/';
                    $item->move($carpetaDestino, $nuevoNombre);
                    $item = ltrim($carpetaDestino, '.') . $nuevoNombre;
                    array_push($rutas, $item);
                    $value .= $item . ' ';
                } else {
                    array_push($ids, $item);
                    $id .= $item . ' ';
                }
                $contador++;
            }
            for ($i = 0; $i < count($ids); $i++) {
                $documento = new DocumentoCliente;
                $documento->tipo_documento_id = $ids[$i];
                $documento->ruta = $rutas[$i];
                $documento->cliente_id = $id_cliente;
                $documento->save();
            }
            return response()->json(['status' => 'success', 'message' => 'Formulario guardado exitosamente']);
        } catch (\Throwable $th) {
            //throw $th;
            // $cliente = cliente::find($id_cliente);
            // $cliente->delete();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intente nuevamente, si el problema persiste por favor contacte al administrador del sitio']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $estado__nuevo_id = $request->estado_firma_id;
        $user = auth()->user();
        $permisos = $this->validaPermiso();
        $cliente = Cliente::where('usr_app_clientes.id', '=', $id)
            ->select()
            ->first();

        try {
            if ($cliente->operacion_id != $request['operacion']) {
                $operacion = new HistoricoOperacionesDDModel();
                $operacion->cliente_id =  $cliente->id;
                $operacion->tipo_operacion_id = $request['operacion'];
                $operacion->nombre_usuario_actualiza = $user->nombres . ' ' . $user->apellidos;
                $operacion->save();
            }
            $cliente->acuerdo_comercial = $request['acuerdo_comercial'];
            $actividad_ciiu = $this->actividades_ciiu($request['actividad_ciiu']);
            $cliente->operacion_id = $request['operacion'];
            $cliente->novedad_servicio = $request['novedad_servicio'];
            $cliente->usuario_corregir_id = $request['usuario_corregir_id'];
            $cliente->afectacion_servicio = $request['afectacion_servicio'];
            $cliente->contratacion_directa = $request['contratacion_directa'];
            $cliente->atraccion_seleccion = $request['atraccion_seleccion'];
            $cliente->tipo_persona_id = $request['tipo_persona'];
            $cliente->tipo_identificacion_id = $request['tipo_identificacion'];
            $cliente->numero_identificacion = $request['numero_identificacion'];
            $cliente->fecha_exp_documento = $request['fecha_expedicion'];
            $cliente->nit = $request['nit'];
            $cliente->digito_verificacion = $request['digito_verificacion'];
            $cliente->razon_social = $request['razon_social'];
            $cliente->periodicidad_liquidacion_id = $request['periodicidad_liquidacion_id'];
            $cliente->fecha_constitucion = $request['fecha_constitucion'];
            $cliente->actividad_ciiu_id = $actividad_ciiu->id;
            $cliente->estrato_id = $request['estrato'];
            $cliente->municipio_id = $request['municipio'];
            $cliente->direccion_empresa = $request['direccion_empresa'];
            $cliente->contacto_empresa = $request['contacto_empresa'];
            $cliente->correo_empresa = $request['correo_electronico'];
            $cliente->telefono_empresa = $request['telefono_empresa'];
            $cliente->celular_empresa = $request['numero_celular'];
            $cliente->sociedad_comercial_id = $request['sociedad_comercial'];
            $cliente->otra = $request['otra_cual'];
            /* $cliente->acuerdo_comercial = $request['observaciones']; */
            $cliente->aiu_negociado = $request['aiu_negociado'];
            $cliente->plazo_pago = $request['plazo_pago'];
            $cliente->vendedor_id = $request['vendedor'];
            $cliente->numero_empleados = $request['empleados_empresa'];
            $cliente->jornada_laboral_id = $request['jornada_laboral'];
            $cliente->rotacion_personal_id = $request['rotacion_personal'];
            $cliente->riesgo_cliente_id = $request['riesgo_cliente'];
            $cliente->junta_directiva = $request['junta_directiva'];
            $cliente->responsable_inpuesto_ventas = $request['responsable_inpuesto_ventas'];
            $cliente->correo_facturacion_electronica = $request['correo_factura_electronica'];
            $cliente->sucursal_facturacion_id = $request['sucursal_facturacion'];
            $cliente->declaraciones_autorizaciones = $request['declaraciones_autorizaciones'];
            $cliente->tratamiento_datos_personales = $request['tratamiento_datos_personales'];
            $cliente->operaciones_internacionales = $request['operaciones_internacionales'];
            $cliente->tipo_cliente_id = $request['tipo_cliente_id'];
            $cliente->tipo_proveedor_id = $request['tipo_proveedor_id'];
            $cliente->municipio_prestacion_servicio_id = $request['municipio_prestacion_servicio'];
            $cliente->empresa_extranjera = $request['empresa_extranjera'];
            $cliente->empresa_en_exterior = $request['empresa_exterior'];
            $cliente->vinculos_empresa = $request['vinculos_empresa'];
            $cliente->numero_empleados_directos = $request['numero_empleados_directos'];
            $cliente->vinculado_empresa_temporal = $request['personal_vinculado_temporal'];
            $cliente->visita_presencial = $request['visita_presencial'];
            $cliente->facturacion_contacto = $request['facturacion_contacto'];
            $cliente->facturacion_cargo = $request['facturacion_cargo'];
            $cliente->facturacion_telefono = $request['facturacion_telefono'];
            $cliente->facturacion_celular = $request['facturacion_celular'];
            $cliente->facturacion_correo = $request['facturacion_correo'];
            $cliente->facturacion_factura_unica = $request['facturacion_factura'];
            $cliente->facturacion_fecha_corte = $request['facturacion_fecha_corte'];
            $cliente->facturacion_encargado_factura = $request['facturacion_encargado_factura'];
            $cliente->requiere_anexo_factura = $request['anexo_factura'];
            $cliente->trabajo_alto_riesgo = $request['trabajo_alto_riesgo'];
            $cliente->accidentalidad = $request['accidentalidad'];
            $cliente->encargado_sst = $request['encargado_sst'];
            $cliente->nombre_encargado_sst = $request['nombre_encargado_sst'];
            $cliente->cargo_encargado_sst = $request['cargo_encargado_sst'];
            $cliente->induccion_entrenamiento = $request['induccion_entrenamiento'];
            $cliente->entrega_dotacion = $request['entrega_dotacion'];
            $cliente->evaluado_arl = $request['evaluado_arl'];
            $cliente->entrega_epp = $request['entrega_epp'];
            $cliente->contratacion_contacto = $request['contratacion_contacto'];
            $cliente->contratacion_cargo = $request['contratacion_cargo'];
            $cliente->contratacion_telefono = $request['contratacion_telefono'];
            $cliente->contratacion_celular = $request['contratacion_celular'];
            $cliente->contratacion_correo = $request['contratacion_correo_electronico'];
            $cliente->contratacion_hora_ingreso = $request['contratacion_hora_ingreso'];
            $cliente->contratacion_manipulacion_alimentos = $request['contratacion_manipulacion_alimentos'];
            $cliente->contratacion_hora_confirmacion = $request['contratacion_confirma_ingreso'];
            $cliente->contratacion_tallas_uniforme = $request['contratacion_tallas_uniforme'];
            $cliente->contratacion_suministra_transporte = $request['contratacion_suministra_transporte'];
            $cliente->contratacion_suministra_alimentacion = $request['contratacion_suministra_alimentacion'];
            $cliente->contratacion_pago_efectivo = $request['contratacion_pago_efectivo'];
            $cliente->contratacion_carnet_corporativo = $request['contratacion_carnet_corporativo'];
            $cliente->contratacion_pagos_31 = $request['contratacion_pagos_31'];
            $cliente->contratacion_observacion = $request['contratacion_observacion'];
            $cliente->dirección_rut = $request['direccion_rut'];
            /*    $cliente->estado_firma_id = $request->estado_firma_id;
            $cliente->responsable = $request->responsable;
            $cliente->responsable_id = $request->responsable_id; */
            $cliente->save();


            $ids = [];
            array_push($ids, $id);
            if ($cliente->responsable_id != null && $cliente->responsable_id != $user->id && !in_array('31', $permisos)) {

                $seguimiento = new ClientesSeguimientoGuardado;
                $seguimiento->estado_firma_id = $request->estado_id;
                $seguimiento->usuario =  $user->nombres . ' ' . str_replace("null", "", $user->apellidos);
                $seguimiento->cliente_id = $id;
                $seguimiento->save();

                return response()->json(['status' => '200', 'message' => 'ok', 'registro_ingreso_id' => $ids]);
            }

            $seguimiento = new ClientesSeguimientoGuardado();
            $seguimiento->estado_firma_id = $request->estado_firma_id;
            $seguimiento->usuario =  $user->nombres . ' ' . str_replace("null", "", $user->apellidos);
            $seguimiento->cliente_id = $id;
            $seguimiento->save();

            $responsable_inicial = str_replace("null", "", $cliente->responsable);

            $estado_inicial = $cliente->estado_firma_id;



            if ($estado__nuevo_id != $estado_inicial ||  $cliente->responsable == null || $cliente->responsable_id != $request->responsable_id) {
                $this->actualizaestadofirma($id, $estado__nuevo_id, $request->responsable_id, $responsable_inicial, $estado_inicial);
            }/* else if( $cliente->responsable_id != $request->responsable_id){
                $this->actualizaestadofirma($id, $estado__nuevo_id, $request->responsable_id, $responsable_inicial, $estado_inicial);
            } */


            /* else {
                $seguimiento_estado = new ClientesSeguimientoEstado();
                $seguimiento_estado->responsable_inicial =  $responsable_inicial;
                $seguimiento_estado->responsable_final = str_replace("null", "", $cliente->responsable);
                $seguimiento_estado->estados_firma_inicial = $estado_inicial;
                $seguimiento_estado->estados_firma_final =   $request->estado_firma_id;
                $seguimiento_estado->cliente_id =  $id;
                $seguimiento_estado->actualiza_registro =   $user->nombres . ' ' . str_replace("null", "", $user->apellidos);
                $seguimiento_estado->save(); 
            }
 */
            $cargo = Cargo2::where('cliente_id', '=', $id)
                ->select()
                ->get();
            $cargoExamen = Cargo2Examen::where('cargo_id', '=', $id)
                ->select()
                ->get();
            $cargoRecomendacion = Cargo2Recomendacion::where('cargo_id', '=', $id)
                ->select()
                ->get();
            foreach ($cargoExamen as $item) {
                $item->delete();
            }
            foreach ($cargoRecomendacion as $item) {
                $item->delete();
            }
            foreach ($cargo as $item) {
                $item->delete();
            }
            $contador = 0;
            foreach ($request['cargos2'] as $item) {
                if ($item['cargo_id'] != '' || $item['riesgo_laboral_id'] != '') {
                    $cargo = new cargo2;
                    $cargo->cargo_id = intval($item['cargo_id']);
                    $cargo->riesgo_laboral_id = $item['riesgo_laboral_id'];
                    $cargo->funcion_cargo = $item['funcion_cargo'];
                    $cargo->cliente_id = $cliente->id;
                    $cargo->save();


                    foreach ($request['cargos2'][$contador]['examenes'] as $item) {
                        if ($item['id'] != '') {
                            $cargoExamen = new Cargo2Examen;
                            $cargoExamen->examen_id = $item['id'];
                            $cargoExamen->cargo_id = $cargo->id;
                            $cargoExamen->save();
                        }
                    }

                    foreach ($request['cargos2'][$contador]['recomendaciones'] as $item) {
                        if ($item['id'] != '') {
                            $cargoRecomendacion = new Cargo2Recomendacion;
                            $cargoRecomendacion->recomendacion_id = $item['id'];
                            $cargoRecomendacion->cargo_id = $cargo->id;
                            $cargoRecomendacion->save();
                        }
                    }
                    $contador++;
                }
            }
            // foreach ($request['cargos2'] as $item) {
            //     if ($item['cargo'] != '' || $item['riesgo_laboral_id'] != '') {
            //         $cargo = new cargo2;
            //         $cargo->cargo_id = $item['cargo'];
            //         $cargo->riesgo_laboral_id = $item['riesgo_laboral_id'];
            //         $cargo->funcion_cargo = $item['funcion_cargo'];
            //         $cargo->cliente_id = $cliente->id;
            //         $cargo->save();


            //         foreach ($request['cargos2'][$contador]['examenes'] as $item) {
            //             if ($item['id'] != '') {
            //                 $cargoExamen = new Cargo2Examen;
            //                 $cargoExamen->examen_id = $item['id'];
            //                 $cargoExamen->cargo_id = $cargo->id;
            //                 $cargoExamen->save();
            //             }
            //         }

            //         // Se eliminan los requisitos del formulario
            //         foreach ($request['cargos2'][$contador]['recomendaciones'] as $item) {
            //             if ($item['id'] != '') {
            //                 $cargoRecomendacion = new Cargo2Recomendacion;
            //                 $cargoRecomendacion->recomendacion_id = $item['id'];
            //                 $cargoRecomendacion->cargo_id = $cargo->id;
            //                 $cargoRecomendacion->save();
            //             }
            //         }
            //         $contador++;
            //     }
            // }

            $accionista = Accionista::where('cliente_id', '=', $id)
                ->get();
            foreach ($accionista as $item) {
                $item->delete();
            }
            $cont = 0;
            foreach ($request['accionistas'] as $item) {
                if ($item['socio'] != '' || $item['tipo_identificacion_id'] != '' || $item['identificacion'] != '' || $item['participacion'] != '') {
                    $accionista = new Accionista;
                    $accionista->accionista = $item['socio'];
                    $accionista->tipo_identificacion_id = $item['tipo_identificacion_id'];
                    $accionista->identificacion = $item['identificacion'];
                    $accionista->participacion = $item['participacion'];
                    $accionista->cliente_id = $id;
                    $accionista->save();
                }
            }

            $RepresentanteLegal = RepresentanteLegal::where('cliente_id', '=', $id)
                ->select()
                ->get();
            foreach ($RepresentanteLegal as $item) {
                $item->delete();
            }
            $cont = 0;
            foreach ($request['representantes_legales'] as $item) {
                if ($item['nombre'] != '' || $item['tipo_identificacion'] != '' || $item['identificacion'] != '' || $item['correo'] != '' || $item['telefono'] != '' || $item['municipio_id'] != '') {
                    $RepresentanteLegal = new RepresentanteLegal;
                    $RepresentanteLegal->nombre = $item['nombre'];
                    $RepresentanteLegal->tipo_identificacion_id = $item['tipo_identificacion'];
                    $RepresentanteLegal->identificacion = $item['identificacion'];
                    $RepresentanteLegal->correo_electronico = $item['correo'];
                    $RepresentanteLegal->telefono = $item['telefono'];
                    $RepresentanteLegal->municipio_expedicion_id = $item['municipio_id'];
                    $RepresentanteLegal->cliente_id = $id;
                    $RepresentanteLegal->save();
                }
            }


            $MiembroJunta = MiembroJunta::where('cliente_id', '=', $id)
                ->select()
                ->get();
            foreach ($MiembroJunta as $item) {
                $item->delete();
            }
            $cont = 0;
            foreach ($request['miembros_Junta'] as $item) {
                if ($item['nombre'] != '' || $item['tipo_identificacion_id'] != '' || $item['identificacion']) {
                    $MiembroJunta = new MiembroJunta;
                    $MiembroJunta->nombre = $item['nombre'];
                    $MiembroJunta->tipo_identificacion_id = $item['tipo_identificacion_id'];
                    $MiembroJunta->identificacion = $item['identificacion'];
                    $MiembroJunta->cliente_id = $id;
                    $MiembroJunta->save();
                }
            }

            $CalidadTributaria = CalidadTributaria::where('cliente_id', '=', $id)
                ->select()
                ->first();
            if ($CalidadTributaria == null) {
                if ($request['calidad_tributaria'][0]['opcion'] != '' ||  $request['calidad_tributaria'][1]['opcion'] != '' || $request['calidad_tributaria'][2]['opcion'] != '') {
                    $CalidadTributaria = new CalidadTributaria;
                    $CalidadTributaria->gran_contribuyente = $request['calidad_tributaria'][0]['opcion'];
                    $CalidadTributaria->resolucion_gran_contribuyente = $request['calidad_tributaria'][0]['numero_resolucion'];
                    $CalidadTributaria->fecha_gran_contribuyente = $request['calidad_tributaria'][0]['fecha'];
                    $CalidadTributaria->auto_retenedor = $request['calidad_tributaria'][1]['opcion'];
                    $CalidadTributaria->resolucion_auto_retenedor = $request['calidad_tributaria'][1]['numero_resolucion'];
                    $CalidadTributaria->fecha_auto_retenedor = $request['calidad_tributaria'][1]['fecha'];
                    $CalidadTributaria->exento_impuesto_rent = $request['calidad_tributaria'][2]['opcion'];
                    $CalidadTributaria->resolucion_exento_impuesto_rent = $request['calidad_tributaria'][2]['numero_resolucion'];
                    $CalidadTributaria->fecha_exento_impuesto_rent = $request['calidad_tributaria'][2]['fecha'];
                    $CalidadTributaria->cliente_id = $cliente->id;
                    $CalidadTributaria->save();
                }
            } else {
                if ($request['calidad_tributaria'][0]['opcion'] != '' && $request['calidad_tributaria'][0]['opcion'] != 0 ||  $request['calidad_tributaria'][1]['opcion'] != '' && $request['calidad_tributaria'][1]['opcion'] != 0 || $request['calidad_tributaria'][2]['opcion'] != '' && $request['calidad_tributaria'][2]['opcion'] != 0) {
                    $CalidadTributaria->gran_contribuyente = $request['calidad_tributaria'][0]['opcion'];
                    $CalidadTributaria->resolucion_gran_contribuyente = $request['calidad_tributaria'][0]['numero_resolucion'];
                    $CalidadTributaria->fecha_gran_contribuyente = $request['calidad_tributaria'][0]['fecha'];
                    $CalidadTributaria->auto_retenedor = $request['calidad_tributaria'][1]['opcion'];
                    $CalidadTributaria->resolucion_auto_retenedor = $request['calidad_tributaria'][1]['numero_resolucion'];
                    $CalidadTributaria->fecha_auto_retenedor = $request['calidad_tributaria'][1]['fecha'];
                    $CalidadTributaria->exento_impuesto_rent = $request['calidad_tributaria'][2]['opcion'];
                    $CalidadTributaria->resolucion_exento_impuesto_rent = $request['calidad_tributaria'][2]['numero_resolucion'];
                    $CalidadTributaria->fecha_exento_impuesto_rent = $request['calidad_tributaria'][2]['fecha'];
                    $CalidadTributaria->cliente_id = $id;
                    $CalidadTributaria->save();
                }
            }

            $Contador = Contador::where('cliente_id', '=', $id)
                ->select()
                ->get();
            foreach ($Contador as $item) {
                $item->delete();
            }
            $cont = 0;
            if ($request['nombre_completo_contador'] != '' || $request['tipo_identificacion_contador'] != '' || $request['identificacion_contador'] != '' || $request['telefono_contador'] != '') {
                $Contador = new Contador;
                $Contador->nombre = $request['nombre_completo_contador'];
                $Contador->tipo_identificacion_id = $request['tipo_identificacion_contador'];
                $Contador->identificacion = $request['identificacion_contador'];
                $Contador->telefono = $request['telefono_contador'];
                $Contador->cliente_id = $id;
                $Contador->save();
            }

            $Tesorero = Tesorero::where('cliente_id', '=', $id)
                ->select()
                ->get();
            foreach ($Tesorero as $item) {
                $item->delete();
            }
            $cont = 0;
            // if ($request['nombre_completo_tesorero'] != '' || $request['telefono_tesorero'] != '' || $request['correo_tesorero'] != '') {
            $Tesorero = new Tesorero;
            $Tesorero->nombre = $request['nombre_completo_tesorero'];
            $Tesorero->telefono = $request['telefono_tesorero'];
            $Tesorero->correo = $request['correo_tesorero'];
            $Tesorero->cliente_id = $id;
            $Tesorero->save();
            // }

            $DatoFinanciero = DatoFinanciero::where('cliente_id', '=', $id)
                ->select()
                ->first();
            if ($DatoFinanciero == null) {
                $DatoFinanciero = new DatoFinanciero;
                $DatoFinanciero->ingreso_mensual = $request['ingreso_mensual'];
                $DatoFinanciero->otros_ingresos = $request['otros_ingresos'];
                $DatoFinanciero->total_ingresos = $request['total_ingresos'];
                $DatoFinanciero->costos_gastos_mensual = $request['costos_gastos'];
                $DatoFinanciero->detalle_otros_ingresos = $request['detalle_otros_ingresos'];
                $DatoFinanciero->reintegro_costos_gastos = $request['reintegro_costos'];
                $DatoFinanciero->activos = $request['activos'];
                $DatoFinanciero->pasivos = $request['pasivos'];
                $DatoFinanciero->patrimonio = $request['patrimonio'];
                $DatoFinanciero->cliente_id = $cliente->id;
                $DatoFinanciero->save();
            } else {
                if ($request['ingreso_mensual'] != '' || $request['otros_ingresos'] != '' || $request['total_ingresos'] != '' || $request['costos_gastos'] != '' || $request['detalle_otros_ingresos'] != '' || $request['reintegro_costos'] != '' || $request['activos'] != '' || $request['pasivos'] != '' || $request['patrimonio'] != '') {
                    $DatoFinanciero->ingreso_mensual = $request['ingreso_mensual'];
                    $DatoFinanciero->otros_ingresos = $request['otros_ingresos'];
                    $DatoFinanciero->total_ingresos = $request['total_ingresos'];
                    $DatoFinanciero->costos_gastos_mensual = $request['costos_gastos'];
                    $DatoFinanciero->detalle_otros_ingresos = $request['detalle_otros_ingresos'];
                    $DatoFinanciero->reintegro_costos_gastos = $request['reintegro_costos'];
                    $DatoFinanciero->activos = $request['activos'];
                    $DatoFinanciero->pasivos = $request['pasivos'];
                    $DatoFinanciero->patrimonio = $request['patrimonio'];
                    $DatoFinanciero->cliente_id = $id;
                    $DatoFinanciero->save();
                }
            }


            $origenFondo = OrigenFondo::where('cliente_id', '=', $id)
                ->select()
                ->first();
            if ($origenFondo == null) {
                $origenFondo = new OrigenFondo();
                $origenFondo->tipo_origen_fondos_id = $request['tipo_origen_fondo'];
                $origenFondo->otro_origen = $request['otro_tipo_origen_fondos'];
                $origenFondo->tipo_origen_medios_id = $request['tipo_origen_medios'];
                $origenFondo->tipo_origen_medios2_id = $request['otro_tipo_origen_medios'];
                $origenFondo->alto_manejo_efectivo = $request['alto_manejo_efectivo'];
                $origenFondo->cliente_id = $cliente->id;
                $origenFondo->save();
            } else {
                $origenFondo->tipo_origen_fondos_id = $request['tipo_origen_fondo'];
                $origenFondo->otro_origen = $request['otro_tipo_origen_fondos'];
                $origenFondo->tipo_origen_medios_id = $request['tipo_origen_medios'];
                $origenFondo->tipo_origen_medios2_id = $request['otro_tipo_origen_medios'];
                $origenFondo->alto_manejo_efectivo = $request['alto_manejo_efectivo'];
                $origenFondo->cliente_id = $id;
                $origenFondo->save();
            }


            $OperacionIternacional = OperacionIternacional::where('cliente_id', '=', $id)
                ->select()
                ->first();
            if ($OperacionIternacional == null) {
                $OperacionIternacional = new OperacionIternacional;
                $OperacionIternacional->tipo_operaciones_id = $request['tipo_operacion_internacional'];
                $OperacionIternacional->cliente_id = $cliente->id;
                $OperacionIternacional->save();
            } else {
                if ($request['tipo_operacion_internacional'] != '') {
                    $OperacionIternacional->tipo_operaciones_id = $request['tipo_operacion_internacional'];
                    $OperacionIternacional->cliente_id = $id;
                    $OperacionIternacional->save();
                }
            }

            $ReferenciaBancaria = ReferenciaBancaria::where('cliente_id', '=', $id)
                ->select()
                ->get();
            if ($ReferenciaBancaria == null) {
                foreach ($request['referencias_bancarias'] as $item) {
                    $ReferenciaBancaria = new ReferenciaBancaria;
                    $ReferenciaBancaria->numero_cuenta = $item['numero_cuenta'];
                    $ReferenciaBancaria->tipo_cuenta_id = $item['tipo_cuenta'];
                    $ReferenciaBancaria->sucursal = $item['sucursal'];
                    $ReferenciaBancaria->telefono = $item['telefono'];
                    $ReferenciaBancaria->contacto = $item['contacto'];
                    $ReferenciaBancaria->banco_id = $item['banco_id'];
                    $ReferenciaBancaria->cliente_id = $cliente->id;
                    $ReferenciaBancaria->save();
                }
            } else {
                foreach ($ReferenciaBancaria as $item) {
                    $item->delete();
                }
                $cont = 0;
                foreach ($request['referencias_bancarias'] as $item) {
                    if ($item['numero_cuenta'] != '' || $item['tipo_cuenta'] != '' ||  $item['sucursal'] != '' || $item['telefono'] != '' || $item['contacto'] != '' || $item['banco_id'] != '') {
                        $ReferenciaBancaria = new ReferenciaBancaria;
                        $ReferenciaBancaria->numero_cuenta = $item['numero_cuenta'];
                        $ReferenciaBancaria->tipo_cuenta_id = $item['tipo_cuenta'];
                        $ReferenciaBancaria->sucursal = $item['sucursal'];
                        $ReferenciaBancaria->telefono = $item['telefono'];
                        $ReferenciaBancaria->contacto = $item['contacto'];
                        $ReferenciaBancaria->banco_id = $item['banco_id'];
                        $ReferenciaBancaria->cliente_id = $id;
                        $ReferenciaBancaria->save();
                    }
                }
            }

            $personasExpuestas = PersonasExpuestas::where('cliente_id', '=', $id)
                ->select()
                ->get();
            if ($personasExpuestas == null) {
                foreach ($request['personas_expuestas'] as $item) {
                    $personasExpuestas = new PersonasExpuestas;
                    $personasExpuestas->nombre = $item['nombre'];
                    $personasExpuestas->numero_identificacion = $item['identificacion'];
                    $personasExpuestas->tipo_identificacion_id = $item['tipo_identificacion_id'];
                    $personasExpuestas->parentesco = $item['parentesco'];
                    $personasExpuestas->cliente_id = $cliente->id;
                    $personasExpuestas->save();
                }
            } else {
                foreach ($personasExpuestas as $item) {
                    $item->delete();
                }
                $cont = 0;
                foreach ($request['personas_expuestas'] as $item) {
                    if ($item['nombre'] != '' || $item['identificacion'] != '' ||  $item['tipo_identificacion_id'] != '' || $item['parentesco'] != '') {
                        $personasExpuestas = new PersonasExpuestas;
                        $personasExpuestas->nombre = $item['nombre'];
                        $personasExpuestas->numero_identificacion = $item['identificacion'];
                        $personasExpuestas->tipo_identificacion_id = $item['tipo_identificacion_id'];
                        $personasExpuestas->parentesco = $item['parentesco'];
                        $personasExpuestas->cliente_id = $id;
                        $personasExpuestas->save();
                    }
                }
            }

            $ReferenciaComercial = ReferenciaComercial::where('cliente_id', '=', $id)
                ->select()
                ->get();
            if ($ReferenciaComercial == null) {
                foreach ($request['referencias_comerciales'] as $item) {
                    $ReferenciaComercial = new ReferenciaComercial;
                    $ReferenciaComercial->razon_social = $item['nombre'];
                    $ReferenciaComercial->contacto = $item['contacto'];
                    $ReferenciaComercial->telefono = $item['telefono'];
                    $ReferenciaComercial->cliente_id = $cliente->id;
                    $ReferenciaComercial->save();
                }
            } else {
                foreach ($ReferenciaComercial as $item) {
                    $item->delete();
                }
                $cont = 0;
                foreach ($request['referencias_comerciales'] as $item) {
                    if ($item['nombre'] != '' || $item['contacto'] != '' || $item['telefono'] != '') {
                        $ReferenciaComercial = new ReferenciaComercial;
                        $ReferenciaComercial->razon_social = $item['nombre'];
                        $ReferenciaComercial->contacto = $item['contacto'];
                        $ReferenciaComercial->telefono = $item['telefono'];
                        $ReferenciaComercial->cliente_id = $id;
                        $ReferenciaComercial->save();
                    }
                }
            }

            $clientes_epps = ClienteEpp::where('cliente_id', $id)
                ->select()
                ->get();
            if ($clientes_epps == null) {
                foreach ($request['elementos_epp'] as $index => $item) {
                    if ($item == true) {
                        $cliente_epp = new ClienteEpp;
                        $cliente_epp->epp_id = $index;
                        $cliente_epp->cliente_id = $cliente->id;
                        $cliente_epp->save();
                    }
                }
            } else {
                if (count($clientes_epps) > 0) {
                    foreach ($clientes_epps as $item) {
                        $item->delete();
                    }
                }
                foreach ($request['elementos_epp'] as $index => $item) {
                    if ($item == true) {
                        $cliente_epp = new ClienteEpp;
                        $cliente_epp->epp_id = $index;
                        $cliente_epp->cliente_id = $cliente->id;
                        $cliente_epp->save();
                    }
                }
            }

            $ClienteConvenioBanco = ClienteConvenioBanco::where('cliente_id', $id)
                ->select()
                ->get();
            if ($ClienteConvenioBanco == null) {
                foreach ($request['bancos_agregados'] as $item) {
                    $Cliente_convenio_banco = new ClienteConvenioBanco;
                    $Cliente_convenio_banco->convenio_banco_id = $item['id'];
                    $Cliente_convenio_banco->cliente_id = $cliente->id;
                    $Cliente_convenio_banco->save();
                }
            } else {
                if (count($ClienteConvenioBanco) > 0) {
                    foreach ($ClienteConvenioBanco as $item) {
                        $item->delete();
                    }
                }

                foreach ($request['bancos_agregados'] as $item) {
                    $Cliente_convenio_banco = new ClienteConvenioBanco;
                    $Cliente_convenio_banco->convenio_banco_id = $item['id'];
                    $Cliente_convenio_banco->cliente_id = $cliente->id;
                    $Cliente_convenio_banco->save();
                }
            }

            $ClienteOtroSi = ClienteOtroSi::where('cliente_id', $id)
                ->select()
                ->get();
            if ($ClienteOtroSi == null) {
                foreach ($request['otros_si_agregados'] as $item) {
                    $Cliente_otrosi = new ClienteOtroSi;
                    $Cliente_otrosi->otro_si_id = $item['id'];
                    $Cliente_otrosi->cliente_id = $cliente->id;
                    $Cliente_otrosi->save();
                }
            } else {
                if (count($ClienteOtroSi) > 0) {
                    foreach ($ClienteOtroSi as $item) {
                        $item->delete();
                    }
                }
                foreach ($request['otros_si_agregados'] as $item) {
                    $Cliente_otrosi = new ClienteOtroSi;
                    $Cliente_otrosi->otro_si_id = $item['id'];
                    $Cliente_otrosi->cliente_id = $cliente->id;
                    $Cliente_otrosi->save();
                }
            }


            $ClienteTipoContrato = ClienteTipoContrato::where('cliente_id', $id)
                ->select()
                ->get();
            if ($ClienteTipoContrato == null) {
                foreach ($request['tipos_contratos_agregados'] as $item) {
                    $Cliente_tipo_contrato = new ClienteTipoContrato;
                    $Cliente_tipo_contrato->tipo_contrato_id = $item['id'];
                    $Cliente_tipo_contrato->cliente_id = $cliente->id;
                    $Cliente_tipo_contrato->save();
                }
            } else {
                if (count($ClienteTipoContrato) > 0) {
                    foreach ($ClienteTipoContrato as $item) {
                        $item->delete();
                    }
                }
                foreach ($request['tipos_contratos_agregados'] as $item) {
                    $Cliente_tipo_contrato = new ClienteTipoContrato;
                    $Cliente_tipo_contrato->tipo_contrato_id = $item['id'];
                    $Cliente_tipo_contrato->cliente_id = $cliente->id;
                    $Cliente_tipo_contrato->save();
                }
            }


            $cliente_laboratorio = ClienteLaboratorio::where('cliente_id', $id)
                ->select()
                ->get();
            if (count($cliente_laboratorio) <= 0) {
                foreach ($request['laboratorios_medicos'] as $item) {
                    $cliente_laboratorio = new ClienteLaboratorio;
                    $cliente_laboratorio->laboratorio_id = $item['id'];
                    $cliente_laboratorio->cliente_id = $cliente->id;
                    $cliente_laboratorio->save();
                }
            } else {
                if (count($cliente_laboratorio) > 0) {
                    foreach ($cliente_laboratorio as $item) {
                        $item->delete();
                    }
                }
                foreach ($request['laboratorios_medicos'] as $item) {
                    $cliente_laboratorio = new ClienteLaboratorio;
                    $cliente_laboratorio->laboratorio_id = $item['id'];
                    $cliente_laboratorio->cliente_id = $cliente->id;
                    $cliente_laboratorio->save();
                }
            }
            DB::commit();
            return response()->json(['status' => '200', 'message' => 'ok', 'client' => $cliente->id]);
        } catch (\Exception $e) {
            // Revertir la transacción si se produce alguna excepción
            DB::rollback();
            return $e;
            return response()->json(['status' => 'error', 'message' => 'Error al guardar formulario, por favor intente nuevamente']);
        }
    }

    public function actualizaestadofirma($item_id, $estado_id, $responsable_id = null,  $responsable_actual = null, $estado_inicial = null)
    {

        $user = auth()->user();
        /*      $usuarios = ResponsablesEstadosModel::where('usr_app_clientes_responsable_estado.estado_firma_id', '=', $estado_id)
            ->join('usr_app_usuarios as usr', 'usr.id', '=', 'usr_app_clientes_responsable_estado.usuario_id')
            ->select(
                'usuario_id',
                'usr.nombres',
                'usr.apellidos'
            )
            ->get();
 */
        $registro_ingreso = Cliente::where('usr_app_clientes.id', '=', $item_id)
            ->first();
        $estado_inicial = $registro_ingreso->estado_firma_id;
        if ($estado_id == 14) {
            $enviarCorreoDDController = new enviarCorreoDDController;

            $usuarioResponsable = User::where('usr_app_usuarios.id', '=', $registro_ingreso->responsable_id)
                ->select()
                ->first();

            $usuarioComercial = User::where('usr_app_usuarios.vendedor_id', '=', $registro_ingreso->vendedor_id)
                ->select()
                ->first();
            $correoResponsable = $usuarioResponsable->usuario;
            $correoCOmercial = $usuarioComercial->usuario;

            $enviarCorreoDDController->enviarCorreo($correoResponsable, $registro_ingreso, $registro_ingreso->id, 15, "", $user->usuario, false, true);
            $enviarCorreoDDController->enviarCorreo($correoCOmercial, $registro_ingreso, $registro_ingreso->id, 15, "", $user->usuario, false, true);
        }



        $fin_semana_controller = new HorarioLaboralController;



        $estadoController = new EstadosFirmaController;
        if ($estado_inicial != null) {
            $estado_inicial_info = $estadoController->byId($estado_inicial);
            $tiempo_respuesta_segundos =  $estado_inicial_info->tiempo_respuesta * 60;
            $fecha_actual = Carbon::now()->format('Y-m-d H:i:s');
            $last_registro = ClientesSeguimientoEstado::where('usr_app_clientes_seguimiento_estado.cliente_id', $item_id)
                ->select()->orderBy('id', 'desc')->first();

            $segundos_desde_unix = Carbon::parse($fecha_actual)->timestamp;
            $fecha_last_registro = $last_registro->created_at;
            $last_registro_segundos = $fecha_last_registro->timestamp;
            $conteo_dia_fin_semana = $fin_semana_controller->cuentaFindes($fecha_last_registro, $fecha_actual);
            $conteo_festivos = $fin_semana_controller->countHolidaysBetweenDates($fecha_last_registro, $fecha_actual);
            $tiempo_cumplimiento_segundos = $segundos_desde_unix - $last_registro_segundos - ($conteo_dia_fin_semana * 86400) - ($conteo_festivos * 86400);

            // 86400 corresponde a la cantidad de segundos que tiene un dia
            $tiempo_cumplimiento_dias =  $tiempo_cumplimiento_segundos / 86400;

            if ($tiempo_cumplimiento_dias >= 1) {
                // 28800 corresponde a 8 horas laborales en segundos
                $tiempo_cumplimiento_laboral =  $tiempo_cumplimiento_dias * 28800;
            } else {
                $tiempo_cumplimiento_laboral =   $tiempo_cumplimiento_segundos;
            }

            if ($tiempo_cumplimiento_laboral <= $tiempo_respuesta_segundos) {
                $last_registro->oportuno = "1";
                $last_registro->save();
            } else {
                $last_registro->oportuno = "0";
                $last_registro->save();
            }
        }
        /*  $estado_final_info = $estadoController->byId($estado_id); */

        if ($responsable_id != 0) {
            $responsable = ResponsablesEstadosModel::where('usuario_id', '=', $responsable_id)
                ->join('usr_app_usuarios as usr', 'usr.id', '=', 'usr_app_clientes_responsable_estado.usuario_id')
                ->select('usuario_id', 'usr.nombres', 'usr.apellidos')
                ->first();
        } else {
            // Obtener la lista de responsables si no se proporciona $responsable_id
            $usuarios = ResponsablesEstadosModel::where('estado_firma_id', "=", $estado_id)
                ->join('usr_app_usuarios as usr', 'usr.id', '=', 'usr_app_clientes_responsable_estado.usuario_id')
                ->select('usuario_id', 'usr.nombres', 'usr.apellidos')
                ->get();

            $numeroResponsables = $usuarios->count();
            $indiceResponsable = $registro_ingreso->id % $numeroResponsables;
            $responsable = $usuarios[$indiceResponsable];
        }
        // Obtener el número total de responsables
        /* $numeroResponsables = $usuarios->count(); */

        // Obtener el registro de ingreso
        $permisos = $this->validaPermiso();


        if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id && !in_array('31', $permisos)) {
            return response()->json(['status' => 'error', 'message' => 'Solo el responsable puede realizar esta acción.']);
        }

        // Asignar a cada registro d e ingreso un responsable
        /*   $indiceResponsable = $registro_ingreso->id % $numeroResponsables; // Calcula el índice del responsable basado en el ID del registro
        $responsable = $usuarios[$indiceResponsable]; */


        $seguimiento_estado = new ClientesSeguimientoEstado;
        $seguimiento_estado->responsable_inicial =  $registro_ingreso->responsable != null ?  str_replace("null", "", $registro_ingreso->responsable) : str_replace("null", "", $responsable_actual);
        $seguimiento_estado->responsable_final = $responsable->nombres . ' ' . str_replace("null", "", $responsable->apellidos);;
        $seguimiento_estado->estados_firma_inicial = $estado_inicial != null ? $estado_inicial : $registro_ingreso->estado_firma_id;
        $seguimiento_estado->estados_firma_final =   $estado_id;
        $seguimiento_estado->cliente_id =  $item_id;
        $seguimiento_estado->actualiza_registro =   $user->nombres . ' ' .  $user->apellidos;
        $seguimiento_estado->oportuno = "2";
        $seguimiento_estado->save();

        // Actualizar el registro de ingreso con el estado y el responsable
        $registro_ingreso->estado_firma_id = $estado_id;
        $registro_ingreso->responsable_id = $responsable->usuario_id;
        $registro_ingreso->responsable = $responsable->nombres . ' ' . str_replace("null", "", $responsable->apellidos);

        $this->eventoSocket($responsable->usuario_id);

        if ($registro_ingreso->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro.']);
    }

    public function actualizaResponsableCliente($item_id, $responsable_id, $nombre_responsable)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $registro_ingreso = cliente::where('usr_app_clientes.id', '=', $item_id)
                ->first();

            $permisos = $this->validaPermiso();


            if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id && !in_array('31', $permisos)) {
                return response()->json(['status' => 'error', 'message' => 'Solo el responsable puede realizar esta acción.']);
            }

            $seguimiento_estado = new ClientesSeguimientoEstado;
            $seguimiento_estado->responsable_inicial =  $registro_ingreso->responsable;
            $seguimiento_estado->responsable_final = $nombre_responsable;
            $seguimiento_estado->estados_firma_inicial =  $registro_ingreso->estado_firma_id;
            $seguimiento_estado->estados_firma_final =  $registro_ingreso->estado_firma_id;
            $seguimiento_estado->cliente_id =  $item_id;
            $seguimiento_estado->actualiza_registro =   $user->nombres . ' ' .  $user->apellidos;
            $seguimiento_estado->oportuno =   "2";

            $seguimiento_estado->save();

            $registro_ingreso->responsable_anterior = $registro_ingreso->responsable;
            $registro_ingreso->responsable = $nombre_responsable;
            $registro_ingreso->responsable_id = $responsable_id;
            $registro_ingreso->save();
            $seguimiento = new ClientesSeguimientoGuardado;
            $seguimiento->estado_firma_id = $registro_ingreso->estado_firma_id;
            $seguimiento->usuario = $user->nombres . ' ' .  $user->apellidos; //Preguntar con Andres si debe ser el ususario
            $seguimiento->cliente_id = $item_id;
            $seguimiento->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa.']);
        } catch (\Exception $e) {

            DB::rollback();
            return $e;
            return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro.']);
        }
    }



    public function validaPermiso()
    {
        $user = auth()->user();
        $responsable = UsuarioPermiso::where('usr_app_permisos_usuarios.usuario_id', '=', $user->id)
            ->select(
                'permiso_id'
            )
            ->get();
        $array = $responsable->toArray();
        $permisos = array_column($array, 'permiso_id');
        return $permisos;
    }

    public function versionformulario($asJson = true)
    {
        $currentDate = now(); // Obtener la fecha actual

        // Establecer la versión del formulario basada en la fecha actual
        $version = $currentDate->isAfter('2024-08-04') ? 2 : 1;

        // Realizar la consulta con la versión seleccionada
        $result = VersionFormularioDD::where('version_formulario', $version)
            ->select()
            ->get();

        if (!$asJson) {
            return $result;
        }
        return response()->json($result);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $error_carga_archivos = null)
    {
        $result = Cliente::find($id);
        if ($result->delete()) {
            if ($error_carga_archivos == null) {
                return response()->json("registro borrado Con Exito");
            }
        } else {
            return response()->json("Error al borrar registro");
        }
    }
    public function eventoSocket($id)
    {
        try {
            $data = [
                'encargado_id' => $id,
                'mensaje' => 'Te han asignado una nueva actividad en el módulo Debida diligencia.'
            ];
            event(new NotificacionSeiya($data));
        } catch (\Throwable $th) {
        }
        return;
    }
    public function generarPdf($id)
    {

        try {
            // Obtener los datos
            $versiones = $this->versionformulario(false);
            $result = $this->getbyid($id, false);
            if (!$result) {
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }
            $url = public_path('public/upload/logo1.png');
            // Iniciar TCPDF
            $pdf = new \TCPDF();
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Tu Empresa');
            $pdf->SetTitle('Reporte de Cliente');
            $pdf->SetSubject('Detalle del Cliente');
            $pdf->SetMargins(15, 10, 15);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            $pdf->AddPage();
            $url = public_path('upload/logo1.png');

            // Verificar que el archivo existe
            if (!file_exists($url)) {
                throw new Exception("La imagen no existe en la ruta especificada.");
            }

            // Convertir la imagen en base64 si el método estándar no funciona
            $imageData = base64_encode(file_get_contents($url));
            $imageSrc = 'data:image/png;base64,' . $imageData;
            // Iniciar HTML
            $html = '
            <style>
                .version{
                font-size: 11px;
           
                }
                h1, h2, h3 {
                    color: #333;
                    text-align: center;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                table th, table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                table th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                }
                .section-title {
                    font-size: 40px;
                    font-weight: bold;
                    margin-top: 20px;
                    margin-bottom: 10px;
                    text-align: left;
                }
            </style>
            ';

            // Datos Generales
            $html = '<table border="1" cellpadding="5">
            <tr>
            <th style="text-align: center;"> <img style="margin: auto;" src="' . $imageSrc . '" alt="test alt attribute" width="50" height="50" border="0" /></th>
                <th colspan="2" style="text-align: center;">
                    <h4 >SAGRILAFT</h4>
                    <h5>Sistema de Autocontrol y Gestión del Riesgo Integral de Lavado de Activos y Financiación del Terrorismo FORMATO ÚNICO DE VINCULACIÓN DE CONTRAPARTES</h5>
                </th>
            ';

            // Aquí empieza el contenido dinámico del `<td>`
            $html .= '
            <td>';

            foreach ($versiones as $version) {
                $html .= '<p style="font-size: 10px;">' . htmlspecialchars($version->descripcion) . '</p>'; // Escapar caracteres especiales si es necesario
            }

            $html .= '</td>
        </tr>';

            $html .= '</table>';
            $html .= '<h3>Datos Generales</h3>';
            $html .= '<table>
                <tr><th><b>Número de radicado:</b></th><td>' . $result['numero_radicado'] . '</td></tr>
                <tr><th><b>Tipo de operación:</b></th><td>' . $result['tipo_operacion'] . '</td></tr>
                <tr><th><b>Tipo de cliente:</b></th><td>' . $result['tipo_cliente'] . '</td></tr>';
            $html .=  '<tr><th><b>Tipo de proveedor:</b></th><td>' . $result['tipo_proveedor'] . '</td></tr>
                <tr><th><b>Tipo de persona:</b></th><td>' . $result['tipo_persona'] . '</td></tr>
                <tr><th><b>Tipo de identificación:</b></th><td>' . $result['tipo_identificacion'] . '</td></tr>
                <tr><th><b>Número de identificación:</b></th><td>' . $result['numero_identificacion'] . '</td></tr>
                <tr><th><b>Fecha de expedición:</b></th><td>' . $result['fecha_exp_documento'] . '</td></tr>
                <tr><th><b>Nombre completo/Razón social:</b></th><td>' . $result['razon_social'] . '</td></tr>
                <tr><th><b>NIT:</b></th><td>' . $result['nit'] . '-' . $result['digito_verificacion'] . '</td></tr>
                <tr><th><b>Teléfono:</b></th><td>' . $result['telefono_empresa'] . '</td></tr>
                <tr><th><b>Número celular:</b></th><td>' . $result['celular_empresa'] . '</td></tr>
                <tr><th><b>Correo:</b></th><td>' . $result['correo_empresa'] . '</td></tr>
                <tr><th><b>Fecha de constitución:</b></th><td>' . $result['fecha_constitucion'] . '</td></tr>
                <tr><th><b>Número de empleados:</b></th><td>' . $result['numero_empleados'] . '</td></tr>
                <tr><th><b>Código ciiu:</b></th><td>' . $result['codigo_ciiu'] . '</td></tr>
                <tr><th><b>Actividad ciiu:</b></th><td>' . $result['codigo_actividad_ciiu'] . '</td></tr>
                <tr><th><b>Estrato socio económico (ubicación empresa):</b></th><td>' . $result['estrato'] . '</td></tr>
                <tr><th><b>Departamento del rut:</b></th><td>' . $result['departamento_rut'] . '</td></tr>
                <tr><th><b>Ciudad del rut:</b></th><td>' . $result['municipio_rut'] . '</td></tr>
                <tr><th><b>Pais de ubicación:</b></th><td>' . $result['pais'] . '</td></tr>
                <tr><th><b>Departamento de ubicación:</b></th><td>' . $result['departamento'] . '</td></tr>
                <tr><th><b>Ciudad de ubicación:</b></th><td>' . $result['municipio'] . '</td></tr>
                <tr><th><b>Dirección de la empresa:</b></th><td>' . $result['direccion_empresa'] . '</td></tr>
                <tr><th><b>Persona de contacto:</b></th><td>' . $result['contacto_empresa'] . '</td></tr>
                <tr><th><b>Sociedad comercial:</b></th><td>' . $result['sociedad_comercial'] . '</td></tr>
                <tr><th><b>Otra ¿Cuál?:</b></th><td>' . $result['otra'] . '</td></tr>
                <tr><th><b>Periocidad de pagos:</b></th><td>' . $result['periodicidad_liquidacion'] . '</td></tr>
                <tr><th><b>Plazo pagos(días):</b></th><td>' . $result['plazo_pago'] . '</td></tr>
                <tr><th><b>Pais prestación servicio:</b></th><td>' . $result['pais_prestacion_servicio'] . '</td></tr>
                <tr><th><b>Departamento prestación servicio:</b></th><td>' . $result['departamento_prestacion_servicio'] . '</td></tr>
                <tr><th><b>Municipio prestación servicio:</b></th><td>' . $result['municipio_prestacion_servicio'] . '</td></tr>
                <tr><th><b>AIU negociado:</b></th><td>' . $result['aiu_negociado'] . '</td></tr>
                <tr><th><b>Ejecutivo comercial:</b></th><td>' . $result['vendedor'] . '</td></tr>
                <tr><th><b>Observaciones acuerdos comerciales:</b></th><td>' . $result['acuerdo_comercial'] . '</td></tr>
                <tr><th><b>Jornada laboral:</b></th><td>' . $result['jornada_laboral'] . '</td></tr>
                <tr><th><b>Rotación de personal:</b></th><td>' . $result['rotacion_personal'] . '</td></tr>
                <tr><th><b>La empresa es extranjera:</b></th><td>' . $result['empresa_extranjera'] . '</td></tr>
                <tr><th><b>¿Es empresa del exterior radicada en colombia?:</b></th><td>' . $result['empresa_en_exterior'] . '</td></tr>
                <tr><th><b>¿Tiene vinculos con alguna empresa activa en saitemp?:</b></th><td>' . $result['vinculos_empresa'] . '</td></tr>
                <tr><th><b>Empleados directos empresa usuaria:</b></th><td>' . $result['numero_empleados_directos'] . '</td></tr>
                <tr><th><b>¿Actualmente tienen personal vinculado con empresa temporal?:</b></th><td>' . $result['vinculado_empresa_temporal'] . '</td></tr>
                <tr><th><b>¿Se realizó la visita presencial a las instalaciones del cliente?:</b></th><td>' . $result['visita_presencial'] . '</td></tr>
            </table>';
            $html .= '<h3>Servicios solicitados</h3>
            <table>';
            if ($result['contratacion_directa'] == 1) {

                $html .= ' <tr><th><b>Contratacion directa: </b></th><td>Si</td></tr> ';
            } else {
                $html .= ' <tr><th><b>Contratacion directa: </b></th><td>No</td></tr> ';
            }
            if ($result['atraccion_seleccion'] == 1) {

                $html .= ' <tr><th><b>Atracción y selección de talento: </b></th><td>Si</td></tr></table> ';
            } else {
                $html .= ' <tr><th><b>Atracción y selección de talento: </b></th><td>No</td></tr></table> ';
            }
            $html .= '<h3>Contratación</h3>';
            $html .= '<table>
            <tr><th><b>Contacto notificación ingreso personal:</b></th><td>' . $result['contratacion_contacto'] . '</td></tr>
            <tr><th><b>Cargo del contacto:</b></th><td>' . $result['contratacion_cargo'] . '</td></tr>
            <tr><th><b>Teléfono del contacto:</b></th><td>' . $result['contratacion_telefono'] . '</td></tr>
            <tr><th><b>Número celular del contacto:</b></th><td>' . $result['contratacion_celular'] . '</td></tr>
            <tr><th><b>Número celular del contacto:</b></th><td>' . $result['contratacion_hora_ingreso'] . '</td></tr>
            <tr><th><b>Hora límite para confirmar ingreso de personal:</b></th><td>' . $result['contratacion_hora_confirmacion'] . '</td></tr>
            <tr><th><b>Correo electrónico notificación ingreso personal:</b></th><td>' . $result['contratacion_correo'] . '</td></tr>
            ';
            if (count($result['otrosi']) > 0) {
                $html .= '<tr><th><b>Otro si solicitados:</b></th><td>-' . $result['otrosi'][0]->nombre . '</td></tr>';
                for ($i = 1; $i < count($result['otrosi']); $i++) {

                    $html .= '<tr><th></th><td>-' . $result['otrosi'][$i]->nombre . '</td></tr>';
                }
            }

            $html .= '
            <tr><th><b>¿Necesita carnet de manipulación de alimentos?:</b></th><td>' . $result['contratacion_manipulacion_alimentos'] . '</td></tr>
            <tr><th><b>¿se require carnet corporativo con especificaciones distintas?:</b></th><td>' . $result['contratacion_carnet_corporativo'] . '</td></tr>
            <tr><th><b>¿Se requieren tallas de uniformes?:</b></th><td>' . $result['contratacion_tallas_uniforme'] . '</td></tr>
            <tr><th><b>¿Empresa suministra transporte?:</b></th><td>' . $result['contratacion_suministra_transporte'] . '</td></tr>
            <tr><th><b>¿La empresa suministra alimentación?:</b></th><td>' . $result['contratacion_suministra_alimentacion'] . '</td></tr>';
            if (count($result['convenios_banco']) > 0) {
                $html .= '<tr><th><b>Convenio bancos:</b></th><td>-' . $result['convenios_banco'][0]->nombre . '</td></tr>';
                for ($i = 1; $i < count($result['convenios_banco']); $i++) {

                    $html .= '<tr><th></th><td>-' . $result['convenios_banco'][$i]->nombre . '</td></tr>';
                }
            }

            $html .= '
            <tr><th><b>¿Realiza pago en efectivo?:</b></th><td>' . $result['contratacion_pago_efectivo'] . '</td></tr>';
            if (count($result['tipos_contrato']) > 0) {
                $html .= '<tr><th><b>Tipos de contrato:</b></th><td>-' . $result['tipos_contrato'][0]->nombre . '</td></tr>';
                for ($i = 1; $i < count($result['tipos_contrato']); $i++) {

                    $html .= '<tr><th></th><td>-' . $result['tipos_contrato'][$i]->nombre . '</td></tr>';
                }
            }
            $html .= '<tr><th><b>¿La empresa paga los días 31?:</b></th><td>' . $result['contratacion_pagos_31'] . '</td></tr>';
            if (count($result['ubicacion_laboratorio']) > 0) {
                $html .= '<tr><th><b>País ubicación laboratorio médico:</b></th><td>' . $result['ubicacion_laboratorio'][0]['pais'] . '</td></tr>
                 <tr><th><b>Departamento ubicación laboratorio médico:</b></th><td>' . $result['ubicacion_laboratorio'][0]['departamento'] . '</td></tr>
                 <tr><th><b>Ciudad ubicación laboratorio médico:</b></th><td>' . $result['ubicacion_laboratorio'][0]['municipio'] . '</td></tr>
                 ';
            }

            if (count($result['laboratorios_agregados']) > 0) {
                $html .= '<tr><th><b>Laboratorios médicos:</b></th><td>-' . $result['laboratorios_agregados'][0]->nombre . '</td></tr>';
                for ($i = 1; $i < count($result['laboratorios_agregados']); $i++) {

                    $html .= '<tr><th></th><td>-' . $result['laboratorios_agregados'][$i]->nombre . '</td></tr>';
                }
            }
            $html .= '</table>';

            $html .= '<h3>Facturación</h3>';
            $html .= '<table>
            <tr><th><b>Contacto:</b></th><td>' . $result['facturacion_contacto'] . '</td></tr>
            <tr><th><b>Cargo:</b></th><td>' . $result['facturacion_cargo'] . '</td></tr>
            <tr><th><b>Teléfono:</b></th><td>' . $result['facturacion_telefono'] . '</td></tr>
            <tr><th><b>Celular:</b></th><td>' . $result['facturacion_celular'] . '</td></tr>
            <tr><th><b>Correo electrónico:</b></th><td>' . $result['facturacion_correo'] . '</td></tr>
            <tr><th><b>Factura única o por CECO:</b></th><td>' . $result['facturacion_factura_unica'] . '</td></tr>
            <tr><th><b>Fecha de corte para recibir las facturas:</b></th><td>' . $result['facturacion_fecha_corte'] . '</td></tr>
            <tr><th><b>Persona encargada de recibir la factura:</b></th><td>' . $result['facturacion_encargado_factura'] . '</td></tr>
            <tr><th><b>¿Requiere anexo de la factura?:</b></th><td>' . $result['requiere_anexo_factura'] . '</td></tr>
            </table>';

            $html .= '<h3>Seguridad y salud en el trabajo</h3>';

            $html .= '<table>
            <tr><th><b>Riesgo de la empresa(ARL):</b></th><td>' . $result['riesgo_cliente'] . '</td></tr>
            <tr><th><b>¿Realizan trabajo de alto riesgo?:</b></th><td>' . $result['trabajo_alto_riesgo'] . '</td></tr>
            <tr><th><b>Accidentalidad:</b></th><td>' . $result['accidentalidad'] . '</td></tr>
            <tr><th><b>Cuenta con persona encargada de SST:</b></th><td>' . $result['encargado_sst'] . '</td></tr>
            <tr><th><b>Nombre encargado SST:</b></th><td>' . $result['nombre_encargado_sst'] . '</td></tr>
            <tr><th><b>Cargo analista SST:</b></th><td>' . $result['cargo_encargado_sst'] . '</td></tr>
            <tr><th><b>¿Realizan inducción y entrenamiento?:</b></th><td>' . $result['induccion_entrenamiento'] . '</td></tr>
            <tr><th><b>¿Entregan dotación?:</b></th><td>' . $result['entrega_dotacion'] . '</td></tr>
            <tr><th><b>¿Fue evaluado el SGST por la ARL?:</b></th><td>' . $result['evaluado_arl'] . '</td></tr>
            <tr><th><b>¿Entrega EPP?:</b></th><td>' . $result['entrega_epp'] . '</td></tr>
            </table>';

            $html .= '<h3>Cargos</h3>';
            if (count($result['cargos2']) > 0) {
                $html .= '<table><tr><th><b>Número de cargos registrados:</b></th><td>' . count($result['cargos2']) . '</td></tr></table>';
                foreach ($result['cargos2'] as $index => $cargo) {
                    $html .= '<h4 style="text-align:center;">Cargo: ' . $index + 1 . '</h4>';
                    $html .= '<table>
                    <tr><th><b>Tipo de cargo:</b></th><td>' . $cargo['tipo_cargo'] . '</td></tr>
                    <tr><th><b>Categoria del cargo:</b></th><td>' . $cargo['categoria'] . '</td></tr>
                    <tr><th><b>Cargo:</b></th><td>' . $cargo['cargo'] . '</td></tr>
                    <tr><th><b>Riesgo del cargo(ARL):</b></th><td>' . $cargo['riesgo_laboral'] . '</td></tr>
                    <tr><th><b>Funciones del cargo:</b></th><td>' . $cargo['funcion_cargo'] . '</td></tr>';
                    if (count($cargo['examenes']) > 0) {
                        $html .= '<tr><th><b>Exámenes:</b></th><td>-' . $cargo['examenes'][0]['nombre'] . '</td></tr>';
                        for ($i = 1; $i < count($cargo['examenes']); $i++) {

                            $html .= '<tr><th></th><td>-' . $cargo['examenes'][$i]['nombre'] . '</td></tr>';
                        }
                    }
                    if (count($cargo['recomendaciones']) > 0) {
                        $html .= '<table><tr><th></th><td></td></tr>';
                        $html .= '<table><tr><th><b>Orientaciones específicas para los exámenes:</b></th><td>-' . $cargo['recomendaciones'][0]['recomendacion1'] . '.</td></tr>';
                        $html .= '<table><tr><th></th><td></td></tr>';
                        $html .= '<tr><th><b>Patologías que restringen la labor:</b></th><td style="margin:20px; ">-' . $cargo['recomendaciones'][0]['recomendacion2'] . '.</td></tr>';
                    }
                }

                $html .= ' </table>';
            }

            $html .= '<h3>Información financiera:</h3>';


            if (count($result['accionistas']) > 0) {
                $html .= '<table>';
                foreach ($result['accionistas'] as $index => $accionista) {
                    $html .= '<h4 style="text-align:center;">Accionista ' . $index + 1 . '</h4>';
                    $html .= '<tr><th><b>Tipo de identificación:</b></th><td>' . $accionista['des_tip'] . '</td></tr>';
                    $html .= '<tr><th><b>Identificación:</b></th><td>' . $accionista['identificacion'] . '</td></tr>';
                    $html .= '<tr><th><b>Socio/accionista:</b></th><td>' . $accionista['socio'] . '</td></tr>';
                    $html .= '<tr><th><b>Porcentaje participación:</b></th><td>' . $accionista['participacion'] . '</td></tr>';
                    $html .= '<table><tr><th></th><td></td></tr>';
                }
                $html .= '</table>';
            }

            $html .= '<h3>Representantes legales:</h3>';

            if (count($result['representantes_legales']) > 0) {
                $html .= '<table>';
                foreach ($result['representantes_legales'] as $index => $representante) {
                    $html .= '<h4 style="text-align:center;">Representante legal ' . $index + 1 . '</h4>';
                    $html .= '<tr><th><b>Tipo de identificación:</b></th><td>' . $representante['des_tip'] . '</td></tr>';
                    $html .= '<tr><th><b>Identificación:</b></th><td>' . $representante['identificacion'] . '</td></tr>';
                    $html .= '<tr><th><b>Nombre:</b></th><td>' . $representante['nombre'] . '</td></tr>';
                    $html .= '<tr><th><b>Correo electrónico:</b></th><td>' . $representante['pais'] . '</td></tr>';
                    $html .= '<tr><th><b>Correo electrónico:</b></th><td>' . $representante['departamento'] . '</td></tr>';
                    $html .= '<tr><th><b>Correo electrónico:</b></th><td>' . $representante['ciudad_expedicion'] . '</td></tr>';
                    $html .= '<tr><th><b>Número celular:</b></th><td>' . $representante['telefono'] . '</td></tr>';
                    $html .= '<tr><th><b>Correo electrónico:</b></th><td>' . $representante['correo'] . '</td></tr>';
                    $html .= '<table><tr><th></th><td></td></tr>';
                }
                $html .= '</table>';
            }
            $html .= '<h3>Miembros junta directiva:</h3>';
            if (count($result['junta_directiva']) > 0) {
                $html .= '<table>';
                foreach ($result['junta_directiva'] as $index => $miembro) {
                    $html .= '<h4 style="text-align:center;">' . $index + 1 . '</h4>';
                    $html .= '<tr><th><b>Tipo de identificación:</b></th><td>' . $miembro['des_tip'] . '</td></tr>';
                    $html .= '<tr><th><b>Identificación:</b></th><td>' . $miembro['identificacion'] . '</td></tr>';
                    $html .= '<tr><th><b>Nombre:</b></th><td>' . $miembro['nombre'] . '</td></tr>';
                    $html .= '<table><tr><th></th><td></td></tr>';
                }
                $html .= '<table>';
            } else {
                $html .= '<table><tr><th><p>La empresa cuenta con Junta directiva</p></th><td></td></tr>';
            }

            $html .= '<h3>Calidad tributaria:</h3>';
            $html .= '<table>';
            if ($result['responsable_inpuesto_ventas'] == 1) {
                $html .= '<tr><th><b>Responsable de Impuestos a las Ventas:</b></th><td>Si</td></tr>';
            } else {
                $html .= '<tr><th><b>Responsable de Impuestos a las Ventas:</b></th><td>No</td></tr>';
            }
            $html .= '<tr><th><b>Correo para factura electrónica:</b></th><td>' . $result['correo_facturacion_electronica'] . '</td></tr>';
            $html .= '<tr><th><b>Sucursal de facturación:</b></th><td>' . $result['sucursal_facturacion'] . '</td></tr>';
            if ($result['calidad_tributaria'][0]['gran_contribuyente'] == 1) {
                $html .= '<tr><th><b>¿Es Gran Contribuyente?:</b></th><td>Si</td></tr>';
                $html .= '<tr><th><b>Número de resolución gran contribuyente:</b></th><td>' . $result['calidad_tributaria'][0]['resolucion_gran_contribuyente'] . '</td></tr>';
                $html .= '<tr><th><b>Fecha de resolución gran contribuyente:</b></th><td>' . $result['calidad_tributaria'][0]['fecha_gran_contribuyente'] . '</td></tr>';
            } else {
                $html .= '<tr><th><b>¿Es Gran Contribuyente?:</b></th><td>No</td></tr>';
            }
            if ($result['calidad_tributaria'][0]['auto_retenedor'] == 1) {
                $html .= '<tr><th><b>¿Es auto-retenedor?:</b></th><td>Si</td></tr>';
                $html .= '<tr><th><b>Número de resolución auto-retenedor:</b></th><td>' . $result['calidad_tributaria'][0]['resolucion_auto_retenedor'] . '</td></tr>';
                $html .= '<tr><th><b>Fecha de resolución auto-retenedor:</b></th><td>' . $result['calidad_tributaria'][0]['fecha_auto_retenedor'] . '</td></tr>';
            } else {
                $html .= '<tr><th><b>¿Es auto-retenedor?:</b></th><td>No</td></tr>';
            }
            if ($result['calidad_tributaria'][0]['resolucion_exento_impuesto_rent'] == 1) {
                $html .= '<tr><th><b>¿Exento de Impuesto a la Renta?:</b></th><td>Si</td></tr>';
                $html .= '<tr><th><b>Número de resolución impuesto a la renta:</b></th><td>' . $result['calidad_tributaria'][0]['resolucion_exento_impuesto_rent'] . '</td></tr>';
                $html .= '<tr><th><b>Fecha de resolución impuesto a la renta:</b></th><td>' . $result['calidad_tributaria'][0]['fecha_exento_impuesto_rent'] . '</td></tr>';
            } else {
                $html .= '<tr><th><b>¿Exento de Impuesto a la Renta?:</b></th><td>No</td></tr>';
            }
            $html .= '</table>';

            $html .= '<h3>Datos del contador</h3> ';

            // Seguimiento de Estados
            if (!empty($result['seguimiento_estados'])) {
                $html .= '<h3>Seguimiento de Estados</h3>';
                $html .= '<table>
                    <tr>
                        <th>Estado Inicial</th>
                        <th>Estado Final</th>
                        <th>Responsable</th>
                        <th>Fecha de Actualización</th>
                    </tr>';
                foreach ($result['seguimiento_estados'] as $estado) {
                    $html .= '<tr>
                        <td>' . $estado['estados_firma_inicial'] . '</td>
                        <td>' . $estado['estados_firma_final'] . '</td>
                        <td>' . $estado['responsable_inicial'] . '</td>
                        <td>' . $estado['actualiza_registro'] . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }

            // Contratos
            if (!empty($result['contrato'])) {
                $html .= '<h3>Contratos</h3>';
                $html .= '<table>
                    <tr>
                        <th>Estado</th>
                        <th>Firmado por Cliente</th>
                        <th>Firmado por Empresa</th>
                    </tr>';
                foreach ($result['contrato'] as $contrato) {
                    $html .= '<tr>
                        <td>' . $contrato['estado_contrato'] . '</td>
                        <td>' . ($contrato['firmado_cliente'] ? 'Sí' : 'No') . '</td>
                        <td>' . ($contrato['firmado_empresa'] ? 'Sí' : 'No') . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }

            // Cargos
            if (!empty($result['cargos'])) {
                $html .= '<h3>Cargos</h3>';
                foreach ($result['cargos'] as $cargo) {
                    $html .= '<div class="section-title">Cargo: ' . $cargo['cargo'] . '</div>';
                    $html .= '<p>Riesgo Laboral: ' . $cargo['riesgo_laboral'] . '</p>';
                    if (!empty($cargo['requisitos'])) {
                        $html .= '<div class="section-title">Requisitos:</div><ul>';
                        foreach ($cargo['requisitos'] as $requisito) {
                            $html .= '<li>' . $requisito['nombre'] . '</li>';
                        }
                        $html .= '</ul>';
                    }
                    if (!empty($cargo['examenes'])) {
                        $html .= '<div class="section-title">Exámenes:</div><ul>';
                        foreach ($cargo['examenes'] as $examen) {
                            $html .= '<li>' . $examen['nombre'] . '</li>';
                        }
                        $html .= '</ul>';
                    }
                }
            }

            // Accionistas
            if (!empty($result['accionistas'])) {
                $html .= '<h3>Accionistas</h3>';
                $html .= '<table>
                    <tr>
                        <th>Nombre</th>
                        <th>Participación (%)</th>
                    </tr>';
                foreach ($result['accionistas'] as $accionista) {
                    $html .= '<tr>
                        <td>' . $accionista['socio'] . '</td>
                        <td>' . $accionista['participacion'] . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }

            // Generar PDF
            $pdf->writeHTML($html, true, false, true, false, '');
            $filename = 'Reporte_Cliente_' . $id . '.pdf';
            $pdf->Output($filename, 'I'); // 'I' para mostrar en navegador, 'D' para descargar directamente
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}
