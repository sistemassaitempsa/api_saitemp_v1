<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecepcionEmpleado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\ReferenciasModel;
use App\Models\ReferenciasFormularioEmpleado;
use App\Models\UsuariosCandidatosModel;

class RecepcionEmpleadoController extends Controller
{

    public function index()
    {
        $novasoft = RecepcionEmpleado::where('cod_emp', "11")->first();
        if ($novasoft) {
            return response()->json($novasoft);
        } else {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }


    public function createNovasoft(Request $request)
    {

        DB::beginTransaction();
        try {
            $novasoft = new RecepcionEmpleado;
            $fechaNacimientoFormated = Carbon::parse($request->fec_nac)->format('d-m-Y H:i:s');
            $fechaExpedicionFormated = Carbon::parse($request->fec_expdoc)->format('d-m-Y H:i:s');
            $novasoft->cod_emp = $request->cod_emp;
            $novasoft->ap1_emp = $request->ap1_emp;
            $novasoft->ap2_emp = $request->ap2_emp;
            $novasoft->nom_emp = $request->nom1_emp;
            $novasoft->nom1_emp = $request->nom1_emp;
            $novasoft->nom2_emp = $request->nom2_emp ?? '';
            $novasoft->tip_ide = $request->tip_ide;
            $novasoft->pai_exp = $request->pai_exp;
            $novasoft->ciu_exp = $request->ciu_exp;
            $novasoft->fec_nac = $fechaNacimientoFormated;
            $novasoft->cod_pai = $request->cod_pai;
            $novasoft->cod_dep = $request->cod_dep;
            $novasoft->cod_ciu = $request->cod_ciu;
            $novasoft->sex_emp = $request->sex_emp;
            $novasoft->gru_san = $request->gru_san;
            $novasoft->fac_rhh = $request->fac_rhh;
            $novasoft->est_civ = $request->est_civ;
            $novasoft->dir_res = $request->dir_res;
            $novasoft->tel_res = $request->tel_res;
            $novasoft->nac_emp = $request->nac_emp;
            $novasoft->pai_res = $request->pai_res;
            $novasoft->dpt_res = $request->dpt_res;
            $novasoft->per_car = $request->per_car;
            $novasoft->e_mail = $request->e_mail;
            $novasoft->tel_cel = $request->tel_cel;
            $novasoft->dpt_exp = $request->dpt_exp;
            $novasoft->Niv_aca = $request->Niv_aca;
            $novasoft->barrio = $request->barrio;
            $novasoft->cta_ban = $request->cta_ban;
            $novasoft->raza = $request->raza;
            $novasoft->cod_grupo = $request->cod_grupo;
            $novasoft->cod_ban = $request->cod_ban;
            $novasoft->fec_expdoc = $fechaExpedicionFormated;
            $novasoft->ciu_res = $request->ciu_res;
            $novasoft->est_soc = $request->est_soc;
            $novasoft->num_ide = $request->cod_emp;
            $novasoft->save();



            foreach ($request->referencias as $referencia) {
                $novasoftReferencia = new ReferenciasFormularioEmpleado;
                $novasoftReferencia->cod_emp = $novasoft->cod_emp;
                $novasoftReferencia->num_ref = $referencia['num_ref'];
                $novasoftReferencia->parent = $referencia['parent'];
                $novasoftReferencia->cel_ref = $referencia['cel_ref'];
                $novasoftReferencia->nom_ref = $referencia['nom_ref'];
                $novasoftReferencia->tip_ref = $referencia['tip_ref'];
                $novasoftReferencia->ocu_ref = 0;
                $novasoftReferencia->save();
            }
            foreach ($request->familiares as $referencia) {
                $novasoftReferencia = new ReferenciasModel;
                if ($referencia['ap1_fam'] != "") {
                    $fechaNacimientoFormated = Carbon::parse($referencia['fec_nac'])->format('d-m-Y H:i:s');
                    $novasoftReferencia->cod_emp = $novasoft->cod_emp;
                    $novasoftReferencia->ap1_fam = $referencia['ap1_fam'];
                    $novasoftReferencia->ap2_fam = $referencia['ap2_fam'];
                    $novasoftReferencia->nom_fam = $referencia['nom_fam'];
                    $novasoftReferencia->tip_fam = $referencia['tip_fam'];
                    $novasoftReferencia->fec_nac = $fechaNacimientoFormated;
                    $novasoftReferencia->ocu_fam = $referencia['ocu_fam'];
                    $novasoftReferencia->save();
                }
            }




            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro guardado de manera exitosa', 'id' => $novasoft]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
    }

    public function searchByCodEmp($cod_emp)
    {
        try {
            $novasoft = RecepcionEmpleado::join('gen_tipide as tipoId', 'tipoId.cod_tip', 'GTH_RptEmplea.tip_ide')
                ->join('gen_paises as pais_exp_name', 'pais_exp_name.cod_pai', 'GTH_RptEmplea.pai_exp')
                ->join('gen_paises as pais_res_name', 'pais_res_name.cod_pai', 'GTH_RptEmplea.pai_res')
                ->join('gen_paises as pais_nac_name', 'pais_nac_name.cod_pai', 'GTH_RptEmplea.cod_pai')
                ->join('gen_deptos as depto_exp_name', function ($join) {
                    $join->on('depto_exp_name.cod_dep', '=', 'GTH_RptEmplea.dpt_exp')
                        ->orOn('depto_exp_name.cod_dep', '=', 'GTH_RptEmplea.pai_exp');
                })
                ->join('gen_deptos as depto_res_name', function ($join) {
                    $join->on('depto_res_name.cod_dep', '=', 'GTH_RptEmplea.dpt_res')
                        ->orOn('depto_res_name.cod_dep', '=', 'GTH_RptEmplea.pai_res');
                })
                ->join('gen_deptos as depto_nac_name', function ($join) {
                    $join->on('depto_nac_name.cod_dep', '=', 'GTH_RptEmplea.cod_dep')
                        ->orOn('depto_nac_name.cod_dep', '=', 'GTH_RptEmplea.cod_pai');
                })
                ->join('gen_ciudad as ciudad_exp_name', function ($join) {
                    $join->on('ciudad_exp_name.cod_ciu', '=', 'GTH_RptEmplea.ciu_exp')
                        ->orOn('ciudad_exp_name.cod_ciu', '=', 'GTH_RptEmplea.dpt_exp')
                        ->where('ciudad_exp_name.cod_pai', '=', 'GTH_RptEmplea.pai_exp');
                })
                ->join('gen_ciudad as ciudad_res_name', function ($join) {
                    $join->on('ciudad_res_name.cod_ciu', '=', 'GTH_RptEmplea.ciu_res')
                        ->orOn('ciudad_res_name.cod_ciu', '=', 'GTH_RptEmplea.dpt_res')
                        ->where('ciudad_res_name.cod_pai', '=', 'GTH_RptEmplea.pai_res');
                })
                ->join('gen_ciudad as ciudad_nac_name', function ($join) {
                    $join->on('ciudad_nac_name.cod_ciu', '=', 'GTH_RptEmplea.cod_ciu')
                        ->orOn('ciudad_nac_name.cod_ciu', '=', 'GTH_RptEmplea.cod_dep')
                        ->where('ciudad_nac_name.cod_pai', '=', 'GTH_RptEmplea.cod_pai');
                })
                ->join('gen_bancos as banco_name', 'banco_name.cod_ban', 'GTH_RptEmplea.cod_ban')
                ->join('rhh_tbclaest as nivelAcademico_name', 'nivelAcademico_name.tip_est', 'GTH_RptEmplea.Niv_aca')
                ->join('GTH_EstCivil as estadoCivil_name', 'estadoCivil_name.cod_est', 'GTH_RptEmplea.cod_est')
                ->join('gen_grupoetnico as etnia_name', 'etnia_name.cod_grupo', 'GTH_RptEmplea.cod_grupo')
                ->where('cod_emp', $cod_emp)
                ->select(
                    'tipoId.des_tip as tipIde_nombre',
                    'pais_exp_name.nom_pai as pais_exp_nombre',
                    'pais_res_name.nom_pai as pais_res_nombre',
                    'pais_res_name.nom_pai as pais_nac_nombre',
                    'depto_exp_name.nom_dep as dep_exp_nombre',
                    'depto_res_name.nom_dep as dep_res_nombre',
                    'depto_nac_name.nom_dep as dep_nac_nombre',
                    'ciudad_exp_name.nom_ciu as ciudad_exp_nombre',
                    'ciudad_res_name.nom_ciu as ciudad_res_nombre',
                    'ciudad_nac_name.nom_ciu as ciudad_nac_nombre',
                    'banco_name.nom_ban as banco_nombre',
                    'nivelAcademico_name.des_est as nivelAcademico_nombre',
                    'estadoCivil_name.des_est as estadoCivil_nombre',
                    'etnia_name.descripcion as etnia_nombre',
                    '*'
                )->first();
            $referencias = ReferenciasFormularioEmpleado::where('cod_emp', $cod_emp)->get();
            $novasoft["referencias"] = $referencias;
            $familiares = ReferenciasModel::where('cod_emp', $cod_emp)->get();
            $novasoft["familiares"] = $familiares;
            if ($novasoft->cod_emp != null) {
                return response()->json(['status' => 'success', 'data' => $novasoft]);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Empleado no encontrado'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al buscar el empleado, por favor intenta nuevamente']);
        }
    }

    public function updateByCodEmpNovasoft(Request $request, $cod_emp)
    {
        try {
            DB::beginTransaction();
            $novasoft = RecepcionEmpleado::find($cod_emp);
            $fechaNacimientoFormated = Carbon::parse($request->fec_nac)->format('d-m-Y H:i:s');
            $fechaExpedicionFormated = Carbon::parse($request->fec_expdoc)->format('d-m-Y H:i:s');
            $novasoft->ap1_emp = $request->ap1_emp;
            $novasoft->ap2_emp = $request->ap2_emp;
            $novasoft->nom_emp = $request->nom1_emp;
            $novasoft->nom1_emp = $request->nom1_emp;
            $novasoft->nom2_emp = $request->nom2_emp ?? '';
            $novasoft->tip_ide = $request->tip_ide;
            $novasoft->pai_exp = $request->pai_exp;
            $novasoft->ciu_exp = $request->ciu_exp;
            $novasoft->fec_nac = $fechaNacimientoFormated;
            $novasoft->cod_pai = $request->cod_pai;
            $novasoft->cod_dep = $request->cod_dep;
            $novasoft->cod_ciu = $request->cod_ciu;
            $novasoft->sex_emp = $request->sex_emp;
            $novasoft->gru_san = $request->gru_san;
            $novasoft->fac_rhh = $request->fac_rhh;
            $novasoft->est_civ = $request->est_civ;
            $novasoft->dir_res = $request->dir_res;
            $novasoft->tel_res = $request->tel_res;
            $novasoft->nac_emp = $request->nac_emp;
            $novasoft->pai_res = $request->pai_res;
            $novasoft->dpt_res = $request->dpt_res;
            $novasoft->per_car = $request->per_car;
            $novasoft->e_mail = $request->e_mail;
            $novasoft->tel_cel = $request->tel_cel;
            $novasoft->dpt_exp = $request->dpt_exp;
            $novasoft->Niv_aca = $request->Niv_aca;
            $novasoft->barrio = $request->barrio;
            $novasoft->cta_ban = $request->cta_ban;
            $novasoft->raza = $request->raza;
            $novasoft->cod_grupo = $request->cod_grupo;
            $novasoft->cod_ban = $request->cod_ban;
            $novasoft->fec_expdoc = $fechaExpedicionFormated;
            $novasoft->ciu_res = $request->ciu_res;
            $novasoft->est_soc = $request->est_soc;
            $novasoft->num_ide = $request->cod_emp;
            $novasoft->save();
            foreach ($request->referencias as $referencia) {
                if (isset($referencia['cod_emp']) && isset($referencia['num_ref'])) {
                    $novasoftReferencia = ReferenciasFormularioEmpleado::where('cod_emp', $referencia['cod_emp'])
                        ->where('num_ref', $referencia['num_ref'])
                        ->first();

                    if ($novasoftReferencia) {
                        $novasoftReferencia->parent = $referencia['parent'];
                        $novasoftReferencia->cel_ref = $referencia['cel_ref'];
                        $novasoftReferencia->nom_ref = $referencia['nom_ref'];
                        $novasoftReferencia->ocu_ref = 0; // Si necesitas cambiar este valor, asegúrate de que sea correcto
                        $novasoftReferencia->save();
                    } else {
                    }
                } else {
                }
            }
            foreach ($request->familiaresConsulta as $index => $referencia) {
                $requestFamiliares = $request->familiares;
                if (isset($referencia['cod_emp']) && isset($referencia['ap1_fam'])) {
                    $novasoftReferencia = ReferenciasModel::where('cod_emp', $referencia['cod_emp'])
                        ->where('ap1_fam', $referencia['ap1_fam'])
                        ->where('nom_fam', $referencia['nom_fam'])
                        ->first();
                    $fechaNacimientoFormated = Carbon::parse($requestFamiliares[$index]['fec_nac'])->format('d-m-Y H:i:s');
                    $novasoftReferencia->ap1_fam = $requestFamiliares[$index]['ap1_fam'];
                    $novasoftReferencia->ap2_fam = $requestFamiliares[$index]['ap2_fam'];
                    $novasoftReferencia->nom_fam = $requestFamiliares[$index]['nom_fam'];
                    $novasoftReferencia->tip_fam = $requestFamiliares[$index]['tip_fam'];
                    $novasoftReferencia->fec_nac = $fechaNacimientoFormated;
                    $novasoftReferencia->ocu_fam = $requestFamiliares[$index]['ocu_fam'];
                    $novasoftReferencia->save();
                }
            }
            if (count($request->familiaresConsulta) < count($request->familiares)) {
                $tamanoInicial = count($request->familiaresConsulta);
                for ($i = $tamanoInicial; $i < count($request->familiares); $i++) {
                    $referencia = $request->familiares[$i];
                    $novasoftReferencia = new ReferenciasModel;
                    if ($referencia['ap1_fam'] != "") {
                        $fechaNacimientoFormated = Carbon::parse($referencia['fec_nac'])->format('d-m-Y H:i:s');
                        $novasoftReferencia->cod_emp = $novasoft->cod_emp;
                        $novasoftReferencia->ap1_fam = $referencia['ap1_fam'];
                        $novasoftReferencia->ap2_fam = $referencia['ap2_fam'];
                        $novasoftReferencia->nom_fam = $referencia['nom_fam'];
                        $novasoftReferencia->tip_fam = $referencia['tip_fam'];
                        $novasoftReferencia->fec_nac = $fechaNacimientoFormated;
                        $novasoftReferencia->ocu_fam = $referencia['ocu_fam'];
                        $novasoftReferencia->save();
                    }
                }
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $novasoft->cod_emp]);
        } catch (\Exception $e) {

            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
    }

    public function createSeiya(Request $request, $usuario_id)
    {
        try {
            DB::beginTransaction();


            /*  $novasoft->est_civ = $request->est_civ; */


            $fechaNacimientoFormated = Carbon::parse($request->fec_nac)->format('d-m-Y H:i:s');
            $fechaExpedicionFormated = Carbon::parse($request->fec_expdoc)->format('d-m-Y H:i:s');
            $user = UsuariosCandidatosModel::where('usuario_id', $usuario_id)->first();
            $user->primer_nombre = $request->nom1_emp;
            $user->primer_apellido = $request->ap1_emp;
            $user->segundo_nombre = $request->nom2_emp ?? '';
            $user->segundo_apellido = $request->ap2_emp;
            $user->fecha_nacimiento = $fechaNacimientoFormated;
            $user->direccion_residencia = $request->dir_res;
            $user->fecha_expedicion = $fechaExpedicionFormated;
            $user->telefono = $request->tel_res;
            $user->celular = $request->tel_cel;
            $user->cuenta_bancaria = $request->cta_ban;
            $user->personas_cargo = $request->per_car;
            $user->grupo_sanguineo = $request->gru_san;
            $user->factor_rh = $request->fac_rhh;
            $user->estrato = $request->est_soc;
            $user->sector_academico_id = $request->sector_academico_id;
            $user->acidente_laboral = $request->acidente_laboral;
            $user->enfermedad = $request->enfermedad;
            $user->tratamiento_medico = $request->tratamiento_medico;
            $user->tratamiento_psicologico = $request->tratamiento_psicologico;
            $user->tratamiento_odontologico = $request->tratamiento_odontologico;
            $user->cirugia = $request->cirugia;
            $user->alerigia = $request->alerigia;
            $user->fractura = $request->fractura;
            $user->sustencia_psicoactiva = $request->sustencia_psicoactiva;
            $user->estatura = $request->estatura;
            $user->peso = $request->peso;
            $user->lentes = $request->lentes;
            $user->eps_id = $request->eps_id;
            $user->afp_id = $request->afp_id;
            $user->descripcion_salud = $request->descripcion_salud;
            $user->vehiculo_propio = $request->vehiculo_propio;
            $user->transporte_publico = $request->transporte_publico;
            $user->licencia_conduccion = $request->licencia_conduccion;
            $user->categoria_licencia = $request->categoria_licencia;
            $user->tip_doc_id = $request->tip_ide;
            $user->ciudad_expedicion_id = $request->ciu_exp;
            $user->ciudad_nacimiento_id = $request->cod_ciu;
            $user->ciudad_residencia_id = $request->ciu_res;
            $user->gen_banco_id = $request->cod_ban;
            $user->nivel_academico_id = $request->Niv_aca;
            $user->genero_id = $request->sex_emp;
            $user->grupo_etnico_id = $request->cod_grupo;
            $user->save();

            $novasoft = RecepcionEmpleado::find($user->num_doc)->where('tip_ide', $request->tip_ide)->first();
            $response = "";
            if ($novasoft) {
                $response = $this->updateByCodEmpNovasoft($request, $user->num_doc);
            } else {
                $response = $this->createNovasoft($request);
            }
            DB::commit();
            if ($response['status'] == "success") {
                return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $user->id]);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
    }
}