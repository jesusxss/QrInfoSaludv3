<?php
include 'db.php';

if (isset($_GET['id'])) {
    $qr_id = $_GET['id'];

    // Obtener la ruta del QR correspondiente
    $stmt = $conn->prepare("SELECT qr_path FROM qr_codes WHERE id = ?");
    $stmt->bind_param("i", $qr_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $qr_data = $result->fetch_assoc();
        echo $qr_data['qr_path'];  // Devolver la ruta del QR
    } else {
        echo "QR no encontrado.";
    }
}
?>
