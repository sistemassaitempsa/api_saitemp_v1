<?php
try {

    $token='';
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
    $paramsl = [
        'login' => 'saitempsa',
        'password' => '4llcontroL3*'
    ];    
    $client = new SoapClient($wsdl, $options);
    // Llamar al método "ping" del WebService
    $response = $client->__soapCall("Autenticar", [$paramsl]);
    // Mostrar la respuesta
    //var_dump($response);

    if (isset($response->return)) {
        // Decodificar el JSON en la propiedad "return"
        $decodedResponse = json_decode($response->return);

        // Validar si el JSON contiene "data" y "salida"
        if (isset($decodedResponse->data->salida)) {
            $token = $decodedResponse->data->salida;
            //echo "Token extraído: " . $token . PHP_EOL;
        } else {
            echo "No se encontró la propiedad 'salida' en la respuesta decodificada." . PHP_EOL;
           // var_dump($decodedResponse);
        }
    } else {
        echo "La respuesta no contiene la propiedad 'return'." . PHP_EOL;
        var_dump($response);
    }  


} catch (SoapFault $e) {
    // Manejo de errores
    echo "Error: " . $e->getMessage() . PHP_EOL;
}