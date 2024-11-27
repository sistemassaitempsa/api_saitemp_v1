<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientesSeguimientoEstado;


class HistoricoEstadosDdController extends Controller
{
    public function index($cantidad)
    {
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
                return $item;
            } else {
                $item->tiempo = "Estado pendiente";
                return $item;
            }
        });

        $totalRegistros = $estados->total();
        $oportunos = $estados->getCollection()->where('oportuno', "1")->count();
        $noOportunos = $estados->getCollection()->where('oportuno', "0")->count();
        $porcentajeOportuno = $totalRegistros > 0 ? round(($oportunos / $totalRegistros) * 100, 2) : 0;
        $porcentajeNoOportunos = $totalRegistros > 0 ? round(($noOportunos / $totalRegistros) * 100, 2) : 0;
        $porcentajePendientes = round(100 - $porcentajeOportuno - $porcentajeNoOportunos, 2);
        $response = $estados->toArray();
        $response['porcentaje_no_oportuno'] = $porcentajeNoOportunos;
        $response['porcentaje_oportuno'] = $porcentajeOportuno;
        $response['porcentaje_pendientes'] = $porcentajePendientes;
        return response()->json($response);
    }
}
