<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\estadosIngreso;

class DashBoardSeleccionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function cargosVacantesHojasVida($anio)
    {
        $resultado = [];
        $result = new OservicioCargoController();
        $cargos = $result->cargoschar($anio);

        $result = new OservicioHojaVidaController();
        $hojas_vida = $result->HojaVidaChar($anio);

        $result = new OservicioCargoController();
        $vacantes = $result->cargosCantidadchar($anio);

        array_push($resultado, $cargos->original);
        array_push($resultado, $vacantes->original);
        array_push($resultado, $hojas_vida->original);
        return $resultado;
    }

    public function cantidadVacantesTipoServicio($anio) //******************************************************/
    {
        try {
            $registrosPorEstadoYMes = DB::table('usr_app_formulario_ingreso')
                ->select(
                    DB::raw('MONTH(created_at) as mes'),
                    'tipo_servicio_id',
                    DB::raw('COUNT(*) as total')
                )
                ->whereYear('created_at', $anio)
                ->groupBy('tipo_servicio_id', DB::raw('MONTH(created_at)'))
                ->get();

            // Inicializar arrays para cada estado
            $tipoServicio = DB::table('usr_app_formulario_ingreso_tipo_servicio')->pluck('nombre_servicio', 'id')->all();
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
                $estadoCargoId = $registro->tipo_servicio_id;
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

    public function vacantesOcupadas($anio) //******************************************************/
    {
        $registrosPorMes = DB::table('usr_app_formulario_ingreso')
            ->leftJoin('usr_app_formulario_ingreso_seguimiento as fs', 'fs.formulario_ingreso_id', '=', 'usr_app_formulario_ingreso.id')
            ->leftJoin('usr_app_estados_ingreso as ei', 'ei.id', '=', 'fs.estado_ingreso_id')
            ->select(
                DB::raw('MONTH(usr_app_formulario_ingreso.created_at) as mes'),
                DB::raw('COUNT(DISTINCT usr_app_formulario_ingreso.numero_identificacion) as total')
            )
            ->whereYear('usr_app_formulario_ingreso.created_at', $anio)
            ->where('fs.estado_ingreso_id', 10)
            ->groupBy(DB::raw('MONTH(usr_app_formulario_ingreso.created_at)'))
            ->orderBy(DB::raw('MONTH(usr_app_formulario_ingreso.created_at)'))
            ->pluck('total', 'mes')
            ->all();

        // Inicializar un array con 12 posiciones, todas con valor 0
        $registrosPorMesArray = array_fill(1, 12, 0);

        // Actualizar las posiciones del array con los valores obtenidos de la consulta
        foreach ($registrosPorMes as $mes => $cantidad) {
            $registrosPorMesArray[$mes] = $cantidad;
        }

        return response()->json($registrosPorMesArray);
    }
    public function estadosseya()
    {
        // $result = estadosIngreso::select(
        //     'id',
        //     'nombre'
        // )
        //     ->orderby('posicion', 'asc')
        //     ->get();
        // return response()->json($result);

        $registrosPorEstado = DB::table('usr_app_formulario_ingreso')
        ->leftJoin('usr_app_estados_ingreso as ei', 'ei.id', '=', 'usr_app_formulario_ingreso.estado_ingreso_id')
        ->select(
            'usr_app_formulario_ingreso.estado_ingreso_id',
            'ei.nombre as estado_nombre',
            'ei.posicion',
            DB::raw('COUNT(usr_app_formulario_ingreso.id) as total')
        )
        ->groupBy('usr_app_formulario_ingreso.estado_ingreso_id', 'ei.nombre', 'ei.posicion')
        ->orderBy('ei.posicion')  // Ordenar por el campo posición
        ->get();

    // Transformar los resultados a un array con el nombre del estado como clave
    $registrosPorEstadoArray = [];
    foreach ($registrosPorEstado as $registro) {
        $registrosPorEstadoArray[$registro->estado_nombre] = $registro->total;
    }

    return response()->json($registrosPorEstadoArray);
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
