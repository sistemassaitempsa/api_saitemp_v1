<?php

namespace App\Http\Controllers;

use App\Models\SigContratoEmpleado;
use App\Models\SigEmpleados;
use App\Models\SigUsuarioContrato;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Validator;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        if ($request->password == '') {
            return response()->json(['status' => 'error', 'message' => 'Por favor ingrese una contraseña correcta']);
        }
        if (str_contains($request->email, '@')) {
            $user = explode('@', $request->email)[0];
        } else {
            $user = $request->email;
        }

        $ldapconn = ldap_connect('saitempsa.local');
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        try {
            if ($ldapconn) {
                try {
                    $ldapbind = ldap_bind($ldapconn, $user . '@saitempsa.local',  $request->password);
                    if ($ldapbind) {
                        ldap_close($ldapconn);
                        $user = User::where('email', $request->email)->first();
                        $uuid = $this->guadarMarcaTemporal($user->email);
                        if ($user) {
                            Auth::guard('no-password-validation')->login($user);
                            $token = JWTAuth::fromUser($user);
                            return response()->json([
                                'access_token' => $token,
                                'token_type' => 'bearer',
                                'expires_in' => auth()->factory()->getTTL() * 60 * 60 * 8,
                                'marca' => $uuid
                            ]);
                        }

                        return response()->json(['error' => 'Unauthenticated.'], 401);
                    }
                } catch (\Exception $e) {
                    ldap_close($ldapconn);
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|email',
                        'password' => 'required|string|min:6',
                    ]);

                    if ($validator->fails()) {
                        return response()->json($validator->errors(), 422);
                    }

                    if (!$token = auth()->attempt($validator->validated())) {
                        return response()->json(['status' => 'error', 'message' => 'Por favor verifique sus datos de inicio de sesión e intente nuevamente']);
                    }

                    $uuid = $this->guadarMarcaTemporal($request->email);
                    $token_marca = $this->createNewToken($token, $uuid);
                    // $token_marca['marca'] = $uuid;
                    return $token_marca;
                }
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'No se pudo establecer la conexión con el servidor LDAP.']);
        }


        // $validator = Validator::make($request->all(), [
        //     'email' => 'required|email',
        //     'password' => 'required|string|min:6',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 422);
        // }

        // if (!$token = auth()->attempt($validator->validated())) {
        //     return response()->json(['status' => 'error', 'message'=>'Por favor verifique sus datos de inicio de sesión e intente nuevamente']);
        // }

        // return $this->createNewToken($token);
    }

    public function guadarMarcaTemporal($email)
    {
        $user = User::where('email', $email)->first();
        $uuid = (string) Str::orderedUuid();
        $user->marca_temporal =  $uuid;
        if ($user->save()) {
            return $user->marca_temporal;
        }
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     // 'nombres' => 'string|between:2,100',
        //     // 'apellidos' => 'string|between:2,100',
        //     // 'documento_identidad' => 'string|between:2,100',
        //     // 'email' => 'required|string|max:100',
        //     // 'password' => 'string|min:6',
        //     // 'rol_id' => 'required',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors()->toJson(), 400);
        // }

        // $user = User::create(array_merge(
        //     $validator->validated(),
        //     ['password' => bcrypt($request->password)]
        // ));

        $user = new User;
        $user->nombres = $request->nombres;
        $user->apellidos = $request->apellidos;
        $user->documento_identidad = $request->documento_identidad;
        $user->usuario = $request->usuario;
        $user->contrasena_correo = Crypt::encryptString($request->contrasena_correo);
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->rol_id = $request->rol_id == '' ? 3 : $request->rol_id;


        $archivos = $request->files->all();
        $contador = 1;
        foreach ($archivos as $archivo) {
            if ($contador <= 2) {

                $nombreArchivoOriginal = ($archivo)->getClientOriginalName();
                $nuevoNombre = Carbon::now()->timestamp . "_" . $nombreArchivoOriginal;

                $carpetaDestino = './upload/';
                ($archivo)->move($carpetaDestino, $nuevoNombre);
                $user->{'imagen_firma_' . $contador} = ltrim($carpetaDestino, '.') . $nuevoNombre;
                $contador++;
            }
        }

        if ($user->save()) {
            return response()->json(['status' => 'success', 'message' => 'Registro guardado exitosamente']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Ha ocurrido un error al guardar los datos de usuario']);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile()
    {
        return response()->json(auth()->user());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token, $uuid = null)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60 * 60 * 8,
            'marca' => $uuid
            // 'user' => auth()->user()
        ]);
    }
}
