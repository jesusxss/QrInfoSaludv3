<?php
// Habilitar reporte de errores (solo en desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

include 'db.php';
$errors = [];
$nombre_usuario = '';
$correo = '';
$registro_exitoso = false;

// Cargar variables de entorno desde .env
function getEnvVar($key, $default = null) {
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
    return isset($env[$key]) ? $env[$key] : $default;
}

$ms_auth_port = getEnvVar('MS_AUTH_PORT');
$ms_auth_token = getEnvVar('MS_AUTH_TOKEN');
$ms_auth_domain = getEnvVar('MS_AUTH_DOMAIN');

// Validar que las variables de entorno estén definidas
if (!$ms_auth_port || !$ms_auth_domain) {
    die("Error: Configuración de entorno incompleta para el microservicio de autenticación.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se aceptaron los términos
    if (!isset($_POST['acepto_terminos'])) {
        $errors[] = 'Debe aceptar los términos de protección de datos personales';
    }

    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($nombre_usuario === '' || $correo === '' || $contrasena === '') {
        $errors[] = 'Por favor, complete todos los campos.';
    }

    if (empty($errors)) {
        // Llamada interna al microservicio de registro en Node.js
        $ch = curl_init();
        // Consumir siempre por Apache reverse proxy (puerto 443, sin :$ms_auth_port)
        curl_setopt($ch, CURLOPT_URL, "https://$ms_auth_domain/apk/register");
        curl_setopt($ch, CURLOPT_POST, 1);
        $headers = ['Content-Type: application/json'];
        if ($ms_auth_token) {
            $headers[] = 'x-api-key: ' . $ms_auth_token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'nombre_usuario' => $nombre_usuario,
            'correo' => $correo,
            'contrasena' => $contrasena
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if ($result === false) {
            error_log('CURL ERROR: ' . curl_error($ch));
        }
        curl_close($ch);
        $reg = json_decode($result, true);

        if ($reg && $reg['success']) {
            $registro_exitoso = true;
        } else {
            // Mensaje personalizado para correo ya registrado
            if (isset($reg['message']) && strpos($reg['message'], 'correo electrónico ya está registrado') !== false) {
                $errors[] = 'El correo electrónico ya está registrado. Intente con otro.';
            } else {
                $errors[] = 'Respuesta del microservicio: ' . htmlspecialchars($result);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registro de Usuario</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.4.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #667eea;
      --primary-gradient: linear-gradient(135deg,#667eea,#764ba2);
      --bg-light: #f9fafb;
      --text-color: #111827;
      --card-bg: rgba(255,255,255,0.95);
      --error-color: #dc3545;
      --success-color: #28a745;
    }
    body {
      margin: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-gradient);
      font-family: 'Segoe UI', sans-serif;
      color: var(--text-color);
    }
    .container-mobile {
      width: 100%;
      max-width: 380px;
      margin: 0 1rem;
      background: var(--card-bg);
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      overflow: hidden;
    }
    .card-header-custom {
      background: var(--primary-gradient);
      text-align: center;
      padding: 1rem;
    }
    .card-header-custom i {
      color: #fff;
      font-size: 2.5rem;
    }
    .card-header-custom h2 {
      color: #fff;
      margin: 0.5rem 0 0;
      font-size: 1.25rem;
      font-weight: 600;
    }
    .card-body-custom {
      padding: 1rem;
    }
    .alert-mobile {
      display: none;
    }
    .form-label {
      font-weight: 500;
      margin-bottom: 0.25rem;
      display: block;
    }
    .input-group {
      margin-bottom: 1rem;
    }
    .input-group-text {
      background: transparent;
      border: none;
      color: var(--text-color);
      font-size: 1.25rem;
    }
    .form-control {
      flex: 1;
      height: 3rem;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 1rem;
      padding: 0 0.75rem;
    }
    .btn-action {
      width: 100%;
      height: 3rem;
      background: var(--primary-gradient);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 500;
      transition: opacity 0.3s;
      cursor: pointer;
    }
    .btn-action:hover {
      opacity: 0.9;
    }
    .login-link {
      text-align: center;
      margin-top: 1rem;
      font-size: 0.9rem;
    }
    .login-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
    }
    .login-link a:hover {
      text-decoration: underline;
    }
    
    /* Estilos mejorados para los modales */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0,0,0,0.7);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      overflow-y: auto;
    }
    
    .modal-container {
      background: white;
      border-radius: 8px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      animation: modalFadeIn 0.3s;
      max-height: 90vh;
      overflow-y: auto;
      padding: 1.5rem;
      margin: 0 auto;
      text-align: left;
    }
    
    .modal-header {
      border-bottom: 1px solid #eee;
      padding-bottom: 1rem;
      margin-bottom: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-title {
      color: #333;
      font-size: 1.5rem;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .modal-body {
      max-height: 60vh;
      overflow-y: auto;
      padding: 0.5rem 0;
    }
    
    .modal-body ul {
      padding-left: 1.5rem;
      margin: 1rem 0;
    }
    
    .modal-body li {
      margin-bottom: 0.5rem;
      line-height: 1.5;
    }
    
    .modal-footer {
      border-top: 1px solid #eee;
      padding-top: 1rem;
      margin-top: 1rem;
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }
    
    .modal-btn {
      padding: 0.5rem 1.5rem;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .modal-btn-primary {
      background: var(--primary-gradient);
      color: white;
      border: none;
    }
    
    .modal-btn-secondary {
      background: #f8f9fa;
      border: 1px solid #ddd;
      color: #333;
    }
    
    .modal-icon {
      font-size: 1.5rem;
    }
    
    .modal-icon-error {
      color: var(--error-color);
    }
    
    .terminos-checkbox {
      margin: 1rem 0;
      display: flex;
      align-items: center;
    }
    
    .terminos-checkbox input {
      margin-right: 0.5rem;
    }
    
    .terminos-link {
      color: var(--primary-color);
      cursor: pointer;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }
    
    .terminos-link:hover {
      color: #5a67d8;
    }
    
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 576px) {
      .container-mobile {
        margin: 0 0.5rem;
      }
      
      .card-body-custom,
      .card-header-custom {
        padding: 0.75rem;
      }
      
      .form-control {
        height: 2.5rem;
        font-size: 0.9rem;
      }
      
      .btn-action {
        height: 2.5rem;
        font-size: 0.9rem;
      }
      
      .modal-container {
        padding: 1rem;
        width: 95%;
        max-height: 85vh;
      }
      
      .modal-title {
        font-size: 1.25rem;
      }
      
      .modal-btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <!-- Modal de Términos y Condiciones -->
  <div id="modalTerminos" class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fas fa-shield-alt"></i> Protección de Datos Personales</h3>
      </div>
      <div class="modal-body">
        <p>De acuerdo con la <strong>Ley N° 29733 - Ley de Protección de Datos Personales</strong> del Perú, le informamos que:</p>
        
        <p>Al registrarse en nuestro sistema, usted autoriza el tratamiento de sus datos personales para los siguientes fines:</p>
        
        <ul>
          <li>Identificación y autenticación como usuario del sistema</li>
          <li>Prestación de los servicios ofrecidos</li>
          <li>Comunicaciones relacionadas con el servicio</li>
          <li>Mejora de nuestra plataforma y servicios</li>
        </ul>
        
        <p><strong>Principios que aplicamos:</strong></p>
        
        <ul>
          <li><strong>Licitud</strong>: Sus datos serán tratados conforme a ley</li>
          <li><strong>Consentimiento</strong>: Requerimos su autorización expresa</li>
          <li><strong>Finalidad</strong>: Uso exclusivo para los fines declarados</li>
          <li><strong>Proporcionalidad</strong>: Solo recabamos datos necesarios</li>
          <li><strong>Calidad</strong>: Mantendremos información actualizada</li>
          <li><strong>Seguridad</strong>: Implementamos medidas técnicas de protección</li>
        </ul>
        
        <p><strong>Usted tiene derecho a:</strong></p>
        <ul>
          <li>Solicitar acceso, rectificación o cancelación de sus datos</li>
          <li>Revocar su consentimiento en cualquier momento</li>
          <li>Presentar reclamos ante la Autoridad Nacional de Protección de Datos</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-secondary" onclick="rechazarTerminos()">Rechazar</button>
        <button type="button" class="modal-btn modal-btn-primary" onclick="aceptarTerminos()">Aceptar</button>
      </div>
    </div>
  </div>

  <!-- Modal de Alerta/Error -->
  <div id="modalAlerta" class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fas fa-exclamation-circle modal-icon-error"></i> Advertencia</h3>
      </div>
      <div class="modal-body" id="alertaMensaje">
        <!-- Mensaje de error se insertará aquí -->
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-primary" onclick="cerrarModal('modalAlerta')">Aceptar</button>
      </div>
    </div>
  </div>

  <!-- Modal de Éxito -->
  <div id="modalExito" class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title" style="color: var(--success-color);"><i class="fas fa-check-circle"></i> Registro exitoso</h3>
      </div>
      <div class="modal-body">
        <p>¡Tu registro se realizó correctamente! Serás redirigido al inicio de sesión.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-primary" onclick="redirigirLogin()">Ir al login</button>
      </div>
    </div>
  </div>

  <div class="container-mobile">
    <div class="card-header-custom">
      <i class="fas fa-user-plus"></i>
      <h2>Registro</h2>
    </div>
    <div class="card-body-custom">
      <form method="POST" id="formRegistro" novalidate>
        <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-user"></i></span>
          <input
            type="text"
            id="nombre_usuario"
            name="nombre_usuario"
            class="form-control"
            placeholder="Tu nombre"
            required
            value="<?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <label for="correo" class="form-label">Correo Electrónico</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-envelope"></i></span>
          <input
            type="email"
            id="correo"
            name="correo"
            class="form-control"
            placeholder="usuario@ejemplo.com"
            required
            value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <label for="contrasena" class="form-label">Contraseña</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-lock"></i></span>
          <input
            type="password"
            id="contrasena"
            name="contrasena"
            class="form-control"
            placeholder="********"
            required
          >
        </div>
        
        <div class="terminos-checkbox">
          <input type="checkbox" id="acepto_terminos" name="acepto_terminos" required>
          <label for="acepto_terminos">Acepto los <span class="terminos-link" onclick="mostrarTerminos(); return false;">Términos y Política de Protección de Datos</span></label>
        </div>
        
        <button type="submit" class="btn-action">Registrarse</button>
      </form>

      <div class="login-link">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
      </div>
    </div>
  </div>

  <script>
    // Mostrar modal de éxito y redirigir si el registro fue exitoso
    document.addEventListener('DOMContentLoaded', function() {
      <?php if ($registro_exitoso): ?>
        mostrarModal('modalExito');
        setTimeout(redirigirLogin, 2500); // Redirige automáticamente después de 2.5 segundos
      <?php elseif (!empty($errors)): ?>
        mostrarAlerta(<?= json_encode($errors) ?>);
      <?php else: ?>
        mostrarModal('modalTerminos');
      <?php endif; ?>
    });
    
    function redirigirLogin() {
      window.location.href = "login.php";
    }
    
    // Funciones para manejar modales
    function mostrarModal(id) {
      document.getElementById(id).style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
    
    function cerrarModal(id) {
      document.getElementById(id).style.display = 'none';
      document.body.style.overflow = 'auto';
    }
    
    // Función mejorada para mostrar alertas
    function mostrarAlerta(mensajes) {
      const contenedor = document.getElementById('alertaMensaje');
      contenedor.innerHTML = '';
      
      if (Array.isArray(mensajes)) {
        mensajes.forEach(msg => {
          const p = document.createElement('p');
          p.textContent = msg;
          contenedor.appendChild(p);
        });
      } else {
        contenedor.textContent = mensajes;
      }
      
      mostrarModal('modalAlerta');
      cerrarModal('modalTerminos');
    }
    
    // Funciones específicas para términos
    function mostrarTerminos() {
      mostrarModal('modalTerminos');
      return false;
    }
    
    function aceptarTerminos() {
      document.getElementById('acepto_terminos').checked = true;
      cerrarModal('modalTerminos');
    }
    
    function rechazarTerminos() {
      document.getElementById('acepto_terminos').checked = false;
      cerrarModal('modalTerminos');
      mostrarAlerta('Debe aceptar los términos para registrarse');
    }
    
    // Validación del formulario
    document.getElementById('formRegistro').addEventListener('submit', function(e) {
      const errores = [];
      
      // Validar términos
      if (!document.getElementById('acepto_terminos').checked) {
        errores.push('Debe aceptar los términos de protección de datos personales');
      }
      
      // Validar campos requeridos
      const camposRequeridos = [
        {id: 'nombre_usuario', nombre: 'Nombre de Usuario'},
        {id: 'correo', nombre: 'Correo Electrónico'}, 
        {id: 'contrasena', nombre: 'Contraseña'}
      ];
      
      camposRequeridos.forEach(function(campo) {
        const elemento = document.getElementById(campo.id);
        if (!elemento.value.trim()) {
          errores.push(`El campo ${campo.nombre} es obligatorio.`);
          elemento.classList.add('is-invalid');
        } else {
          elemento.classList.remove('is-invalid');
        }
      });
      
      // Si hay errores, mostrar alerta y prevenir envío
      if (errores.length > 0) {
        e.preventDefault();
        mostrarAlerta(errores);
      }
    });
  </script>
</body>
</html>