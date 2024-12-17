<?php
try {

    include('cone.php');
    include('autenticar.php');

    try {

    if (isset($token))
    {
        $fecha = '20241101 00:00:00';
        $fechaf = '20241230 23:59:59';
        $sub_tip = '010';
        $num_doc = 'BTAE24987';

        // Preparar el procedimiento almacenado con parámetros
        $stmt = $conn->prepare("EXEC usr_sp_factESfacturaXML @fecha = :fecha, @fechaf = :fechaf, @sub_tip = :sub_tip, @num_doc = :num_doc");

        $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindParam(':fechaf', $fechaf, PDO::PARAM_STR);
        $stmt->bindParam(':sub_tip', $sub_tip, PDO::PARAM_STR);
        $stmt->bindParam(':num_doc', $num_doc, PDO::PARAM_STR);

        $stmt->execute();
        // Obtener el resultado del procedimiento almacenado
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $xml = $result[var_dump($result)]; // Ajusta si el procedimiento devuelve un campo diferente

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
        ];    
        $client = new SoapClient($wsdl, $options);
        // Llamar al método "ping" del WebService
        $response = $client->__soapCall("registrarDocumentoElectronico_Generar_FuenteXML", [$params]);
        // Mostrar la respuesta
        //var_dump($response);
    
        // Manejar la respuesta
        if (isset($response->data->idDocumentoElectronico)) {
            echo "Documento registrado con éxito. ID: " . $response->data->idDocumentoElectronico . PHP_EOL;
        } else {
            echo "Error al registrar el documento." . PHP_EOL;
            var_dump($response);
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
