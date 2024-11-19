<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use App\Models\cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    public function uploadFileValidarT(Request $request, $id)
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
                    $cliente = Cliente::where('usr_app_clientes.id', '=', $id)
                        ->select(
                            'usr_app_clientes.id',
                            'usr_app_clientes.contrato_firma_id',
                            'usr_app_clientes.numero_radicado',
                            DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
                        )
                        ->first();
                    $cliente->contrato_firma_id = $response->json()['id'];
                    $cliente->save();
                    return [
                        'status' => 'success',
                        'response' => $response->json(),
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

    public function firmaEstandar(Request $request, $id)
    {
        $url_validart = Config::get('app.VALIDART_URL');
        $end_point = '/api/Transaccion/create';
        $firmantes = $request['firmantes'];
        $cliente = Cliente::where('usr_app_clientes.id', '=', $id)
            ->select(
                'usr_app_clientes.id',
                'usr_app_clientes.contrato_firma_id',
                'usr_app_clientes.numero_radicado',
                'usr_app_clientes.transaccion_id',
                DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
            )
            ->first();
        if ($cliente['contrato_firma_id'] != "" && $cliente['contrato_firma_id'] != null) {
            $datos = [
                "documentoid" => $cliente['contrato_firma_id'],
                "Modificar" => false,
                "Firmantes" => $firmantes,
                "NombreCreador" => "Saitemp S.A",
                "Notificacion" => "5",
                "Callback" => "https://prueba.c.com",
                "DiasVence" => "30",
                "FirmaGrafica" => "0"
            ];
            try {
                $takenToken = $this->takeTokenValidart();
                if ($takenToken['status'] == 'success') {
                    $token = $takenToken['token']['token'];
                    $response = Http::withHeaders(['Authorization' => 'Bearer ' . $token])->post($url_validart . $end_point, $datos);
                    if ($response->successful()) {
                        $cliente->transaccion_id = $response->json()['id'];
                        $cliente->save();
                        return [
                            'status' => 'success',
                            'token' => $response->json(),
                            'cliente' => $cliente
                        ];
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => $response->json()
                        ], $response->status());
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $takenToken['message'] ?? 'No se pudo obtener el token'
                    ]);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }
    }
    public function callBackFirmado(Request $request)
    {
        $id = $request['id'];
        $cliente = Cliente::where('usr_app_clientes.transaccion_id', '=', $id)
            ->select(
                'usr_app_clientes.id',
                'usr_app_clientes.contrato_firma_id',
                'usr_app_clientes.numero_radicado',
                'usr_app_clientes.transaccion_id',
                DB::raw('COALESCE(CONVERT(VARCHAR, usr_app_clientes.numero_radicado), CONVERT(VARCHAR, usr_app_clientes.id)) AS numero_radicado'),
                'usr_app_clientes.ruta_contrato',
            )
            ->first();
        if (!$cliente) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cliente no encontrado para la transacción especificada',
            ], 404);
        }

        $estado = $request['estado'];
        if ($estado === "true") {
            $url = $request['url'];

            try {
                // Descargar archivo desde la URL
                $response = Http::get($url);
                if ($response->successful()) {
                    $fileContent = $response->body();
                    $fileName = $id . '_evidencia.pdf';
                    $filePath = public_path('upload/contratosFirmados/' . $fileName);
                    if (!file_exists(public_path('upload/contratosFirmados'))) {
                        mkdir(public_path('upload/contratosFirmados'), 0755, true);
                    }
                    file_put_contents($filePath, $fileContent);
                    $relativePath = 'upload/contratosFirmados/' . $fileName;
                    $cliente->ruta_contrato = $relativePath;
                    $cliente->save();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Archivo descargado y guardado exitosamente',
                        'file_path' => asset($relativePath),
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pudo descargar el archivo desde la URL proporcionada',
                    ], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'El estado no permite procesar la solicitud',
            ], 400);
        }
    }
    public function reenvioFirmantes($id)
    {
        $url_validart = Config::get('app.VALIDART_URL');
        $end_point = '/api/Transaccion/reenvio/' . $id;
        $takenToken = $this->takeTokenValidart();

        if ($takenToken['status'] == 'success') {
            $token = $takenToken['token']['token'];
            try {
                $response = Http::withHeaders(['Authorization' => 'Bearer ' . $token])->get($url_validart . $end_point);
                if ($response->successful()) {
                    return [
                        'status' => 'success',
                        'response' => $response->json(),
                    ];
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $response->json()
                    ], $response->status());
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $takenToken['message'] ?? 'No se pudo obtener el token'
            ]);
        }
    }
    public function anularContrato(Request $request, $id)
    {
        $url_validart = Config::get('app.VALIDART_URL');
        $end_point = '/api/Transaccion/transaccionanular/anular';
        $takenToken = $this->takeTokenValidart();
        $motivo = $request['motivo'];
        $datos = [
            'Id' => $id,
            'Motivo' => $motivo
        ];
        if ($takenToken['status'] == 'success') {
            $token = $takenToken['token']['token'];
            try {
                $response = Http::withHeaders(['Authorization' => 'Bearer ' . $token])->post($url_validart . $end_point, $datos);
                if ($response->successful()) {
                    return [
                        'status' => 'success',
                        'response' => $response->json(),

                    ];
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $response->json()
                    ], $response->status());
                }
            } catch (\Exception $e) {
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $takenToken['message'] ?? 'No se pudo obtener el token'
            ]);
        }
    }
}
