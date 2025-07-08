<?php
// Cargar variables de entorno
function getEnvVar($key) {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $env[trim($k)] = trim($v);
                }
            }
        }
    }
    return $env[$key] ?? null;
}

$ms_dni_port = getEnvVar('MS_DNI_PORT');
$ms_dni_token = getEnvVar('MS_DNI_TOKEN');
$ms_dni_domain = getEnvVar('MS_DNI_DOMAIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$ms_dni_domain:$ms_dni_port/verificar-dni");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['dni' => $dni]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-key: ' . $ms_dni_token]);
    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);

    // Solo devuelve lo necesario al frontend
    if ($data && $data['success'] && isset($data['data']['nombre_completo'])) {
        echo json_encode(['success' => true, 'nombre_completo' => $data['data']['nombre_completo']]);
    } else {
        echo json_encode(['success' => false, 'nombre_completo' => '']);
    }
    exit;
}
echo json_encode(['success' => false, 'nombre_completo' => '']);
