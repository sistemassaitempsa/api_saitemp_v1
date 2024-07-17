<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MatrizRiesgo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MatrizRiesgoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cantidad)
    {
        $result = MatrizRiesgo::select(
            'id',
            'tipo_proceso_id',
            'nombre_proceso_id',
        )
        ->paginate($cantidad);
        return response()->json($result);
    }

    // usr_app_matriz_riesgo
    public function byid($id){
        $result = MatrizRiesgo::join('usr_app_riesgos_tipos_proceso as tp','tp.id','usr_app_matriz_riesgo.tipo_proceso_id')
        ->join('usr_app_riesgos_nombres_proceso as np','np.id','usr_app_matriz_riesgo.nombre_proceso_id')
        ->join('usr_app_riesgos_nivel_probabilidad as a_nip','a_nip.id','usr_app_matriz_riesgo.a_probabilidad_id')
        ->join('usr_app_riesgos_nivel_impacto as a_niip','a_niip.id','usr_app_matriz_riesgo.a_impacto_id')
        ->join('usr_app_riesgos_metodos_identificacion as a_meti','a_meti.id','usr_app_matriz_riesgo.a_metodo_identificacion_id')
        ->join('usr_app_riesgos_factores as a_fac','a_fac.id','usr_app_matriz_riesgo.a_factor_id')
        ->join('usr_app_riesgos_seguimientos as a_seg','a_seg.id','usr_app_matriz_riesgo.a_seguimiento_id')
        ->join('usr_app_riesgos_documentos_registrados as a_docr','a_docr.id','usr_app_matriz_riesgo.a_documento_registrado_id')
        ->join('usr_app_riesgos_clases_control as a_clac','a_clac.id','usr_app_matriz_riesgo.a_clase_control_id')
        ->join('usr_app_riesgos_frecuencias_control as a_fc','a_fc.id','usr_app_matriz_riesgo.a_frecuencia_control_id')
        ->join('usr_app_riesgos_tipos_control as a_tc','a_tc.id','usr_app_matriz_riesgo.a_tipo_control_id')
        ->join('usr_app_riesgos_existe_evidencias as a_eev','a_eev.id','usr_app_matriz_riesgo.a_existe_evidencia_id')
        ->join('usr_app_riesgos_ejecuciones_eficaces as a_ee','a_ee.id','usr_app_matriz_riesgo.a_ejecucion_eficaz_id')
        
        ->join('usr_app_riesgos_nivel_probabilidad as o_nip','o_nip.id','usr_app_matriz_riesgo.o_probabilidad_id')
        ->join('usr_app_riesgos_nivel_impacto as o_niip','o_niip.id','usr_app_matriz_riesgo.o_impacto_id')
        ->join('usr_app_riesgos_metodos_identificacion as o_meti','o_meti.id','usr_app_matriz_riesgo.o_metodo_identificacion_id')
        ->join('usr_app_riesgos_factores as o_fac','o_fac.id','usr_app_matriz_riesgo.o_factor_id')
        ->join('usr_app_riesgos_seguimientos as o_seg','o_seg.id','usr_app_matriz_riesgo.o_seguimiento_id')
        ->join('usr_app_riesgos_documentos_registrados as o_docr','o_docr.id','usr_app_matriz_riesgo.o_documento_registrado_id')
        ->join('usr_app_riesgos_clases_control as o_clac','o_clac.id','usr_app_matriz_riesgo.o_clase_control_id')
        ->join('usr_app_riesgos_frecuencias_control as o_fc','o_fc.id','usr_app_matriz_riesgo.o_frecuencia_control_id')
        ->join('usr_app_riesgos_tipos_control as o_tc','o_tc.id','usr_app_matriz_riesgo.o_tipo_control_id')
        ->join('usr_app_riesgos_existe_evidencias as o_eev','o_eev.id','usr_app_matriz_riesgo.o_existe_evidencia_id')
        ->join('usr_app_riesgos_ejecuciones_eficaces as o_ee','o_ee.id','usr_app_matriz_riesgo.o_ejecucion_eficaz_id')
        ->select(
            'usr_app_matriz_riesgo.id',
            'usr_app_matriz_riesgo.nombre_riesgo',
            'usr_app_matriz_riesgo.oportunidad',
            'usr_app_matriz_riesgo.causa',
            'usr_app_matriz_riesgo.plan_accion',
            'usr_app_matriz_riesgo.consecuencia',
            'usr_app_matriz_riesgo.efecto',
            'usr_app_matriz_riesgo.amenaza',
            'usr_app_matriz_riesgo.oportunidad_2',

            'tp.nombre as a_tipo_proceso',
            'np.nombre as a_nombre_proceso',
            'tp.id as a_tipo_proceso_id',
            'np.id as a_nombre_proceso_id',
            'a_nip.id as a_probabilidad_id',
            'a_nip.probabilidad as a_probabilidad',
            'a_nip.nivel as a_nivel_probabilidad',
            'a_niip.id as a_impacto_id',
            'a_niip.impacto as a_impacto',
            'a_niip.nivel as a_nivel_impacto',
            'a_meti.id as a_metodo_identificacion_id',
            'a_meti.nombre as a_metodo_identificacion',
            'a_fac.id as a_factor_id',
            'a_fac.nombre as a_factor',
            'a_seg.id as a_seguimiento_id',
            'a_seg.nombre as a_seguimiento',
            'a_docr.id as a_documento_registrado_id',
            'a_docr.nombre as a_documento_registrado',
            'a_docr.peso as a_documento_registrado_peso',
            'a_clac.id as a_clase_control_id',
            'a_clac.nombre as a_clase_control',
            'a_clac.peso as a_clase_control_peso',
            'a_fc.id as a_frecuencia_control_id',
            'a_fc.nombre as a_frecuencia_control',
            'a_fc.peso as a_frecuencia_control_peso',
            'a_tc.id as a_tipo_control_id',
            'a_tc.nombre as a_tipo_control',
            'a_tc.peso as a_tipo_control_peso',
            'a_eev.id as a_existe_evidencia_id',
            'a_eev.nombre as a_existe_evidencia',
            'a_eev.peso as a_existe_evidencia_peso',
            'a_ee.id as a_ejecucion_eficaz_id',
            'a_ee.nombre as a_ejecucion_eficaz',
            'a_ee.peso as a_ejecucion_eficaz_peso',

            'o_nip.id as o_probabilidad_id',
            'o_nip.probabilidad as o_probabilidad',
            'o_nip.nivel as o_nivel_probabilidad',
            'o_niip.id as o_impacto_id',
            'o_niip.impacto as o_impacto',
            'o_niip.nivel as o_nivel_impacto',
            'o_meti.id as o_metodo_identificacion_id',
            'o_meti.nombre as o_metodo_identificacion',
            'o_fac.id as o_factor_id',
            'o_fac.nombre as o_factor',
            'o_seg.id as o_seguimiento_id',
            'o_seg.nombre as o_seguimiento',
            'o_docr.id as o_documento_registrado_id',
            'o_docr.nombre as o_documento_registrado',
            'o_docr.peso as o_documento_registrado_peso',
            'o_clac.id as o_clase_control_id',
            'o_clac.nombre as o_clase_control',
            'o_clac.peso as o_clase_control_peso',
            'o_fc.id as o_frecuencia_control_id',
            'o_fc.nombre as o_frecuencia_control',
            'o_fc.peso as o_frecuencia_control_peso',
            'o_tc.id as o_tipo_control_id',
            'o_tc.nombre as o_tipo_control',
            'o_tc.peso as o_tipo_control_peso',
            'o_eev.id as o_existe_evidencia_id',
            'o_eev.nombre as o_existe_evidencia',
            'o_eev.peso as o_existe_evidencia_peso',
            'o_ee.id as o_ejecucion_eficaz_id',
            'o_ee.nombre as o_ejecucion_eficaz',
            'o_ee.peso as o_ejecucion_eficaz_peso',
        )
        ->where('usr_app_matriz_riesgo.id',$id)
        ->first();
        return response()->json($result);
    }

    public function lideres()
    {
        $result = User::select(
            'id',
            DB::raw("CONCAT(nombres,' ',apellidos)  AS nombre")
        )
            ->where('lider', 1)
            ->get();
        return response()->json($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $result = new MatrizRiesgo();
        $result->tipo_proceso_id = $request->tipo_proceso['id'];
        $result->nombre_proceso_id = $request->nombre_proceso['id'];
        $result->nombre_riesgo = $request->nombre_riesgo;
        $result->oportunidad = $request->oportunidad;
        $result->causa = $request->causa;
        $result->plan_accion = $request->plan_accion;
        $result->consecuencia = $request->consecuencia;
        $result->efecto = $request->efecto;
        $result->amenaza = $request->amenaza;
        $result->oportunidad_2 = $request->oportunidad2;

        $result->a_probabilidad_id = $request->a_probabilidad['id'];
        $result->a_impacto_id = $request->a_impacto['id'];
        $result->a_total = $request->a_total;
        $result->a_nivel_riesgo = $request->a_nivel_riesgo;
        $result->a_tratamiento = $request->a_tratamiento;
        $result->a_metodo_identificacion_id = $request->a_metodo_indentificacion['id'];
        $result->a_factor_id = $request->a_factor['id'];
        $result->a_nombre_control = $request->a_nombre_control;
        $result->a_soporte = $request->a_soporte;
        $result->a_seguimiento_id = $request->a_seguimiento['id'];
        $result->a_documento_registrado_id = $request->a_documento_registrado['id'];
        $result->a_clase_control_id = $request->a_clase_control['id'];
        $result->a_frecuencia_control_id = $request->a_frecuencia_control['id'];
        $result->a_tipo_control_id = $request->a_tipo_control['id'];
        $result->a_existe_evidencia_id = $request->a_existe_evidencia['id'];
        $result->a_ejecucion_eficaz_id = $request->a_ejecucion_eficas['id'];
        $result->a_resultado_control_descripcion = $request->a_resultado_control['descripcion'];
        $result->a_resultado_control_peso = $request->a_resultado_control['peso'];
        
        $result->o_probabilidad_id = $request->a_probabilidad['id'];
        $result->o_impacto_id = $request->a_impacto['id'];
        $result->o_total = $request->o_total;
        $result->o_nivel_riesgo = $request->o_nivel_riesgo;
        $result->o_tratamiento = $request->o_tratamiento;
        $result->o_metodo_identificacion_id = $request->o_metodo_indentificacion['id'];
        $result->o_factor_id = $request->o_factor['id'];
        $result->o_nombre_control = $request->o_nombre_control;
        $result->o_soporte = $request->o_soporte;
        $result->o_seguimiento_id = $request->o_seguimiento['id'];
        $result->o_documento_registrado_id = $request->o_documento_registrado['id'];
        $result->o_clase_control_id = $request->o_clase_control['id'];
        $result->o_frecuencia_control_id = $request->o_frecuencia_control['id'];
        $result->o_tipo_control_id = $request->o_tipo_control['id'];
        $result->o_existe_evidencia_id = $request->o_existe_evidencia['id'];
        $result->o_ejecucion_eficaz_id = $request->o_ejecucion_eficas['id'];
        $result->o_resultado_control_descripcion = $request->o_resultado_control['descripcion'];
        $result->o_resultado_control_peso = $request->o_resultado_control['peso'];

        $result->responsable_id = $request->responsable_id;
        $result->nombre_responsable = $request->responsable_nombre;
        $result->ultima_revision = $request->ultima_revision;
        if($result->save()){
            return 'Registro guardado con exito';
        }else{

            return 'Rerror al guardar registro';
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
