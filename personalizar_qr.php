<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
  <title>Personalizar QR</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.4.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"/>
  <style>
    :root {
      --gradient-start: #667eea;
      --gradient-end:   #764ba2;
      --bg-light:       #f9fafb;
      --card-bg:        #ffffff;
      --text-color:     #1f2937;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: var(--bg-light);
      font-family: 'Segoe UI', sans-serif;
      color: var(--text-color);
    }
    .container-mobile {
      padding: 1rem;
      max-width: 400px;
      margin: 0 auto;
    }
    .card-custom {
      background: var(--card-bg);
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .card-header-custom {
      background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
      text-align: center;
      padding: 1rem;
    }
    .card-header-custom i {
      color: #fff; font-size: 1.75rem;
    }
    .card-header-custom h5 {
      color: #fff; margin: 0.5rem 0 0; font-size: 1.25rem;
    }
    .card-body-custom { padding: 1rem; }
    .form-label { font-weight: 500; font-size: 1rem; }
    .form-control,
    .form-select,
    .form-range {
      border-radius: 8px;
      height: 2.75rem;
      font-size: 1rem;
    }
    .input-group-text { background: transparent; border: none; }
    #qr-size-display { float: right; font-size: 0.875rem; }
    #canvas-preview {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      margin-top: 0.5rem;
      touch-action: none;
      cursor: grab;
      display: block;
    }
    .btn-action {
      background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
      color: #fff; border: none; border-radius: 8px;
      height: 3rem; font-size: 1rem; font-weight: 500;
      width: 100%; transition: opacity 0.3s;
    }
    .btn-action:hover { opacity: 0.9; }
    @media (max-width: 400px) {
      .container-mobile,
      .card-header-custom,
      .card-body-custom { padding: 0.75rem; }
      .form-control,
      .form-select,
      .form-range { height: 2.5rem; font-size: 0.9rem; }
      .btn-action { height: 2.5rem; font-size: 0.9rem; }
    }
  </style>
