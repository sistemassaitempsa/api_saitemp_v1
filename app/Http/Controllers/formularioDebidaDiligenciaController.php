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
                return $query->where(function ($subQuery) use ($user) {
                    $subQuery->where('usr_app_clientes.vendedor_id', $user->vendedor_id)
                        ->orWhere('usr_app_clientes.responsable_id', $user->id);
                });
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
                'usr_app_clientes.responsable',
                'estf.id as estado_firma_id',
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

            $clientes_epps = ClienteEpp::where('cliente_id', $id)->leftjoin('usr_app_elementos_pp as epp', DB::raw('epp.id'), '=', DB::raw('usr_app_cliente_epp.epp_id + 1'))
                ->select(
                    'epp_id',
                    'epp.nombre as nombre'
                )
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
            $nombres = str_replace("null", "", $user->nombres);
            $apellidos = str_replace("null", "", $user->apellidos);
            $registroCambio = new RegistroCambio;
            $registroCambio->observaciones = $request['registro_cambios']['observaciones'];
            $registroCambio->solicitante = $request['registro_cambios']['solicitante'];
            $registroCambio->autoriza = $request['registro_cambios']['autoriza'];
            $registroCambio->actualiza = $nombres . ' ' . $apellidos;
            $registroCambio->cliente_id = $id;
            $registroCambio->save();

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


        if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id && !in_array('34', $permisos)) {
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


            if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id && !in_array('34', $permisos)) {
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
    public function generarPdf($id, $outputToBrowser = true)
    {

        try {
            // Obtener los datos
            $versiones = $this->versionformulario(false);
            $result = $this->getbyid($id, false);

            $resultGenerales = [
                'Número de radicado:' => $result['numero_radicado'],
                'Tipo de operación:' => $result['tipo_operacion'],
                'Tipo de cliente:' => $result['tipo_cliente'],
                'Tipo de proveedor' => $result['tipo_proveedor'],
                'Tipo de persona:' => $result['tipo_persona'],
                'Tipo de identificación:' => $result['tipo_identificacion'],
                'Número de identificación:' => $result['numero_identificacion'],
                'Fecha de expedición:' => $result['fecha_exp_documento'],
                'Nombre completo/Razón social:' => $result['razon_social'],
                'NIT:' => $result['nit'] . '-' . $result['digito_verificacion'],
                'Teléfono:' => $result['telefono_empresa'],
                'Número celular:' => $result['celular_empresa'],
                'Correo:' => $result['correo_empresa'],
                'Fecha de constitución:' => $result['fecha_constitucion'],
                'Número de empleados:' => $result['numero_empleados'],
                'Código ciiu:' => $result['codigo_ciiu'],
                'Actividad ciiu:' => $result['codigo_actividad_ciiu'],
                'Estrato socio económico (ubicación empresa):' => $result['estrato'],
                'Departamento del rut:' => $result['departamento_rut'],
                'Ciudad del rut:' => $result['municipio_rut'],
                'Pais de ubicación:' => $result['pais'],
                'Departamento de ubicación:' => $result['departamento'],
                'Ciudad de ubicación:' => $result['municipio'],
                'Dirección de la empresa:' => $result['direccion_empresa'],
                'Persona de contacto:' => $result['contacto_empresa'],
                'Sociedad comercial:' => $result['sociedad_comercial'],
                'Otra ¿Cuál?:' => $result['otra'],
                'Periocidad de pagos:' => $result['periodicidad_liquidacion'],
                'Plazo pagos(días):' => $result['plazo_pago'],
                'Pais prestación servicio:' => $result['pais_prestacion_servicio'],
                'Departamento prestación servicio:' => $result['departamento_prestacion_servicio'],
                'Municipio prestación servicio:' => $result['municipio_prestacion_servicio'],
                'AIU negociado:' => $result['aiu_negociado'],
                'Ejecutivo comercial:' => $result['vendedor'],
                'Observaciones acuerdos comerciales:' => $result['acuerdo_comercial'],
                'Jornada laboral:' => $result['jornada_laboral'],
                'Rotación de personal:' => $result['rotacion_personal'],
                'La empresa es extranjera:' => $result['empresa_extranjera'],
                '¿Es empresa del exterior radicada en colombia?:' => $result['empresa_en_exterior'],
                '¿Tiene vinculos con alguna empresa activa en saitemp?:' => $result['vinculos_empresa'],
                'Empleados directos empresa usuaria:' => $result['numero_empleados_directos'],
                '¿Actualmente tienen personal vinculado con empresa temporal?:' => $result['vinculado_empresa_temporal'],
                '¿Se realizó la visita presencial a las instalaciones del cliente?:' => $result['visita_presencial'],
            ];



            if (!$result) {
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }
            $url = public_path('public/upload/logo1.png');
            // Iniciar TCPDF
            $pdf = new \TCPDF();
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Saitemp SAS');
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
            $indiceGenerales = 0;
            $html .= '</table>';
            $html .= '<h3>Datos Generales</h3>';
            $html .= '<table >';
            $clavePrev = "";
            $valorPrev = "";
            foreach ($resultGenerales as  $clave => $valor) {

                if ($indiceGenerales % 2 == 0) {
                    $clavePrev = $clave;
                    $valorPrev = $valor;
                } else {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $clavePrev . '</b> ' . $valorPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                }

                $html .= '<tr><th></th><td></td></tr>';
                $indiceGenerales++;
            }

            if ($indiceGenerales % 2 == 1) {
                $html .= '<tr><td style="font-size:10px;"><b>' . $clavePrev . '</b> ' . $valorPrev . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            $html .= '</table>';

            if ($result['tipo_cliente_id'] == 1) {
                $html .= '<h3>Servicios solicitados</h3>
            <table>';
                if ($result['contratacion_directa'] == 1) {
                    $contratacionDirecta = "Si";
                } else {
                    $contratacionDirecta = "No";
                }
                if ($result['atraccion_seleccion'] == 1) {
                    $atraccionSeleccion = "Si";
                } else {
                    $atraccionSeleccion = "No";
                }
                $html .= ' <tr><th style="font-size:10px;"><b>Contratacion directa:</b> ' . $contratacionDirecta . '</th><td style="font-size:10px;"><b>Atracción y selección de talento:</b> ' . $atraccionSeleccion . '</td></tr> ';
                $html .= '<h3>Contratación</h3>';

                $resultContratacion = [
                    'Contacto notificación ingreso personal:' => $result['contratacion_contacto'],
                    'Cargo del contacto:' => $result['contratacion_cargo'],
                    'Teléfono del contacto:' => $result['contratacion_telefono'],
                    'Número celular del contacto:' => $result['contratacion_celular'],
                    'Hora de ingreso del personal primer día:' => $result['contratacion_hora_ingreso'],
                    'Hora límite para confirmar ingreso de personal:' => $result['contratacion_hora_confirmacion'],
                    'Correo electrónico notificación ingreso personal:' => $result['contratacion_correo'],
                    '¿Necesita carnet de manipulación de alimentos?:' => $result['contratacion_manipulacion_alimentos'],
                    '¿se require carnet corporativo con especificaciones distintas?:' => $result['contratacion_carnet_corporativo'],
                    '¿Se requieren tallas de uniformes?:' => $result['contratacion_tallas_uniforme'],
                    '¿Empresa suministra transporte?:' => $result['contratacion_suministra_transporte'],
                    '¿La empresa suministra alimentación?:' => $result['contratacion_suministra_alimentacion'],
                    '¿Realiza pago en efectivo?:' => $result['contratacion_pago_efectivo'],
                    '¿La empresa paga los días 31?:' => $result['contratacion_pagos_31'],

                ];
                $indiceContratacion = 0;
                $claveConPrev = "";
                $valorConPrev = "";
                $html .= '<table>';
                foreach ($resultContratacion as $clave => $valor) {
                    if ($indiceContratacion % 2 == 0) {
                        $claveConPrev = $clave;
                        $valorConPrev = $valor;
                    } else {
                        $html .= '<tr><td style="font-size:10px;"><b>' . $claveConPrev . '</b> ' . $valorConPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                    }
                    $html .= '<tr><th></th><td></td></tr>';
                    $indiceContratacion++;
                }

                if ($indiceContratacion % 2 == 1) {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $claveConPrev . '</b> ' . $valorConPrev . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                }
                $html .= '<tr><th><b>Otro si solicitados:</b></th></tr>';

                $otroSiIndice = 0;
                $otroSiValPrev = "";

                if (count($result['otrosi']) > 0) {

                    foreach ($result['otrosi'] as $index => $otro) {
                        if ($index % 2 == 0) {
                            $otroSiValPrev = $otro['nombre'];
                        } else {
                            $html .= '<tr><td style="font-size:10px;">-' . $otroSiValPrev . '</td><td style="font-size:10px;">-' . $otro['nombre'] . '</td></tr>';
                            $html .= '<tr><th></th><td></td></tr>';
                        }

                        $otroSiIndice++;
                    }

                    if ($otroSiIndice % 2 == 1) {
                        $html .= '<tr><td style="font-size:10px;">-' . $otroSiValPrev . '</td></tr>';
                        $html .= '<tr><th></th><td></td></tr>';
                    }
                }


                $convBancIndice = 0;
                $convBancValPrev = "";

                $html .= '<tr><th><b>Convenio bancos:</b></th></tr>';
                if (count($result['convenios_banco']) > 0) {
                    foreach ($result['convenios_banco'] as $index => $banco) {
                        if ($index % 2 == 0) {
                            $convBancValPrev = $banco['nombre'];
                        } else {
                            $html .= '<tr><td style="font-size:10px;">-' . $convBancValPrev . '</td><td style="font-size:10px;">-' . $banco['nombre'] . '</td></tr>';
                            $html .= '<tr><th></th><td></td></tr>';
                        }

                        $convBancIndice++;
                    }


                    if ($convBancIndice % 2 == 1) {
                        $html .= '<tr><td style="font-size:10px;">-' . $convBancValPrev . '</td></tr>';
                        $html .= '<tr><th></th><td></td></tr>';
                    }
                }

                $tipoConIndice = 0;
                $tipoConValPrev = "";
                $html .= '<tr><th><b>Tipos de contrato:</b></th></tr>';
                if (count($result['tipos_contrato']) > 0) {
                    foreach ($result['tipos_contrato'] as $index => $contrato) {
                        if ($index % 2 == 0) {
                            $tipoConValPrev = $contrato['nombre'];
                        } else {
                            $html .= '<tr><td style="font-size:10px;">-' . $tipoConValPrev . '</td><td style="font-size:10px;">-' . $contrato['nombre'] . '</td></tr>';
                            $html .= '<tr><th></th><td></td></tr>';
                        }

                        $tipoConIndice++;
                    }


                    if ($tipoConIndice % 2 == 1) {
                        $html .= '<tr><td style="font-size:10px;">-' . $tipoConValPrev . '</td></tr>';
                        $html .= '<tr><th></th><td></td></tr>';
                    }
                }


                if (count($result['ubicacion_laboratorio']) > 0) {
                    $html .= '<tr><th style="font-size:10px;"><b>País ubicación laboratorio médico: </b> ' . $result['ubicacion_laboratorio'][0]['pais'] . '</th><td style="font-size:10px;"><b>Departamento ubicación laboratorio médico:</b> ' . $result['ubicacion_laboratorio'][0]['departamento'] . '</td></tr>
                 <tr><th style="font-size:10px;"><b>Ciudad ubicación laboratorio médico:</b>' . $result['ubicacion_laboratorio'][0]['municipio'] . '</th><td></td></tr>
                <tr><th></th><td></td></tr>';
                }

                $labMedIndice = 0;
                $labMedValPrev = "";

                $html .= '<tr><th><b>Laboratorios médicos:</b></th></tr>';
                if (count($result['laboratorios_agregados']) > 0) {
                    foreach ($result['laboratorios_agregados'] as $index => $lab) {
                        if ($index % 2 == 0) {
                            $labMedValPrev = $lab['nombre'];
                        } else {
                            $html .= '<tr><td style="font-size:10px;">-' . $labMedValPrev . '</td><td style="font-size:10px;">-' . $lab['nombre'] . '</td></tr>';
                            $html .= '<tr><th></th><td></td></tr>';
                        }

                        $labMedIndice++;
                    }


                    if ($labMedIndice % 2 == 1) {
                        $html .= '<tr><td style="font-size:10px;">-' . $labMedValPrev . '</td></tr>';
                        $html .= '<tr><th></th><td></td></tr>';
                    }
                }

                $resultFacturacion = [
                    'Contacto:' => $result['facturacion_contacto'],
                    'Cargo:' => $result['facturacion_cargo'],
                    'Teléfono:' => $result['facturacion_telefono'],
                    'Celular:' => $result['facturacion_celular'],
                    'Correo electrónico:' => $result['facturacion_correo'],
                    'Factura única o por CECO:' => $result['facturacion_factura_unica'],
                    'Fecha de corte para recibir las facturas:' => $result['facturacion_fecha_corte'],
                    'Persona encargada de recibir la factura:' => $result['facturacion_encargado_factura'],
                    '¿Requiere anexo de la factura?:' => $result['requiere_anexo_factura'],
                ];

                $indiceFactura = 0;
                $claveFacPrev = "";
                $valorFacPrev = "";
                $html .= '</table>';

                $html .= '<h3>Facturación</h3>';
                $html .= '<table>';

                foreach ($resultFacturacion as $clave => $valor) {
                    if ($indiceFactura % 2 == 0) {
                        $claveFacPrev = $clave;
                        $valorFacPrev = $valor;
                    } else {
                        $html .= '<tr><td style="font-size:10px;"><b>' . $claveFacPrev . '</b> ' . $valorFacPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                    }
                    $html .= '<tr><th></th><td></td></tr>';
                    $indiceFactura++;
                }

                if ($indiceFactura % 2 == 1) {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $claveFacPrev . '</b> ' . $valorFacPrev . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                }

                $resultSST = [
                    'Riesgo de la empresa(ARL):' => $result['riesgo_cliente'],
                    '¿Realizan trabajo de alto riesgo?:' => $result['trabajo_alto_riesgo'],
                    'Accidentalidad:' => $result['accidentalidad'],
                    'Cuenta con persona encargada de SST:' => $result['encargado_sst'],
                    'Nombre encargado SST:' => $result['nombre_encargado_sst'],
                    'Cargo analista SST:' => $result['cargo_encargado_sst'],
                    '¿Realizan inducción y entrenamiento?:' => $result['induccion_entrenamiento'],
                    '¿Entregan dotación?:' => $result['entrega_dotacion'],
                    '¿Fue evaluado el SGST por la ARL?:' => $result['evaluado_arl'],
                    '¿Entrega EPP?:' => $result['entrega_epp'],
                ];

                $indiceSST = 0;
                $claveSSTPrev = "";
                $valorSSTPrev = "";

                $html .= '</table>';
                $html .= '<h3>Seguridad y salud en el trabajo</h3>';
                $html .= '<table>';

                foreach ($resultSST as $clave => $valor) {
                    if ($indiceSST % 2 == 0) {
                        $claveSSTPrev = $clave;
                        $valorSSTPrev = $valor;
                    } else {
                        $html .= '<tr><td style="font-size:10px;"><b>' . $claveSSTPrev . '</b> ' . $valorSSTPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                    }
                    $html .= '<tr><th></th><td></td></tr>';
                    $indiceSST++;
                }

                if ($indiceSST % 2 == 1) {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $claveSSTPrev . '</b> ' . $valorSSTPrev . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                }

                $html .= ' </table>';

                $eppMedIndice = 0;
                $eppMedValPrev = "";
                if ($result['entrega_epp'] == "Si") {
                    $html .= '<table>';
                    $html .= '<tr><th><b>Epp solicitados:</b></th></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    if (count($result['clientes_epps']) > 0) {
                        foreach ($result['clientes_epps'] as $index => $epp) {

                            $html .= '<tr><td style="font-size:10px;">-' . $epp['nombre'] . '</td></tr>';
                            $html .= '<tr><th></th><td></td></tr>';

                            $eppMedIndice++;
                        }
                    }
                    $html .= '</table>';
                }

                $html .= '<h3>Cargos</h3>';
                if (count($result['cargos2']) > 0) {
                    $html .= '<table><tr><th><b>Número de cargos registrados:</b></th><td>' . count($result['cargos2']) . '</td></tr></table>';
                    foreach ($result['cargos2'] as $index => $cargo) {
                        $html .= '<h4>Cargo: ' . $index + 1 . '</h4>';
                        $html .= '<table>
                    <tr><th style="font-size:10px;"><b>Tipo de cargo:</b> ' . $cargo['tipo_cargo'] . '</th><td style="font-size:10px;"> <b>Categoria del cargo:</b>' . $cargo['categoria'] . '</td> </tr>
                    <tr><th style="font-size:10px;"></th><td></td></tr>
                    <tr><th style="font-size:10px;"><b>Cargo:</b> ' . $cargo['cargo'] . '</th><td style="font-size:10px;"><b>Riesgo del cargo(ARL): </b>' . $cargo['riesgo_laboral'] . '</td></tr>
                    <tr><th style="font-size:10px;"></th><td></td></tr>
                    <tr><th style="font-size:10px;"><b>Funciones del cargo:</b>' . $cargo['funcion_cargo'] . '</th><td></td></tr>
                    <tr><th style="font-size:10px;"></th><td></td></tr>';


                        $examIndice = 0;
                        $examValPrev = "";
                        $html .= '<tr><th><b>Exámenes:</b></th><td style="text-align:justify;"></td></tr>';
                        if (count($cargo['examenes']) > 0) {
                            foreach ($cargo['examenes'] as $index => $examen) {
                                if ($index % 2 == 0) {
                                    $examValPrev = $examen['nombre'];
                                } else {
                                    $html .= '<tr><td style="font-size:10px;">-' . $examValPrev . '</td><td style="font-size:10px;">-' . $examen['nombre'] . '</td></tr>';
                                    $html .= '<tr><th></th><td></td></tr>';
                                }

                                $examIndice++;
                            }


                            if ($examIndice % 2 == 1) {
                                $html .= '<tr><td style="font-size:10px;">-' . $examValPrev . '</td></tr>';
                                $html .= '<tr><th></th><td></td></tr>';
                            }
                        }


                        if (count($cargo['recomendaciones']) > 0) {
                            $html .= '<tr><th></th><td></td></tr>';
                            $html .= '<table><tr><th style="text-align:justify; font-size:10px; margin:20px;"><b>Orientaciones específicas para los exámenes:</b> ' . $cargo['recomendaciones'][0]['recomendacion1'] . '</th></tr>';
                            $html .= '<tr><th></th><td></td></tr>';
                            $html .= '<table><tr><th style="text-align:justify; font-size:10px; margin:20px;"><b>Patologías que restringen la labor:</b> ' . $cargo['recomendaciones'][0]['recomendacion2'] . '</th></tr>';
                        }
                    }

                    $html .= ' </table>';
                }
            }

            $html .= '<h3>Información financiera:</h3>';


            if (count($result['accionistas']) > 0) {
                $html .= '<table>';
                foreach ($result['accionistas'] as $index => $accionista) {
                    $html .= '<h4>Accionista ' . $index + 1 . '</h4>';
                    $html .= '<tr><th style="font-size:10px;"><b>Tipo de identificación: </b> ' . $accionista['des_tip'] . '</th><td style="font-size:10px;"><b>Identificación:</b> ' . $accionista['identificacion'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<tr><th style="font-size:10px;"><b>Socio/accionista: </b>' . $accionista['socio'] . '</th><td style="font-size:10px;"><b>Porcentaje participación:</b>' . $accionista['participacion'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<table>';
                }
                $html .= '</table>';
            }


            $html .= '<h3>Representantes legales:</h3>';

            if (count($result['representantes_legales']) > 0) {
                $html .= '<table>';
                foreach ($result['representantes_legales'] as $index => $representante) {
                    $html .= '<h4>Representante legal ' . $index + 1 . '</h4>';
                    $html .= '<tr><th style="font-size:10px;"><b>Tipo de identificación:</b> ' . $representante['des_tip'] . '</th><td style="font-size:10px;"><b>Identificación:</b> ' . $representante['identificacion'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<tr><th style="font-size:10px;"><b>Nombre:</b> ' . $representante['nombre'] . '</th><td style="font-size:10px;"><b>Correo electrónico:</b> ' . $representante['pais'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<tr><th style="font-size:10px;"><b>Correo electrónico:</b> ' . $representante['departamento'] . '</th><td style="font-size:10px;"><b>Correo electrónico:</b>' . $representante['ciudad_expedicion'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<tr><th style="font-size:10px;"><b>Número celular:</b> ' . $representante['telefono'] . '</th><td style="font-size:10px;"><b>Correo electrónico:</b>' . $representante['correo'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<table>';
                }
                $html .= '</table>';
            }
            $html .= '<h3>Miembros junta directiva:</h3>';
            if (count($result['junta_directiva']) > 0) {
                $html .= '<table>';
                foreach ($result['junta_directiva'] as $index => $miembro) {
                    $html .= '<h4>Miembro ' . $index + 1 . '</h4>';
                    $html .= '<tr><th style="font-size:10px;"><b>Tipo de identificación: </b>' . $miembro['des_tip'] . '</th><td style="font-size:10px;"><b>Identificación:</b> ' . $miembro['identificacion'] . '</td></tr>';
                    $html .= '<tr><th></th><td></td></tr>';
                    $html .= '<tr><th style="font-size:10px;"><b>Nombre:</b> ' . $miembro['nombre'] . '</th><td></td></tr>';
                    $html .= '<table><tr><th></th><td></td></tr>';
                }
                $html .= '<table>';
            } else {
                $html .= '<table><tr><th><p>La empresa cuenta con Junta directiva</p></th><td></td></tr>';
            }

            $html .= '<h3>Calidad tributaria:</h3>';
            $html .= '<table>';
            if ($result['responsable_inpuesto_ventas'] == 1) {
                $html .= '<tr><th style="font-size:10px;"><b>Responsable de Impuestos a las Ventas:</b> Si</th><td style="font-size:10px;"><b>Correo para factura electrónica:</b> ' . $result['correo_facturacion_electronica'] . '</td></tr>';
            } else {
                $html .= '<tr><th style="font-size:10px;"><b>Responsable de Impuestos a las Ventas:</b> No</th><td style="font-size:10px;"><b>Correo para factura electrónica:</b> ' . $result['correo_facturacion_electronica'] . '</td></tr>';
            }
            $html .= '<tr><th></th><td></td></tr>';

            if ($result['calidad_tributaria'][0]['gran_contribuyente'] == 1) {
                $html .= '<tr><th style="font-size:10px;"><b>Sucursal de facturación:</b>' . $result['sucursal_facturacion'] . '</th><td style="font-size:10px;"><b>¿Es Gran Contribuyente?:</b> Si</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
                $html .= '<tr><th style="font-size:10px;"><b>Fecha de resolución gran contribuyente:</b>' . $result['calidad_tributaria'][0]['fecha_gran_contribuyente'] . '</th><td style="font-size:10px;"><b>Número de resolución gran contribuyente:</b>' . $result['calidad_tributaria'][0]['resolucion_gran_contribuyente'] . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            } else {
                $html .= '<tr><th style="font-size:10px;"><b>Sucursal de facturación:</b>' . $result['sucursal_facturacion'] . '</th><td style="font-size:10px;"><b>¿Es Gran Contribuyente?: </b> No</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            if ($result['calidad_tributaria'][0]['auto_retenedor'] == 1) {
                $html .= '<tr><th style="font-size:10px;"><b>¿Es auto-retenedor?:</b> Si</th><td style="font-size:10px;"><b>Número de resolución auto-retenedor:</b> ' . $result['calidad_tributaria'][0]['resolucion_auto_retenedor'] . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
                $html .= '<tr><th style="font-size:10px;"><b>Fecha de resolución auto-retenedor:</b>' . $result['calidad_tributaria'][0]['fecha_auto_retenedor'] . '</th><td></td></tr>';
            } else {
                $html .= '<tr><th style="font-size:10px;"><b>¿Es auto-retenedor?:</b> No</th><td></td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            if ($result['calidad_tributaria'][0]['resolucion_exento_impuesto_rent'] == 1) {
                $html .= '<tr><th style="font-size:10px;"><b>¿Exento de Impuesto a la Renta?:</b> Si</th><td style="font-size:10px;"><b>Número de resolución impuesto a la renta:</b>' . $result['calidad_tributaria'][0]['resolucion_exento_impuesto_rent'] . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
                $html .= '<tr><th style="font-size:10px;"><b>Fecha de resolución impuesto a la renta:</b> ' . $result['calidad_tributaria'][0]['fecha_exento_impuesto_rent'] . '</th><td></td></tr>';
            } else {
                $html .= '<tr><th style="font-size:10px;"><b>¿Exento de Impuesto a la Renta?:</b> No</th><td></td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            $html .= '</table>';

            $resultContador = [
                'Tipo de identificación:' => $result['tipo_identificacion_contador'],
                'Identificación:' => $result['identificacion_contador'],
                'Nombre:' => $result['nombre_contador'],
                'Teléfono:' => $result['telefono_contador'],
            ];

            $indiceContador = 0;
            $claveContaPrev = "";
            $valorContaPrev = "";

            $html .= '<h3>Datos del contador</h3> ';
            $html .= '<table>';
            foreach ($resultContador as  $clave => $valor) {

                if ($indiceContador % 2 == 0) {
                    $claveContaPrev = $clave;
                    $valorContaPrev = $valor;
                } else {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $claveContaPrev . '</b> ' . $valorContaPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                }

                $html .= '<tr><th></th><td></td></tr>';
                $indiceContador++;
            }

            if ($indiceContador % 2 == 1) {
                $html .= '<tr><td style="font-size:10px;"><b>' . $claveContaPrev . '</b> ' . $valorContaPrev . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            $html .= '</table>';

            $html .= '<h3>Datos del tesorero</h3> ';
            $html .= '<table>';
            $html .= '<tr><th style="font-size:10px;"><b>Nombre:</b> ' . $result['nombre_tesorero'] . '</th><td style="font-size:10px;"><b>Teléfono:</b> ' . $result['telefono_tesorero'] . '</td></tr>';
            $html .= '<tr><th></th><td></td></tr>';
            $html .= '<tr><th style="font-size:10px;"><b>Correo:</b> ' . $result['correo_tesorero'] . '</th><td></td></tr>';
            $html .= '</table>';

            $resulUltimoPeriodo = [
                'Ingreso mensual:' => $result['ingreso_mensual'],
                'Costos y Gastos Mensual:' => $result['costos_gastos_mensual'],
                'Activos:' => $result['activos'],
                'Otros ingresos:' => $result['otros_ingresos'],
                'Detalle de otros ingresos:' => $result['detalle_otros_ingresos'],
                'Pasivos:' => $result['pasivos'],
                'Total ingresos:' => $result['total_ingresos'],
                'Reintegro de costos y gastos:' => $result['reintegro_costos_gastos'],
                'Patrimonio:' => $result['patrimonio'],
            ];

            $indiceUltimoPeriodo = 0;
            $claveUltimoPrev = "";
            $valorUltimoPrev = "";

            $html .= '<h3>Último periodo contable</h3> ';
            $html .= '<table>';
            foreach ($resulUltimoPeriodo as  $clave => $valor) {

                if ($indiceUltimoPeriodo % 2 == 0) {
                    $claveUltimoPrev = $clave;
                    $valorUltimoPrev = $valor;
                } else {
                    $html .= '<tr><td style="font-size:10px;"><b>' . $claveUltimoPrev . '</b> ' . $valorUltimoPrev . '</td><td style="font-size:10px;"><b>' . $clave . '</b> ' . $valor . '</td></tr>';
                }

                $html .= '<tr><th></th><td></td></tr>';
                $indiceUltimoPeriodo++;
            }

            if ($indiceUltimoPeriodo % 2 == 1) {
                $html .= '<tr><td style="font-size:10px;"><b>' . $claveUltimoPrev . '</b> ' . $valorUltimoPrev . '</td></tr>';
                $html .= '<tr><th></th><td></td></tr>';
            }
            $html .= '</table>';

            $html .= '<h3>operaciones internacionales</h3> ';
            $html .= '<table>';
            if ($result['operaciones_internacionales'] == 1) {
                $html .= '<tr><th style="font-size:10px;"><b>¿Realiza operaciones en moneda extranjera?:</b> Si</th><td style="font-size:10px;"><b>¿Cuál?:</b>' . $result['tipo_operacion_internacional'] . '</td></tr>';
            } else {
                $html .= '<tr><th style="font-size:10px;"><b>¿Realiza operaciones en moneda extranjera?:</b> No</th><td style="font-size:10px;"><b>¿Cuál?:</b>' . $result['tipo_operacion_internacional'] . '</td></tr>';
            }
            $html .= '<tr><th></th><td></td></tr>';
            $html .= '</table>';

            $html .= '<h3>Referencias bancarias</h3> ';
            $html .= '<table>';
            if (count($result['referencia_bancaria'])) {
                foreach ($result['referencia_bancaria'] as $index => $referencia) {
                    $html .= '<h4>Referencia ' . $index + 1 . ' </h4> ';
                    $html .= '<tr style="font-size:10px;"><th><b>Banco:</b>' . $referencia['banco'] . '</th><td><b>Número de cuenta:</b> ' . $referencia['numero_cuenta'] . '</td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                    $html .= '<tr style="font-size:10px;"><th><b>Tipo cuenta:</b> ' . $referencia['tipo_cuenta'] . '</th><td><b>Sucursal:</b>' . $referencia['sucursal'] . '</td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                    $html .= '<tr style="font-size:10px;"><th><b>Teléfono:</b>' . $referencia['telefono'] . '</th><td><b>Contacto:</b>' . $referencia['contacto'] . '</td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                }
            }

            $html .= '</table>';

            $html .= '<h3>Referencias comerciales</h3> ';
            $html .= '<table>';
            if (count($result['referencia_comercial'])) {
                foreach ($result['referencia_comercial'] as $index => $referencia) {
                    $html .= '<h4>Referencia ' . $index + 1 . ' </h4> ';
                    $html .= '<tr style="font-size:10px;"><th><b>Nombre:</b>' . $referencia['nombre'] . '</th><td><b>Contacto:</b> ' . $referencia['contacto'] . '</td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                    $html .= '<tr style="font-size:10px;"><th><b>Teléfono:</b> ' . $referencia['telefono'] . '</th><td></td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                }
            }
            $html .= '</table>';

            $html .= '<h3>Declaraciones y autorizaciones</h3> ';
            $html .= '<p style="text-align:justify; font-size:10px;">Cumplo con alguno de los siguientes atributos o tengo un vínculo familiar (cónyuge o compañero permanente, padres, abuelos, hijos, nietos, cuñados, adoptantes o adoptivos) con una persona que:</p>

            <p style="font-size:10px;">-Esté expuesta políticamente según la legislación nacional.</p>

            <p style="font-size:10px;">-Tenga la representación legal de un organismo internacional.</p>

            <p style="font-size:10px;">-Goce de reconocimiento público generalizado.</p>

            <p style="font-size:10px;">-En afirmativo indique que le aplica y diligencie la siguiente información.</p>';

            if ($result['declaraciones_autorizaciones'] == 1) {
                $html .= '<label style="font-size:10px;"  for="rqb2"><span style="font-size:10px;">X </span>Si acepto</label><br/><label style="font-size:10px;"><span style="font-size:10px;">   </span>No acepto</label><br />';
            } else {
                $html .= '<label  for="rqb2"><span style="font-size:20px;">°</span>Si acepto</label><br />  <label  for="rqb3"><span style="font-size:20px;">*</span> No acepto</label><br />';
            }

            $html .= '<table>';
            $html .= '<h3>Datos de personas expuestas politicamente</h3> ';
            if (count($result['personas_expuestas']) > 0) {
                foreach ($result['personas_expuestas'] as $index => $persona) {
                    $html .= '<h4>Referencia ' . $index + 1 . ' </h4> ';
                    $html .= '<tr style="font-size:10px;"><th><b>Tipo de identificación:</b>' . $persona['des_tip'] . '</th><td><b>Identificación:</b>' . $persona['identificacion'] . '</td></tr>';
                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                    $html .= '<tr style="font-size:10px;"><th><b>Nombre:</b> ' . $persona['nombre'] . '</th><td><b>Parentesco:</b>' . $persona['parentesco'] . '</td></tr>';

                    $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
                }
            }
            $html .= '</table>';

            $html .= '<h3>Declaración de Origen de Fondos</h3> ';
            $html .= '<p style="text-align:justify; font-size:10px;">Quien suscribe la presente solicitud obrando en nombre propio y/o en representación legal de la persona 
            jurídica que represento, de manera voluntaria y dando certeza de que todo lo aquí consignado es cierto, veraz y verificable, 
            realizo la siguiente declaración de fuente de bienes y/o fondos, con el propósito de dar cumplimiento a lo señalado
            al respecto a las normas legales vigentes y concordantes.</p>

            <p style="text-align:justify; font-size:10px;">A. Declaro que yo y/o la persona jurídica que represento es beneficiaria efectiva de los recursos
            y son compatibles con mis actividades y situación patrimonial.</p>

            <p style="text-align:justify; font-size:10px;">B. Que los recursos que se entreguen de mi parte en desarrollo de cualquiera de las relaciones contractuales que tenga
             con los destinatarios de la presente declaración, provienen de mi patrimonio y/o de la sociedad que represento y no de 
            terceros, y se derivan de las siguientes fuentes: (detalle de la actividad o negocio del que provienen los recursos)</p>';

            $html .= '<tr style="font-size:10px;"><th><b>- ' . $result['origen_fondos']->origen_fondos . '</b></th><td>Otra¿Cuál?<b>' . $result['origen_fondos']->otro_origen . '</b></td></tr>';
            $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
            $html .= '
            <p style="text-align:justify; font-size:10px;">C. Declaro que los recursos no provienen de ninguna actividad ilícita de las contempladas en el Código Penal Colombiano o 
            en cualquier norma que lo modifique o adicione.</p>

            <p style="text-align:justify; font-size:10px;">D. No se admitirá que terceros efectúen depósitos a mis cuentas y/o de la Entidad que represento con fondos provenientes 
            de las actividades ilícitas contempladas en el Código penal Colombiano o en cualquier norma que lo modifique, sustituya o adicione,
            ni se efectuarán transacciones destinadas a tales actividades o a favor de personas relacionadas con las mismas.</p>

            <p  style="font-size:10px;">E. Los recursos que recibo de mis contrapartes principalmente los capto por: </p>';

            $html .= '<tr style="font-size:10px;"><th><b>- ' . $result['origen_fondos']->origen_medios . '</b></th><td><b>- ' . $result['origen_fondos']->origen_medios2 . '</b></td></tr>';
            $html .= '<tr style="font-size:10px;"><th></th><td></td></tr>';
            $html .= '         
            <p  style="font-size:10px;">F. Las operaciones que realizo por mi actividad implican un alto manejo de efectivo:</p>';

            if ($result['origen_fondos']->alto_manejo_efectivo == 1) {
                $html .= '<p style="font-size:10px;"><b>Si</b></p> ';
            } else {
                $html .= '<p style="font-size:10px;"><b>No</b></p> ';
            }

            $html .= '<p style="text-align:justify; font-size:10px;">En nombre propio y/o de mi representado, declaro que no estoy impedido para realizar cualquier tipo de operación y 
            que conozco y acepto las normas que regulan el comercio colombiano y me obligo a cumplirlas. Conozco y acepto los riesgos que puedan 
            presentarse frente a las instrucciones y órdenes que imparta, derivados de la utilización de los medios y canales de distribución de 
            productos y servicios, tales como Internet, correos electrónicos u otros mecanismos similares, mensajería instantánea, teléfono, fax, 
            medios digitales entre otros. Autorizo a realizar los traslados de recursos y/o valores, previo cumplimiento de los procedimientos 
            establecidos por la entidad; así mismo, autorizo la realización de transferencias bancarias y conozco los riesgos de su utilización.
             Conozco y acepto las políticas establecidas para todos los productos ofrecidos, incluyendo los servicios de Internet. Bajo la gravedad 
            de juramento manifiesto que todos los datos acá consignados, incluidos los números de identificación tributaria, son ciertos, que la 
            información que adjunto es veraz, fidedigna, completa y verificable y autorizo su verificación ante cualquier persona natural o jurídica,
             pública o privada, sin limitación alguna, desde ahora y mientras subsista alguna relación comercial y que toda declaración falsa o 
             inexacta podrá ser sancionada, por las autoridades de conformidad con la legislación aplicable. Me comprometo a actualizar la información
              y documentación de acuerdo con la solicitud que se me haga, a proporcionar toda la información adicional y de apoyo que sea necesaria y 
              requerida, por lo menos cada año y cada vez que se presenten modificaciones respecto de cualquiera de mis datos, esto con el fin de dar 
              cumplimiento a la normatividad vigente para el efecto, y por tanto, autorizo, entre otras, a reportar la información fiscal, a verificar
               la autenticidad de mis firmas y de mis ordenantes y/o a validar los poderes y facultades de mis representantes. A su vez declaro que
                asumiré la responsabilidad civil, administrativa y/o penal derivada de cualquier información errónea, falsa o inexacta que llegaré
                 a suministrar o que dejare de suministrar oportunamente. De igual forma, declaro que resarciré a La empresa por cualquier multa,
                  perdida o daño que pudiera llegar a sufrir como consecuencia de la inexactitud o falsedad de dicha información Autorizo a La empresa
                   a suministrar la información contenida ente documento, al igual que sus anexos, a las autoridades administrativas y gubernamentales
                    correspondientes, incluidas las autoridades de mi país de residencia o de nacionalidad, de conformidad con la regulación vigente,
                     entre ellos, los Convenios Internacionales firmados por Colombia. Manifiesto que yo y/o la empresa que represento y sus empleados
                      conocen bien las normas referentes a la prevención del Lavado de Activos y Financiación del Terrorismo, todos aportamos con el
                       fin de no ser cómplices de la violación de las normas de esta ley. Igualmente, que no he pertenecido ni pertenezco a ningún
                        tipo de grupos ilegales al margen de la Ley, no les he auxiliado o colaborado en el desarrollo de sus actividades ilícitas,
                         como tampoco he realizado actividades de lavados de activos en Colombia o fuera de ella y que los bienes que conforman mi 
                         patrimonio han sido adquiridos por vías legales en desarrollo de mi profesión o actividad. De la misma manera, declaro que 
                         no tengo vínculos de parentesco con personas que estén o hayan estado incluidas en listas públicas como sospechosos de Lavado de 
                         Activos/Financiación de terrorismo o las empresas de las cuales sean accionistas, o que desarrollen o hayan desarrollado, apoyado 
                         o financiado cualquiera de las actividades descritas en el párrafo precedente. Todos los datos aquí consignados y los documentos 
                         anexos a él, son ciertos, la información que adjunto es veraz y verificable, y autorizo su verificación ante cualquier persona natural 
                         o jurídica, privada o pública, sin limitación alguna, desde ahora y mientras subsista alguna relación comercial con cualquiera de las 
                         entidades que pertenezcan a SAITEMP S.A. o con quien represente sus derechos, y me comprometo a actualizar la información y/o 
                         documentación al menos una vez cada 2 años o cada vez que se me indique. Así mismo, autorizo a SAITEMP S.A., o a quien represente 
                         sus derechos, en forma permanente e irrevocable, para que con fines estadísticos y de información financiera o comercial, consulte, 
                         informe, reporte, procese o divulgue, a las entidades de consulta de bases de datos o Centrales de Información y Riesgo, todo 
                         lo referente a mi comportamiento como cliente en general.</p> ';

            $html .= '<h3>Autorización de Tratamiento de Datos Personales</h3> ';

            $html .= '<p style="text-align:justify; font-size:10px; ">La Sociedad SAITEMP S.A., en cumplimiento de lo definido por la Ley 1581 de 2012, el decreto reglamentario 1377 de 2013 y 
            nuestra política de protección de datos personales, le informan que los datos personales que usted suministre en cualquiera de nuestros 
            establecimientos en desarrollo de cualquier operación comercial, serán tratados mediante el uso y mantenimiento de medidas de seguridad 
            técnicas, físicas y administrativas a fin de impedir que terceros no autorizados accedan a los mismos, lo anterior de conformidad con 
            lo establecido en la ley. El responsable del tratamiento de sus datos personales es SAITEMP S.A. sociedad legalmente existente de 
            acuerdo con la leyes Colombianas, domiciliado en la ciudad de MEDELLÍN, en la CALLE 51 # 49-11 PISO 10, quien recogerá dichos datos a 
            través de sus diferentes canales y serán usados para a) Ofrecer o informarle productos b) Para hacerle llegar información publicitaria 
            sobre promociones c) Atender o formalizar cualquier solicitud relacionada con nuestro objeto social e) Controles estadísticos sobre 
            proveedores, clientes f) Establecer rotación de los empleados. Usted podrá ejercer los derechos que la ley prevé, siguiendo los 
            procedimientos establecidos en nuestras políticas y procedimientos de Protección de datos Personales publicados en la página web de la 
            empresa, http://www.saitempsa.com, o solicitando la información que requiera a través de nuestro correo misdatos@saitempsa.com o llamando 
            al teléfono: (4) 4485744 Tenga en cuenta que el ejercicio de sus derechos no es requisito previo ni impide el ejercicio de otros 
            derechos y que cualquier modificación al presente aviso le será avisado a través de nuestra página Web. Leído lo anterior, autorizo 
            de manera previa, explicita e inequívoca a la sociedad SAITEMP S.A., para el tratamiento de los datos personales suministrados por mi 
            persona para las finalidades aquí establecidas. Declaro que soy el titular de la información reportada en este formato para autorizar 
            el tratamiento de datos personales y que la he suministrado de forma voluntaria y es completa, veraz, exacta y verídica.</p> ';

            if ($result['tratamiento_datos_personales'] == 1) {
                $html .= '<label style="font-size:10px;"  for="rqb2"><span style="font-size:10px;">X </span>Si acepto</label><br/><label style="font-size:10px;"><span style="font-size:10px;">   </span>No acepto</label><br />';
            } else {
                $html .= '<label  for="rqb2"><span style="font-size:10px;">   </span>Si acepto</label><br />  <label  for="rqb3"><span style="font-size:10px;">X</span> No acepto</label><br />';
            }



            // Generar PDF
            $pdf->writeHTML($html, true, false, true, false, '');
            if ($outputToBrowser) {
                // Enviar el PDF directamente al navegador
                $pdf->Output('Reporte_Cliente_' . $id . '.pdf', 'I');
            } else {
                // Guardar el PDF en un archivo temporal y devolver su ruta
                $tempFilePath = tempnam(sys_get_temp_dir(), 'tcpdf_') . '.pdf';
                $pdf->Output($tempFilePath, 'F');
                return $tempFilePath;
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}