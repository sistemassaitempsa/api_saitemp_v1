<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UsuarioDebidaDiligencia;
use App\Models\UsuariosCandidatosModel;

trait AutenticacionGuard
{
    public function getGuard()
    {
        $user = auth()->user();
        return ["user" => $user];
    }


    public function getUserId()
    {
        $user = $this->getGuard();
        return $user['user']->id;
    }

    public function getUser()
    {
        $user = $this->getGuard();
        return $user['user'];
    }

    public function getUserRelaciones()
    {
        $user = $this->getGuard();
        if ($user['user']->tipo_usuario_id == "1") {
            $result = user::join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
                ->join("usr_app_estados_usuario", "usr_app_estados_usuario.id", "=", "usr_app_usuarios.estado_id")
                ->where('usr_app_usuarios.login_usuario_id', '=', $user['user']->id)
                ->select(
                    'usr_app_usuarios.id as usuario_id',
                    "usr_app_usuarios.nombres",
                    "usr_app_usuarios.apellidos",
                    "usr_app_usuarios.documento_identidad",
                    "usr_app_usuarios.usuario",
                    "usr_app_usuarios.email",
                    "usr_app_roles.nombre as rol",
                    "usr_app_roles.id as rol_id",
                    "usr_app_estados_usuario.nombre as estado",
                    "usr_app_estados_usuario.id as estado_id",
                    "usr_app_usuarios.vendedor_id",
                )
                ->get();
            return response()->json($result);
        } else if ($user['user']->tipo_usuario_id == "2") {
            $result = UsuarioDebidaDiligencia::where('usr_app_usuarios_clientes.login_usuario_id', $user['user']->id)->select(
                'usr_app_usuarios_clientes.id',
                'usr_app_usuarios_clientes.razon_social',
                'usr_app_usuarios_clientes.nit',
                'usr_app_usuarios_clientes.email',
                'usr_app_usuarios_clientes.nombre_contacto',
                'usr_app_usuarios_clientes.telefono_contacto',
                'usr_app_usuarios_clientes.cargo_contacto',
            )->first();
            return response()->json($result);
        } else if ($user['user']->tipo_usuario_id == "3") {
            $result = UsuariosCandidatosModel::join("gen_tipide", "gen_tipide.cod_tip", "=", "usr_app_candidatos_c.tip_doc_id")
                ->join("usr_app_login_usuarios", "usr_app_login_usuarios.id", "=", "usr_app_candidatos_c.login_usuario_id")
                ->join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_login_usuarios.rol_id")
                ->where('usr_app_candidatos_c.login_usuario_id', $user['user']->id)
                ->select(
                    'usr_app_candidatos_c.primer_nombre',
                    'usr_app_candidatos_c.primer_apellido',
                    'usr_app_candidatos_c.num_doc',
                    'usr_app_candidatos_c.celular',
                    'gen_tipide.des_tip as tip_doc',
                    'gen_tipide.cod_tip as tip_doc_id',
                    'usr_app_roles.id as rol_id',
                    'usr_app_roles.nombre as rol',
                    'usr_app_login_usuarios.estado_id',
                    'usr_app_login_usuarios.email',
                    'usr_app_login_usuarios.tipo_usuario_id',
                    'usr_app_login_usuarios.id'

                )->first();
            return response()->json($result);
        }
    }
}
