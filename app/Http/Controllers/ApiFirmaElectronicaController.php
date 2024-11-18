<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;


class ApiFirmaElectronicaController extends Controller

{


    public function takeTokenValidart()
    {
        $usuario = Config::get('app.USUARIO_VALIDART');
        $password =  Config::get('app.PASSWORD_VALIDART');
        $url_validart = Config::get('app.VALIDART_URL');
        $datos = [
            "username" => $usuario,
            "password" => $password
        ];
        try {

            $response = Http::post($url_validart . '/api/users/authenticate', $datos);
            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'token' => $response->json()
                ];
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function uploadFileValidarT(Request $request)
    {
        $url_validart = Config::get('app.VALIDART_URL');
        $end_point = '/api/Transaccion/upload/doc';
        $takenToken = $this->takeTokenValidart();

        if ($takenToken['status'] == 'success') {
            $token = $takenToken['token']['token'];
            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Archivo no encontrado en la solicitud.'
                ], 400);
            }
            $file = $request->file('file');
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token
                ])
                    ->asMultipart()
                    ->post($url_validart . $end_point, [
                        'file' => fopen($file->getPathname(), 'r'),
                        'filename' => $file->getClientOriginalName(),
                    ]);

                if ($response->successful()) {
                    return [
                        'status' => 'success',
                        'response' => $response->json()
                    ];
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $response->body()
                    ], $response->status());
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $takenToken['message'] ?? 'No se pudo obtener el token'
            ]);
        }
    }

    public function firmaEstandar(Request $request)
    {
        $url_validart = Config::get('app.VALIDART_URL');
        $end_point = '/api/Transaccion/create';
        $docId = $this->uploadFileValidarT($request);
        if ($docId['status'] == 'success') {
            $takenDocId = $docId['response']['id'];
        }
    }
}