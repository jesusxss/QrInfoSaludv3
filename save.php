<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // ruta para log de errores
ob_start();

session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

require 'vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$user_id = $_SESSION['user_id'];

// Recoger y limpiar datos
$dni = trim($_POST['dni'] ?? '');
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$foto = $_FILES['foto'] ?? null;
$enfermedad_actual = trim($_POST['enfermedad_actual'] ?? '');
$alergias = trim($_POST['alergias'] ?? '');
$seguro_medico = $_POST['seguro_medico'] ?? '';
$otro_seguro = trim($_POST['otro_seguro'] ?? '');
$tipo_sangre = $_POST['tipo_sangre'] ?? '';
$donador_org = $_POST['donador_org'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
$contactos = $_POST['contacto_emergencia'] ?? [];
$parentescos = $_POST['parentesco_emergencia'] ?? [];
$intervenciones_quirurgicas = trim($_POST['intervenciones_quirurgicas'] ?? '');
$direccion_actual = trim($_POST['direccion_actual'] ?? '');

$response = ['success' => false, 'message' => ''];

// Validaciones básicas
if (in_array('', [$dni, $nombre_completo, $seguro_medico, $tipo_sangre, $donador_org, $fecha_nacimiento], true)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios.']);
    exit();
}

if (!is_array($contactos) || !is_array($parentescos) || count($contactos) === 0 || count($contactos) !== count($parentescos)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Complete correctamente los contactos y parentescos.']);
    exit();
}

if ($seguro_medico === 'Otros' && empty($otro_seguro)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Especifique el nombre del seguro médico']);
    exit();
}

if (!$foto || $foto['error'] !== UPLOAD_ERR_OK) {
    error_log('Error upload: ' . print_r($foto, true));
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'La foto es obligatoria']);
    exit();
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($foto['type'], $allowed_types)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Formato de imagen no válido (solo JPEG, PNG o GIF)']);
    exit();
}

// Procesar la foto
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
$foto_ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
$foto_name = uniqid('img_', true) . '.' . $foto_ext;
$foto_path = $upload_dir . $foto_name;

if (!move_uploaded_file($foto['tmp_name'], $foto_path)) {
    error_log('move_uploaded_file failed. TMP: ' . $foto['tmp_name'] . ' DEST: ' . $foto_path . ' | FILES: ' . print_r($_FILES, true));
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error al subir la imagen']);
    exit();
}

if ($seguro_medico === 'Otros') {
    $seguro_medico = $otro_seguro;
}

try {
    $conn->begin_transaction();

    // Insertar persona
    $stmt = $conn->prepare("INSERT INTO personas 
        (dni, nombre_completo, foto, enfermedad_actual, alergias, seguro_medico, tipo_sangre, donador_org, fecha_nacimiento, intervenciones_quirurgicas, direccion_actual) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss",
        $dni, $nombre_completo, $foto_path, $enfermedad_actual, $alergias,
        $seguro_medico, $tipo_sangre, $donador_org, $fecha_nacimiento,
        $intervenciones_quirurgicas, $direccion_actual
    );
    if (!$stmt->execute()) {
        throw new Exception("Error al guardar persona: " . $stmt->error);
    }
    $stmt->close();

    // Borrar contactos previos para el dni (si existen)
    $del = $conn->prepare("DELETE FROM contactos_emergencia WHERE dni_persona = ?");
    $del->bind_param("s", $dni);
    $del->execute();
    $del->close();

    // Insertar nuevos contactos
    $stmtContacto = $conn->prepare("INSERT INTO contactos_emergencia (dni_persona, contacto, parentesco) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($contactos); $i++) {
        $contacto = trim($contactos[$i]);
        $parentesco = trim($parentescos[$i]);
        if ($contacto === '' || $parentesco === '') continue;
        $stmtContacto->bind_param("sss", $dni, $contacto, $parentesco);
        if (!$stmtContacto->execute()) {
            throw new Exception("Error al guardar contacto: " . $stmtContacto->error);
        }
    }
    $stmtContacto->close();

    // Generar QR
    $qr_dir = 'qrcodes/';
    if (!file_exists($qr_dir)) mkdir($qr_dir, 0777, true);
    $url = "https://www.qrinfosalud.xyz/apk/view.php?dni=$dni";
    $qr = QrCode::create($url);
    $writer = new PngWriter();
    $qr_path = $qr_dir . $dni . '.png';
    $writer->write($qr)->saveToFile($qr_path);

    // Guardar QR en la tabla qr_codes
    $stmt2 = $conn->prepare("INSERT INTO qr_codes (user_id, dni, qr_path) VALUES (?, ?, ?)");
    $stmt2->bind_param("iss", $user_id, $dni, $qr_path);
    if (!$stmt2->execute()) {
        throw new Exception("Error al guardar QR: " . $stmt2->error);
    }
    $stmt2->close();

    $conn->commit();

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Registro completado exitosamente', 'redirect' => 'dashboard.php']);

} catch (Exception $e) {
    $conn->rollback();
    if (isset($foto_path) && file_exists($foto_path)) unlink($foto_path);
    if (isset($qr_path) && file_exists($qr_path)) unlink($qr_path);

    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el sistema: ' . $e->getMessage()]);
}
