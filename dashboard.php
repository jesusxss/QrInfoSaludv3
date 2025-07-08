<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard - LoqQRSalud</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.4.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #4f46e5;
      --bg-light: #f9fafb;
      --text-color: #111827;
    }
    body { margin:0; padding:0; background:var(--bg-light); color:var(--text-color); font-family:'Segoe UI',sans-serif; }
    #content-container { padding:1rem; padding-bottom:4.5rem; }
    .nav-bottom { position:fixed; bottom:0; left:0; right:0; height:3.5rem; background:#fff;
                  display:flex; justify-content:space-around; align-items:center;
                  box-shadow:0 -2px 8px rgba(0,0,0,0.1); z-index:1000; }
    .nav-bottom a { flex:1; text-align:center; color:var(--text-color); font-size:0.875rem; text-decoration:none; }
    .nav-bottom a.active { color:var(--primary-color); }
    .nav-bottom i { display:block; font-size:1.25rem; margin-bottom:0.2rem; }
  </style>
</head>
<body>
  <div id="content-container"></div>

  <nav class="nav-bottom">
    <a href="dashboard.php" id="nav-sugerencias" class="active"><i class="fas fa-lightbulb"></i><small>Sugerencias</small></a>
    <a href="index2.php" id="nav-generar"><i class="fas fa-qrcode"></i><small>Generar</small></a>
    <a href="qr_generados.php" id="nav-misqr"><i class="fas fa-file-alt"></i><small>Mis Qr</small></a>
    <a href="personalizar_qr.php" id="nav-personalizar"><i class="fas fa-cogs"></i><small>Personalizar</small></a>
  </nav>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>
    $(function() {
      const suggestionsHTML = `
        <div class="page-header" style="background: var(--primary-color); color:#fff; padding:1rem; text-align:center;">
          <h2>Dashboard</h2>
          <p class="mb-0">Este proyecto/aplicativo fue desarrollado con la finalidad de brindar una ayuda adicional frente a una situación de riesgo u accidente. Úselo con precaución y responsabilidad.</p>
        <p> Test deslogueo <a href="logout.php">Logout</a></p>
          </div>`;

      // Carga inicial
      $('#content-container').html(suggestionsHTML);

      // Función para cargar páginas
      function loadPage(page) {
        const uniquePage = page + (page.includes('?') ? '&' : '?') + '_=' + Date.now();
        
        $('#content-container').html(`
          <div class="text-center mt-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
          </div>`);

        $.ajax({
          url: uniquePage,
          cache: false,
          success: function(response) {
            $('#content-container').html(response);
            // Inicializar módulos específicos si existen
            if (typeof initRegistroForm === 'function') initRegistroForm();
            if (typeof initPersonalizarQR === 'function') initPersonalizarQR();
          },
          error: function() {
            $('#content-container').html('<div class="alert alert-danger">Error al cargar la página</div>');
          }
        });
      }

      // Navegación
      $('.nav-bottom a').on('click', function(e) {
        e.preventDefault();
        const page = $(this).attr('href');
        $('.nav-bottom a').removeClass('active');
        $(this).addClass('active');
        page ? loadPage(page) : $('#content-container').html(suggestionsHTML);
      });
    });
  </script>
<!--<script type='text/javascript'>
var t="20203c7363726970743e0d0a20202f2f20426c6f717565617220636c69636b206465726563686f20656e2050430d0a2020646f63756d656e742e6164644576656e744c697374656e65722827636f6e746578746d656e75272c2066756e6374696f6e286529207b0d0a20202020652e70726576656e7444656661756c7428293b0d0a20207d293b0d0a0d0a20202f2f20426c6f717565617220746f7175652070726f6c6f6e6761646f20656e206dc3b376696c657320286c6f6e67207072657373290d0a20206c657420746f75636854696d6572203d206e756c6c3b0d0a2020646f63756d656e742e6164644576656e744c697374656e65722827746f7563687374617274272c2066756e6374696f6e286529207b0d0a20202020746f75636854696d6572203d2073657454696d656f75742866756e6374696f6e2829207b0d0a2020202020202f2f20457669746172207175652061706172657a636120656c206d656ec3ba20636f6e7465787475616c20656e20746f7175652070726f6c6f6e6761646f0d0a202020202020652e70726576656e7444656661756c7428293b0d0a202020207d2c20353030293b202f2f203530306d73207061726120646574656374617220746f717565206c6172676f0d0a20207d293b0d0a0d0a2020646f63756d656e742e6164644576656e744c697374656e65722827746f756368656e64272c2066756e6374696f6e286529207b0d0a2020202069662028746f75636854696d65722920636c65617254696d656f757428746f75636854696d6572293b0d0a20207d293b0d0a3c2f7363726970743e";for(i=0;i<t.length;i+=2){document.write(String.fromCharCode(parseInt(t.substr(i,2),16)));}
</script>-->
</body>
</html>