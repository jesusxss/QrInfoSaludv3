<?php
session_start();
include 'db.php';

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

$dni_param = $_GET['dni'] ?? '';

// Usar variable de entorno para el dominio y puerto del microservicio (sin valores por defecto)
$ms_dni_port = getEnvVar('MS_DNI_PORT');
$ms_dni_token = getEnvVar('MS_DNI_TOKEN');
$ms_dni_domain = getEnvVar('MS_DNI_DOMAIN');

// Validar que las variables de entorno estén definidas
if (!$ms_dni_port || !$ms_dni_domain) {
    die("Error: Configuración de entorno incompleta para el microservicio de DNI.");
}

function verifyDniWithMicroservice($dni) {
    global $ms_dni_token, $ms_dni_domain;
    $ch = curl_init();
    // Consumir siempre por Apache reverse proxy (https, sin puerto)
    curl_setopt($ch, CURLOPT_URL, "https://$ms_dni_domain/apk/verificar-dni");
    curl_setopt($ch, CURLOPT_POST, 1);
    $headers = ['Content-Type: application/json'];
    if ($ms_dni_token) {
        $headers[] = 'x-api-key: ' . $ms_dni_token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['dni' => $dni]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
        error_log('CURL ERROR: ' . curl_error($ch));
    }
    curl_close($ch);
    $data = json_decode($result, true);

    if (!$data) return ['success' => false, 'error' => "Respuesta inválida del microservicio"];
    if (isset($data['success']) && $data['success'] && isset($data['data']['nombre_completo'])) {
        return ['success' => true, 'nombre_completo' => $data['data']['nombre_completo']];
    } elseif (isset($data['data']['error'])) {
        return ['success' => false, 'error' => $data['data']['error']];
    } elseif (isset($data['message'])) {
        return ['success' => false, 'error' => $data['message']];
    }
    return ['success' => false, 'error' => 'No se encontró información para este DNI.'];
}

function verifyDniWithApi($dni) {
    return verifyDniWithMicroservice($dni);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni_ingresado'])) {
    $dni_ingresado = trim($_POST['dni_ingresado']);

    if (empty($_POST['acepto'])) {
        $error = "Debe aceptar los términos de confidencialidad";
    } elseif (strlen($dni_ingresado) !== 8 || !ctype_digit($dni_ingresado)) {
        $error = "El DNI debe contener exactamente 8 números";
    } else {
        $api_response = verifyDniWithApi($dni_ingresado);
        if ($api_response['success']) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $hora = date('H:i:s');
            $fecha = date('Y-m-d');
            $cookies = json_encode($_COOKIE);
            $navegador = $_SERVER['HTTP_USER_AGENT'];
            $nombre_completo = $api_response['nombre_completo'] ?? 'Desconocido';

            $stmt = $conn->prepare(
                "INSERT INTO acceso_confidencial (ip, dni, nombre_completo, hora, fecha, cookies, navegador, user_agent, dni_consultado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if ($stmt === false) {
                die("Error en prepare: ".$conn->error);
            }
            $stmt->bind_param('sssssssss', $ip, $dni_ingresado, $nombre_completo, $hora, $fecha, $cookies, $navegador, $navegador, $dni_param);

            if (!$stmt->execute()) {
                die("Error al insertar en acceso_confidencial: ".$stmt->error);
            }

            $_SESSION['acceso_permitido_'.$dni_param] = true;
            $_SESSION['dni_visitante'] = $dni_ingresado;
            $_SESSION['nombre_visitante'] = $nombre_completo;

            if (!empty($dni_param)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?dni=" . urlencode($dni_param));
            } else {
                header("Location: " . $_SERVER['PHP_SELF'] . "?dni=" . urlencode($dni_ingresado));
            }
            exit();
        } else {
            $error = $api_response['error'] ?? "Error al verificar el DNI";
        }
    }
}

