<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\formularioGestionIngreso;
use App\Models\FormularioIngresoArchivos;
use App\Models\FormularioIngresoResponsable;
use App\Models\FormularioIngresoPendientes;
use App\Models\ListaTrump;
use App\Models\RegistroIngresoLaboratorio;
use App\Models\FormularioIngresoSeguimiento;
use Carbon\Carbon;
use TCPDF;
use Illuminate\Support\Facades\DB;
// use App\Events\EventoPrueba2;


class formularioGestionIngresoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($cantidad)
    {

        // $objeto = (object) [
        //     'mensaje' => 'Filtrando empresas',
        //     'componente' => 'navbar/gestion-ingresosl'
        // ];
        // event(new EventoPrueba2($objeto));

        $result = formularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->LeftJoin('usr_app_estados_ingreso as est', 'est.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->select(
                'usr_app_formulario_ingreso.id',
                'usr_app_formulario_ingreso.numero_radicado',
                'usr_app_formulario_ingreso.created_at',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'usr_app_formulario_ingreso.cargo',
                'cli.razon_social',
                'mun.nombre as ciudad',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.fecha_examen',
                DB::raw("FORMAT(CAST(usr_app_formulario_ingreso.fecha_ingreso AS DATE), 'dd/MM/yyyy') as fecha_ingreso"),
                'usr_app_formulario_ingreso.estado_vacante',
                'usr_app_formulario_ingreso.novedades',
                'usr_app_formulario_ingreso.observacion_estado',
                'usr_app_formulario_ingreso.profesional',
                'est.nombre as estado_ingreso',
                'usr_app_formulario_ingreso.responsable',
                // 'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'est.id as estado_ingreso_id',
                'est.color as color_estado',
            )
            ->orderby('usr_app_formulario_ingreso.id', 'DESC')
            ->paginate($cantidad);
        foreach ($result as $item) {
            $item->fecha_examen = $item->fecha_examen ? date('d/m/Y H:i', strtotime($item->fecha_examen)) : null;
        }
        return response()->json($result);
    }

    public function consulta_id_trump($id)
    {
        $result = ListaTrump::select(
            'cod_emp',
            'nombre',
            'observacion',
            'fecha',
            'bloqueado',
        )
            ->where('cod_emp', '=', $id)
            ->first();

        if ($result !== null) {
            if ($result->bloqueado == 1) {
                $result->bloqueado = 'Si';
            } else {
                $result->bloqueado = 'No';
            }
            return $result;
        } else {
            $result = formularioGestionIngreso::where('usr_app_formulario_ingreso.numero_identificacion', '=', $id)
                ->whereRaw('created_at BETWEEN DATEADD(MONTH, -2, GETDATE()) AND GETDATE()')
                ->select(
                    'created_at as fecha_radicado',
                    'numero_identificacion',
                    'usr_app_formulario_ingreso.responsable as responsable_ingreso'
                )
                ->first();
            return $result;
        }
    }

    public function actualizaestadoingreso($item_id, $estado_id)
    {
        $user = auth()->user();
        $usuarios = FormularioIngresoResponsable::where('usr_app_formulario_ingreso_responsable.estado_ingreso_id', '=', $estado_id)
            ->join('usr_app_usuarios as usr', 'usr.id', '=', 'usr_app_formulario_ingreso_responsable.usuario_id')
            ->select(
                'usuario_id',
                'usr.nombres'
            )
            ->get();

        // Obtener el número total de responsables
        $numeroResponsables = $usuarios->count();

        // Obtener el registro de ingreso
        $registro_ingreso = formularioGestionIngreso::where('usr_app_formulario_ingreso.id', '=', $item_id)
            ->first();

        if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Solo el responsable puede realizar esta acción.']);
        }

        // Asignar a cada registro de ingreso un responsable
        $indiceResponsable = $registro_ingreso->id % $numeroResponsables; // Calcula el índice del responsable basado en el ID del registro
        $responsable = $usuarios[$indiceResponsable];

        // Actualizar el registro de ingreso con el estado y el responsable
        $registro_ingreso->estado_ingreso_id = $estado_id;
        // $registro_ingreso->responsable = $responsable->nombres;
        if ($registro_ingreso->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro.']);
    }

    public function actualizaResponsableingreso($item_id, $responsable_id, $nombre_responsable)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $registro_ingreso = formularioGestionIngreso::where('usr_app_formulario_ingreso.id', '=', $item_id)
                ->first();


            if ($registro_ingreso->responsable_id != null && $registro_ingreso->responsable_id != $user->id) {
                return response()->json(['status' => 'error', 'message' => 'Solo el responsable puede realizar esta acción.']);
            }

            $registro_ingreso->responsable_anterior = $registro_ingreso->responsable;
            $registro_ingreso->responsable = $nombre_responsable;
            $registro_ingreso->responsable_id = $responsable_id;
            $registro_ingreso->save();
            $seguimiento = new FormularioIngresoSeguimiento;
            $seguimiento->estado_ingreso_id = $registro_ingreso->estado_ingreso_id;
            $seguimiento->usuario = $registro_ingreso->responsable;
            $seguimiento->formulario_ingreso_id = $item_id;
            $seguimiento->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Registro actualizado de manera exitosa.']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Error al actualizar registro.']);
        }
    }
    public function responsableingresos($estado)
    {
        $usuarios = FormularioIngresoResponsable::join('usr_app_usuarios as usr', 'usr.id', '=', 'usr_app_formulario_ingreso_responsable.usuario_id')
            ->where('usr_app_formulario_ingreso_responsable.estado_ingreso_id', '=', $estado)
            ->select(
                'usuario_id',
                DB::raw("CONCAT(nombres,' ',apellidos)  AS nombre")
            )
            ->get();
        return response()->json($usuarios);
    }


    public function byid($id)
    {
        $result = formularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->leftJoin('usr_app_departamentos as dep', 'dep.id', 'mun.departamento_id')
            ->leftJoin('usr_app_paises as pais', 'pais.id', 'dep.pais_id')
            ->leftJoin('usr_app_afp as afp', 'afp.id', 'usr_app_formulario_ingreso.afp_id')
            ->leftJoin('usr_app_estados_ingreso as esti', 'esti.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->leftJoin('usr_app_formulario_ingreso_tipo_servicio as tiser', 'tiser.id', 'usr_app_formulario_ingreso.tipo_servicio_id')
            ->leftJoin('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_formulario_ingreso.tipo_documento_id')
            ->where('usr_app_formulario_ingreso.id', '=', $id)
            ->select(
                'usr_app_formulario_ingreso.id',
                'esti.nombre as estado_ingreso',
                'esti.id as estado_ingreso_id',
                'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'usr_app_formulario_ingreso.fecha_ingreso',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'usr_app_formulario_ingreso.cliente_id',
                'cli.razon_social',
                'usr_app_formulario_ingreso.cargo',
                'usr_app_formulario_ingreso.salario',
                'usr_app_formulario_ingreso.municipio_id',
                'mun.nombre as municipio',
                'usr_app_formulario_ingreso.numero_contacto',
                'usr_app_formulario_ingreso.eps',
                'usr_app_formulario_ingreso.afp_id',
                'afp.nombre as afp',
                'usr_app_formulario_ingreso.estradata',
                'usr_app_formulario_ingreso.novedades',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.examenes',
                'usr_app_formulario_ingreso.fecha_examen',
                'dep.id as departamento_id',
                'dep.nombre as departamento',
                'pais.id as pais_id',
                'pais.nombre as pais',
                'usr_app_formulario_ingreso.created_at as fecha_radicado',
                'tiser.nombre_servicio',
                'tiser.id as tipo_servicio_id',
                'usr_app_formulario_ingreso.numero_vacantes',
                'usr_app_formulario_ingreso.numero_contrataciones',
                'usr_app_formulario_ingreso.citacion_entrevista',
                'usr_app_formulario_ingreso.profesional',
                'usr_app_formulario_ingreso.informe_seleccion',
                'usr_app_formulario_ingreso.cambio_fecha',
                'usr_app_formulario_ingreso.numero_radicado',
                'usr_app_formulario_ingreso.direccion_empresa',
                'usr_app_formulario_ingreso.direccion_laboratorio',
                'usr_app_formulario_ingreso.recomendaciones_examen',
                'usr_app_formulario_ingreso.novedad_stradata',
                'usr_app_formulario_ingreso.correo_notificacion_empresa',
                'usr_app_formulario_ingreso.correo_notificacion_usuario',
                'usr_app_formulario_ingreso.novedades_examenes',
                'ti.des_tip as tipo_identificacion',
                'usr_app_formulario_ingreso.subsidio_transporte',
                'usr_app_formulario_ingreso.estado_vacante',
                'usr_app_formulario_ingreso.responsable_id',
                'ti.cod_tip as tipo_identificacion_id',
                'usr_app_formulario_ingreso.afectacion_servicio',
                'usr_app_formulario_ingreso.observacion_estado',
                'usr_app_formulario_ingreso.correo_laboratorio',
                'usr_app_formulario_ingreso.contacto_empresa',

            )
            ->first();

        $laboratorios = RegistroIngresoLaboratorio::join('usr_app_ciudad_laboraorio as ciulab', 'ciulab.id', '=', 'usr_app_registro_ingreso_laboraorio.laboratorio_medico_id')
            ->join('usr_app_municipios as mun', 'mun.id', '=', 'ciulab.ciudad_id')
            ->join('usr_app_departamentos as dep', 'dep.id', '=', 'mun.departamento_id')
            ->where('usr_app_registro_ingreso_laboraorio.registro_ingreso_id', '=', $id)
            ->select(
                'ciulab.id',
                'ciulab.laboratorio as nombre',
                'mun.id as municipio_id',
                'mun.nombre as municipio',
                'dep.id as departamento_id',
                'dep.nombre as departamento',
            )
            ->get();
        $result['laboratorios'] = $laboratorios;

        $archivos = FormularioIngresoArchivos::join('usr_app_archivos_formulario_ingreso as fi', 'fi.id', '=', 'usr_app_formulario_ingreso_archivos.arhivo_id')
            ->where('ingreso_id', $id)
            ->select(
                'usr_app_formulario_ingreso_archivos.arhivo_id',
                'usr_app_formulario_ingreso_archivos.ruta',
                'usr_app_formulario_ingreso_archivos.observacion',
                'fi.nombre',
                'fi.tipo_archivo'
            )
            ->orderby('usr_app_formulario_ingreso_archivos.arhivo_id', 'ASC')
            ->get();
        $result['archivos'] = $archivos;


        $seguimiento = FormularioIngresoSeguimiento::join('usr_app_estados_ingreso as ei', 'ei.id', '=', 'usr_app_formulario_ingreso_seguimiento.estado_ingreso_id')
            ->where('usr_app_formulario_ingreso_seguimiento.formulario_ingreso_id', $id)
            ->select(
                'usr_app_formulario_ingreso_seguimiento.usuario',
                'ei.nombre as estado',
                'usr_app_formulario_ingreso_seguimiento.created_at',

            )
            ->orderby('usr_app_formulario_ingreso_seguimiento.id', 'desc')
            ->get();
        $result['seguimiento'] = $seguimiento;
        return response()->json($result);
    }


    // public function gestioningresospdf($modulo = null, $registro_id, $id)
    // {

    //     $formulario = $this->byid($registro_id)->getData();


    //     $pdf = new TCPDF();
    //     $pdf->SetTextColor(4, 66, 105);
    //     $pdf->setPrintHeader(false);
    //     $pdf->AddPage();

    //     $pdf->SetAutoPageBreak(false, 0);
    //     $pdf->SetMargins(0, 0, 0);

    //     $url = public_path('\/upload\/MEMBRETE.png');
    //     $img_file = $url;
    //     $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);

    //     $combinacion_correos = '';

    //     if ($formulario->correo_laboratorio != null && $id == 3) {
    //         $combinacion_correos =  $formulario->correo_laboratorio;
    //     } else {
    //         $combinacion_correos = $formulario->correo_notificacion_empresa;
    //     }

    //     $fecha_ingreso = $formulario->fecha_ingreso;
    //     $numero_identificacion = $formulario->numero_identificacion;
    //     $nombre_completo = $formulario->nombre_completo;
    //     $razon_social = $formulario->razon_social;
    //     $cargo = $formulario->cargo;
    //     $salario = $formulario->salario;
    //     $municipio = $formulario->municipio;
    //     $numero_contacto = $formulario->numero_contacto;
    //     $otro_laboratorio = $formulario->laboratorio;
    //     $examenes = $formulario->examenes;

    //     $fecha_examenes = $formulario->fecha_examen;
    //     if (!empty($fecha_examenes)) {
    //         $timestamp = strtotime($fecha_examenes);
    //         $fecha_examen = date('d/m/Y', $timestamp);
    //     } else {
    //         $fecha_examen = '';
    //     }


    //     $departamento = $formulario->departamento;
    //     $nombre_servicio = $formulario->nombre_servicio;
    //     $tipo_servicio_id = $formulario->tipo_servicio_id;

    //     $fecha_citacion_entrevista = $formulario->citacion_entrevista;

    //     if (!empty($fecha_citacion_entrevista)) {
    //         $timestamp2 = strtotime($fecha_citacion_entrevista);
    //         $citacion_entrevista = date('d/m/Y, H:i', $timestamp2);
    //     } else {
    //         $citacion_entrevista = '';
    //     }

    //     $profesional = $formulario->profesional;
    //     $informe_seleccion = $formulario->informe_seleccion;
    //     $direccion_empresa = $formulario->direccion_empresa;
    //     $direccion_laboratorio = $formulario->direccion_laboratorio;
    //     $recomendaciones_examen = $formulario->recomendaciones_examen;
    //     $ancho_maximo = 70;
    //     $ancho_seleccion = 100;

    //     if (isset($formulario->laboratorios[0])) {
    //         $departamento_laboratorio = $formulario->laboratorios[0]->departamento;
    //         $municipio_laboratorio = $formulario->laboratorios[0]->municipio;
    //         $laboratorio_medico = $formulario->laboratorios[0]->nombre;
    //     }


    //     if ($id == 1  || $id == 3) {

    //         $pdf->Ln(20);

    //         $html = '<table cellpadding="2" cellspacing="0" style="width: 100%;">
    //         <tr>
    //         <td style="text-align: center;">
    //         <div style="font-size: 16pt; font-weight: bold;">Orden de servicio:</div>
    //         </td>
    //         </tr>
    //         </table>';

    //         $pdf->writeHTML($html, true, false, true, false, '');

    //         if (strlen($razon_social) < 35 && strlen($direccion_empresa) < 35) {

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Dirección empresa:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $razon_social != null ? $razon_social : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);
    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($nombre_servicio);

    //             $pdf->MultiCell($ancho_texto + 7, 7, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 'L');
    //             $pdf->Ln(1);
    //         } else if (strlen($direccion_empresa) < 35) {

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($razon_social);

    //             $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);
    //         } else {
    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($razon_social);
    //             $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');


    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($direccion_empresa);
    //             $pdf->MultiCell($ancho_texto + 7, 7, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 'L');


    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($nombre_servicio);
    //             $pdf->MultiCell($ancho_texto + 7, 7, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 'L');
    //         }


    //         $pdf->SetFont('helvetica', 'B', 11);
    //         $pdf->SetX(20);
    //         $pdf->Cell(95, 10, 'Fecha de ingreso:', 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(95, 10, 'Número de identificación:', 0, 1, 'L');
    //         $pdf->SetFont('helvetica', '', 11);

    //         $pdf->SetX(20);
    //         $pdf->Cell(10, 1, $fecha_ingreso != null ? $fecha_ingreso : 'Sin datos', 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(65, 1, $numero_identificacion != null ? $numero_identificacion : 'Sin datos', 0, 1, 'L');
    //         $pdf->Ln(3);

    //         $pdf->SetFont('helvetica', 'B', 11);
    //         $pdf->SetX(20);
    //         $pdf->Cell(95, 10, 'Apellidos y Nombres:', 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(95, 10, 'Número contacto:', 0, 1, 'L');
    //         $pdf->SetFont('helvetica', '', 11);

    //         $pdf->SetX(20);
    //         $pdf->Cell(10, 1, $nombre_completo != null ? $nombre_completo : 'Sin datos', 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(65, 1, $numero_contacto != null ? $numero_contacto : 'Sin datos', 0, 1, 'L');
    //         $pdf->Ln(2);


    //         if (strlen($cargo) < 35) {
    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Salario:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $cargo != null ? $cargo : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $salario != null ? $salario : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(2);
    //         } else {
    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);
    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $lineas = explode("\n", wordwrap($cargo != null ? $cargo : 'Sin datos', $ancho_maximo, "\n"));

    //             foreach ($lineas as $linea) {
    //                 $ancho_texto = $pdf->GetStringWidth($linea);
    //                 $pdf->SetX(20);
    //                 $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Salario:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($salario);

    //             $pdf->MultiCell($ancho_texto + 7, 7, $salario != null ? $salario : 'Sin datos', 0, 'L');
    //         }

    //         $pdf->SetFont('helvetica', 'B', 11);
    //         $pdf->SetX(20);
    //         $pdf->Cell(95, 10, 'Departamento de prestación de servicios:', 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(95, 10, 'Ciudad de prestación de servicios:', 0, 1, 'L');
    //         $pdf->SetFont('helvetica', '', 11);

    //         $pdf->SetX(20);
    //         $pdf->Cell(10, 1, $departamento, 0, 0, 'L');

    //         $pdf->SetX(120);
    //         $pdf->Cell(65, 1, $municipio, 0, 1, 'L');
    //         $pdf->Ln(2);

    //         if (isset($formulario->laboratorios[0])) {

    //             if (!empty($formulario->laboratorios)) {
    //                 if (strlen($direccion_laboratorio) < 30) {

    //                     $pdf->SetFont('helvetica', 'B', 11);
    //                     $pdf->SetX(20);
    //                     $pdf->Cell(95, 10, 'Departamento ubicación laboratorio médico:', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(95, 10, 'Ciudad ubicación laboratorio médico:', 0, 1, 'L');
    //                     $pdf->SetFont('helvetica', '', 11);

    //                     $pdf->SetX(20);
    //                     $pdf->Cell(10, 1, $departamento_laboratorio != '' ? $departamento_laboratorio : 'Sin datos', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(65, 1, $municipio_laboratorio != '' ? $municipio_laboratorio : 'Sin datos', 0, 1, 'L');
    //                     $pdf->Ln(2);

    //                     $pdf->SetFont('helvetica', 'B', 11);
    //                     $pdf->SetX(20);
    //                     $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 1, 'L');
    //                     $pdf->SetFont('helvetica', '', 11);

    //                     $pdf->SetX(20);
    //                     $pdf->Cell(10, 1, $laboratorio_medico != null ? $laboratorio_medico : 'Sin datos', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(65, 1, $direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', 0, 1, 'L');
    //                     $pdf->Ln(2);
    //                 } else {

    //                     $pdf->SetFont('helvetica', 'B', 11);
    //                     $pdf->SetX(20);
    //                     $pdf->Cell(95, 10, 'Departamento ubicación laboratorio médico:', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(95, 10, 'Ciudad ubicación laboratorio médico:', 0, 1, 'L');
    //                     $pdf->SetFont('helvetica', '', 11);

    //                     $pdf->SetX(20);
    //                     $pdf->Cell(10, 1, $departamento_laboratorio != '' ? $departamento_laboratorio : 'Sin datos', 0, 0, 'L');

    //                     $pdf->SetX(120);
    //                     $pdf->Cell(65, 1, $municipio_laboratorio != '' ? $municipio_laboratorio : 'Sin datos', 0, 1, 'L');
    //                     $pdf->Ln(2);

    //                     $pdf->SetFont('helvetica', 'B', 11);
    //                     $pdf->SetX(20);
    //                     $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');
    //                     $pdf->SetFont('helvetica', '', 11);

    //                     $pdf->Ln(10);
    //                     $pdf->SetX(20);
    //                     $ancho_texto = $pdf->GetStringWidth($laboratorio_medico);
    //                     $pdf->MultiCell($ancho_texto + 30, 7, $laboratorio_medico != '' ? $laboratorio_medico : 'Sin datos', 0, 'L');

    //                     $pdf->SetFont('helvetica', 'B', 11);
    //                     $pdf->SetX(20);
    //                     $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 0, 'L');
    //                     $pdf->SetFont('helvetica', '', 11);
    //                     $pdf->Ln(10);
    //                     $pdf->SetX(20);
    //                     $lineas = explode("\n", wordwrap($direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', $ancho_maximo, "\n"));

    //                     foreach ($lineas as $linea) {
    //                         $ancho_texto = $pdf->GetStringWidth($linea);
    //                         $pdf->SetX(20);
    //                         $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                     }
    //                 }
    //             }
    //         }

    //         if ($otro_laboratorio != '') {

    //             if (strlen($direccion_laboratorio) < 30) {

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 1, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->SetX(20);
    //                 $pdf->Cell(10, 1, $otro_laboratorio != null ? $otro_laboratorio : 'Sin datos', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(65, 1, $direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', 0, 1, 'L');
    //                 $pdf->Ln(2);
    //             } else {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $ancho_texto = $pdf->GetStringWidth($otro_laboratorio);
    //                 $pdf->MultiCell($ancho_texto + 30, 7, $otro_laboratorio != '' ? $otro_laboratorio : 'Sin datos', 0, 'L');

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }
    //             }
    //         }


    //         if ($tipo_servicio_id == 3 || $tipo_servicio_id == 4) {
    //             $pdf->AddPage();
    //             $url = public_path('\/upload\/MEMBRETE.png');
    //             $img_file = $url;
    //             $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
    //             $pdf->Ln(45);
    //             if (strlen($examenes) < 30 && strlen($recomendaciones_examen) < 30) {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 1, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->SetX(20);
    //                 $pdf->Cell(10, 1, $examenes != null ? $examenes : 'Sin datos', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(65, 1, $recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', 0, 1, 'L');
    //                 $pdf->Ln(2);
    //             } else {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($examenes != null ? $examenes : 'Sin datos', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Fecha de exámenes:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($fecha_examen);
    //             $pdf->MultiCell($ancho_texto + 30, 7, $fecha_examen != '' ? $fecha_examen : 'Sin datos', 0, 'L');
    //         } else {
    //             if (strlen($examenes) < 30 && strlen($recomendaciones_examen) < 30 && strlen($direccion_empresa) < 30) {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 1, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->SetX(20);
    //                 $pdf->Cell(10, 1, $examenes != null ? $examenes : 'Sin datos', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(65, 1, $recomendaciones_examen != null ? $recomendaciones_examen : 'Sin datos', 0, 1, 'L');
    //                 $pdf->Ln(2);
    //             } else {
    //                 $pdf->AddPage();
    //                 $url = public_path('\/upload\/MEMBRETE.png');
    //                 $img_file = $url;
    //                 $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
    //                 $pdf->Ln(45);

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($examenes != null ? $examenes : 'Sin datos', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }
    //             }
    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Fecha de exámenes:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($fecha_examen);
    //             $pdf->MultiCell($ancho_texto + 30, 7, $fecha_examen != '' ? $fecha_examen : 'Sin datos', 0, 'L');
    //         }
    //     } else if ($id == 2) {

    //         $pdf->Ln(20);

    //         $html = '<table cellpadding="2" cellspacing="0" style="width: 100%;">
    //         <tr>
    //         <td style="text-align: center;">
    //         <div style="font-size: 16pt; font-weight: bold;">Informe de seleccion:</div>
    //         </td>
    //         </tr>
    //         </table>';

    //         $pdf->writeHTML($html, true, false, true, false, '');

    //         if (strlen($razon_social) < 35 && strlen($direccion_empresa) < 35) {

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Dirección empresa:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $razon_social != null ? $razon_social : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);


    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Citación entrevista:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $citacion_entrevista != null ? $citacion_entrevista : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);
    //         } else {
    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($razon_social);
    //             $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');


    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($direccion_empresa);
    //             $pdf->MultiCell($ancho_texto + 30, 7, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 'L');


    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Citación entrevista:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $citacion_entrevista != null ? $citacion_entrevista : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);
    //         }

    //         if (strlen($informe_seleccion) < 500) {

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Fecha de ingreso:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Número de identificación:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $fecha_ingreso != null ? $fecha_ingreso : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $numero_identificacion != null ? $numero_identificacion : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Apellidos y nombres:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Número contacto:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $nombre_completo != null ? $nombre_completo : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $numero_contacto != null ? $numero_contacto : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(2);

    //             if (strlen($cargo) < 35) {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(95, 10, 'Salario:', 0, 1, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->SetX(20);
    //                 $pdf->Cell(10, 1, $cargo != null ? $cargo : 'Sin datos', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(65, 1, $salario != null ? $salario : 'Sin datos', 0, 1, 'L');
    //                 $pdf->Ln(2);
    //             } else {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($cargo != null ? $cargo : 'Sin datos', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Salario:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $ancho_texto = $pdf->GetStringWidth($salario);

    //                 $pdf->MultiCell($ancho_texto + 7, 7, $salario != null ? $salario : 'Sin datos', 0, 'L');
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Departamento de prestación de servicios:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Ciudad de prestación de servicios:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $departamento, 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $municipio, 0, 1, 'L');
    //             $pdf->Ln(2);

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Informe selección:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);
    //             $pdf->Ln(10);
    //             $pdf->SetX(20);

    //             $lineas = explode("\n", wordwrap($informe_seleccion != null ? $informe_seleccion : 'Sin datos', $ancho_seleccion, "\n"));

    //             foreach ($lineas as $linea) {
    //                 $ancho_texto = $pdf->GetStringWidth($linea);
    //                 $pdf->SetX(20);
    //                 $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Profesional:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($profesional);

    //             $pdf->MultiCell($ancho_texto + 7, 7, $profesional != null ? $profesional : 'Sin datos', 0, 'L');
    //         } else {

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Fecha de ingreso:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Número de identificación:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $fecha_ingreso != null ? $fecha_ingreso : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $numero_identificacion != null ? $numero_identificacion : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(3);

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Apellidos y nombres:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Número contacto:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $nombre_completo != null ? $nombre_completo : 'Sin datos', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $numero_contacto != null ? $numero_contacto : 'Sin datos', 0, 1, 'L');
    //             $pdf->Ln(2);

    //             if (strlen($cargo) < 35) {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(95, 10, 'Salario:', 0, 1, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->SetX(20);
    //                 $pdf->Cell(10, 1, $cargo != null ? $cargo : 'Sin datos', 0, 0, 'L');

    //                 $pdf->SetX(120);
    //                 $pdf->Cell(65, 1, $salario != null ? $salario : 'Sin datos', 0, 1, 'L');
    //                 $pdf->Ln(2);
    //             } else {
    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);
    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $lineas = explode("\n", wordwrap($cargo != null ? $cargo : 'Sin datos', $ancho_maximo, "\n"));

    //                 foreach ($lineas as $linea) {
    //                     $ancho_texto = $pdf->GetStringWidth($linea);
    //                     $pdf->SetX(20);
    //                     $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //                 }

    //                 $pdf->SetFont('helvetica', 'B', 11);
    //                 $pdf->SetX(20);
    //                 $pdf->Cell(95, 10, 'Salario:', 0, 0, 'L');
    //                 $pdf->SetFont('helvetica', '', 11);

    //                 $pdf->Ln(10);
    //                 $pdf->SetX(20);
    //                 $ancho_texto = $pdf->GetStringWidth($salario);

    //                 $pdf->MultiCell($ancho_texto + 7, 7, $salario != null ? $salario : 'Sin datos', 0, 'L');
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Departamento de prestación de servicios:', 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(95, 10, 'Ciudad de prestación de servicios:', 0, 1, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->SetX(20);
    //             $pdf->Cell(10, 1, $departamento, 0, 0, 'L');

    //             $pdf->SetX(120);
    //             $pdf->Cell(65, 1, $municipio, 0, 1, 'L');
    //             $pdf->Ln(2);

    //             $pdf->AddPage();
    //             $url = public_path('\/upload\/MEMBRETE.png');
    //             $img_file = $url;
    //             $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
    //             $pdf->Ln(45);

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Informe selección:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);
    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $lineas = explode("\n", wordwrap($informe_seleccion != null ? $informe_seleccion : 'Sin datos', $ancho_seleccion, "\n"));

    //             foreach ($lineas as $linea) {
    //                 $ancho_texto = $pdf->GetStringWidth($linea);
    //                 $pdf->SetX(20);
    //                 $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
    //             }

    //             $pdf->SetFont('helvetica', 'B', 11);
    //             $pdf->SetX(20);
    //             $pdf->Cell(95, 10, 'Profesional:', 0, 0, 'L');
    //             $pdf->SetFont('helvetica', '', 11);

    //             $pdf->Ln(10);
    //             $pdf->SetX(20);
    //             $ancho_texto = $pdf->GetStringWidth($profesional);

    //             $pdf->MultiCell($ancho_texto + 7, 7, $profesional != null ? $profesional : 'Sin datos', 0, 'L');
    //         }
    //     }

    //     if ($modulo != 'null') {
    //         $pdfPath = storage_path('app/temp.pdf');
    //         $pdf->Output($pdfPath, 'F');
    //     } else {
    //         $pdf->Output('I');
    //     }

    //     $body = '';
    //     $subject = '';
    //     $nomb_membrete = '';
    //     if ($id == 3 && $formulario->correo_laboratorio != null) {
    //         $body = "Cordial saludo, esperamos se encuentren bien.\n\nAutorizamos exámenes médicos en solicitud de servicio adjunta, cualquier información adicional que se requiera, comunicarse a la línea Servisai de Saitemp S.A. marcando al (604) 4485744, donde con gusto uno de nuestros facilitadores atenderá su llamada.\n\nSimplificando conexiones, facilitando experiencias.";
    //         $body = nl2br($body);
    //         $subject = 'Autorización de exámenes.';
    //         $nomb_membrete = 'Autorizacion';
    //     } elseif ($id == 1 && $formulario->correo_notificacion_empresa != null) {
    //         $body = "Cordial saludo, esperamos se encuentren muy bien.\n\n Informamos que su solicitud de servicio ha sido recibida satisfactoriamente, Cualquier información adicional podrá ser atendida en la línea Servisai de Saitemp S.A. marcando  al (604) 4485744, con gusto uno de nuestros facilitadores atenderá su llamada.\n\n simplificando conexiones, facilitando experiencias.";
    //         $body = nl2br($body);
    //         $subject = 'Confirmación de servicio recibido .';
    //         $nomb_membrete = 'Confirmacion';
    //     } elseif ($id == 2 && $formulario->correo_notificacion_empresa != null) {
    //         $body = 'Cordial saludo, hago envío del informe de seleccion.';
    //         $body = nl2br($body);
    //         $subject = 'Informe de seleccion.';
    //         $nomb_membrete = 'Informe de seleccion';
    //     }



    //     $correo = null;
    //     $correo['subject'] =  $subject;
    //     $correo['body'] = $body;
    //     $correo['formulario_ingreso'] = $pdfPath;
    //     $correo['to'] = $combinacion_correos;
    //     $correo['cc'] = '';
    //     $correo['cco'] = '';
    //     $correo['modulo'] = $modulo;
    //     $correo['registro_id'] = $registro_id;
    //     $correo['nom_membrete'] = $nomb_membrete;

    //     $EnvioCorreoController = new EnvioCorreoController();
    //     $request = Request::createFromBase(new Request($correo));
    //     $result = $EnvioCorreoController->sendEmail($request);
    //     return $result;
    // }

    public function gestioningresospdf($modulo = null, $registro_id, $id)
    {

        $formulario = $this->byid($registro_id)->getData();


        $pdf = new TCPDF();
        $pdf->SetTextColor(4, 66, 105);
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);

        $url = public_path('\/upload\/MEMBRETE.png');
        $img_file = $url;
        $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);

        $combinacion_correos = '';

        if ($formulario->correo_laboratorio != null && $id == 3) {
            $combinacion_correos =  $formulario->correo_laboratorio;
        } else {
            $combinacion_correos = $formulario->correo_notificacion_empresa;
        }

        $fecha_ingreso = $formulario->fecha_ingreso;
        $numero_identificacion = $formulario->numero_identificacion;
        $nombre_completo = $formulario->nombre_completo;
        $razon_social = $formulario->razon_social;
        $cargo = $formulario->cargo;
        $salario = $formulario->salario;
        $municipio = $formulario->municipio;
        $numero_contacto = $formulario->numero_contacto;
        $otro_laboratorio = $formulario->laboratorio;
        $examenes = $formulario->examenes;

        $fecha_examenes = $formulario->fecha_examen;
        if (!empty($fecha_examenes)) {
            $timestamp = strtotime($fecha_examenes);
            $fecha_examen = date('d/m/Y', $timestamp);
        } else {
            $fecha_examen = '';
        }


        $departamento = $formulario->departamento;
        $nombre_servicio = $formulario->nombre_servicio;
        $tipo_servicio_id = $formulario->tipo_servicio_id;

        $fecha_citacion_entrevista = $formulario->citacion_entrevista;

        if (!empty($fecha_citacion_entrevista)) {
            $timestamp2 = strtotime($fecha_citacion_entrevista);
            $citacion_entrevista = date('d/m/Y, H:i', $timestamp2);
        } else {
            $citacion_entrevista = '';
        }

        $profesional = $formulario->profesional;
        $informe_seleccion = $formulario->informe_seleccion;
        $direccion_empresa = $formulario->direccion_empresa;
        $direccion_laboratorio = $formulario->direccion_laboratorio;
        $recomendaciones_examen = $formulario->recomendaciones_examen;
        $ancho_maximo = 70;
        $ancho_seleccion = 97;

        if (isset($formulario->laboratorios[0])) {
            $departamento_laboratorio = $formulario->laboratorios[0]->departamento;
            $municipio_laboratorio = $formulario->laboratorios[0]->municipio;
            $laboratorio_medico = $formulario->laboratorios[0]->nombre;
        }


        if ($id == 1  || $id == 3) {

            $pdf->Ln(20);

            $html = '<table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
            <td style="text-align: center;">
            <div style="font-size: 16pt; font-weight: bold;">Orden de servicio:</div>
            </td>
            </tr>
            </table>';

            $pdf->writeHTML($html, true, false, true, false, '');

            if (strlen($razon_social) < 35 && strlen($direccion_empresa) < 35) {

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Dirección empresa:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $razon_social != null ? $razon_social : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(3);

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($nombre_servicio);

                $pdf->MultiCell($ancho_texto + 7, 7, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 'L');
                $pdf->Ln(1);
            } else if (strlen($direccion_empresa) < 35) {

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($razon_social);

                $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(3);
            } else {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($razon_social);
                $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');


                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($direccion_empresa);
                $pdf->MultiCell($ancho_texto + 7, 7, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 'L');


                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($nombre_servicio);
                $pdf->MultiCell($ancho_texto + 7, 7, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 'L');
            }

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Número de identificación:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->ln(10);
            $pdf->SetX(20);
            $ancho_texto = $pdf->GetStringWidth($numero_identificacion);
            $pdf->MultiCell($ancho_texto + 7, 7, $numero_identificacion != null ? $numero_identificacion : 'Sin datos', 0, 'L');


            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Apellidos y Nombres:', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(95, 10, 'Número contacto:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->SetX(20);
            $pdf->Cell(10, 1, $nombre_completo != null ? $nombre_completo : 'Sin datos', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(65, 1, $numero_contacto != null ? $numero_contacto : 'Sin datos', 0, 1, 'L');
            $pdf->Ln(2);


            if (strlen($cargo) < 35) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Salario:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $cargo != null ? $cargo : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $salario != null ? $salario : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(2);
            } else {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);
                $pdf->SetX(20);
                $lineas = explode("\n", wordwrap($cargo != null ? $cargo : 'Sin datos', $ancho_maximo, "\n"));

                foreach ($lineas as $linea) {
                    $ancho_texto = $pdf->GetStringWidth($linea);
                    $pdf->SetX(20);
                    $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                }

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Salario:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($salario);

                $pdf->MultiCell($ancho_texto + 7, 7, $salario != null ? $salario : 'Sin datos', 0, 'L');
            }

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Departamento de prestación de servicios:', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(95, 10, 'Ciudad de prestación de servicios:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->SetX(20);
            $pdf->Cell(10, 1, $departamento, 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(65, 1, $municipio, 0, 1, 'L');
            $pdf->Ln(2);

            if (isset($formulario->laboratorios[0])) {

                if (!empty($formulario->laboratorios && $otro_laboratorio == '')) {

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Departamento ubicación laboratorio médico:', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(95, 10, 'Ciudad ubicación laboratorio médico:', 0, 1, 'L');
                    $pdf->SetFont('helvetica', '', 11);

                    $pdf->SetX(20);
                    $pdf->Cell(10, 1, $departamento_laboratorio != '' ? $departamento_laboratorio : 'Sin datos', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(65, 1, $municipio_laboratorio != '' ? $municipio_laboratorio : 'Sin datos', 0, 1, 'L');
                    $pdf->Ln(2);

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(95, 10, 'Fecha de exámenes:', 0, 1, 'L');
                    $pdf->SetFont('helvetica', '', 11);

                    $pdf->SetX(20);
                    $pdf->Cell(10, 1, $laboratorio_medico != null ? $laboratorio_medico : 'Sin datos', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(65, 1, $fecha_examen != null ? $fecha_examen : 'Sin datos', 0, 1, 'L');
                    $pdf->Ln(2);

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->Ln(10);
                    $pdf->SetX(20);
                    $lineas = explode("\n", wordwrap($direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', $ancho_maximo, "\n"));

                    foreach ($lineas as $linea) {
                        $ancho_texto = $pdf->GetStringWidth($linea);
                        $pdf->SetX(20);
                        $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    }
                }
            }

            if ($otro_laboratorio != '') {

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Laboratorio médico:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Fecha de exámenes:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $otro_laboratorio != null ? $otro_laboratorio : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $fecha_examen != null ? $fecha_examen : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(2);

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Dirección laboratorio:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);
                $pdf->SetX(20);
                $lineas = explode("\n", wordwrap($direccion_laboratorio != null ? $direccion_laboratorio : 'Sin datos', $ancho_maximo, "\n"));

                foreach ($lineas as $linea) {
                    $ancho_texto = $pdf->GetStringWidth($linea);
                    $pdf->SetX(20);
                    $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                }
            }


            if ($tipo_servicio_id == 3 || $tipo_servicio_id == 4) {
                $pdf->AddPage();
                $url = public_path('\/upload\/MEMBRETE.png');
                $img_file = $url;
                $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
                $pdf->Ln(45);
                if (strlen($examenes) < 30 && strlen($recomendaciones_examen) < 30) {
                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 1, 'L');
                    $pdf->SetFont('helvetica', '', 11);

                    $pdf->SetX(20);
                    $pdf->Cell(10, 1, $examenes != null ? $examenes : 'Sin datos', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(65, 1, $recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', 0, 1, 'L');
                    $pdf->Ln(2);
                } else {
                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->Ln(10);
                    $pdf->SetX(20);
                    $lineas = explode("\n", wordwrap($examenes != null ? $examenes : 'Sin datos', $ancho_maximo, "\n"));

                    foreach ($lineas as $linea) {
                        $ancho_texto = $pdf->GetStringWidth($linea);
                        $pdf->SetX(20);
                        $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    }

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->Ln(10);
                    $pdf->SetX(20);
                    $lineas = explode("\n", wordwrap($recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', $ancho_maximo, "\n"));

                    foreach ($lineas as $linea) {
                        $ancho_texto = $pdf->GetStringWidth($linea);
                        $pdf->SetX(20);
                        $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    }
                }
            } else {
                if (strlen($examenes) < 30 && strlen($recomendaciones_examen) < 30 && strlen($direccion_empresa) < 30) {
                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 1, 'L');
                    $pdf->SetFont('helvetica', '', 11);

                    $pdf->SetX(20);
                    $pdf->Cell(10, 1, $examenes != null ? $examenes : 'Sin datos', 0, 0, 'L');

                    $pdf->SetX(120);
                    $pdf->Cell(65, 1, $recomendaciones_examen != null ? $recomendaciones_examen : 'Sin datos', 0, 1, 'L');
                    $pdf->Ln(2);
                } else {
                    $pdf->AddPage();
                    $url = public_path('\/upload\/MEMBRETE.png');
                    $img_file = $url;
                    $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
                    $pdf->Ln(45);

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Exámenes:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->Ln(10);
                    $pdf->SetX(20);
                    $lineas = explode("\n", wordwrap($examenes != null ? $examenes : 'Sin datos', $ancho_maximo, "\n"));

                    foreach ($lineas as $linea) {
                        $ancho_texto = $pdf->GetStringWidth($linea);
                        $pdf->SetX(20);
                        $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    }

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetX(20);
                    $pdf->Cell(95, 10, 'Recomendaciones exámenes:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 11);
                    $pdf->Ln(10);
                    $pdf->SetX(20);
                    $lineas = explode("\n", wordwrap($recomendaciones_examen != null ? $recomendaciones_examen : 'N/A', $ancho_maximo, "\n"));

                    foreach ($lineas as $linea) {
                        $ancho_texto = $pdf->GetStringWidth($linea);
                        $pdf->SetX(20);
                        $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    }
                }
            }
        } else if ($id == 2) {

            $pdf->Ln(20);

            $html = '<table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
            <td style="text-align: center;">
            <div style="font-size: 16pt; font-weight: bold;">Informe de seleccion:</div>
            </td>
            </tr>
            </table>';

            $pdf->writeHTML($html, true, false, true, false, '');

            if (strlen($razon_social) < 35 && strlen($direccion_empresa) < 35) {

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Dirección empresa:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $razon_social != null ? $razon_social : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(3);


                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Citación entrevista:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $citacion_entrevista != null ? $citacion_entrevista : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(3);
            } else {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Empresa usuaria:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($razon_social);
                $pdf->MultiCell($ancho_texto + 7, 7, $razon_social != null ? $razon_social : 'Sin datos', 0, 'L');


                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Dirección empresa:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($direccion_empresa);
                $pdf->MultiCell($ancho_texto + 30, 7, $direccion_empresa != null ? $direccion_empresa : 'Sin datos', 0, 'L');


                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Tipo de servicio:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Citación entrevista:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $nombre_servicio != null ? $nombre_servicio : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $citacion_entrevista != null ? $citacion_entrevista : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(3);
            }

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Número de identificación:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->ln(10);
            $pdf->SetX(20);
            $ancho_texto = $pdf->GetStringWidth($numero_identificacion);
            $pdf->MultiCell($ancho_texto + 7, 7, $numero_identificacion != null ? $numero_identificacion : 'Sin datos', 0, 'L');

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Apellidos y nombres:', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(95, 10, 'Número contacto:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->SetX(20);
            $pdf->Cell(10, 1, $nombre_completo != null ? $nombre_completo : 'Sin datos', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(65, 1, $numero_contacto != null ? $numero_contacto : 'Sin datos', 0, 1, 'L');
            $pdf->Ln(2);

            if (strlen($cargo) < 35) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(95, 10, 'Salario:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->SetX(20);
                $pdf->Cell(10, 1, $cargo != null ? $cargo : 'Sin datos', 0, 0, 'L');

                $pdf->SetX(120);
                $pdf->Cell(65, 1, $salario != null ? $salario : 'Sin datos', 0, 1, 'L');
                $pdf->Ln(2);
            } else {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Cargo:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);
                $pdf->SetX(20);
                $lineas = explode("\n", wordwrap($cargo != null ? $cargo : 'Sin datos', $ancho_maximo, "\n"));

                foreach ($lineas as $linea) {
                    $ancho_texto = $pdf->GetStringWidth($linea);
                    $pdf->SetX(20);
                    $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                }

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Salario:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);
                $ancho_texto = $pdf->GetStringWidth($salario);

                $pdf->MultiCell($ancho_texto + 7, 7, $salario != null ? $salario : 'Sin datos', 0, 'L');
            }

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetX(20);
            $pdf->Cell(95, 10, 'Departamento de prestación de servicios:', 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(95, 10, 'Ciudad de prestación de servicios:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 11);

            $pdf->SetX(20);
            $pdf->Cell(10, 1, $departamento, 0, 0, 'L');

            $pdf->SetX(120);
            $pdf->Cell(65, 1, $municipio, 0, 1, 'L');
            $pdf->Ln(2);

            $cantidad_saltos_linea = substr_count($informe_seleccion, "\n");
            $longitud_informe_ajustada = mb_strlen($informe_seleccion) + ($cantidad_saltos_linea * 90);

            // return $longitud_informe_ajustada;

            // Verificar si la longitud ajustada cumple con la condición
            if ($longitud_informe_ajustada <= 600) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Informe selección:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);
                $pdf->SetX(20);

                $lineas = explode("\n", wordwrap($informe_seleccion != null ? $informe_seleccion : 'Sin datos', $ancho_seleccion, "\n"));

                foreach ($lineas as $linea) {
                    $ancho_texto = $pdf->GetStringWidth($linea);
                    $pdf->SetX(20);
                    $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                }

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX(20);
                $pdf->Cell(95, 10, 'Profesional:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX(20);

                $pdf->MultiCell($ancho_maximo + 7, 7, $profesional != null ? $profesional : 'Sin datos', 0, 'L');
            } else {

                $max_caracteres = 2600;
                $margen_izquierdo = 15;
                $margen_derecho = 15;

                $pdf->SetMargins($margen_izquierdo, 0, $margen_derecho);
                $pdf->SetAutoPageBreak(true, 0);

                $pdf->AddPage();

                $url = public_path('\/upload\/MEMBRETE.png');
                $img_file = $url;
                $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
                $pdf->Ln(40);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX($margen_izquierdo);
                $pdf->Cell(95, 10, 'Informe selección:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(10);

                $caracteres_restantes = $max_caracteres;

                $lineas_restantes = ceil($caracteres_restantes / $ancho_seleccion);

                $lineas2 = explode("\n", wordwrap($informe_seleccion, $ancho_seleccion, "\n"));
                foreach ($lineas2 as $linea) {
                    $pdf->SetX($margen_izquierdo);
                    $ancho_texto = $pdf->GetStringWidth($linea);

                    if ($lineas_restantes <= 0) {
                        $pdf->AddPage();
                        $pdf->Image($img_file, -0.5, 0, $pdf->getPageWidth() + 0.5, $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
                        $pdf->Ln(30);
                        $pdf->SetFont('helvetica', 'B', 11);
                        $pdf->SetX($margen_izquierdo);
                        $pdf->Cell(95, 10, '', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 11);
                        $pdf->Ln(10);
                        $caracteres_restantes = $max_caracteres;
                        $lineas_restantes = ceil($caracteres_restantes / $ancho_seleccion);
                    }

                    $pdf->MultiCell($ancho_texto + 7, 7, $linea, 0, 'L');
                    $caracteres_restantes -= strlen($linea);
                    $lineas_restantes--;
                }

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetX($margen_izquierdo);
                $pdf->Cell(95, 10, 'Profesional:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 11);

                $pdf->Ln(10);
                $pdf->SetX($margen_izquierdo);
                $ancho_texto = $pdf->GetStringWidth($profesional);

                $pdf->MultiCell($ancho_texto + 7, 7, $profesional != null ? $profesional : 'Sin datos', 0, 'L');
            }
        }

        if ($modulo != 'null') {
            $pdfPath = storage_path('app/temp.pdf');
            $pdf->Output($pdfPath, 'F');
        } else {
            $pdf->Output('I');
        }

        $body = '';
        $subject = '';
        $nomb_membrete = '';
        if ($id == 3 && $formulario->correo_laboratorio != null) {
            $body = "Cordial saludo, esperamos se encuentren bien.\n\nAutorizamos exámenes médicos en solicitud de servicio adjunta, cualquier información adicional que se requiera, comunicarse a la línea Servisai de Saitemp S.A. marcando al (604) 4485744, donde con gusto uno de nuestros facilitadores atenderá su llamada.\n\nSimplificando conexiones, facilitando experiencias.";
            $body = nl2br($body);
            $subject = 'Autorización de exámenes.';
            $nomb_membrete = 'Autorizacion';
        } elseif ($id == 1 && $formulario->correo_notificacion_empresa != null) {
            $body = "Cordial saludo, esperamos se encuentren muy bien.\n\n Informamos que su solicitud de servicio ha sido recibida satisfactoriamente, Cualquier información adicional podrá ser atendida en la línea Servisai de Saitemp S.A. marcando  al (604) 4485744, con gusto uno de nuestros facilitadores atenderá su llamada.\n\n simplificando conexiones, facilitando experiencias.";
            $body = nl2br($body);
            $subject = 'Confirmación de servicio recibido .';
            $nomb_membrete = 'Confirmacion';
        } elseif ($id == 2 && $formulario->correo_notificacion_empresa != null) {
            $body = "Cordial saludo, esperamos se encuentren muy bien.\n\n
            De acuerdo a su solicitud me permito enviar el formato para el cargo " . $cargo . "\n\n
            Quedo atenta a sus comentarios; es importante recordar que la retroalimentación debe hacerse de manera escrita a través de correo electrónico y dentro de los tres (3) días hábiles siguientes al envío del formato.\n\n
            SAITEMP S.A. no se hace responsable en caso de que el candidato acuerde o consienta con la empresa usuaria hacer ensayos  en la ejecución real de la labor misional sin que EL CANDIDATO cuente con contrato suscrito con SAITEMP S.A. y con  las respectivas afiliaciones a la seguridad social, en caso de accidente o reclamación como consecuencia de una prueba técnica de conocimiento que implique ejecución  de la labor misional dentro o fuera de las instalaciones de la “Empresa usuaria”, será exclusiva responsabilidad del Candidato las consecuencias  que tal convenio con la empresa usuaria llegaran a presentar.\n\n     
            Agradezco ponerme en copia en caso de enviar autorización de ingreso
            Cordialmente, ". ' ' .$profesional . '.';
            $body = nl2br($body);
            // return $body;
            $subject = 'Comparto Hojas de vida para el Cargo ' . $cargo . '.';
            $nomb_membrete = 'Informe de seleccion';
        }



        $correo = null;
        $correo['subject'] =  $subject;
        $correo['body'] = $body;
        $correo['formulario_ingreso'] = $pdfPath;
        $correo['to'] = $combinacion_correos;
        $correo['cc'] = '';
        $correo['cco'] = '';
        $correo['modulo'] = $modulo;
        $correo['registro_id'] = $registro_id;
        $correo['nom_membrete'] = $nomb_membrete;

        $EnvioCorreoController = new EnvioCorreoController();
        $request = Request::createFromBase(new Request($correo));
        $result = $EnvioCorreoController->sendEmail($request);
        return $result;
    }



    public function filtro($cadena, $cantidad = null)
    {
        if ($cantidad == null) {
            $cantidad = 15;
        }
        $cadenaJSON = base64_decode($cadena);
        $cadenaUTF8 = mb_convert_encoding($cadenaJSON, 'UTF-8', 'ISO-8859-1');
        $arrays = explode('/', $cadenaUTF8);
        $arraysDecodificados = array_map('json_decode', $arrays);

        // return $arraysDecodificados;

        $campo = $arraysDecodificados[0];
        $operador = $arraysDecodificados[1];
        $valor_comparar = $arraysDecodificados[2];
        $valor_comparar2 = $arraysDecodificados[3];

        $query = FormularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->LeftJoin('usr_app_estados_ingreso as est', 'est.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->select(
                'usr_app_formulario_ingreso.id',
                'usr_app_formulario_ingreso.numero_radicado',
                'usr_app_formulario_ingreso.created_at',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'usr_app_formulario_ingreso.cargo',
                'cli.razon_social',
                'mun.nombre as ciudad',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.fecha_examen',
                DB::raw("FORMAT(CAST(usr_app_formulario_ingreso.fecha_ingreso AS DATE), 'dd/MM/yyyy') as fecha_ingreso"),
                'usr_app_formulario_ingreso.estado_vacante',
                'usr_app_formulario_ingreso.novedades',
                'usr_app_formulario_ingreso.observacion_estado',
                'usr_app_formulario_ingreso.profesional',
                'est.nombre as estado_ingreso',
                'usr_app_formulario_ingreso.responsable',
                // 'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'est.id as estado_ingreso_id',
                'est.color as color_estado',

            )
            ->orderBy('usr_app_formulario_ingreso.created_at', 'DESC');
        $numElementos = count($campo);

        for ($i = 0; $i < $numElementos; $i++) {
            $campoActual = $campo[$i];
            $operadorActual = $operador[$i];
            $valorCompararActual = $valor_comparar[$i];

            $prefijoCampo = '';
            if ($campoActual === 'ciudad') {
                $prefijoCampo = 'mun.';
                $campoActual = 'nombre';
            } elseif ($campoActual === 'estado_ingreso') {
                $prefijoCampo = 'est.';
                $campoActual = 'nombre';
            } elseif ($campoActual === 'razon_social') {
                $prefijoCampo = 'cli.';
            } else {
                $prefijoCampo = 'usr_app_formulario_ingreso.';
            }

            switch ($operadorActual) {
                case 'Menor que':
                    $query->where($prefijoCampo . $campoActual, '<', $valorCompararActual);
                    break;
                case 'Mayor que':
                    $query->where($prefijoCampo . $campoActual, '>', $valorCompararActual);
                    break;
                case 'Menor o igual que':
                    $query->where($prefijoCampo . $campoActual, '<=', $valorCompararActual);
                    break;
                case 'Mayor o igual que':
                    $query->where($prefijoCampo . $campoActual, '>=', $valorCompararActual);
                    break;
                case 'Igual a número':
                    $query->where($prefijoCampo . $campoActual, '=', $valorCompararActual);
                    break;
                case 'Entre':
                    $valorComparar2Actual = $valor_comparar2[$i];
                    $query->whereDate($prefijoCampo . $campoActual, '>=', $valorCompararActual);
                    $query->whereDate($prefijoCampo . $campoActual, '<=', $valorComparar2Actual);
                    break;
                case 'Igual a':
                    $query->where($prefijoCampo . $campoActual, '=', $valorCompararActual);
                    break;
                case 'Igual a fecha':
                    // $fechaHora = date('Y-m-d H:i:s', strtotime($valorCompararActual));
                    // $query->whereRaw("TRY_CONVERT(datetime, $prefijoCampo$campoActual) = ?", [$fechaHora]);
                    // break;
                    // if ($prefijoCampo == 'usr_app_formulario_ingreso.created_at') {
                    //     $fechaComparar = trim($valorCompararActual, '"'); // Eliminar las comillas dobles
                    //     $query->whereRaw("TRY_CONVERT(DATE, $prefijoCampo$campoActual, 126) = ?", [$fechaComparar]);
                    // }
                    //    return $prefijoCampo .''. $campoActual. '='. $valorCompararActual;
                    $query->whereDate($prefijoCampo . $campoActual, '=', $valorCompararActual);
                    break;
                case 'Contiene':
                    // return $prefijoCampo . $campoActual . 'LIKE' . '%' . $valorCompararActual . '%';
                    $query->where($prefijoCampo . $campoActual, 'like', '%' . $valorCompararActual . '%');
                    break;
                    // default:
                    //     // Manejar el operador desconocido
                    //     break;
            }
        }

        // Al final, ejecutar la consulta y obtener los resultados
        $resultados = $query->paginate($cantidad); // paginamos los resultados

        foreach ($resultados as $item) {
            $item->fecha_examen = $item->fecha_examen ? date('d/m/Y H:i', strtotime($item->fecha_examen)) : null;
        }

        return $resultados;
    }

    public function buscardocumentoformularioi($cedula)
    {
        $result = formularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->leftJoin('usr_app_departamentos as dep', 'dep.id', 'mun.departamento_id')
            ->leftJoin('usr_app_paises as pais', 'pais.id', 'dep.pais_id')
            ->leftJoin('usr_app_afp as afp', 'afp.id', 'usr_app_formulario_ingreso.afp_id')
            ->leftJoin('usr_app_estados_ingreso as esti', 'esti.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->leftJoin('usr_app_formulario_ingreso_tipo_servicio as tiser', 'tiser.id', 'usr_app_formulario_ingreso.tipo_servicio_id')
            ->leftJoin('gen_tipide as ti', 'ti.cod_tip', '=', 'usr_app_formulario_ingreso.tipo_documento_id')
            ->where('usr_app_formulario_ingreso.numero_identificacion', '=', $cedula)
            ->select(
                'usr_app_formulario_ingreso.id',
                'esti.nombre as estado_ingreso',
                'esti.id as estado_ingreso_id',
                'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'usr_app_formulario_ingreso.fecha_ingreso',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'usr_app_formulario_ingreso.cliente_id',
                'cli.razon_social',
                'usr_app_formulario_ingreso.cargo',
                'usr_app_formulario_ingreso.salario',
                'usr_app_formulario_ingreso.municipio_id',
                'mun.nombre as municipio',
                'usr_app_formulario_ingreso.numero_contacto',
                'usr_app_formulario_ingreso.eps',
                'usr_app_formulario_ingreso.afp_id',
                'afp.nombre as afp',
                'usr_app_formulario_ingreso.estradata',
                'usr_app_formulario_ingreso.novedades',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.examenes',
                'usr_app_formulario_ingreso.fecha_examen',
                'dep.id as departamento_id',
                'dep.nombre as departamento',
                'pais.id as pais_id',
                'pais.nombre as pais',
                'usr_app_formulario_ingreso.created_at as fecha_radicado',
                'tiser.nombre_servicio',
                'tiser.id as tipo_servicio_id',
                'usr_app_formulario_ingreso.numero_vacantes',
                'usr_app_formulario_ingreso.numero_contrataciones',
                'usr_app_formulario_ingreso.citacion_entrevista',
                'usr_app_formulario_ingreso.profesional',
                'usr_app_formulario_ingreso.informe_seleccion',
                'usr_app_formulario_ingreso.cambio_fecha',
                'usr_app_formulario_ingreso.numero_radicado',
                'usr_app_formulario_ingreso.direccion_empresa',
                'usr_app_formulario_ingreso.direccion_laboratorio',
                'usr_app_formulario_ingreso.recomendaciones_examen',
                'usr_app_formulario_ingreso.novedad_stradata',
                'usr_app_formulario_ingreso.correo_notificacion_empresa',
                'usr_app_formulario_ingreso.correo_notificacion_usuario',
                'usr_app_formulario_ingreso.novedades_examenes',
                'ti.des_tip as tipo_identificacion',
                'usr_app_formulario_ingreso.subsidio_transporte',
                'usr_app_formulario_ingreso.estado_vacante',
                'usr_app_formulario_ingreso.responsable_id',
                'ti.cod_tip as tipo_identificacion_id',
                'usr_app_formulario_ingreso.afectacion_servicio',
                'usr_app_formulario_ingreso.observacion_estado',
                'usr_app_formulario_ingreso.correo_laboratorio',
                'usr_app_formulario_ingreso.contacto_empresa',

            )
            ->first();

        $laboratorios = RegistroIngresoLaboratorio::join('usr_app_ciudad_laboraorio as ciulab', 'ciulab.id', '=', 'usr_app_registro_ingreso_laboraorio.laboratorio_medico_id')
            ->join('usr_app_municipios as mun', 'mun.id', '=', 'ciulab.ciudad_id')
            ->join('usr_app_departamentos as dep', 'dep.id', '=', 'mun.departamento_id')
            ->where('usr_app_registro_ingreso_laboraorio.registro_ingreso_id', '=',  $result->id)
            ->select(
                'ciulab.id',
                'ciulab.laboratorio as nombre',
                'mun.id as municipio_id',
                'mun.nombre as municipio',
                'dep.id as departamento_id',
                'dep.nombre as departamento',
            )
            ->get();
        $result['laboratorios'] = $laboratorios;

        $archivos = FormularioIngresoArchivos::join('usr_app_archivos_formulario_ingreso as fi', 'fi.id', '=', 'usr_app_formulario_ingreso_archivos.arhivo_id')
            ->where('ingreso_id',  $result->id)
            ->select(
                'usr_app_formulario_ingreso_archivos.arhivo_id',
                'usr_app_formulario_ingreso_archivos.ruta',
                'usr_app_formulario_ingreso_archivos.observacion',
                'fi.nombre',
                'fi.tipo_archivo'
            )
            ->get();
        $result['archivos'] = $archivos;


        $seguimiento = FormularioIngresoSeguimiento::join('usr_app_estados_ingreso as ei', 'ei.id', '=', 'usr_app_formulario_ingreso_seguimiento.estado_ingreso_id')
            ->where('usr_app_formulario_ingreso_seguimiento.formulario_ingreso_id', $result->id)
            ->select(
                'usr_app_formulario_ingreso_seguimiento.usuario',
                'ei.nombre as estado',
                'usr_app_formulario_ingreso_seguimiento.created_at',
            )
            ->orderby('usr_app_formulario_ingreso_seguimiento.id', 'desc')
            ->get();
        $result['seguimiento'] = $seguimiento;
        return response()->json($result);
    }

    public function buscardocumentolistai($cedula)
    {
        $result = FormularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->LeftJoin('usr_app_estados_ingreso as est', 'est.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->where('usr_app_formulario_ingreso.numero_identificacion', 'like', '%' . $cedula . '%')
            ->select(
                'usr_app_formulario_ingreso.id',
                'usr_app_formulario_ingreso.numero_radicado',
                'usr_app_formulario_ingreso.created_at',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'usr_app_formulario_ingreso.cargo',
                'cli.razon_social',
                'mun.nombre as ciudad',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.fecha_examen',
                DB::raw("FORMAT(CAST(usr_app_formulario_ingreso.fecha_ingreso AS DATE), 'dd/MM/yyyy') as fecha_ingreso"),
                'usr_app_formulario_ingreso.estado_vacante',
                'usr_app_formulario_ingreso.novedades',
                'usr_app_formulario_ingreso.observacion_estado',
                'usr_app_formulario_ingreso.profesional',
                'est.nombre as estado_ingreso',
                'usr_app_formulario_ingreso.responsable',
                // 'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'est.id as estado_ingreso_id',
                'est.color as color_estado',

            )
            ->orderBy('usr_app_formulario_ingreso.created_at', 'DESC')
            ->paginate();
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
            $estado_id = $request->estado_id;
            $user = auth()->user();
            $result = new formularioGestionIngreso;
            $result->fecha_ingreso = $request->fecha_ingreo;
            $result->numero_identificacion = $request->numero_identificacion;
            $result->nombre_completo = $request->nombre_completo;
            $result->cliente_id = $request->empresa_cliente_id;
            $result->cargo = $request->cargo;
            $result->salario = $request->salario;
            $result->municipio_id = $request->municipio_id;
            $result->numero_contacto = $request->numero_contacto;
            $result->eps = $request->eps;
            $result->afp_id = $request->afp_id;
            $result->estradata = $request->consulta_stradata;
            $result->novedades = $request->novedades;
            $result->laboratorio = $request->laboratorio;
            $result->examenes = $request->examenes;
            $result->afectacion_servicio = $request->afectacion_servicio;
            if ($request->fecha_examen != null) {
                // $result->fecha_examen = Carbon::createFromFormat('Y-m-d\TH:i', $request->fecha_examen)->format('Y-m-d H:i:s');
                $result->fecha_examen = $request->fecha_examen;
            }
            if ($request->estado_id == '') {
                $result->estado_ingreso_id = 1;
            } else {
                $result->estado_ingreso_id = $request->estado_id;
            }
            $result->responsable = $user->nombres . ' ' . $user->apellidos;
            $result->tipo_servicio_id = $request->tipo_servicio_id;
            $result->numero_vacantes = $request->numero_vacantes;
            $result->numero_contrataciones = $request->numero_contrataciones;
            if ($request->citacion_entrevista != null) {
                $result->citacion_entrevista = Carbon::createFromFormat('Y-m-d\TH:i', $request->citacion_entrevista)->format('Y-m-d H:i:s');
            }
            $result->profesional = $request->profesional;
            $result->informe_seleccion = $request->informe_seleccion;
            if ($request->cambio_fecha != null) {
                $result->cambio_fecha = $request->cambio_fecha;
            }
            $result->responsable = $request->consulta_encargado;
            $result->novedad_stradata = $request->novedades_stradata;
            $result->correo_notificacion_usuario = $request->correo_candidato;
            $result->correo_notificacion_empresa = $request->correo_empresa;
            $result->direccion_empresa = $request->direccion_empresa;
            $result->direccion_laboratorio = $request->direccion_laboratorio;
            $result->recomendaciones_examen = $request->recomendaciones_examen;
            $result->novedades_examenes = $request->novedades_examenes;
            $result->subsidio_transporte = $request->consulta_subsidio;
            $result->estado_vacante = $request->consulta_vacante;
            $result->tipo_documento_id = $request->tipo_identificacion;
            $result->observacion_estado = $request->consulta_observacion_estado;
            $result->correo_laboratorio = $request->correo_laboratorio;
            $result->contacto_empresa = $request->contacto_empresa;
            $result->responsable_id = $request->encargado_id;

            $result->save();

            $laboratorio = new RegistroIngresoLaboratorio;
            $laboratorio->registro_ingreso_id  = $result->id;
            $laboratorio->laboratorio_medico_id = $request->laboratorio_medico_id;
            $laboratorio->save();

            $seguimiento = new FormularioIngresoSeguimiento;
            $seguimiento->estado_ingreso_id = $request->estado_id;
            $seguimiento->usuario = $user->nombres . ' ' . $user->apellidos;
            $seguimiento->formulario_ingreso_id = $result->id;
            $seguimiento->save();

            $id_ = $result->id;
            if ($result->responsable == null) {
                $this->actualizaestadoingreso($id_, $estado_id);
            }
            DB::commit();
            return response()->json(['status' => '200', 'message' => 'ok', 'registro_ingreso_id' => $result->id]);
        } catch (\Exception $e) {
            // Revertir la transacción si se produce alguna excepción
            DB::rollback();
            return $e;
            return response()->json(['status' => 'error', 'message' => 'Error al guardar formulario, por favor verifique el llenado de todos los campos e intente nuevamente']);
        }
    }

    public function pendientes(Request $request)
    {
        $user = auth()->user();
        $lista = $request->all();
        foreach ($lista as $item) {
            $existeIngreso = FormularioIngresoPendientes::where('registro_ingreso_id', $item)->where('usuario_id', $user->id)->first();

            if (!$existeIngreso) {
                $result = new FormularioIngresoPendientes;
                $result->registro_ingreso_id = $item;
                $result->usuario_id = $user->id;
                $result->save();
            }
        }
        return response()->json(['status' => 'success', 'message' => 'Tareas pendientes agregadas exitosamente.']);
    }

    public function pendientes2($cantidad)
    {

        $user = auth()->user();
        $result = formularioGestionIngreso::leftJoin('usr_app_clientes as cli', 'cli.id', 'usr_app_formulario_ingreso.cliente_id')
            ->leftJoin('usr_app_municipios as mun', 'mun.id', 'usr_app_formulario_ingreso.municipio_id')
            ->LeftJoin('usr_app_estados_ingreso as est', 'est.id', 'usr_app_formulario_ingreso.estado_ingreso_id')
            ->LeftJoin('usr_app_formulario_ingreso_pendientes as pen', 'pen.registro_ingreso_id', 'usr_app_formulario_ingreso.id')
            ->where('pen.usuario_id', '=', $user->id)
            ->select(
                'usr_app_formulario_ingreso.id',
                'usr_app_formulario_ingreso.created_at',
                'usr_app_formulario_ingreso.fecha_ingreso',
                'est.nombre as estado',
                'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'usr_app_formulario_ingreso.numero_identificacion',
                'usr_app_formulario_ingreso.nombre_completo',
                'cli.razon_social',
                'usr_app_formulario_ingreso.cargo',
                'mun.nombre as ciudad',
                'usr_app_formulario_ingreso.laboratorio',
                'usr_app_formulario_ingreso.responsable as responsable_ingreso',
                'est.id as estado_ingreso_id',
                'est.color as color_estado',
            )
            ->orderby('usr_app_formulario_ingreso.id', 'DESC')
            ->paginate($cantidad);
        return response()->json($result);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $ingreso_id)
    {

        try {
            $documentos = $request->all();
            $value = '';
            $id = '';
            $ids = [];
            $observacion = '';
            $observaciones = [];
            $rutas = [];

            // $directorio = public_path('upload/');
            // $archivos = glob($directorio . '*');

            // foreach ($archivos as $archivo) {
            //     $nombreArchivo = basename($archivo);
            //     if (strpos($nombreArchivo, '_' . $ingreso_id . '_') !== false) {
            //         unlink($archivo);
            //     }
            // }

            foreach ($documentos as $item) {
                $contador = 0;
                if (!is_numeric($item) && !is_string($item)) {

                    $microtime = microtime(true);
                    $microtimeString = (string) $microtime;
                    $microtimeWithoutDecimal = str_replace('.', '', $microtimeString);

                    $nombreArchivoOriginal = $item->getClientOriginalName();
                    $nuevoNombre = '_' . $ingreso_id . '_' . $microtimeWithoutDecimal . "_" . $nombreArchivoOriginal;

                    $carpetaDestino = './upload/';
                    $item->move($carpetaDestino, $nuevoNombre);
                    $item = ltrim($carpetaDestino, '.') . $nuevoNombre;
                    array_push($rutas, $item);
                    $value .= $item . ' ';
                } else {
                    if (is_numeric($item)) {
                        array_push($ids, $item);
                        $id .= $item . ' ';
                    } else {
                        array_push($observaciones, $item);
                        $observacion .= $item . ' ';
                    }
                }
                $contador++;
            }

            for ($i = 0; $i < count($ids); $i++) {
                $documento = new FormularioIngresoArchivos;
                $documento->arhivo_id = $ids[$i];
                $documento->ruta = $rutas[$i];
                $documento->observacion = $observaciones[$i];
                $documento->ingreso_id = $ingreso_id;
                $documento->save();
            }
            // return response()->json(['status' => 'success', 'message' => 'Formulario guardado exitosamente']);
            return response()->json(['status' => 'success', 'message' => 'Los archivos adjuntos del formulario fueron actualizados de manera exitosa']);
        } catch (\Exception $e) {
            //throw $th;
            // $cliente = cliente::find($ingreso_id);
            // $cliente->delete();
            return $e;
            return response()->json(['status' => 'error', 'message' => 'Error al guardar el formulario, por favor intente nuevamente, si el problema persiste por favor contacte al administrador del sitio']);
        }
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
            $user = auth()->user();
            $result = formularioGestionIngreso::find($id);

            if ($result->responsable_id != null && $result->responsable_id != $user->id) {

                $seguimiento = new FormularioIngresoSeguimiento;
                $seguimiento->estado_ingreso_id = $request->estado_id;
                $seguimiento->usuario =  $user->nombres . ' ' . $user->apellidos;
                $seguimiento->formulario_ingreso_id = $id;
                $seguimiento->save();
    
                return response()->json(['status' => '200', 'message' => 'ok', 'registro_ingreso_id' => $id]);
                // return response()->json(['status' => 'error', 'message' => 'Solo el responsable puede realizar esta acción.']);
            }

            $result->fecha_ingreso = $request->fecha_ingreo;
            $result->numero_identificacion = $request->numero_identificacion;
            $result->nombre_completo = $request->nombre_completo;
            $result->cliente_id = $request->empresa_cliente_id;
            $result->cargo = $request->cargo;
            $result->salario = $request->salario;
            $result->municipio_id = $request->municipio_id;
            $result->numero_contacto = $request->numero_contacto;
            $result->eps = $request->eps;
            $result->afp_id = $request->afp_id;
            $result->estradata = $request->consulta_stradata;
            $result->novedades = $request->novedades;
            $result->laboratorio = $request->laboratorio;
            $result->examenes = $request->examenes;
            $result->fecha_examen = $request->fecha_examen;
            $result->estado_ingreso_id = 1;
            $result->tipo_servicio_id = $request->tipo_servicio_id;
            $result->numero_vacantes = $request->numero_vacantes;
            $result->numero_contrataciones = $request->numero_contrataciones;
            $result->citacion_entrevista = $request->citacion_entrevista;
            $result->profesional = $request->profesional;
            $result->informe_seleccion = $request->informe_seleccion;
            $result->cambio_fecha = $request->cambio_fecha;
            $result->responsable = $request->consulta_encargado;
            $result->estado_ingreso_id = $request->estado_id;
            $result->novedad_stradata = $request->novedades_stradata;
            $result->correo_notificacion_usuario = $request->correo_candidato;
            $result->correo_notificacion_empresa = $request->correo_empresa;
            $result->direccion_empresa = $request->direccion_empresa;
            $result->direccion_laboratorio = $request->direccion_laboratorio;
            $result->recomendaciones_examen = $request->recomendaciones_examen;
            $result->novedades_examenes = $request->novedades_examenes;
            $result->subsidio_transporte = $request->consulta_subsidio;
            $result->estado_vacante = $request->consulta_vacante;
            $result->afectacion_servicio = $request->afectacion_servicio;
            $result->observacion_estado = $request->consulta_observacion_estado;
            $result->tipo_documento_id = $request->tipo_identificacion;
            $result->correo_laboratorio = $request->correo_laboratorio;
            $result->contacto_empresa = $request->contacto_empresa;
            $result->responsable_id = $request->encargado_id;

            $result->save();

            $seguimiento = new FormularioIngresoSeguimiento;
            $seguimiento->estado_ingreso_id = $request->estado_id;
            $seguimiento->usuario =  $user->nombres . ' ' . $user->apellidos;
            $seguimiento->formulario_ingreso_id = $id;
            $seguimiento->save();

            $laboratorio = RegistroIngresoLaboratorio::where('registro_ingreso_id', $id)->get();

            if ($request->filled('laboratorio_medico_id')) {
                if ($laboratorio->isEmpty()) {
                    $laboratorio = new RegistroIngresoLaboratorio;
                    $laboratorio->registro_ingreso_id = $id;
                    $laboratorio->laboratorio_medico_id = $request->laboratorio_medico_id;
                    $laboratorio->save();
                } else {
                    foreach ($laboratorio as $item) {
                        $item->delete();
                    }
                    $laboratorio = new RegistroIngresoLaboratorio;
                    $laboratorio->registro_ingreso_id = $id;
                    $laboratorio->laboratorio_medico_id = $request->laboratorio_medico_id;
                    $laboratorio->save();
                }
            }

            DB::commit();
            // if ($estado_id != $result->estado_ingreso_id ||  $result->responsable == null) {
            //     $this->actualizaestadoingreso($id, $estado_id);
            // }

            return response()->json(['status' => '200', 'message' => 'ok', 'registro_ingreso_id' => $result->id]);
        } catch (\Exception $e) {
            // Revertir la transacción si se produce alguna excepción
            DB::rollback();
            return $e;
            // return response()->json(['status' => 'error', 'message' => 'Error al guardar formulario, por favor verifique el llenado de todos los campos e intente nuevamente']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminararchivo($item, $id)
    {
        $result = FormularioIngresoArchivos::where('usr_app_formulario_ingreso_archivos.ingreso_id', '=', $item)
            ->where('usr_app_formulario_ingreso_archivos.arhivo_id', '=', $id)
            ->first();
        $registro = FormularioIngresoArchivos::find($result->id);
        if ($registro->delete()) {
            return response()->json(['status' => 'success', 'message' => 'Registro eliminado con Exito']);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Error al eliminar registro']);
        }
    }
    public function destroy($id)
    {
        $result = formularioGestionIngreso::find($id);
        if ($result->delete()) {
            return response()->json(['status' => 'success', 'message' => 'Registro eliminado con Exito']);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Error al eliminar registro']);
        }
    }

    public function borradomasivo(Request $request)
    {
        try {
            $user = auth()->user();
            for ($i = 0; $i < count($request->id); $i++) {
                $result = FormularioIngresoPendientes::where('registro_ingreso_id', '=', $request->id[$i])->where('usuario_id', $user->id)->first();
                // return $result;
                $result->delete();
            }
            return response()->json(['status' => 'success', 'message' => 'Registros eliminados exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar el registro, por favor intente nuevamente']);
        }
    }
}
