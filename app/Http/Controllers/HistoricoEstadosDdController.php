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
            'cliente.numero_radicado as radicado'
        )
            ->orderby('usr_app_clientes_seguimiento_estado.cliente_id', 'DESC')
            ->paginate($cantidad);
        return response()->json($estados);
    }
}
