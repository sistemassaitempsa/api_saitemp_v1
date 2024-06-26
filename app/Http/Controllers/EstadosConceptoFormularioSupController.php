<?php

namespace App\Http\Controllers;
use App\Models\EstadosConceptoFormularioSup;
use Illuminate\Http\Request;

class EstadosConceptoFormularioSupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $result = EstadosConceptoFormularioSup::select(
            'id',
            'estado_concepto'
        )
        ->where('tipo_concepto','=','1')
        ->orderby('posicion','ASC')
        ->get();
        return response()->json($result);
    }
    public function estadosepp()
    {
        $result = EstadosConceptoFormularioSup::select(
            'id',
            'estado_concepto'
        )
        ->where('tipo_concepto','=','2')
        ->orderby('posicion','ASC')
        ->get();
        return response()->json($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
