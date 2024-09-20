<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeguimientoCrm;
use App\Models\SeguimientoCrmPendiente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Evidencia;
use App\Models\TemasVisitaCrm;
use App\Models\CompromisosVisitaCrm;
use App\Models\AsistenciaVisitaCrm;
use TCPDF;
use App\Models\AtencionInteraccion;
class SeguimientoCrmController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cantidad)
    {
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'sede.nombre as sede',
                'proces.nombre as proceso',
                'soli.nombre as solicitante',
                'usr_app_seguimiento_crm.nombre_contacto',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
            )
            ->orderby('usr_app_seguimiento_crm.id','DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }
   

    public function byid($id)
    {
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->join('usr_app_pqrsf_crm as pqrsf', 'pqrsf.id', 'usr_app_seguimiento_crm.pqrsf_id')
            ->where('usr_app_seguimiento_crm.id', '=', $id)
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'usr_app_seguimiento_crm.created_at',
                'usr_app_seguimiento_crm.fecha_cerrado',
                'sede.nombre as sede',
                'usr_app_seguimiento_crm.sede_id',
                'proces.nombre as proceso',
                'usr_app_seguimiento_crm.proceso_id',
                'soli.nombre as solicitante',
                'usr_app_seguimiento_crm.solicitante_id',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.tipo_atencion_id',
                'usr_app_seguimiento_crm.nombre_contacto',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
                'usr_app_seguimiento_crm.estado_id',
                'usr_app_seguimiento_crm.observacion',
                'usr_app_seguimiento_crm.nit_documento',
                'usr_app_seguimiento_crm.pqrsf_id',
                'pqrsf.nombre as pqrsf',
                'usr_app_seguimiento_crm.creacion_pqrsf',
                'usr_app_seguimiento_crm.cierre_pqrsf',
                'usr_app_seguimiento_crm.responsable',
                'usr_app_seguimiento_crm.hora_inicio',
                'usr_app_seguimiento_crm.hora_cierre',
                'usr_app_seguimiento_crm.alcance',
                'usr_app_seguimiento_crm.objetivo',
                'usr_app_seguimiento_crm.cargo_visitado',
                'usr_app_seguimiento_crm.visitado',
                'usr_app_seguimiento_crm.cargo_visitante',
                'usr_app_seguimiento_crm.visitante',
                'usr_app_seguimiento_crm.latitud',
                'usr_app_seguimiento_crm.longitud',
            )
            ->first();
            $evidencias = Evidencia::where('registro_id', $id)->get();
            $evidencias->transform(function ($item) {
            $item->edit=false;
            return $item;
            });
            $result["Evidencias"]= $evidencias;
            $temasPrincipales =  TemasVisitaCrm::where('registro_id', $id)->get();
            $temasPrincipales->transform(function ($item) {
                $item->edit=false;
                return $item;
                });
                $result["temasPrincipales"]= $temasPrincipales;
                $compromisos =  CompromisosVisitaCrm::where('registro_id', $id)->get();
                $compromisos->transform(function ($item) {
                    $item->edit=false;
                    return $item;
                    });
                    $result["compromisos"]= $compromisos;
                    $asistencias =  AsistenciaVisitaCrm::where('registro_id', $id)->get();
                    $asistencias->transform(function ($item) {
                        $item->edit=false;
                        return $item;
                        });
                        $result["asistencias"]= $asistencias;


        return response()->json($result);
    }

    public function filtro($cadena)
    {
        try {

            $cadenaJSON = base64_decode($cadena);
            $cadenaUTF8 = mb_convert_encoding($cadenaJSON, 'UTF-8', 'ISO-8859-1');
            $valores = explode("/", $cadenaUTF8);
            $campo = $valores[0];
            $operador = $valores[1];
            $valor = $valores[2];
            $valor2 = isset($valores[3]) ? $valores[3] : null;

            $query = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
                ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
                ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
                ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
                ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
                ->select(
                    'usr_app_seguimiento_crm.id',
                    'usr_app_seguimiento_crm.numero_radicado',
                    'sede.nombre as sede',
                    'proces.nombre as proceso',
                    'soli.nombre as solicitante',
                    'usr_app_seguimiento_crm.nombre_contacto',
                    'inter.nombre as iteraccion',
                    'usr_app_seguimiento_crm.telefono',
                    'usr_app_seguimiento_crm.correo',
                    'cierre.nombre as estado',
                )
                ->orderby('id', 'DESC');


            switch ($operador) {
                case 'Contiene':
                    if ($campo == "sede") {
                        $query->where('sede.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "proceso") {
                        $query->where('proces.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "iteraccion") {
                        $query->where('inter.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "estado") {
                        $query->where('cierre.nombre', 'like', '%' . $valor . '%');
                    } else if ($campo == "solicitante") {
                        $query->where('soli.nombre', 'like', '%' . $valor . '%');
                    } else {
                        $query->where($campo, 'like', '%' . $valor . '%');
                    }
                    break;
                case 'Igual a':
                    if ($campo == "sede") {
                        $query->where('sede.nombre', '=', $valor);
                    } else if ($campo == "proceso") {
                        $query->where('proces.nombre', '=', $valor);
                    } else if ($campo == "iteraccion") {
                        $query->where('inter.nombre', '=', $valor);
                    } else if ($campo == "estado") {
                        $query->where('cierre.nombre', '=', $valor);
                    } else if ($campo == "solicitante") {
                        $query->where('soli.nombre', '=', $valor);
                    } else {
                        $query->where($campo, '=', $valor);
                    }
                    break;
                case 'Igual a fecha':
                    $query->whereDate($campo, '=', $valor);
                    break;
                case 'Entre':
                    $query->whereDate($campo, '>=', $valor)
                        ->whereDate($campo, '<=', $valor2);
                    break;
            }

            $result = $query->paginate();
            return response()->json($result);
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function pendientes(Request $request)
    {
        $user = auth()->user();
        $lista = $request->all();
        foreach ($lista as $item) {
            $existeIngreso = SeguimientoCrmPendiente::where('registro_crm_id', $item)->where('usuario_id', $user->id)->first();

            if (!$existeIngreso) {
                $result = new SeguimientoCrmPendiente;
                $result->registro_crm_id = $item;
                $result->usuario_id = $user->id;
                $result->save();
            }
        }
        return response()->json(['status' => 'success', 'message' => 'Tareas pendientes agregadas exitosamente.']);
    }

    public function pendientes2($cantidad)
    {
        $user = auth()->user();
        $result = SeguimientoCrm::join('usr_app_sedes_saitemp as sede', 'sede.id', 'usr_app_seguimiento_crm.sede_id')
            ->join('usr_app_procesos as proces', 'proces.id', 'usr_app_seguimiento_crm.proceso_id')
            ->join('usr_app_atencion_interacion as inter', 'inter.id', 'usr_app_seguimiento_crm.tipo_atencion_id')
            ->join('usr_app_estado_cierre_crm as cierre', 'cierre.id', 'usr_app_seguimiento_crm.estado_id')
            ->join('usr_app_solicitante_crm as soli', 'soli.id', 'usr_app_seguimiento_crm.solicitante_id')
            ->join('usr_app_seguimiento_crm_pendientes as pen', 'pen.registro_crm_id', 'usr_app_seguimiento_crm.id')
            ->where('pen.usuario_id', '=', $user->id)
            ->select(
                'usr_app_seguimiento_crm.id',
                'usr_app_seguimiento_crm.numero_radicado',
                'sede.nombre as sede',
                'proces.nombre as proceso',
                'soli.nombre as solicitante',
                'inter.nombre as iteraccion',
                'usr_app_seguimiento_crm.nombre_contacto',
                'usr_app_seguimiento_crm.telefono',
                'usr_app_seguimiento_crm.correo',
                'cierre.nombre as estado',
            )
            ->orderby('usr_app_seguimiento_crm.id', 'DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
         
            DB::beginTransaction();
            try {
                $user = auth()->user();
                $result = new SeguimientoCrm;
                $result->sede_id = $request->sede_id;
                $result->proceso_id = $request->proceso_id;
                $result->solicitante_id = $request->solicitante_id;
                $result->nombre_contacto = $request->nombre_contacto;
                $result->tipo_atencion_id = $request->tipo_atencion_id;
                $result->telefono = $request->telefono;
                $result->correo = $request->correo;
                $result->estado_id = $request->estado_id;
                $result->observacion = $request->observacion;
                $result->nit_documento = $request->nit_documento;
                $result->pqrsf_id = $request->pqrsf_id;
                $result->creacion_pqrsf = $user->nombres . ' ' . $user->apellidos;
                $result->cierre_pqrsf = $request->cierre_pqrsf;
                $result->responsable = $request->responsable;
            //campos agregados para el formulario de visita
            $result->visitante= $request->visitante;
            $result->visitado= $request->visitado; 
            $result-> hora_inicio = $request->hora_inicio;
            $result-> hora_cierre = $request->hora_cierre;  
            $result-> cargo_visitante = $request->cargo_visitante;
            $result-> cargo_visitado= $request->cargo_atendio;
            $result-> objetivo = $request->objetivo_visita;
            $result-> alcance = $request->alcance_visita;
            $result-> latitud = $request->latitud;
            $result-> longitud = $request->longitud;

            
            if ($request->estado_id == 2) {
                $fechaHoraActual = Carbon::now();
                $result->fecha_cerrado = $fechaHoraActual->format('d-m-Y H:i:s');
            }
    
            $result->save();
            
            foreach ($request->imagen as $item) {
                for ($i = 0; $i < count($item); $i++) {
                    if ($i > 0) {
                        $evidencia = new Evidencia;
                        $evidencia->descripcion = $item[0]?$item[0]:"";
                        $evidencia->registro_id = $result->id;

                        $nombreArchivoOriginal = $item[$i]->getClientOriginalName();
                        $nuevoNombre = Carbon::now()->timestamp . "_" . $nombreArchivoOriginal;

                        $carpetaDestino = './upload/evidenciasCrm/';
                        $item[$i]->move($carpetaDestino, $nuevoNombre);
                        $evidencia->archivo = ltrim($carpetaDestino, '.') . $nuevoNombre;
                        $evidencia->save();
                    }
                }
            }

            if($request->compromisos ){
                $decodeCompromisos= json_decode($request->compromisos,true);
                if (count($decodeCompromisos) > 0) {
                    foreach ($decodeCompromisos as $item) {
                        $compromiso = new CompromisosVisitaCrm; 
                        $compromiso->titulo = isset($item['titulo']) ? $item['titulo'] : '';
                        $compromiso->descripcion = isset($item['descripcion']) ? $item['descripcion'] : '';
                        $compromiso->registro_id = $result->id;
                        $compromiso->responsable = isset($item['responsable']) ? $item['responsable'] : '';
                        $compromiso->estado_cierre_id = isset($item['estado_cierre_id']) ? $item['estado_cierre_id'] : '';
                        $compromiso->observacion = isset($item['observacion']) ? $item['observacion'] : '';
                /*         $fechaCierreFormatted = Carbon::parse($item['fecha_cierre'])->format('d-m-Y H:i:s');
                        $compromiso->fecha_cierre = isset($fechaCierreFormatted) ? $fechaCierreFormatted : ''; */
                        $compromiso->save();
                    }
                }
            }
             if($request->temasPrincipales){
                $decodeTemas= json_decode($request->temasPrincipales,true);
                if (count($decodeTemas) > 0) {
                    foreach ($decodeTemas as $item) {
                        
                        $temaPrincipal = new TemasVisitaCrm;
                        $temaPrincipal->titulo = isset($item['titulo']) ? $item['titulo'] : '';
                        $temaPrincipal->descripcion = isset($item['descripcion']) ? $item['descripcion'] : '';
                        $temaPrincipal->registro_id = $result->id;
                        $temaPrincipal->save();
                    }
                }
             }
           
           // Procesar temas principales
           

           if($request->asistencia ){
            foreach ($request->asistencia as $item) {
                for ($i = 0; $i < count($item); $i++) {
                    if ($i > 0) {
                        $asistencia = new AsistenciaVisitaCrm;
                        $decodeFirma= json_decode($item[0],true);
                    
                        $asistencia->nombre = $decodeFirma?$decodeFirma["nombre"]:"";
                        $asistencia->registro_id = $result->id;
                        $asistencia->cargo= $decodeFirma?$decodeFirma["cargo"]:""; 
                        $nombreArchivoOriginal = $item[$i]->getClientOriginalName();
                        $nuevoNombre = Carbon::now()->timestamp . "_" . $nombreArchivoOriginal;
                        $carpetaDestino = './upload/evidenciasCrm/';
                        $item[$i]->move($carpetaDestino, $nuevoNombre);
                        $asistencia->firma = ltrim($carpetaDestino, '.') . $nuevoNombre;
                        $asistencia ->save();
                    }
                }
            } 
           }
            
    
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro guardado de manera exitosa', 'id' => $result->id]);
        }
        catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
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
        DB::beginTransaction();
        try {
            $result = SeguimientoCrm::find($id);
        $result->sede_id = $request->sede_id;
        $result->proceso_id = $request->proceso_id;
        $result->solicitante_id = $request->solicitante_id;
        $result->nombre_contacto = $request->nombre_contacto;
        $result->tipo_atencion_id = $request->tipo_atencion_id;
        $result->telefono = $request->telefono;
        $result->correo = $request->correo;
        $result->estado_id = $request->estado_id;
        $result->observacion = $request->observacion;
        $result->nit_documento = $request->nit_documento;
        $result->cierre_pqrsf = $request->cierre_pqrsf;
        $result->responsable = $request->responsable;
        $result->pqrsf_id = $request->pqrsf_id;
          //campos agregados para el formulario de visita
          $result->visitante= $request->visitante;
          $result->visitado= $request->visitado; 
          $result-> hora_inicio = $request->hora_inicio;
          $result-> hora_cierre = $request->hora_cierre;  
          $result-> cargo_visitante = $request->cargo_visitante;
          $result-> cargo_visitado= $request->cargo_atendio;
          $result-> objetivo = $request->objetivo_visita;
          $result-> alcance = $request->alcance_visita;

          
          if ($request->estado_id == 3) {
              $fechaHoraActual = Carbon::now();
              $result->fecha_cerrado = $fechaHoraActual->format('d-m-Y H:i:s');
          }
  
          $result->save();
          
          foreach ($request->imagen as $item) {
              for ($i = 0; $i < count($item); $i++) {
                  if ($i > 0) {
                      $evidencia = new Evidencia;
                      $evidencia->descripcion = $item[0]?$item[0]:"";
                      $evidencia->registro_id = $result->id;

                      $nombreArchivoOriginal = $item[$i]->getClientOriginalName();
                      $nuevoNombre = Carbon::now()->timestamp . "_" . $nombreArchivoOriginal;

                      $carpetaDestino = './upload/evidenciasCrm/';
                      $item[$i]->move($carpetaDestino, $nuevoNombre);
                      $evidencia->archivo = ltrim($carpetaDestino, '.') . $nuevoNombre;
                      $evidencia->save();
                  }
              }
          }
          if($request->compromisos){
            $decodeCompromisos= json_decode($request->compromisos,true);
            if (count($decodeCompromisos) > 0) {
                foreach ($decodeCompromisos as $item) {
                    $compromiso = CompromisosVisitaCrm::find($item['id']);
                    $compromiso->titulo = isset($item['titulo']) ? $item['titulo'] : '';
                    $compromiso->descripcion = isset($item['descripcion']) ? $item['descripcion'] : '';
                    $compromiso->registro_id = $result->id;
                    $compromiso->estado_cierre_id = isset($item['estado_cierre_id']) ? $item['estado_cierre_id'] : '';
                    $fechaCierreFormatted = Carbon::parse($item['fecha_cierre'])->format('d-m-Y H:i:s');
                    $compromiso->responsable = isset($item['responsable']) ? $item['responsable'] : '';
                    $compromiso->observacion = isset($item['observacion']) ? $item['observacion'] : '';
                    $compromiso->fecha_cierre = isset($fechaCierreFormatted) ? $fechaCierreFormatted : '';
                    $compromiso->save();
                }
            }
          }
           if($request->temasPrincipales){
            $decodeTemas= json_decode($request->temasPrincipales,true);
            if (count($decodeTemas) > 0) {
                foreach ($decodeTemas as $item) {
                  $temaPrincipal = TemasVisitaCrm::find($item['id']);
                    $temaPrincipal->titulo = isset($item['titulo']) ? $item['titulo'] : '';
                    $temaPrincipal->descripcion = isset($item['descripcion']) ? $item['descripcion'] : '';
                    $temaPrincipal->registro_id = $result->id;
                    $temaPrincipal->save();
                }
            }}
          
         // Procesar temas principales
          
if($request->asistencia){
    foreach ($request->asistencia as $item) {
        for ($i = 0; $i < count($item); $i++) {
            if ($i > 0) {
                $asistencia = new AsistenciaVisitaCrm;
                $decodeFirma= json_decode($item[0],true);
            
                $asistencia->nombre = $decodeFirma?$decodeFirma["nombre"]:"";
                $asistencia->registro_id = $result->id;
                $asistencia->cargo= $decodeFirma?$decodeFirma["cargo"]:""; 
                $nombreArchivoOriginal = $item[$i]->getClientOriginalName();
                $nuevoNombre = Carbon::now()->timestamp . "_" . $nombreArchivoOriginal;
                $carpetaDestino = './upload/evidenciasCrm/';
                $item[$i]->move($carpetaDestino, $nuevoNombre);
                $asistencia->firma = ltrim($carpetaDestino, '.') . $nuevoNombre;
                $asistencia ->save();
            }
        }
    } 
}
          
        
  
        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $result->id]);
        } catch (\Throwable $th) {
            return $th;
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intenta nuevamente']);
        }
       
       
        
    }

    public function borradomasivo(Request $request)
    {
        try {
            $user = auth()->user();
            for ($i = 0; $i < count($request->id); $i++) {
                $result = SeguimientoCrmPendiente::where('registro_crm_id', '=', $request->id[$i])->where('usuario_id', $user->id)->first();
                $result->delete();
            }
            return response()->json(['status' => 'success', 'message' => 'Registros eliminados exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar el registro, por favor intente nuevamente']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }
    public function eliminararchivo($item, $id)
    {
        
        $result = Evidencia::where('usr_app_evidencia_crm.registro_id', '=', $item)
            ->where('usr_app_evidencia_crm.id', '=', $id)
            ->first();
        $registro = Evidencia::find($result->id);
        if ($registro->archivo != null) {
            $rutaArchivo = base_path('public') . $registro->archivo;
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
        }
        if ($registro->delete()) {
            return response()->json(['status' => 'success', 'message' => 'Registro eliminado con Exito']);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Error al eliminar registro']);
        }
    }
    public function updateEvidencia(Request $request, $id){
        $result = Evidencia::find($id); 
        $result->descripcion = $request->descripcion;
        if ($result->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa', 'id' => $result->id]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro']);
        }
    }

    
        public function generarPdfCrm(Request $request,$registro_id, $btnId)
        {
            $modulo = 46;
          
            // Obtener los datos del formulario

            $atencionInteracion="";
            $formulario = $this->byid($registro_id)->getData();
            if (isset($formulario->tipo_atencion_id)) {
                
                $atencionInteracion = DB::table('usr_app_atencion_interacion')
                    ->where('id', $formulario->tipo_atencion_id)
                    ->first();
            } else {
                // Manejar el caso donde tipo_atencion_id no exista
                $atencionInteracion = null;
            }
        
            // Inicializar TCPDF
            /* $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); */
            $pdf = new \TCPDF();
            // Establecer los metadatos del documento
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Saitemp');
            $pdf->SetTitle('Reporte CRM');
            $pdf->SetSubject('Detalles del CRM');
            $pdf->SetKeywords('TCPDF, PDF, CRM, reporte');
            $horaInicioFormateada = '';
            $horaCierreFormateada = '';
            $fechaCreacionFromated='';
            if (!empty($formulario->created_at)) {
                $fechaCreacionFromated = Carbon::parse($formulario->created_at)->format('d-m-Y H:i:s');
            }

            if (!empty($formulario->hora_inicio)) {
                $horaInicioFormateada = Carbon::parse($formulario->hora_inicio)->format('H:i');
            }
            if (!empty($formulario->hora_cierre)) {
                $horaCierreFormateada = Carbon::parse($formulario->hora_cierre)->format('H:i');}
            // Eliminar la cabecera y pie de página por defecto
            $pdf->setPrintHeader(false);
           /*  function addMembrete(\TCPDF $pdf)
            {
                $url = public_path('/upload/MEMBRETE.png');
                $pdf->Image($url, -0.5, 0, $pdf->getPageWidth() + 0.5, 30, '', '', '', false, 300, '', false, false, 0);
            }
         */
            // Añadir la primera página
            $pdf->AddPage();
           /*  addMembrete($pdf); */
           /*  $pdf->AddPage(); */
          /*   $pdf->setPrintFooter(false);
            $pdf->setFooterMargin(0); */
        
            // Añadir una página
            
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            // Agregar imagen de fondo
           $url = public_path('/upload/MEMBRETE.png');
            $pdf->Image($url, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
            // Colocar la imagen como fondo, cubriendo toda la página (A4 en este caso, 210x297 mm)
        
            // Asegurarse de que el contenido no esté afectado por la imagen de fondo
            
           /*  $pdf->SetMargins(5, 20, 5); */
            // Establecer fuente
            $pdf->SetFont('helvetica', '', 12);
        
            // Construir el contenido del PDF con las claves y valores del formulario y aplicando estilos


            $pdf->Ln(20);
            $html = '
                <style>
                    .divInit{
                    height: 200px;
                    color:transparent;
                    }
                    h1 {
                        color: #043c69;
                        text-align: center;
                        font-size: 16px;
                        margin-bottom: 20px;
                    }
                    .info {
                        font-size: 12px;
                        color: #000;
                        line-height: 1.5;
                        margin-bottom: 8px;
                        font-weight: normal;
                        
                        
                    }
                    .data-label {
                        font-weight: bold;
                        color: #043c69;
                    }
                    .section-title {
                        font-size: 16px;
                        color: #043c69;
                        margin-top: 15px;
                        margin-bottom: 10px;
                    }
                    table {
                        width: 100%;
                        higth:100%
                        border-collapse: collapse;
                    }
                    td {
                        border: 1px solid #ddd;
                        padding: 8px;
                        font-size: 12px;
                    }
                    .signature {
                        width: 100px;
                        height: 50px;
                        border: 1px solid #ddd;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .asistencia_title{
                        color: #043c69; 
                    }
                  
                </style>
                <div class="divInit">
         <h1> Registro de servicio </h1>
         </div>
                <table>
        <tr>
            <td class="data-label">Número Radicado:<br> <span class="info">' . $formulario->numero_radicado . '</span></td>
             <td class="data-label">Fecha de Creación:<br><span class="info"> ' . $fechaCreacionFromated. '</span></td>
        </tr>
        </table>
        <table>
        <tr>
          <td class="data-label">Sede:<br><span class="info"> ' . $formulario->sede . '</span></td>
          <td class="data-label">Medio de atencion:<br><span class="info"> ' . $atencionInteracion->nombre . '</span></td>
          ' . ($formulario->iteraccion == "Visita presencial" ? '
          <td class="data-label">Hora inicio:<br><span class="info"> ' .$horaInicioFormateada. '</span></td>
           <td class="data-label">Hora cierre:<br> <span class="info">' . $horaCierreFormateada  . '</span></td>' : '') . '
       </tr>
</table>
       <table>
        <tr>
            <td class="data-label">Empresa usuaria:<br><span class="info"> ' . $formulario->nombre_contacto . '</span></td>
            <td class="data-label">Nit:<br><span class="info"> ' . $formulario->nit_documento. '</span></td>
            
        </tr>
        <tr>
             <td class="data-label">Teléfono:<br><span class="info"> ' . $formulario->telefono . '</span></td>
             <td class="data-label">Correo:<br><span class="info"> ' . $formulario->correo . '</span></td>
        </tr>
         <tr>
            <td class="data-label">Tipo PQRSF:<br><span class="info"> '. $formulario->pqrsf . '</span></td>
            <td class="data-label">Responsable:<br><span class="info"> ' . $formulario->responsable. '</span></td>
        </tr>
        ' . ($formulario->iteraccion == "Visita presencial" ? '
        <tr>
            <td class="data-label">Visita realizada por:<br><span class="info"> '. $formulario->visitante . '</span></td>
            <td class="data-label">Cargo:<br><span class="info"> ' . $formulario->cargo_visitante . '</span></td>
        </tr>
         <tr>
            <td class="data-label">Visita atendida por:<br><span class="info"> '. $formulario->visitado . '</span></td>
            <td class="data-label">Cargo:<br><span class="info"> ' . $formulario->cargo_visitado . '</span></td>
        </tr>

        </table>
        <table>
        <tr>
        <td class="data-label">Objetivo:<br><span class="info"> ' . $formulario->objetivo . '</span></td>
        </tr>
       <tr>
        <td class="data-label">Alcance:<br><span class="info"> ' . $formulario->alcance . '</span></td>
        </tr>
        </table>
        
        ' : '') . '
        <table>
    <tr>
            <td class="data-label">Observación:<br><span class="info"> ' . $formulario->observacion . '</span></td>
        </tr>
        </table>
            ';

            // Mostrar evidencias en una tabla
            if (!empty($formulario->temasPrincipales)) {$html .= '
                <h2 class="section-title">Presentación y revision de temas</h2>
                <table>';
                    foreach ($formulario->temasPrincipales as $tema) {
                        $html .= '
                            <tr>
                                <td>' . $tema->titulo . ':</td>
                                <td>' . $tema->descripcion . '</td>
                            </tr>';
                    }
                    $html .= '
                    <h2 class="section-title">Compromisos Generales</h2>
                    <table>';
            foreach ($formulario->compromisos as $compromiso) {
                $html .= '
                    <tr>
                        <td>' . $compromiso->titulo . ':</td>
                        <td>' . $compromiso->descripcion . '</td>
                    </tr>';
            }
            $html .= '</table>';}
            if ($formulario->iteraccion == "Visita presencial") {
            $html .= '
                <h2 class="section-title">Asistencias</h2>
                <table>
                    <tr>
                        <td class="asistencia_title" ><strong>Nombre:</strong></td>
                        <td class="asistencia_title"><strong>Cargo:</strong></td>
                        <td class="asistencia_title"><strong>Firma:</strong></td>
                    </tr>';
            foreach ($formulario->asistencias as $asistencia) {
                $html .= '
                    <tr>
                        <td>' . $asistencia->nombre . '</td>
                        <td>' . $asistencia->cargo . '</td>
                        <td><img src="' . public_path($asistencia->firma) . '" class="signature" /></td>
                    </tr>';
            }
            $html .= '</table>';}
            $margen_izquierdo = 15;
            $margen_derecho = 15;
            $pdf->SetMargins($margen_izquierdo, 40, $margen_derecho);
            $pdf->SetAutoPageBreak(true, 30); // 10 mm de margen inferior
            // Escribir el HTML en el PDF
            $pdf->writeHTML($html, false, false, true, false, '');
            $totalPages=0;
            if ($pdf->getNumPages() == 1) {
                $totalPages = 1;
            } else {
                $totalPages = $pdf->getNumPages();
            }
        
            // Agregar membrete en cada página después de la primera
            for ($i = 2; $i <= $totalPages; $i++) {
               
                $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 30);
            $url = public_path('/upload/MEMBRETE.png');
            $pdf->Image($url, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
            $pdf->SetMargins(0, 30, 0);
            }
        
        
            // Opción 1: Descargar el PDF
            if($btnId==2){
            $pdf->Output('formulario.pdf', 'D');};  
        
            // Opción 2: Mostrar el PDF en el navegador
            // $pdf->Output('formulario.pdf', 'I');
            try {
                if($btnId==1){
                    $pdfPath = storage_path('app/temp.pdf');
                    $pdf->Output($pdfPath, 'F');
                    if (!file_exists($pdfPath)) {
                        return response()->json(['message' => 'Error al crear el PDF'], 500);
                    }
                    $this->enviarCorreo($formulario->correo, $formulario, $pdfPath, $registro_id, $modulo);
                   
                    // Enviar correos a cada uno en el request
                        foreach ($request->correos as $correoData) {
                             if ($correoData['correo'] !="") {
                                $this->enviarCorreo($correoData['correo'], $formulario, $pdfPath, $registro_id, $modulo, $correoData['observacion']);
                            } 
                        }
                  
                        return response()->json(['status' => 'success', 'message' => 'Registro enviado de manera exitosa']);
                }
            } catch (\Throwable $th) {
                return response()->json(['status' => 'error', 'message' => 'No fue posible enviar el registro verifique el correo de contacto']);
            }
          
           
        }






        private function enviarCorreo($destinatario, $formulario, $pdfPath, $registro_id, $modulo, $observacion = '')
{
    $body = "Cordial saludo, esperamos se encuentren muy bien.\n\n Informamos que el registro de servicio ha sido creado satisfactoriamente, Cualquier información adicional podrá ser atendida en la línea Servisai de Saitemp S.A. marcando  al (604) 4485744, con gusto uno de nuestros facilitadores atenderá su llamada.\n\n simplificando conexiones, facilitando experiencias.";
    $body = nl2br($body);

    if($observacion!=""){
        $body= "Cordial saludo, tiene nuevos compromisos asignados en el radicado adjunto con las siguientes observaciones: $observacion";
    }

    
    $subject = 'Confirmación registro de servicio.';
    $nomb_membrete = 'Informe de servicio';

    // Datos del correo
    $correo = [
        'subject' => $subject,
        'body' => $body,
        'formulario_ingreso' => $pdfPath,
        'to' => $destinatario,
        'cc' => '',
        'cco' => '',
        'modulo' => $modulo,
        'registro_id' => $registro_id,
        'nom_membrete' => $nomb_membrete
    ];

    // Instanciar el controlador de envío de correo
    $EnvioCorreoController = new EnvioCorreoController();
    $requestEmail = Request::createFromBase(new Request($correo));

    // Enviar el correo
    return $EnvioCorreoController->sendEmail($requestEmail);
}
}