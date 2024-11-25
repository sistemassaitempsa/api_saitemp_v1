<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DateTime;

class HorarioLaboralController extends Controller
{

    function cuentaFindes($fechaInicio, $fechaFin)
    {
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $diffDays = $inicio->diff($fin)->days + 1;
        $cuentaFinde = 0;
        for ($i = 0; $i < $diffDays; $i++) {

            $diaSemana = $inicio->format('w');

            if ($diaSemana == 0 || $diaSemana == 6) {
                $cuentaFinde++;
            }
            $inicio->modify('+1 day');
        }
        return $cuentaFinde;
    }
}