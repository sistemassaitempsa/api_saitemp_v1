<?php

namespace App\Http\Controllers;

use App\Models\CargosCandidatoModel;
use Illuminate\Http\Request;

class CargosCandidatoController extends Controller
{
    //
    public function index()
    {
        $cargo = CargosCandidatoModel::orderby('usr_app_cargos_candidatos.nombre', 'ASC')->get();
        return response()->json($cargo);
    }
}