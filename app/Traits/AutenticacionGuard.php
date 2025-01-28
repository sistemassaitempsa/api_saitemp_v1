<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UsuarioDebidaDiligencia;

trait AutenticacionGuard
{
    public function getGuard()
    {
        $guards = ['api', 'usuariosdebidadiligencia']; // Lista de tus guards

        foreach ($guards as $guard) {
            $user = auth()->guard($guard)->user();
            if ($user) {
                return ["user" => $user, "guard" => $guard];
            }
        }
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
        if ($user['guard'] == 'api') {
            $result = user::join("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios.rol_id")
                ->join("usr_app_estados_usuario", "usr_app_estados_usuario.id", "=", "usr_app_usuarios.estado_id")
                ->where('usr_app_usuarios.id', '=', $user['user']->id)
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
        } else if ($user['guard'] == 'usuariosdebidadiligencia') {
            $result = UsuarioDebidaDiligencia::join("usr_app_roles as rol", "rol.id", "=", "usr_app_usuario_debida_diligencia.rol_id")
            ->join("usr_app_estados_usuario as est", "est.id", "=", "usr_app_usuario_debida_diligencia.estado_id")
            ->where('usr_app_usuario_debida_diligencia.id', $user['user']->id)->select(
                'usr_app_usuario_debida_diligencia.id',
                'usr_app_usuario_debida_diligencia.razon_social',
                'usr_app_usuario_debida_diligencia.nit',
                'usr_app_usuario_debida_diligencia.email',
                'usr_app_usuario_debida_diligencia.usuario',
                'usr_app_usuario_debida_diligencia.nombre_contacto',
                'usr_app_usuario_debida_diligencia.telefono_contacto',
                'usr_app_usuario_debida_diligencia.cargo_contacto',
                'rol.nombre as rol',
                'rol.id as rol_id',
                'est.nombre as estado',
                'est.id as estado_id',
            )->first();
            return response()->json($result);
        }
    }
}
