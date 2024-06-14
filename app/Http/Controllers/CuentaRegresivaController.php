<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActualizacionProgramada;
use App\Events\TiempoActualizacion;


class CuentaRegresivaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $result = ActualizacionProgramada::first();
        return response()->json($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // event(new TiempoActualizacion($request->visible));
        $result = new ActualizacionProgramada;
        $result->visible = $request->visible;
        $result->fecha_hora = $request->fecha_hora;
        $result->mensaje_navbar = $request->mensaje_navbar;
        $result->mensaje_navbar2 = $request->mensaje_navbar2;
        $result->mensaje_popup = $request->mensaje_popup;
        $result->icono_popup = $request->icono_popup;
        $result->estilo_span = $request->estilo_span;
        $result->estilo_contador = $request->estilo_contador;
        $result->tamano_contador = $request->tamano_contador;
        $result->tamano_texto_contador = $request->tamano_texto_contador;
        $result->tiempo_espera = $request->tiempo_espera;
        if ($result->save()) {
            return response()->json(["status" => "success", "message" => "Registro insertado de manera exitosa."]);
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

        event(new TiempoActualizacion($request->visible . '*' . $request->fecha_hora));
        $result = ActualizacionProgramada::find($id);
        $result->visible = $request->visible;
        $result->fecha_hora = $request->fecha_hora;
        $result->mensaje_navbar = $request->mensaje_navbar;
        $result->mensaje_navbar2 = $request->mensaje_navbar2;
        $result->mensaje_popup = $request->mensaje_popup;
        $result->icono_popup = $request->icono_popup;
        $result->estilo_span = $request->estilo_span;
        $result->estilo_contador = $request->estilo_contador;
        $result->tamano_contador = $request->tamano_contador;
        $result->tamano_texto_contador = $request->tamano_texto_contador;
        $result->tiempo_espera = $request->tiempo_espera;
        if ($result->save()) {
            return response()->json(["status" => "success", "message" => "Registro actualizado de manera exitosa."]);
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
        //
    }
}