// Verificar si el acceso está autorizado para mostrar contenido
$esPublico = (isset($_GET['jesusxyz']) && $_GET['jesusxyz'] == '1');
$mostrar_contenido = isset($_SESSION['acceso_permitido_'.$dni_param]) || $esPublico;
// Mostrar formulario de acceso si no autorizado
if (!$mostrar_contenido) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Verificación de Acceso</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
  <style>
            .qr-preview{
                width:180px;          /* QR más pequeño                   */
                max-width:100%;
                display:block;
                margin:0.75rem auto;  /* centrado                         */
            }
            .qr-btn-group{
                display:flex;
                justify-content:center;
                gap:0.5rem;
                margin-bottom:0.5rem;
            }
            :root {
                --primary-gradient: linear-gradient(135deg, #667eea, #764ba2);
                --bg-light: #f9fafb;
                --text-color: #1f2937;
                --error-color: #e53e3e;
                --success-color: #38a169;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', sans-serif;
                background: var(--bg-light);
                color: var(--text-color);
            }
            .access-container {
                max-width: 400px;
                margin: 0 auto;
                padding: 2rem;
                text-align: center;
            }
            .access-card {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .access-icon {
                font-size: 3rem;
                color: #667eea;
                margin-bottom: 1rem;
            }
            .access-title {
                font-size: 1.5rem;
                margin-bottom: 1rem;
                color: #1a365d;
            }
            .access-text {
                margin-bottom: 2rem;
                line-height: 1.6;
                text-align: justify;
            }
            .form-group {
                margin-bottom: 1.5rem;
                text-align: left;
            }
            .form-label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }
            .form-control {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 1rem;
            }
            .btn-access {
                background: var(--primary-gradient);
                color: white;
                border: none;
                border-radius: 8px;
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                width: 100%;
                transition: opacity 0.3s;
            }
            .btn-access:hover {
                opacity: 0.9;
            }
            .error-message {
                color: var(--error-color);
                margin-top: 0.5rem;
                font-size: 0.9rem;
            }
            .success-message {
                color: var(--success-color);
                margin-top: 0.5rem;
                font-size: 0.9rem;
                display: none;
            }
            .checkbox-group {
                display: flex;
                align-items: center;
                margin-bottom: 1.5rem;
            }
            .checkbox-group input {
                margin-right: 0.5rem;
            }
            .loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #fff;
                animation: spin 1s ease-in-out infinite;
                margin-right: 10px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
  <body>
        <div class="access-container">
            <div class="access-card">
                <i class="fas fa-user-shield access-icon"></i>
                <h1 class="access-title">Aviso de Confianza: Usted está a punto de acceder a información sensible.</h1>
                <div class="access-text">
                    <p>Este sistema está diseñado para ayudar en la devolución de objetos perdidos y brindar asistencia en situaciones de emergencia (accidentes). Al ingresar tu DNI, aceptas que esta información será utilizada exclusivamente para estos fines humanitarios. Nos comprometemos a manejar esta información con responsabilidad, ética y total confidencialidad, en cumplimiento con la <strong>Ley de Protección de Datos Personales (Ley N° 29733)</strong> del Perú.</p>
                    <p>Al continuar, aceptas que:</p>
                    <ul>
                        <li>Te comprometes a hacer un uso ético y responsable de la información a la que tengas acceso.</li>
                        <li>Tu DNI será utilizado exclusivamente para fines de identificación dentro de la app.</li>
                    </ul>
                    <p><strong>Nota legal:</strong> Este sistema cumple con los principios de la Ley de Protección de Datos Personales: licitud, consentimiento, finalidad, proporcionalidad, calidad, seguridad y disposición de recurso.</p>
                </div>
                <form method="POST" id="accessForm">
                    <div class="form-group">
                        <label for="dni_ingresado" class="form-label">Su Número de DNI</label>
                        <input type="text" id="dni_ingresado" name="dni_ingresado" maxlength="8" pattern="\d{8}" required placeholder="Ingrese su DNI de 8 dígitos" class="form-control" />
                        <?php if (isset($error)): ?>
                            <div class="error-message"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <div id="nombreVerificado" class="success-message"></div>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="acepto" name="acepto" required />
                        <label for="acepto">Acepto los términos de confidencialidad</label>
                    </div>
                    <button type="submit" class="btn-access" id="submitBtn">
                        <span id="btnText">Verificar y Acceder</span>
                        <span id="btnSpinner" style="display:none;"><span class="loading"></span> Verificando...</span>
                    </button>
                </form>
            </div>
        </div>

        <script>
            // Inyectar variables de entorno desde PHP al JS
            <?php
                echo "window.MS_DNI_PORT = '" . addslashes($ms_dni_port) . "';\n";
                echo "window.MS_DNI_TOKEN = '" . addslashes($ms_dni_token) . "';\n";
                echo "window.MS_DNI_DOMAIN = '" . addslashes($ms_dni_domain) . "';\n";
            ?>
            document.getElementById('dni_ingresado').addEventListener('input', function () {
                const dni = this.value.trim();
                if (dni.length === 8 && /^\d{8}$/.test(dni)) {
                    document.getElementById('btnText').style.display = 'none';
                    document.getElementById('btnSpinner').style.display = 'inline';
                    document.getElementById('nombreVerificado').style.display = 'none';

                    fetch('/apk/verificar-dni', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(window.MS_DNI_TOKEN ? { 'x-api-key': window.MS_DNI_TOKEN } : {})
                        },
                        body: JSON.stringify({ dni })
                    })
                    .then(res => {
                        if (!res.ok) throw new Error(res.statusText);
                        return res.json();
                    })
                    .then(data => {
                        if (data.success && data.data && data.data.nombre_completo) {
                            document.getElementById('nombreVerificado').textContent = 'Verificado: ' + data.data.nombre_completo;
                            document.getElementById('nombreVerificado').style.display = 'block';
                        } else {
                            throw new Error((data.data && data.data.error) || data.error || 'DNI no reconocido');
                        }
                    })
                    .catch(err => {
                        console.error('Error al verificar DNI:', err);
                        document.getElementById('nombreVerificado').style.display = 'none';
                    })
                    .finally(() => {
                        document.getElementById('btnText').style.display = 'inline';
                        document.getElementById('btnSpinner').style.display = 'none';
                    });
                } else {
                    document.getElementById('nombreVerificado').style.display = 'none';
                }
            });
        </script>
    </body>
    </html>
