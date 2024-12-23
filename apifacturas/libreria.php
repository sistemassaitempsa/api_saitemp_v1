<?php


date_default_timezone_set('America/Bogota');
ini_set('memory_limit', '512M');
try {
    include('cone.php');
    include('autenticar.php');
    try {
        $fechaHoyStart = date('Ymd') . ' 00:00:00';
        $fechaHoyEnd = date('Ymd') . ' 23:59:59';
        $stmt2 = $conn->prepare("SELECT num_doc,sub_tip FROM inv_cabdoc WHERE sub_tip IN ('01080','010') AND fecha BETWEEN :fechaHoyStart AND :fechaHoyEnd AND cliente NOT IN ('0')");


        // Asignar los parámetros de la nueva consulta
        $stmt2->bindParam(':fechaHoyStart', $fechaHoyStart, PDO::PARAM_STR);
        $stmt2->bindParam(':fechaHoyEnd', $fechaHoyEnd, PDO::PARAM_STR);

        // Ejecutar la nueva consulta
        $stmt2->execute();
        $resultados = $stmt2->fetchAll(PDO::FETCH_ASSOC);




        if (isset($token)) {
            foreach ($resultados as $factura) {
                $fecha = $fechaHoyStart;
                $fechaf = $fechaHoyEnd;
                $sub_tip = $factura['sub_tip'];
                $num_doc = $factura['num_doc'];


                // Preparar el procedimiento almacenado con parámetros
                $stmt = $conn->prepare("EXEC usr_sp_factESfacturaXML @fecha = :fecha, @fechaf = :fechaf, @sub_tip = :sub_tip, @num_doc = :num_doc");

                $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
                $stmt->bindParam(':fechaf', $fechaf, PDO::PARAM_STR);
                $stmt->bindParam(':sub_tip', $sub_tip, PDO::PARAM_STR);
                $stmt->bindParam(':num_doc', $num_doc, PDO::PARAM_STR);

                $stmt->execute();
                // Obtener el resultado del procedimiento almacenado
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $xml = $result['resultado']; // Aseg��rate de usar el alias correcto
                $base64XML = base64_encode($xml);
                // Ajusta si el procedimiento devuelve un campo diferente

                // Codificar el XML en base64
                $base64XML = base64_encode($xml);
                // URL del WSDL
                $wsdl = "https://habilitacion-factible.avance.org.co/FactibleWebService/FacturacionWebService?wsdl";
                // Endpoint adicional personalizado
                $customEndpoint = "https://habilitacion-factible.avance.org.co/FactibleWebService/FacturacionWebService";
                // Crear un cliente SOAP con el WSDL y especificar un endpoint personalizado
                $options = [
                    'location' => $customEndpoint, // Endpoint adicional
                    'uri' => 'http://webservice.facturacion.com/', // Namespace según tu WSDL
                    //'trace' => 1, // Habilita el seguimiento para depuración
                    //'exceptions' => true, // Habilita excepciones
                ];
                $params = [
                    'token' => $token,
                    'base64XML' => $base64XML,
                    'obtenerDatosTecnicos' => true
                ];
                $client = new SoapClient($wsdl, $options);
                // Llamar al método "ping" del WebService
                $response = $client->__soapCall("registrarDocumentoElectronico_Generar_FuenteXML", [$params]);
                // Mostrar la respuesta

                $data = json_decode($response->return);
                $success = $data->success == true ? 1 : 0;
                $msg = $data->msg;
                $date = date("Y-m-d H:i:s");
                var_dump($success);

                $stmtInsert = $conn->prepare("INSERT INTO usr_estadoenvio (num_doc, estado, descrip,fecha_reg) 
                VALUES (:num_doc, :estado, :descrip,:fecha_reg)");
                $stmtInsert->bindParam(':num_doc', $num_doc, PDO::PARAM_STR);
                $stmtInsert->bindValue(':estado', $success, PDO::PARAM_INT);
                $stmtInsert->bindParam(':descrip', $msg, PDO::PARAM_STR);
                $stmtInsert->bindParam(':fecha_reg', $date, PDO::PARAM_STR);
                $stmtInsert->execute();

                // Mostrar el resultado
                echo $msg . " | num_doc: " . $num_doc . PHP_EOL;

                // Manejar la respuesta
                if (isset($response->data->idDocumentoElectronico)) {
                    echo "Documento registrado con éxito. ID: " . $response->data->idDocumentoElectronico . PHP_EOL;
                } else {
                    echo "Error al registrar el documento." . PHP_EOL;
                    var_dump($response);
                }
            }
        }
    } catch (SoapFault $e) {
        // Manejo de errores
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
} catch (SoapFault $e) {
    // Manejo de errores
    echo "Error: " . $e->getMessage() . PHP_EOL;
}