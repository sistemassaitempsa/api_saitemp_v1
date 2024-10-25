<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EstadosFirma;
use Illuminate\Support\Facades\DB;

class EstadosFirmaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $result = EstadosFirma::select(
            'id',
            'nombre',
            'color'
        )
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
        DB::beginTransaction();
        try {
            $estadosFirma= new EstadosFirma;
            $estadosFirma->nombre= $request->nombre;
            $estadosFirma->color= $request->color;
            $estadosFirma->horas_requeridas= $request->horas_requeridas;
            $estadosFirma->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro guardado de manera exitosa', 'id' => $estadosFirma->id]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
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
        DB::beginTransaction();
        try {
            $result = EstadosFirma::find($id);
            $result->nombre= $request->nombre;
            $result->color= $request->color;
            $result->horas_requeridas= $request->horas_requeridas;
            $result->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $result->id]);
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
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
        $result = EstadosFirma::find($id);
        if (!$result) {
            return response()->json(['message' => 'El radicado no existe.'], 404);}
            try {
                $result->delete();
                return response()->json(['message' => 'Estado eliminado con éxito.'], 200);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error al eliminar el estado.', 'error' => $e->getMessage()], 500);
            }
    }
}