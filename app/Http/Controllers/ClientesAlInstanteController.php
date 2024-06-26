<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use App\Models\ClientesAlInstante;
use Illuminate\Support\Facades\DB;
// use App\Events\EventoPrueba;

class ClientesAlInstanteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // event(new EventoPrueba('Listando empresas'));
        $result = DB::connection('second_db')->table('cxc_cliente')->select(
            'cod_cli as codigo',
            'nom_cli as nombre'
        )->paginate();

        return response()->json($result);
    }

    public function filter($filtro)
    {
        $result = DB::connection('second_db')->table('cxc_cliente')->select(
            'cod_cli as codigo',
            'nom_cli as nombre'
        )
        ->where('cod_cli','like','%'.$filtro.'%')
        ->orWhere('nom_cli','like','%'.$filtro.'%')
        ->paginate();
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
