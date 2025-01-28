<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\UsuariosCandidatosModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Http\Controllers\EnvioCorreoController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthCandidatosController extends Controller
{

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        /* if (!$token = auth('external_ppl')->attempt([
            'correo' => $request->correo,
            'password' => $request->password,
        ])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales inválidas'
            ], 401);
        } */
        /*   $user = UsuariosCandidatosModel::where('email', $request->email)->first();
        Auth::guard('external_ppl')->login($user);
        $credentials = $request->only('email', 'password'); */
        if (!$token = Auth::guard('external_ppl')->attempt([
            'email' => $request->email,
            'password' => $request->password,
        ])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales inválidas'
            ], 401);
        }
        return response()->json([
            'status'       => 'success',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'user_type' => '2',
            'expires_in'   => auth('external_ppl')->factory()->getTTL() / 60 / 60 / 8,
        ], 200);
    }


    public function createUserCandidato(Request $request)
    {
        $existingUser = UsuariosCandidatosModel::where('numero_documento', $request->numero_documento)
            ->where('doc_tip_id', $request->doc_tip_id)
            ->first();

        if ($existingUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya existe un usuario registrado con este número de documento'
            ], 422);
        }
        $existingUser2 = UsuariosCandidatosModel::where('email', $request->email)->first();

        if ($existingUser2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya existe un usuario registrado con este correo electrónico'
            ], 422);
        }

        $user = new UsuariosCandidatosModel;
        $user->nombre = $request->nombre;
        $user->apellidos = $request->apellidos;
        $user->email = $request->correo;
        $user->password = bcrypt($request->password);
        $user->numero_documento = $request->numero_documento;
        $user->doc_tip_id = $request->doc_tip_id;
        $user->rol_id = $request->rol_id == '' ? 3 : $request->rol_id;
        $user->telefono = $request->telefono;


        if ($user->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro guardado exitosamente']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Ha ocurrido un error al guardar los datos de usuario']);
        }
    }

    public function mostrarUsuarios()
    {
        $users = UsuariosCandidatosModel::get();
        return response()->json($users);
    }

    public function enviarTokenRecuperacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|exists:usr_app_usuarios_candidatos,correo',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = UsuariosCandidatosModel::where('correo', $request->correo)->first();

        // Generar token
        $token = Str::random(60);

        $resetUrl = url("/reset-password?token=$token&email={$user->correo}");
        $subject = 'Cambio de contraseña';
        $nomb_membrete = 'Informe de servicio';

        $body = "Hemos recibido una solicitud para el reestablecimiento de su contraseña\n\n clic aqui para continuar con el proceso $resetUrl, Cualquier información adicional podrá ser 
        atendida en la línea Servisai de Saitemp S.A. marcando  al (604) 4485744, con gusto uno de nuestros facilitadores atenderá 
        su llamada.\n\n simplificando conexiones, facilitando experiencias.";


        // Datos del correo
        $correo = [
            'subject' => $subject,
            'body' => $body,
            'to' => $request->correo,
            'cc' => '',
        ];

        $user->token_recuperacion = $token;
        $user->save();
        $EnvioCorreoController = new EnvioCorreoController();
        $requestEmail = Request::createFromBase(new Request($correo));


        return $EnvioCorreoController->sendEmailExternal($requestEmail);
    }

    public function recuperarContraseña(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'correo'      => 'required|email|exists:usr_app_usuarios_candidatos,correo',
            'password'  => 'required|string|min:6|confirmed',
            'token'       => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $user = UsuariosCandidatosModel::where('correo', $request->correo)
            ->where('token_recuperacion', $request->token)
            ->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Enlace inválido o expirado.'], 400);
        }

        // Verificar caducidad (1 hora)
        $tokenLifetime = 10; // En minutos
        if (Carbon::parse($user->updated_at)->addMinutes($tokenLifetime)->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'El enlace ha expirado.'], 400);
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->password);
        $user->token_recuperacion = null; // Eliminar el token
        $user->updated_at = now();
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'La contraseña se ha restablecido correctamente.']);
    }

    public function  updateCandidatoUser(Request $request)
    {
        try {
            $user = UsuariosCandidatosModel::find($request->id);
            $user->nombre = $request->nombre;
            $user->apellidos = $request->apellidos;
            $user->correo = $request->correo;
            $user->rol_id = $request->rol_id;
            $user->telefono = $request->telefono;
            if ($request->password != null || $request->password != "") {
                $user->password = app('hash')->make($request->password);
            }

            if ($user->save()) {
                return response()->json(['status' => 'success', 'message' => 'Usuario actualizado exitosamente']);
            }
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function userloguedCandidato()
    {


        $id = auth('external_ppl')->id();



        $users = UsuariosCandidatosModel::leftjoin("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios_candidatos.rol_id")
            /*  ->leftjoin("usr_app_estados_usuario", "usr_app_estados_usuario.id", "=", "usr_app_usuarios.estado_id") */
            ->where('usr_app_usuarios_candidatos.id', '=', $id)
            ->select(
                "usr_app_roles.nombre as rol",
                "usr_app_usuarios_candidatos.nombre as nombres",
                "usr_app_usuarios_candidatos.apellidos",
                "usr_app_usuarios_candidatos.numero_documento as documento_identidad",
                "usr_app_usuarios_candidatos.email",
                "usr_app_roles.id",
                'usr_app_usuarios_candidatos.id as usuario_id',

            )
            ->get();

        if (count($users) == 0) {
            $users = UsuariosCandidatosModel::leftjoin("usr_app_roles", "usr_app_roles.id", "=", "usr_app_usuarios_candidatos.rol_id")
                /*  ->join("usr_app_estados_usuario", "usr_app_estados_usuario.id", "=", "usr_app_usuarios_candidatos.estado_id") */
                ->where('usr_app_usuarios_candidatos.id', '=', $id)
                ->select(
                    "usr_app_roles.nombre as rol",
                    "usr_app_usuarios_candidatos.nombre as nombres",
                    "usr_app_usuarios_candidatos.apellidos",
                    "usr_app_usuarios_candidatos.numero_documento as documento_identidad",
                    "usr_app_usuarios_candidatos.email",
                    "usr_app_roles.id",
                    'usr_app_usuarios_candidatos.id as usuario_id',

                )
                ->get();
            return response()->json($users);
        } else {
            return response()->json($users);
        }
    }
}