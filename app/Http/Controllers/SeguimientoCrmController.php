<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeguimientoCrm;
use App\Models\SeguimientoCrmPendiente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class SeguimientoCrmController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cantidad)
    {
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'sede.nombre as sede',
                'proces.nombre as proceso',
                'soli.nombre as solicitante',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.nombre_contacto',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
                'usr_app_seguimiento_crm.observacion',
            )
            ->orderby('usr_app_seguimiento_crm.id','DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }


    public function byid($id)
    {
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->join('usr_app_pqrsf_crm as pqrsf', 'pqrsf.id', 'usr_app_seguimiento_crm.pqrsf_id')
            ->where('usr_app_seguimiento_crm.id', '=', $id)
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'usr_app_seguimiento_crm.created_at',
                'usr_app_seguimiento_crm.fecha_cerrado',
                'sede.nombre as sede',
                'usr_app_seguimiento_crm.sede_id',
                'proces.nombre as proceso',
                'usr_app_seguimiento_crm.proceso_id',
                'soli.nombre as solicitante',
                'usr_app_seguimiento_crm.solicitante_id',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.tipo_atencion_id',
                'usr_app_seguimiento_crm.nombre_contacto',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
                'usr_app_seguimiento_crm.estado_id',
                'usr_app_seguimiento_crm.observacion',
                'usr_app_seguimiento_crm.nit_documento',
                'usr_app_seguimiento_crm.pqrsf_id',
                'pqrsf.nombre as pqrsf',
                'usr_app_seguimiento_crm.creacion_pqrsf',
                'usr_app_seguimiento_crm.cierre_pqrsf',
                'usr_app_seguimiento_crm.responsable',
            )
            ->first();
        return response()->json($result);
    }

    public function filtro($cadena)
    {
        try {

            $cadenaJSON = base64_decode($cadena);
            $cadenaUTF8 = mb_convert_encoding($cadenaJSON, 'UTF-8', 'ISO-8859-1');
            $valores = explode("/", $cadenaUTF8);
            $campo = $valores[0];
            $operador = $valores[1];
            $valor = $valores[2];
            $valor2 = isset($valores[3]) ? $valores[3] : null;

            $query = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
                ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
                ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
                ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
                ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
                ->select(
                    'usr_app_seguimiento_crm.id',
                    'usr_app_seguimiento_crm.numero_radicado',
                    'sede.nombre as sede',
                    'proces.nombre as proceso',
                    'soli.nombre as solicitante',
                    'inter.nombre as iteraccion',
                    'usr_app_seguimiento_crm.nombre_contacto',
                    'usr_app_seguimiento_crm.telefono',
                    'usr_app_seguimiento_crm.correo',
                    'cierre.nombre as estado',
                    'usr_app_seguimiento_crm.observacion',
                )
                ->orderby('id', 'DESC');


            switch ($operador) {
                case 'Contiene':
                    if ($campo == "sede") {
                        $query->where('sede.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "proceso") {
                        $query->where('proces.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "iteraccion") {
                        $query->where('inter.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "estado") {
                        $query->where('cierre.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "solicitante") {
                        $query->where('soli.nombre', 'like', '%' . $valor . '%');
                    } else {
                        $query->where($campo, 'like', '%' . $valor . '%');
                    }
                    break;
                case 'Igual a':
                    if ($campo == "sede") {
                        $query->where('sede.nombre', '=', $valor);
                    } else if ($campo == "proceso") {
                        $query->where('proces.nombre', '=', $valor);
                    } else if ($campo == "iteraccion") {
                        $query->where('inter.nombre', '=', $valor);
                    } else if ($campo == "estado") {
                        $query->where('cierre.nombre', '=', $valor);
                    } else if ($campo == "solicitante") {
                        $query->where('soli.nombre', '=', $valor);
                    } else {
                        $query->where($campo, '=', $valor);
                    }
                    break;
                case 'Igual a fecha':
                    $query->whereDate($campo, '=', $valor);
                    break;
                case 'Entre':
                    $query->whereDate($campo, '>=', $valor)
                        ->whereDate($campo, '<=', $valor2);
                    break;
            }

            $result = $query->paginate();
            return response()->json($result);
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function pendientes(Request $request)
    {
        $user = auth()->user();
        $lista = $request->all();
        foreach ($lista as $item) {
            $existeIngreso = SeguimientoCrmPendiente::where('registro_crm_id', $item)->where('usuario_id', $user->id)->first();

            if (!$existeIngreso) {
                $result = new SeguimientoCrmPendiente;
                $result->registro_crm_id = $item;
                $result->usuario_id = $user->id;
                $result->save();
            }
        }
        return response()->json(['status' => 'success', 'message' => 'Tareas pendientes agregadas exitosamente.']);
    }

    public function pendientes2($cantidad)
    {
        $user = auth()->user();
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->join('usr_app_seguimiento_crm_pendientes as pen', 'pen.registro_crm_id', 'usr_app_seguimiento_crm.id')
            ->where('pen.usuario_id', '=', $user->id)
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'sede.nombre as sede',
                'proces.nombre as proceso',
                'soli.nombre as solicitante',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.nombre_contacto',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
                'usr_app_seguimiento_crm.observacion',
            )
            ->orderby('usr_app_seguimiento_crm.id', 'DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        $result = new SeguimientoCrm;
        $result->sede_id = $request->sede_id;
        $result->proceso_id = $request->proceso_id;
        $result->solicitante_id = $request->solicitante_id;
        $result->nombre_contacto = $request->nombre_contacto;
        $result->tipo_atencion_id = $request->tipo_atencion_id;
        $result->telefono = $request->telefono;
        $result->correo = $request->correo;
        $result->estado_id = $request->estado_id;
        $result->observacion = $request->observacion;
        $result->nit_documento = $request->nit_documento;
        $result->pqrsf_id = $request->pqrsf_id;
        $result->creacion_pqrsf = $user->nombres.' '.$user->apellidos;
        $result->cierre_pqrsf = $request->cierre_pqrsf;
        $result->responsable = $request->responsable;
        if ($request->estado_id == 2) {
            $fechaHoraActual = Carbon::now();
            $result->fecha_cerrado = $fechaHoraActual->format('d-m-Y H:i:s');
        }
        if ($result->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro guardado de manera exitosa', 'id' => $result->id]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Error al guardar registro']);
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
        $result = SeguimientoCrm::find($id);
        $result->sede_id = $request->sede_id;
        $result->proceso_id = $request->proceso_id;
        $result->solicitante_id = $request->solicitante_id;
        $result->nombre_contacto = $request->nombre_contacto;
        $result->tipo_atencion_id = $request->tipo_atencion_id;
        $result->telefono = $request->telefono;
        $result->correo = $request->correo;
        $result->estado_id = $request->estado_id;
        $result->observacion = $request->observacion;
        $result->nit_documento = $request->nit_documento;
        $result->cierre_pqrsf = $request->cierre_pqrsf;
        $result->responsable = $request->responsable;
        $result->pqrsf_id = $request->pqrsf_id;
        if ($request->estado_id == 2) {
            $fechaHoraActual = Carbon::now();
            $result->fecha_cerrado = $fechaHoraActual->format('d-m-Y H:i:s');
        }
        if ($result->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $result->id]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro']);
        }
    }

    public function borradomasivo(Request $request)
    {
        try {
            $user = auth()->user();
            for ($i = 0; $i < count($request->id); $i++) {
                $result = SeguimientoCrmPendiente::where('registro_crm_id', '=', $request->id[$i])->where('usuario_id', $user->id)->first();
                $result->delete();
            }
            return response()->json(['status' => 'success', 'message' => 'Registros eliminados exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar el registro, por favor intente nuevamente']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }
}
