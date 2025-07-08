<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Leer JSON enviado
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['image'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$image = $data['image'];

// Extraer base64 después de la coma
if (preg_match('/^data:image\/png;base64,(.*)$/', $image, $matches)) {
    $base64_str = $matches[1];
    $image_data = base64_decode($base64_str);

    // Generar nombre único para el archivo
    $filename = 'qr_' . uniqid() . '.png';

    // Carpeta donde se guardarán las imágenes (debe existir y tener permisos)
    $folder = __DIR__ . '/saved_qr/';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $filepath = $folder . $filename;

    // Guardar archivo
    if (file_put_contents($filepath, $image_data) !== false) {
        // URL para descargar (ajusta si usas ruta distinta o virtual host)
        $url = 'saved_qr/' . $filename;

        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'url' => $url
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Error guardando archivo']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Formato de imagen no válido']);
    exit;
}
