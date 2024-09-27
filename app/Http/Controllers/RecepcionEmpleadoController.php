<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecepcionEmpleado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\ReferenciasModel;
use App\Models\ReferenciasFormularioEmpleado;

class RecepcionEmpleadoController extends Controller
{
   
        public function index()
        {
            $result = RecepcionEmpleado::where('cod_emp', "7854654464"  )->first();
            if ($result) {
                return response()->json($result);
            } else {
                return response()->json(['message' => 'Registro no encontrado'], 404);
        }
        
    }


    public function create(Request $request){

        try {
            DB::beginTransaction();
        $result = new RecepcionEmpleado;
        $fechaNacimientoFormated = Carbon::parse($request-> fec_nac)->format('d-m-Y H:i:s');
        $fechaExpedicionFormated = Carbon::parse($request-> fec_expdoc)->format('d-m-Y H:i:s');
        $result->cod_emp= $request->cod_emp;
        $result->ap1_emp= $request->ap1_emp;
        $result->ap2_emp= $request->ap2_emp;
        $result->nom_emp= $request->nom_emp;
        $result-> nom2_emp= $request->nom2_emp;
        $result-> tip_ide= $request-> tip_ide;
        $result-> pai_exp= $request-> pai_exp;
        $result-> ciu_exp= $request-> ciu_exp;
        $result-> fec_nac= $fechaNacimientoFormated;
        $result-> cod_pai= $request-> cod_pai;
        $result-> cod_dep= $request-> cod_dep;
        $result-> cod_ciu= $request-> cod_ciu;
        $result-> sex_emp= $request-> sex_emp;
        $result-> gru_san= $request-> gru_san;
        $result-> fac_rhh= $request-> fac_rhh;
        $result-> est_civ= $request-> est_civ;
        $result-> dir_res= $request-> dir_res;
        $result-> tel_res= $request-> tel_res;
        $result-> nac_emp= $request-> nac_emp;
        $result-> pai_res= $request-> pai_res;
        $result-> dpt_res= $request-> dpt_res;
        $result-> per_car= $request-> per_car;
        $result-> e_mail= $request-> e_mail;
        $result-> tel_cel= $request-> tel_cel;
        $result-> dpt_exp= $request-> dpt_exp;
        $result-> Niv_aca= $request-> Niv_aca;
        $result-> barrio= $request-> barrio;
        $result-> cta_ban= $request-> cta_ban;
        $result-> raza= $request-> raza;
        $result->  cod_grupo= $request->  cod_grupo;
        $result-> cod_ban= $request-> cod_ban;
        $result-> fec_expdoc= $fechaExpedicionFormated;
        $result-> ciu_res= $request-> ciu_res;
        $result-> est_soc= $request-> est_soc;
        $result-> num_ide= $request-> cod_emp;
        $result->save();
        
       foreach($request->referencias as $referencia){
        $resultReferencia = new ReferenciasFormularioEmpleado;
        $resultReferencia->cod_emp= $result->cod_emp;
        $resultReferencia->num_ref= $referencia['num_ref'];
        $resultReferencia->parent= $referencia['parent'];
        $resultReferencia->cel_ref= $referencia['cel_ref'];
        $resultReferencia->nom_ref= $referencia['nom_ref'];
        $resultReferencia->tip_ref= $referencia['tip_ref'];
        $resultReferencia->ocu_ref= 0;

        $resultReferencia->save();
       }
       foreach($request->familiares as $referencia){
        $resultReferencia = new ReferenciasModel;
        if($referencia['ap1_fam']!=""){
        $fechaNacimientoFormated = Carbon::parse($referencia['fec_nac'])->format('d-m-Y H:i:s');
        $resultReferencia->cod_emp= $result->cod_emp;
        $resultReferencia->ap1_fam= $referencia['ap1_fam'];
        $resultReferencia->ap2_fam= $referencia['ap2_fam'];
        $resultReferencia->nom_fam= $referencia['nom_fam'];
        $resultReferencia->tip_fam= $referencia['tip_fam'];
        $resultReferencia->fec_nac= $fechaNacimientoFormated;
        $resultReferencia->ocu_fam= $referencia['ocu_fam'];
        $resultReferencia->save();}
       }
        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Registro guardado de manera exitosa', 'id' => $result]);
        } catch (\Exception $e) {
            return $e;
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
    }
    
public function searchByCodEmp($cod_emp)
{
    try {
        $result = RecepcionEmpleado::where('cod_emp', $cod_emp)->first();
        $referencias = ReferenciasFormularioEmpleado::where('cod_emp', $cod_emp)->get();
        $result["referencias"]= $referencias;
        $familiares = ReferenciasModel::where('cod_emp', $cod_emp)->get();
        $result["familiares"]= $familiares;
        if ($result) {
            return response()->json(['status' => 'success', 'data' => $result]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Empleado no encontrado'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => 'Error al buscar el empleado, por favor intenta nuevamente']);
    }
}
}