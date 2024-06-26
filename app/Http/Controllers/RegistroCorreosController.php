<?php

namespace App\Http\Controllers;

use App\Models\RegistroCorreos;
use Illuminate\Http\Request;

class RegistroCorreosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cantidad)
    {
        $result = RegistroCorreos::select(
            'id',
            'remitente',
            'destinatario',
            'con_copia',
            'con_copia_oculta',
            'asunto',
            'mensaje',
            'adjunto',
        )
            ->orderby('id', 'DESC')
            ->paginate($cantidad);
        return $result;
    }

    public function index2($modulo, $registro_id)
    {
        $result = RegistroCorreos::where('usr_app_registro_correos.modulo', '=', $modulo)
            ->where('usr_app_registro_correos.registro_id', '=', $registro_id)
            ->select(
                'id',
                'remitente',
                'destinatario',
                'con_copia',
                'con_copia_oculta',
                'asunto',
                'mensaje',
                'adjunto',
                'created_at',
            )

            ->orderby('id', 'DESC')
            ->get();
        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($request)
    {
        $result = new RegistroCorreos;
        $result->remitente = $request['remitente'];
        $result->destinatario = implode(", ", $request['destinatario']);
        $result->con_copia = implode(", ", $request['con_copia']);
        $result->con_copia_oculta = implode(", ", $request['con_copia_oculta']);
        $result->asunto = $request['asunto'];
        $result->mensaje = htmlspecialchars_decode(strip_tags($request['mensaje']));
        $result->adjunto = implode(", ", $request['adjunto']);
        $result->modulo = intval($request['modulo']);
        $result->registro_id = $request['registro_id'];
        $result->formulario_correo = $request['formulario_correo'];
        $result->save();
    }

    public function correosfiltro($cadena)
    {
        $consulta = base64_decode($cadena);
        $valores = explode("/", $consulta);
        $campo = $valores[0];
        $operador = $valores[1];
        $valor = $valores[2];
        $valor2 = $valores[3];
        if ($operador == 'Contiene') {
            $operador = 'like';
            $valor = '%' . $valor . '%';
        } else if ($operador == 'Igual a') {
            $operador = '=';
        }
        $result = RegistroCorreos::where($valores[0], $operador, $valor)
            ->select(
                'id',
                'remitente',
                'destinatario',
                'con_copia',
                'con_copia_oculta',
                'asunto',
                'mensaje',
                'adjunto',
            )
            ->paginate();
        return $result;
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
