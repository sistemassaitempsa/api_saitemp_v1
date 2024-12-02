<?php

namespace App\Http\Controllers;

use App\Models\HistoricoOperacionesDDModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Operacion;

class IndicadoresDDController extends Controller
{
    public function numeroRadicadosMes($anio)
    {
        $registrosPorMes = DB::table('usr_app_clientes')
            ->select(
                DB::raw('MONTH(usr_app_clientes.created_at) as mes'),
                DB::raw('COUNT(usr_app_clientes.id) as total')
            )
            ->whereYear('usr_app_clientes.created_at', $anio)
            ->groupBy(DB::raw('MONTH(usr_app_clientes.created_at)'))
            ->orderBy(DB::raw('MONTH(usr_app_clientes.created_at)'))
            ->pluck('total', 'mes')
            ->all();

        // Construir array de nombres de los meses
        $nombresMesesArray = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        // Inicializar el array resultado con los nombres de los meses activos
        $nombresMesesActivos = [];

        // Iterar sobre los registros y construir los nombres de meses activos
        foreach ($registrosPorMes as $mes => $total) {
            if ($total > 0) {
                $nombresMesesActivos[$mes] = $nombresMesesArray[$mes];
            }
        }

        // Inicializar el array resultado final
        $resultadoFinal = [['nombres' => $nombresMesesActivos]];

        // Iterar sobre los registros y construir los objetos correspondientes
        foreach ($registrosPorMes as $mes => $total) {
            if ($total > 0) {
                $registro = array_fill(1, 12, 0);
                $registro[$mes] = $total;
                $resultadoFinal[] = $registro;
            }
        }

        return response()->json($resultadoFinal);
    }
    public function tipoDeOperacionMes($anio)
    { {
            try {
                $registrosPorEstadoYMes = DB::table('usr_app_historico_operaciondd')
                    ->select(
                        DB::raw('MONTH(created_at) as mes'),
                        'tipo_operacion_id',
                        DB::raw('COUNT(*) as total')
                    )
                    ->whereYear('created_at', $anio)
                    ->groupBy('tipo_operacion_id', DB::raw('MONTH(created_at)'))
                    ->get();

                // Inicializar arrays para cada estado
                $tipoServicio = DB::table('usr_app_operaciones')->pluck('nombre', 'id')->all();
                $registrosPorEstadoArray = [];

                // Inicializar array adicional con los nombres de los estados
                $nombresEstadosArray = ['nombres' => $tipoServicio];

                // Inicializar arrays para cada estado, incluso si no tienen registros
                foreach ($tipoServicio as $estadoId => $estadoNombre) {
                    $registrosPorEstadoArray[$estadoId] = array_fill(1, 12, 0);
                }

                // Actualizar las posiciones del array con los valores obtenidos de la consulta
                foreach ($registrosPorEstadoYMes as $registro) {
                    $mes = $registro->mes;
                    $estadoCargoId = $registro->tipo_operacion_id;
                    $cantidad = $registro->total;

                    // Actualizar la posición del array con el total de registros por mes
                    $registrosPorEstadoArray[$estadoCargoId][$mes] = $cantidad;
                }

                // Combinar el array de nombres de estados con el array principal
                $resultadoFinal = array_merge([$nombresEstadosArray], $registrosPorEstadoArray);

                return response()->json($resultadoFinal);
            } catch (\Exception $e) {
                //throw $th;
                return $e;
            }
        }
    }
    public function getAllHistoricoOperaciones()
    {
        $contratos = Operacion::all();

        // Retornar la respuesta en formato JSON
        return response()->json([
            'status' => 'success',
            'data' => $contratos,
        ], 200);
    }
}