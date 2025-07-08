<?php
$host = "qrinfosalud.mysql.database.azure.com";
$user = "jesusxss";
$password = "JexusKuOixlSpV5";
$dbname = "apk";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// ← Aquí forzamos UTF-8 multibyte
if (! $conn->set_charset('utf8mb4')) {
    die("Error al establecer charset: " . $conn->error);
}
?>
	