<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UsuarioDebidaDiligencia;
use App\Models\UsuariosCandidatosModel;
use App\Models\UsuariosInternosModel;

trait AutenticacionGuard
{
    public function getGuard($id = null)
    {
        if ($id) {
            $user = User::find($id);
            return ["user" => $user];
        }

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

    public function getUserRelaciones($id = null)
    {
        if ($id) {
            $user = $this->getGuard($id);
        } else {
            $user = $this->getGuard();
        }
        if ($user['user']->tipo_usuario_id == "1") {
            $result = UsuariosInternosModel::join("usr_app_roles as rol", "rol.id", "=", "usr_app_usuarios_internos.rol_usuario_interno_id")
                ->join("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_usuarios_internos.usuario_id")
                ->join("usr_app_estados_usuario as estado", "estado.id", "=", "usr_app_usuarios.estado_id")
                ->where('usr_app_usuarios.id', '=', $user['user']->id)
                ->select(
                    'usr_app_usuarios_internos.id as usuario_id',
                    "usr_app_usuarios_internos.nombres",
                    "usr_app_usuarios_internos.apellidos",
                    "usr_app_usuarios_internos.documento_identidad",
                    "usr_app_usuarios_internos.correo",
                    "rol.nombre as rol",
                    "rol.id as rol_id",
                    "estado.nombre as estado",
                    "estado.id as estado_id",
                    "usr_app_usuarios_internos.vendedor_id",
                    'usr_app_usuarios.email',
                    'usr_app_usuarios.tipo_usuario_id',
                    'usr_app_usuarios.id',
                )
                ->first();
            return response()->json($result);
        } else if ($user['user']->tipo_usuario_id == "2") {
            $result = user::join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
                ->join("usr_app_estados_usuario as est ", "est .id", "=", "usr_app_usuarios.estado_id")
                ->join("usr_app_usuarios_clientes as uc ", "uc.usuario_id", "=", "usr_app_usuarios.id")
                ->where('usr_app_usuarios.id', '=', $user['user']->id)
                ->select(
                    "usr_app_roles.nombre as rol",
                    "usr_app_roles.id as rol_id",
                    "uc.usuario_id",
                    "uc.email",
                    "uc.razon_social as nombres",
                    "uc.nit",
                    'uc.nombre_contacto',
                    'uc.telefono_contacto',
                    'uc.cargo_contacto',
                    "est.id as estado_id",
                    "est.nombre as estado",
                    'usr_app_usuarios.tipo_usuario_id',
                )->first();
            return response()->json($result);
        } else if ($user['user']->tipo_usuario_id == "3") {
            $result = UsuariosCandidatosModel::leftjoin("gen_tipide", "gen_tipide.cod_tip", "=", "usr_app_candidatos_c.tip_doc_id")
                ->leftjoin("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_candidatos_c.usuario_id")
                ->leftjoin("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
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

    public function listaUsuarios($cantidad, $tipo_usaurio)
    {
        if ($tipo_usaurio == "1") {
            $result = UsuariosInternosModel::join("usr_app_roles as rol", "rol.id", "=", "usr_app_usuarios_internos.rol_usuario_id")
                ->join("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_usuarios_internos.usuario_id")
                ->join("usr_app_estados_usuario as estado", "estado.id", "=", "usr_app_usuarios.estado_id")
                ->select(
                    'usr_app_usuarios_internos.id as usuario_id',
                    'usr_app_usuarios.tipo_usuario_id',
                    "usr_app_usuarios_internos.nombres",
                    "usr_app_usuarios_internos.apellidos",
                    "usr_app_usuarios_internos.documento_identidad",
                    "usr_app_usuarios_internos.correo",
                    "rol.nombre as rol",
                    "rol.id as rol_id",
                    "estado.nombre as estado",
                    "estado.id as estado_id",
                    "usr_app_usuarios_internos.vendedor_id",
                    'usr_app_usuarios.email',
                    'usr_app_usuarios.id'
                )
                ->paginate($cantidad);
            return response()->json($result);
        } else if ($tipo_usaurio == "2") {
            $result = user::join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
                ->join("usr_app_estados_usuario as est ", "est .id", "=", "usr_app_usuarios.estado_id")
                ->join("usr_app_usuarios_clientes as uc ", "uc.usuario_id", "=", "usr_app_usuarios.id")
                ->select(
                    "usr_app_roles.nombre as rol",
                    "uc.usuario_id",
                    "uc.email",
                    "uc.razon_social as nombres",
                    "uc.nit",
                    "est.id as estado_id",
                    "est.nombre as estado",
                    'usr_app_usuarios.tipo_usuario_id',
                )->paginate($cantidad);
            return response()->json($result);
        } else if ($tipo_usaurio == "3") {
            $result = UsuariosCandidatosModel::join("gen_tipide", "gen_tipide.cod_tip", "=", "usr_app_candidatos_c.tip_doc_id")
                ->join("usr_app_usuarios", "usr_app_usuarios.id", "=", "usr_app_candidatos_c.usuario_id")
                ->join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
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
                )->paginate($cantidad);
            return response()->json($result);
        }
    }
}