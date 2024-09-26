<?php

namespace App\Http\Controllers;
use App\Models\TipoIdFormularioEmpleado;
use Illuminate\Http\Request;

class TipoIdFormularioEmpleadoController extends Controller
{
    public function index()
    {
        $result = TipoIdFormularioEmpleado::select()->get();
        if ($result) {
            return response()->json($result);
        } else {
            
            return response()->json(['message' => 'Registro no encontrado'], 404);
    }}
}