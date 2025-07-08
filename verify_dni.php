<?php
// verify_dni.php

header('Content-Type: application/json');

$dni = $_POST['dni'] ?? '';

if (!$dni || strlen($dni) !== 8 || !ctype_digit($dni)) {
    echo json_encode(['success' => false, 'error' => 'DNI inválido']);
    exit;
}

$token = 'apis-token-16515.cmct3FMmw1UKJGh8iHHFJGfVZR2jMQYJ'; // Reemplaza con tu token válido

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.apis.net.pe/v2/reniec/dni?numero=' . $dni,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Referer: https://apis.net.pe/consulta-dni-api',
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(['success' => false, 'error' => 'Error en conexión a API']);
    exit;
}

$data = json_decode($response, true);

if (isset($data['nombres'])) {
    echo json_encode([
        'success' => true,
        'nombre_completo' => trim($data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'])
    ]);
} elseif (isset($data['error'])) {
    echo json_encode(['success' => false, 'error' => $data['error']]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se encontró información para este DNI']);
}
