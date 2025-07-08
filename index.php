<?php

// Iniciar la sesión

session_start();



// Si el usuario ya está autenticado, redirigir a la página de dashboard (o página principal)

if (isset($_SESSION['user_id'])) {

    header("Location: dashboard.php"); // Redirige al área de usuario

    exit();

}



// Si el usuario no está autenticado, redirigir a login.php

header("Location: login.php");

exit();

?>

<?php

// Iniciar la sesión

session_start();



// Si el usuario ya está autenticado, redirigir a la página de dashboard (o página principal)

if (isset($_SESSION['user_id'])) {

    header("Location: dashboard.php"); // Redirige al área de usuario

    exit();

}



// Si el usuario no está autenticado, redirigir a login.php

header("Location: login.php");

exit();

?>

