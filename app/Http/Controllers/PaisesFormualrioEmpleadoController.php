<?php

namespace App\Http\Controllers;

use App\Models\PaisesFormularioEmpleados;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaisesFormualrioEmpleadoController extends Controller
{
    public function index()
    {
        $result = PaisesFormularioEmpleados::all()
            ->map(function ($pais) {
                $pais->nom_pai = Str::ucfirst(Str::lower($pais->nom_pai));
                return $pais;
            });

        if ($result->isEmpty()) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        return response()->json($result);
    }
}