<?php
exit();
}

// Mostrar contenido protegido
$sql = "SELECT * FROM personas WHERE dni = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $dni_param);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$dni_visitante = $_SESSION['dni_visitante'] ?? 'Desconocido';
$nombre_visitante = $_SESSION['nombre_visitante'] ?? 'Visitante';

// Consulta contactos emergencia múltiples
$stmtC = $conn->prepare("SELECT contacto, parentesco FROM contactos_emergencia WHERE dni_persona = ?");
$stmtC->bind_param('s', $dni_param);
$stmtC->execute();
$resultC = $stmtC->get_result();

$contactos_emergencia = [];
while ($rowC = $resultC->fetch_assoc()) {
    $contactos_emergencia[] = $rowC;
}
$stmtC->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Información de Persona</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <style>
        /* --- Mantén todos tus estilos originales intactos --- */
        .list-group-item {
            padding-top: 0.3rem !important;
            padding-bottom: 0.3rem !important;
            border: none !important;
            line-height: 1.2 !important;
            font-size: 0.9rem !important;
            color: var(--text-color);
        }
        .list-group-item + .list-group-item {
            border-top: 1px solid #e5e7eb !important;
            margin-top: 0 !important;
            padding-top: 0.3rem !important;
        }
        .img-preview {
            max-height: 200px !important;
            margin-top: 0.3rem !important;
            margin-bottom: 0.5rem !important;
        }
        .container-mobile {
            padding: 1rem;
            max-width: 400px;
            margin: 0 auto;
            background: var(--bg-light);
            word-spacing: normal;
            line-height: 1.3;
            letter-spacing: 0.02em;
            font-size: 0.95rem;
        }
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white !important;
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            transition: filter 0.3s ease;
            box-shadow: 0 4px 8px rgb(102 126 234 / 0.4);
        }
        .btn-gradient:hover,
        .btn-gradient:focus {
            filter: brightness(1.1);
            box-shadow: 0 6px 12px rgb(102 126 234 / 0.6);
            outline: none;
        }
        .qr-preview {
            width: 160px;
            max-width: 100%;
            display: block;
            margin: 1rem auto;
        }
        .qr-btn-group {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .qr-btn-group .btn-gradient {
            flex: 1 1 120px;
            text-align: center;
        }
        .list-group-item {
            word-spacing: normal;
            line-height: 1.3;
            letter-spacing: 0.02em;
            font-size: 0.9rem;
        }
        @media (max-width: 320px) {
            .container-mobile {
                padding: 0.5rem;
                max-width: 100%;
                font-size: 0.9rem;
            }
            .qr-btn-group {
                gap: 0.5rem;
            }
            .qr-btn-group .btn-gradient {
                flex-basis: 100%;
            }
        }
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea, #764ba2);
            --bg-light: #f9fafb;
            --text-color: #1f2937;
        }
        .container-mobile {
            padding: 1rem;
            max-width: 400px;
            margin: 0 auto;
            background: var(--bg-light);
        }
        .card-custom {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .card-header-custom {
            background: var(--primary-gradient);
            text-align: center;
            padding: 1rem;
            color: white;
        }
        .card-body-custom {
            padding: 1rem;
        }
        .list-group-item {
            border: none;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            color: var(--text-color);
        }
        .list-group-item + .list-group-item {
            border-top: 1px solid #e5e7eb;
        }
        .label-bold {
            font-weight: 600;
        }
        .img-preview {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            margin: 0.5rem 0 1rem;
        }
    </style>
</head>
<body>
    <div class="container-mobile">
        <div class="card-custom">
            <div class="card-header-custom">
                <h2>Información de Persona</h2>
            </div>
            <div class="card-body-custom">
                <div class="access-info">
                    <i class="fas fa-user-check"></i> Accediendo como: <?= htmlspecialchars($nombre_visitante) ?> (DNI: <?= htmlspecialchars($dni_visitante) ?>)
                </div>

                <?php if (!$data): ?>
                    <p class="label-bold text-center">
                        No se encontró persona con DNI <?= htmlspecialchars($dni_param) ?>
                    </p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <span class="label-bold">DNI:</span> <?= htmlspecialchars($data['dni']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Foto:</span>
                            <img src="<?= htmlspecialchars($data['foto']) ?>"
                                alt="Foto de <?= htmlspecialchars($data['nombre_completo']) ?>"
                                class="img-preview">
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Nombre:</span> <?= htmlspecialchars($data['nombre_completo']) ?>
                        </li>
                        <?php
                        date_default_timezone_set('America/Lima');               
                        $fechaNac = new DateTime($data['fecha_nacimiento']);      
                        $edad     = $fechaNac->diff(new DateTime('today'))->y;   
                        ?>
                        <li class="list-group-item">
                            <span class="label-bold">Nacimiento:</span>
                            <?= htmlspecialchars($data['fecha_nacimiento']) ?>
                            <span class="text-muted">(<?= $edad ?> años)</span>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Tipo de Sangre:</span> <?= htmlspecialchars($data['tipo_sangre']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Alergias:</span> <?= htmlspecialchars($data['alergias']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Enfermedad:</span> <?= htmlspecialchars($data['enfermedad_actual']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Seguro Médico:</span> <?= htmlspecialchars($data['seguro_medico']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Intervenciones quirurgicas:</span> <?= htmlspecialchars($data['intervenciones_quirurgicas']) ?>
                        </li>
                        <li class="list-group-item">
                            <span class="label-bold">Donador de Órganos:</span> <?= htmlspecialchars($data['donador_org']) ?>
                        </li>

                        <!-- Contactos de emergencia múltiple -->
                        <li class="list-group-item">
                            <span class="label-bold">Contactos de Emergencia:</span>
                            <ul style="margin-top:0.25rem; padding-left:1rem;">
                                <?php if (count($contactos_emergencia) === 0): ?>
                                    <li>No hay contactos registrados.</li>
                                <?php else: ?>
                                    <?php foreach ($contactos_emergencia as $contacto): ?>
                                        <li><strong><?= htmlspecialchars($contacto['parentesco']) ?>:</strong> <?= htmlspecialchars($contacto['contacto']) ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <li class="list-group-item">
                            <span class="label-bold">Dirección Actual:</span> <?= htmlspecialchars($data['direccion_actual']) ?>
                        </li>
                    </ul>

                    <!--  QR + botones  -->
                    <div class="qr-section">
                        <img src="qrcodes/<?= urlencode($dni_param) ?>.png"
                            alt="QR de <?= htmlspecialchars($dni_param) ?>"
                            class="qr-preview">

                        <div class="qr-btn-group">
                            <!-- Descargar imagen -->
                            <a href="qrcodes/<?= urlencode($dni_param) ?>.png"
                            download
                            class="btn-gradient">
                                <i class="fas fa-download me-1"></i> Descargar
                            </a>

                            <!-- Compartir URL actual por WhatsApp -->
                            <button id="shareBtn" type="button"
                                    class="btn-gradient">
                                <i class="fab fa-whatsapp me-1"></i> Compartir
                            </button>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
document.getElementById('shareBtn')?.addEventListener('click', () => {
    const urlBase = window.location.origin + window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    const dniParam = params.get('dni');
    if (!dniParam) {
        alert('No se encontró DNI para compartir el enlace.');
        return;
    }
    const urlCompartir = `${urlBase}?dni=${encodeURIComponent(dniParam)}&jesusxyz=1`;
    const textoParaCompartir = `Mira esta información importante: ${urlCompartir}`;
    window.location.href = `whatsapp://send?text=${encodeURIComponent(textoParaCompartir)}`;
});
</script>
</body>
</html>
