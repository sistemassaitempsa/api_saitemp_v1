<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrdenServicioliente;
use App\Models\OrdenServcio;
use Illuminate\Support\Facades\DB;

class OrdenServiciolienteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $result = OrdenServcio::join('usr_app_formulario_ingreso_tipo_servicio as ts','ts.id','=','usr_app_orden_servicio.linea_servicio_id')
        ->join('usr_app_motivos_servicio as ms', 'ms.id', '=', 'usr_app_orden_servicio.motivo_servicio_id')
        ->join('usr_app_municipios as ciu', 'ciu.id', '=', 'usr_app_orden_servicio.ciudad_prestacion_servicio_id')
        ->select(
            'usr_app_orden_servicio.id',
            'usr_app_orden_servicio.numero_radicado',
            'usr_app_orden_servicio.created_at',
            'usr_app_orden_servicio.radicador',
            'usr_app_orden_servicio.fecha_inicio',
            'usr_app_orden_servicio.fecha_fin',
            'ts.nombre_servicio as linea_servicio',
            'ciudad_prestacion_servicio_id',
            'ciu.nombre as ciudad_prestacion_servicio',
            'ms.nombre as motivo_servicio',
            // 'usr_app_orden_servicio.nombre_contacto',
            // 'usr_app_orden_servicio.telefono_contacto',
            // 'usr_app_orden_servicio.cargo_contacto',
            'usr_app_orden_servicio.motivo_servicio_id',
            'usr_app_orden_servicio.cantidad_contrataciones',
            'usr_app_orden_servicio.cargo_solicitado_id',
            'usr_app_orden_servicio.salario',
        )->get();
        return response()->json($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            // return $request;
            $user = auth()->user();
            DB::beginTransaction();
            $OrdenServicio = new OrdenServcio;
            $OrdenServicio->tipo_usuario = $user->empresa_cliente;
            $OrdenServicio->usuario_id = $user->id;
            $OrdenServicio->radicador = $user->nombres . ' ' . $user->apelidos;
            $OrdenServicio->fecha_inicio = $request->fecha_inicio;
            $OrdenServicio->fecha_fin = $request->fecha_fin;
            $OrdenServicio->linea_servicio_id = $request->linea_servicio_id;
            $OrdenServicio->ciudad_prestacion_servicio_id = $request->ciudad_prestacion_servicio_id;
            // $OrdenServicio->laboratorio_id = $request->laboratorio_medico_id;
            $OrdenServicio->nombre_contacto = $request->nombre_contacto;
            $OrdenServicio->telefono_contacto = $request->telefono_contacto;
            $OrdenServicio->cargo_contacto = $request->cargo_contacto;
            $OrdenServicio->motivo_servicio_id = $request->motivo_servicio_id;
            $OrdenServicio->cantidad_contrataciones = $request->cantidad_contrataciones;
            $OrdenServicio->cargo_solicitado_id = $request->cargo_solicitado_id;
            $OrdenServicio->funciones_cargo = $request->funciones_cargo;
            $OrdenServicio->salario = $request->salario;

            if ($OrdenServicio->save()) {
                DB::commit();
                return response()->json(["status" => "success", "message" => "Formulario guardado exitosamente"]);
            } else {
                return response()->json(["status" => "error", "message" => "Error al guadar los datos del formulario"]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["status" => "error", "message" => "Error al guadar los datos del formulario"]);
        }
    }

    public function mensaje($bandera)
    {
        if ($bandera) {
            return response()->json(["status" => "success", "message" => "Formulario guardado exitosamente"]);
        } else {
            return response()->json(["status" => "error", "message" => "Error al guadar los datos del formulario"]);
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
