<?php

namespace App\Http\Controllers;

use App\Http\Controllers\formularioDebidaDiligenciaController;
use Illuminate\Http\Request;

class enviarCorreoDDController extends Controller
{
    public function enviarCorreosDD(Request $request, $registro_id, $modulo)
    {
        $formularioDebidaDiligenciaController = new formularioDebidaDiligenciaController;
        $formulario = $formularioDebidaDiligenciaController->getbyid($registro_id)->getData();
        $user = auth()->user();
        foreach ($request->correos as $correoData) {
            try {
                if ($correoData['correo'] != "" && $correoData['correo'] != "null") {

                    $resultCorreo = $this->enviarCorreo($correoData['correo'], $formulario,  $registro_id, $modulo, $correoData['observacion'], $user->usuario, $correoData['corregir']);
                }
            } catch (\Exception $th) {
                return response()->json(['status' => 'error', 'message' => 'No fue posible enviar el registro verifique el correo de contacto o de los responsables']);
            }
        }
    }
    private function enviarCorreo($destinatario, $formulario,  $registro_id, $modulo, $observacion = '', $user, $booleanCorregir)
    {


        $numeroRadicado = $formulario->numero_radicado;




        if ($booleanCorregir == true) {
            $body = "Cordial saludo, tiene nuevas tareas pendientes por corregir asignados en el radicado Debida Diligencia número: <b><i>$numeroRadicado</i></b> adjunto con las siguientes observaciones: $observacion.
        \n\n Atentamente:";
        } else {
            $body = "Cordial saludo, tiene nuevas tareas asignadas en el radicado Debida Diligencia número: <b><i>$numeroRadicado</i></b> adjunto con las siguientes observaciones: $observacion.
            \n\n Atentamente:";
        }

        $body = nl2br($body);
        $subject = 'Confirmación registro de servicio.';
        $nomb_membrete = 'Informe de servicio';

        // Datos del correo
        $correo = [
            'subject' => $subject,
            'body' => $body,
            'formulario_ingreso' => "",
            'to' => $destinatario,
            'cc' => '',
            'cco' => $user,
            'modulo' => $modulo,
            'registro_id' => $registro_id,
            'nom_membrete' => $nomb_membrete
        ];

        // Instanciar el controlador de envío de correo
        $EnvioCorreoController = new EnvioCorreoController();
        $requestEmail = Request::createFromBase(new Request($correo));

        // Enviar el correo
        return $EnvioCorreoController->sendEmail($requestEmail);
    }
}
