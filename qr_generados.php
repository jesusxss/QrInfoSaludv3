<?php
session_start();
include 'db.php';  // Conexión a la base de datos

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM qr_codes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis QRs Generados</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
    :root {
      --primary-gradient: linear-gradient(135deg,#667eea,#764ba2);
      --bg-light: #f9fafb;
      --text-color: #1f2937;
    }
    .container-mobile {
      box-sizing: border-box;
      width: 100%;
      max-width: 400px;
      margin: 0 auto;
      padding: 1rem;
      background: var(--bg-light);
    }
    .card-custom {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .card-header-custom {
      background: var(--primary-gradient);
      text-align: center;
      padding: 1rem;
    }
    .card-header-custom i {
      color: #fff;
      font-size: 1.75rem;
    }
    .card-header-custom h5 {
      color: #fff;
      margin: 0.5rem 0 0;
      font-size: 1.25rem;
    }
    .row-cols-mobile {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    .card-mobile {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .card-mobile img {
      width: 100%;
      height: auto;
      object-fit: cover;
      cursor: pointer;
    }
    .card-body-mobile {
      padding: 0.75rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .card-text-mobile {
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
      color: var(--text-color);
      width: 100%;
    }
    .btn-mobile {
      flex: 0 0 calc(50% - 0.5rem);
      box-sizing: border-box;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 2.5rem;
      padding: 0.4rem;
      font-size: 0.8rem;
      background: var(--primary-gradient);
      color: #fff;
      border: none;
      border-radius: 6px;
      transition: opacity 0.3s;
      cursor: pointer;
    }
    .btn-mobile:hover {
      opacity: 0.9;
    }
    
    /* Estilos para el modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        z-index: 1000;
        text-align: center;
    }
    
    .modal-content {
        margin: auto;
        display: block;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        position: relative;
        top: 50%;
        transform: translateY(-50%);
        animation: zoom 0.3s;
    }
    
    .close-modal {
        position: absolute;
        top: 20px;
        right: 35px;
        color: white;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
    }
    
    .close-modal:hover {
        color: #ccc;
    }
    
    @keyframes zoom {
        from {transform: translateY(-50%) scale(0.9);}
        to {transform: translateY(-50%) scale(1);}
    }
    
    @media (max-width: 400px) {
      .container-mobile,
      .card-header-custom,
      .card-body-mobile {
        padding: 0.75rem;
      }
      .btn-mobile {
        flex: 0 0 100%;
        height: 2.25rem;
        font-size: 0.75rem;
        padding: 0.3rem;
      }
    }
    </style>
</head>
<body>
    <div class="container-mobile">
      <div class="card-custom">
        <div class="card-header-custom">
          <i class="fas fa-file-alt"></i>
          <h5>Mis QRs Generados</h5>
        </div>
      </div>

      <div class="row-cols-mobile">
        <?php while ($row = $result->fetch_assoc()):
          $dni = htmlspecialchars($row['dni']);
          $qr  = htmlspecialchars($row['qr_path']);
        ?>
          <div class="card-mobile">
            <img src="<?= $qr ?>" alt="QR de <?= $dni ?>" class="qr-image" onclick="openModal('<?= $qr ?>')">
            <div class="card-body-mobile">
              <p class="card-text-mobile"><strong>DNI:</strong> <?= $dni ?></p>
             <!--compartir qr antiguo <a href="whatsapp://send?text=<?= urlencode('Hola, si me ocurriese algún accidente me puedes ayudar escaneando este código QR: https://www.qrinfosalud.xyz/apk/qrcodes/' . basename($qr)) ?>" class="btn-mobile">
                Editar Datos-->
              </a>
              <button type="button" class="btn-mobile" onclick="openModal('<?= $qr ?>')">
                Agrandar QR
              </button>
              <a href="<?= $qr ?>" download="qr_<?= $dni ?>.png" class="btn-mobile">Descargar QR</a>
                            <a href="whatsapp://send?text=<?= urlencode('Hola, si me ocurriese algún accidente me puedes ayudar escaneando este código QR: https://www.qrinfosalud.xyz/apk/qrcodes/' . basename($qr)) ?>" class="btn-mobile">
                Compartir WhatsApp
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Modal para mostrar el QR grande -->
    <div id="qrModal" class="modal">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <img id="modalQrImage" class="modal-content">
    </div>

    <script>
    // Función para abrir el modal con el QR
    function openModal(qrSrc) {
        const modal = document.getElementById('qrModal');
        const modalImg = document.getElementById('modalQrImage');
        
        modal.style.display = "block";
        modalImg.src = qrSrc;
        modalImg.alt = "Código QR ampliado";
        
        // Deshabilitar scroll del body cuando el modal está abierto
        document.body.style.overflow = "hidden";
    }
    
    // Función para cerrar el modal
    function closeModal() {
        const modal = document.getElementById('qrModal');
        modal.style.display = "none";
        document.body.style.overflow = "auto"; // Restaurar scroll
    }
    
    // Cerrar modal al hacer click fuera de la imagen
    window.onclick = function(event) {
        const modal = document.getElementById('qrModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Cerrar modal con tecla ESC
    document.onkeydown = function(evt) {
        evt = evt || window.event;
        if (evt.key === "Escape") {
            closeModal();
        }
    };
    </script>
</body>
</html>