<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CentrosDeTrabajoSeiyaModel;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\cliente;
use App\Models\ActividadCiiu;
use Illuminate\Support\Facades\DB;
use App\Models\CentroTrabajo;

class CentrosDeTrabajoSeiyaController extends Controller
{
    public function index($cantidad)
    {
        $result = CentrosDeTrabajoSeiyaModel::join('usr_app_actividades_ciiu as actividad_ciiu', 'actividad_ciiu.id', 'usr_app_centros_trabajo.actividad_ciiu_id')
            ->select(
                'usr_app_centros_trabajo.id',
                'usr_app_centros_trabajo.cliente_id',
                'usr_app_centros_trabajo.nombre',
                'actividad_ciiu.codigo_actividad as codigo_actividad',
                'actividad_ciiu.descripcion as actividad_ciiu_descripcion',
            )
            ->orderby('usr_app_centros_trabajo.id', 'DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }
    public function searchByClienteId($cliente_id)
    {
        $result = CentrosDeTrabajoSeiyaModel::join(
            'usr_app_actividades_ciiu as actividad_ciiu',
            'actividad_ciiu.id',
            'usr_app_centros_trabajo.actividad_ciiu_id'
        )
            ->select(
                'usr_app_centros_trabajo.id',
                'usr_app_centros_trabajo.cliente_id',
                'usr_app_centros_trabajo.codigo_centro_trabajo',
                'usr_app_centros_trabajo.nombre',
                'actividad_ciiu.codigo_actividad as codigo_actividad',
                'actividad_ciiu.descripcion as actividad_ciiu_descripcion',
                'usr_app_centros_trabajo.created_at',
            )
            ->where('usr_app_centros_trabajo.cliente_id', $cliente_id)
            ->orderBy('usr_app_centros_trabajo.id', 'DESC')
            ->get();

        return response()->json($result);
    }
    public function searchById($id)
    {
        $result = CentrosDeTrabajoSeiyaModel::join('usr_app_actividades_ciiu as actividad_ciiu', 'actividad_ciiu.id', 'usr_app_centros_trabajo.actividad_ciiu_id')
            ->select(
                'usr_app_centros_trabajo.id',
                'usr_app_centros_trabajo.cliente_id',
                'usr_app_centros_trabajo.nombre',
                'actividad_ciiu.codigo_actividad as codigo_actividad',
                'actividad_ciiu.descripcion as actividad_ciiu_descripcion',
            )->where('usr_app_centros_trabajo.id', $id)
            ->orderby('usr_app_centros_trabajo.id', 'DESC')
            ->get();

        return response()->json($result);
    }
    public function create(Request $request)
    {
        $ultimoCentroSeiya = CentrosDeTrabajoSeiyaModel::orderBy('codigo_centro_trabajo', 'desc')->first();
        $nuevoCodigo = $ultimoCentroSeiya ? $ultimoCentroSeiya->codigo_centro_trabajo + 1 : 1;
        $centroTrabajoFounded = true;
        do {
            $centroTrabajoNovasoft = CentroTrabajo::where('rhh_CentroTrab.cod_CT', $nuevoCodigo)->first();
            if ($centroTrabajoNovasoft) {
                $nuevoCodigo = $nuevoCodigo + 1;
                $centroTrabajoFounded = true;
            } else {
                $centroTrabajoFounded = false;
            }
        } while ($centroTrabajoFounded == true);
        $actividadCiiuSearched = ActividadCiiu::where('usr_app_actividades_ciiu.codigo_actividad', $request->actividad_ciiu)->first();
        $centro = new CentrosDeTrabajoSeiyaModel;
        $centro->cliente_id = $request->cliente_id;
        $centro->actividad_ciiu_id = $actividadCiiuSearched['id'];
        $centro->codigo_centro_trabajo = $nuevoCodigo;
        $centro->nombre = $request->nombre;
        if ($centro->save()) {
            return response()->json(['status' => 'success', 'message' => 'Centro de trabajo creado exitosamente']);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Error al crear centro de trabajo']);
        }
    }

    public function inyectarCentrosTrabajo(Request $request)
    {
        // Validar que el archivo sea un Excel
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,csv,xls'
        ]);

        try {
            // Cargar archivo
            $archivo = $request->file('archivo');
            $datos = Excel::toArray([], $archivo)[0]; // Obtener la primera hoja

            // Verificar si hay datos
            if (count($datos) <= 1) {
                return response()->json(['message' => 'El archivo está vacío o no tiene encabezados'], 400);
            }

            // Obtener encabezados en minúsculas
            $headers = array_map('strtolower', $datos[0]);
            $codigoIndex = array_search('codigo_centro_trabajo', $headers);
            $nombreIndex = array_search('nombre', $headers);
            $actividadIndex = array_search('actividades_ciu', $headers);
            $nitIndex = array_search('nit', $headers);

            if ($codigoIndex === false || $nombreIndex === false || $nitIndex === false || $actividadIndex === false) {
                return response()->json(['message' => 'El archivo debe contener las columnas: codigo_centro_trabajo, nombre, nit, actividades_ciu'], 400);
            }

            // Procesar filas
            $nuevosRegistros = [];
            foreach (array_slice($datos, 1) as $fila) {
                if (!isset($fila[$codigoIndex], $fila[$nombreIndex], $fila[$nitIndex], $fila[$actividadIndex])) {
                    continue; // Saltar filas con datos incompletos
                }

                // Limpiar datos
                $codigoCentroTrabajo = ltrim($fila[$codigoIndex], '0'); // Eliminar ceros a la izquierda
                $nombre = trim($fila[$nombreIndex]);
                $nit = trim($fila[$nitIndex]);
                $actividadCiiu = trim($fila[$actividadIndex]);

                // Buscar cliente_id en la tabla Clientes
                $cliente = Cliente::where('nit', $nit)->first() ?? Cliente::where('numero_identificacion', $nit)->first();
                if ($cliente == false) {
                    $cliente = cliente::where('numero_identificacion', $nit)->first();
                }
                $clienteId = $cliente ? $cliente->id : null;

                $actividad = ActividadCiiu::where('codigo_actividad', $actividadCiiu)->first();
                $actividadId = $actividad ? $actividad->id : null;

                // Agregar a la lista de inserción
                $nuevosRegistros[] = [
                    'codigo_centro_trabajo' => $codigoCentroTrabajo,
                    'nombre' => $nombre,
                    'cliente_id' => $clienteId,
                    'actividad_ciiu_id' => $actividadId,

                ];
            }

            // Insertar en la base de datos por lotes
            if (!empty($nuevosRegistros)) {
                $chunks = array_chunk($nuevosRegistros, 300);
                foreach ($chunks as $chunk) {
                    DB::table('usr_app_centros_trabajo')->insert($chunk);
                }
            }

            return response()->json(['message' => 'Importación completada', 'registros_insertados' => count($nuevosRegistros)], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error en la importación', 'error' => $e->getMessage()], 500);
        }
    }
}
