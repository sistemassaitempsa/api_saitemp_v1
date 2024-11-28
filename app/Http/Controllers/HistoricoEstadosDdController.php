<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientesSeguimientoEstado;


class HistoricoEstadosDdController extends Controller
{
    public function index($cantidad)
    {
        // Consulta total de registros oportunos, no oportunos y pendientes a nivel global
        $totalRegistros = ClientesSeguimientoEstado::count();
        $oportunosGlobal = ClientesSeguimientoEstado::where('oportuno', '1')->count();
        $noOportunosGlobal = ClientesSeguimientoEstado::where('oportuno', '0')->count();
        $pendientesGlobal = $totalRegistros - ($oportunosGlobal + $noOportunosGlobal);
        $porcentajeOportunoGlobal = $totalRegistros > 0 ? round(($oportunosGlobal / $totalRegistros) * 100, 2) : 0;
        $porcentajeNoOportunoGlobal = $totalRegistros > 0 ? round(($noOportunosGlobal / $totalRegistros) * 100, 2) : 0;
        $porcentajePendientesGlobal = round(100 - $porcentajeOportunoGlobal - $porcentajeNoOportunoGlobal, 2);

        $estados = ClientesSeguimientoEstado::leftJoin(
            'usr_app_clientes as cliente',
            'cliente.id',
            '=',
            'usr_app_clientes_seguimiento_estado.cliente_id'
        )->leftJoin(
            'usr_app_estados_firma as estado',
            'estado.id',
            '=',
            'usr_app_clientes_seguimiento_estado.estados_firma_inicial'
        )->select(
            'usr_app_clientes_seguimiento_estado.id',
            'usr_app_clientes_seguimiento_estado.responsable_inicial',
            'usr_app_clientes_seguimiento_estado.responsable_final',
            'usr_app_clientes_seguimiento_estado.estados_firma_inicial',
            'usr_app_clientes_seguimiento_estado.estados_firma_final',
            'usr_app_clientes_seguimiento_estado.actualiza_registro',
            'usr_app_clientes_seguimiento_estado.cliente_id',
            'usr_app_clientes_seguimiento_estado.created_at',
            'usr_app_clientes_seguimiento_estado.updated_at',
            'usr_app_clientes_seguimiento_estado.oportuno',
            'usr_app_clientes_seguimiento_estado.inactivo',
            'cliente.numero_radicado as radicado',
            'estado.nombre as nombre_estado'
        )
            ->orderby('usr_app_clientes_seguimiento_estado.cliente_id', 'DESC')
            ->paginate($cantidad);


        $estados->getCollection()->transform(function ($item) {
            $created = \Carbon\Carbon::parse($item->created_at);
            $updated = \Carbon\Carbon::parse($item->updated_at);

            $dif_seconds = $created->diffInMilliseconds($updated);
            if ($dif_seconds != 0) {
                $item->tiempo = $created->diffInMinutes($updated);
            } else {
                $item->tiempo = "Estado pendiente";
            }
            return $item;
        });

        $response = $estados->toArray();

        $response['porcentaje_oportuno'] = $porcentajeOportunoGlobal;
        $response['porcentaje_no_oportuno'] = $porcentajeNoOportunoGlobal;
        $response['porcentaje_pendientes'] = $porcentajePendientesGlobal;

        return response()->json($response);
    }
}