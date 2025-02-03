<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UsuarioDebidaDiligencia;
use App\Models\UsuariosCandidatosModel;
use App\Models\UsuariosInternosModel;

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
            $result = UsuariosInternosModel::join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios_internos.rol_usuario_id")
                ->join("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_usuarios_internos.usuario_id")
                ->where('usr_app_usuarios.id', '=', $user['user']->id)
                ->select(
                    'usr_app_usuarios_internos.id as usuario_id',
                    "usr_app_usuarios_internos.nombres",
                    "usr_app_usuarios_internos.apellidos",
                    "usr_app_usuarios_internos.documento_identidad",
                    "usr_app_usuarios_internos.correo",
                    "usr_app_roles.nombre as rol",
                    "usr_app_roles.id as rol_id",
                    "usr_app_usuarios_internos.vendedor_id",
                    'usr_app_usuarios.email',
                    'usr_app_usuarios.tipo_usuario_id',
                    'usr_app_usuarios.id'
                )
                ->first();
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
                ->join("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_candidatos_c.usuario_id")
                ->join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_login_usuarios.rol_id")
                ->where('usr_app_candidatos_c.usuario_id', $user['user']->id)
                ->select(
                    'usr_app_candidatos_c.primer_nombre',
                    'usr_app_candidatos_c.primer_apellido',
                    'usr_app_candidatos_c.num_doc',
                    'usr_app_candidatos_c.celular',
                    'gen_tipide.des_tip as tip_doc',
                    'gen_tipide.cod_tip as tip_doc_id',
                    'usr_app_roles.id as rol_id',
                    'usr_app_roles.nombre as rol',
                    'usr_app_usuarios.estado_id',
                    'usr_app_usuarios.email',
                    'usr_app_usuarios.tipo_usuario_id',
                    'usr_app_usuarios.id'
                )->first();
            return response()->json($result);
        }
    }
}