</head>
<body>
  <?php
  session_start();
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
  }
  include 'db.php';
  $user_id = $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT * FROM qr_codes WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  ?>

  <div class="container-mobile">
    <div class="card-custom">
      <div class="card-header-custom">
        <i class="fas fa-qrcode"></i>
        <h5>Personalizar QR</h5>
      </div>
      <div class="card-body-custom">
        <form>
          <div class="mb-3">
            <label for="qr_id" class="form-label">Seleccionar QR</label>
            <select id="qr_id" class="form-select" required>
              <option value="">Selecciona un QR</option>
              <?php while ($row = $result->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['dni']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="image" class="form-label">Subir Imagen de Fondo</label>
            <input type="file" id="image" class="form-control" accept="image/jpeg,image/png" required>
          </div>
          <div class="mb-3">
            <label for="custom-text" class="form-label">Texto Personalizado</label>
            <input type="text" id="custom-text" class="form-control" placeholder="Escanéame por favor" maxlength="100">
            <small>Máximo 100 caracteres.</small>
          </div>
          <div class="mb-3">
            <label for="qr-size" class="form-label">Tamaño del QR (% del ancho)</label>
            <input type="range" id="qr-size" class="form-range" min="5" max="50" value="25">
            <small><span id="qr-size-display">25%</span></small>
          </div>
          <div class="mb-3">
            <label for="output-width" class="form-label">Ancho de salida (px)</label>
            <input type="number" id="output-width" class="form-control" value="1080" min="1" required>
          </div>
          <div class="mb-3">
            <label for="output-height" class="form-label">Alto de salida (px)</label>
            <input type="number" id="output-height" class="form-control" value="1920" min="1" required>
          </div>
          <div class="mb-3 text-center">
            <canvas id="canvas-preview"></canvas>
          </div>
          <div class="d-grid">
            <button type="button" id="generate-image" class="btn-action">
              <i class="fas fa-download me-1"></i><span class="btn-text">Generar y Descargar</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function() {
  let backgroundImage = null, qrImage = null;
  const preview = {w: 0, h: 0, qrW: 0, qrH: 0, qrX: 0, qrY: 0, dragging: false, offsetX: 0, offsetY: 0, fontSize: 0};
  let isGenerating = false;

  function initPersonalizarQR() {
    const canvas = document.getElementById('canvas-preview');
    const ctx = canvas.getContext('2d');
    const btnGenerate = $('#generate-image');
    const btnText = btnGenerate.find('.btn-text');

    async function loadBackground(file) {
      return new Promise(res => {
        const fr = new FileReader();
        fr.onload = e => {
          backgroundImage = new Image();
          backgroundImage.onload = () => {
            const cw = document.querySelector('.container-mobile').clientWidth;
            preview.w = cw;
            preview.h = cw * (backgroundImage.naturalHeight / backgroundImage.naturalWidth);
            preview.fontSize = Math.round(preview.w * 0.045);
            canvas.width = preview.w;
            canvas.height = preview.h;
            res();
          };
          backgroundImage.src = e.target.result;
        };
        fr.readAsDataURL(file);
      });
    }

    function loadQR(id) {
      return new Promise(res => {
        $.get('get_qr.php', {id}, data => {
          qrImage = new Image();
          qrImage.onload = res;
          qrImage.src = data;
        });
      });
    }

    function drawAll() {
      if (!backgroundImage || !qrImage) return;
      ctx.clearRect(0, 0, preview.w, preview.h);
      ctx.drawImage(backgroundImage, 0, 0, preview.w, preview.h);
      ctx.drawImage(qrImage, preview.qrX, preview.qrY, preview.qrW, preview.qrH);
      ctx.save();
      ctx.translate(preview.qrX - 10, preview.qrY + preview.qrH / 2);
      ctx.rotate(-Math.PI / 2);
      ctx.font = `${preview.fontSize}px sans-serif`;
      ctx.shadowColor = '#0caeb9';
      ctx.shadowOffsetX = 2;
      ctx.shadowOffsetY = 2;
      ctx.shadowBlur = 5;
      ctx.fillStyle = '#00efff';
      ctx.fillText($('#custom-text').val(), -preview.qrH / 2, 0);
      ctx.restore();
    }

    function updatePreview() {
      const p = Number($('#qr-size').val()) / 100;
      $('#qr-size-display').text((p * 100) + '%');
      preview.qrW = preview.w * p;
      preview.qrH = preview.qrW;
      preview.qrX = (preview.w - preview.qrW) / 2;
      preview.qrY = preview.h - preview.qrH - preview.h * 0.05;
      drawAll();
    }

    $('#image').off('change').on('change', async function() {
      if (this.files[0]) {
        await loadBackground(this.files[0]);
        updatePreview();
      }
    });

    $('#qr_id').off('change').on('change', async function() {
      const id = $(this).val();
      if (id) {
        await loadQR(id);
        updatePreview();
      }
    });

    $('#qr-size').off('input').on('input', updatePreview);
    $('#custom-text').off('input').on('input', drawAll);

    canvas.addEventListener('pointerdown', e => {
      if (!backgroundImage || !qrImage) return;
      const r = canvas.getBoundingClientRect();
      preview.dragging = true;
      preview.offsetX = e.clientX - r.left - preview.qrX;
      preview.offsetY = e.clientY - r.top - preview.qrY;
      canvas.setPointerCapture(e.pointerId);
      canvas.style.cursor = 'grabbing';
    });

    canvas.addEventListener('pointermove', e => {
      if (!preview.dragging) return;
      const r = canvas.getBoundingClientRect();
      preview.qrX = (e.clientX - r.left) - preview.offsetX;
      preview.qrY = (e.clientY - r.top) - preview.offsetY;
      drawAll();
    });

    ['pointerup', 'pointercancel'].forEach(evt => {
      canvas.addEventListener(evt, e => {
        preview.dragging = false;
        canvas.releasePointerCapture(e.pointerId);
        canvas.style.cursor = 'grab';
      });
    });

    btnGenerate.off('click').on('click', async () => {
      if (isGenerating) return;
      if (!backgroundImage || !qrImage) {
        alert('Seleccione fondo y QR.');
        return;
      }

      isGenerating = true;
      btnGenerate.prop('disabled', true);
      btnText.text('Generando...');

      try {
        const dw = parseInt($('#output-width').val(), 10);
        const dh = parseInt($('#output-height').val(), 10);
        const sx = dw / preview.w;
        const sy = dh / preview.h;
        const qrW_out = preview.qrW * sx;
        const qrH_out = preview.qrH * sx;
        const qrX_out = preview.qrX * sx;
        const qrY_out = preview.qrY * sy;

        const dc = document.createElement('canvas');
        dc.width = dw;
        dc.height = dh;
        const dctx = dc.getContext('2d');
        dctx.drawImage(backgroundImage, 0, 0, dw, dh);
        dctx.drawImage(qrImage, qrX_out, qrY_out, qrW_out, qrH_out);
        const fontSize = preview.fontSize * sx;
        dctx.save();
        dctx.translate(qrX_out - 10 * sx, qrY_out + qrH_out / 2);
        dctx.rotate(-Math.PI / 2);
        dctx.font = `${fontSize}px sans-serif`;
        dctx.shadowColor = '#0caeb9';
        dctx.shadowOffsetX = 2 * sx;
        dctx.shadowOffsetY = 2 * sx;
        dctx.shadowBlur = 5 * sx;
        dctx.fillStyle = '#00efff';
        dctx.fillText($('#custom-text').val(), -qrH_out / 2, 0);
        dctx.restore();

        const base64Image = dc.toDataURL('image/png');

        const response = await fetch('save_qr_image.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({image: base64Image})
        });
        const data = await response.json();

        if (data.success) {
          btnText.text('Descargando...');
          const link = document.createElement('a');
          link.href = data.url;
          link.download = data.filename;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        } else {
          alert('Error al guardar la imagen en el servidor.');
        }
      } catch(err) {
        alert('Error en la comunicación con el servidor.');
        console.error(err);
      } finally {
        btnText.text('Generar y Descargar');
        btnGenerate.prop('disabled', false);
        isGenerating = false;
      }
    });
  }

  $(document).ready(initPersonalizarQR);
})();
</script>
</body>
</html>
