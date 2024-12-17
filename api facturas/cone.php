

<?php
try {
    // Parámetros de conexión a la base de datos
    $serverName = "srv-saitemp03\SQL19"; // Cambia esto por tu servidor
    $database = "Saitemp_V3";
    $username = "UsuarioNovaSQL";
    $password = "P@ssw0rd";

    // Conectar a la base de datos usando PDO
    $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


} catch (PDOException $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage() . PHP_EOL;
} catch (SoapFault $e) {
    echo "Error en el WebService: " . $e->getMessage() . PHP_EOL;
}