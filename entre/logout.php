<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se usaron cookies de sesión, eliminarlas también
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión en el servidor
session_destroy();

// Redirigir al inicio de sesión
header("Location: ../index.html");
exit();
?>