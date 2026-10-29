<?php
declare(strict_types=1);

/**
 * Acciones sobre un usuario (formularios POST): activar, desactivar y restablecer contraseña.
 * Después redirige al detalle o al listado, con un aviso guardado en la sesión.
 */
session_start();
require_once __DIR__ . '/conexion.php';          // deja disponible $cnx (mysqli)
require_once __DIR__ . '/usuarios_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso no permitido.');
}
if (!hash_equals((string) ($_SESSION['csrf_usuario'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('La sesión del formulario expiró. Vuelve a la página anterior e inténtalo de nuevo.');
}

$id     = (int) ($_POST['id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');
$volver = (($_POST['volver'] ?? '') === 'detalle') ? 'usuario-ver.php?id=' . $id : 'usuarios.php';

function terminar(string $destino, string $tipo, string $texto, ?string $password = null): void
{
    $_SESSION['flash_usuario'] = ['tipo' => $tipo, 'texto' => $texto, 'password' => $password];
    header('Location: ' . $destino);
    exit;
}

if ($id <= 0 || !in_array($accion, ['activar', 'desactivar', 'restablecer'], true)) {
    terminar($volver, 'error', 'La acción solicitada no es válida.');
}

// Un administrador no puede desactivar su propia cuenta
if ($accion === 'desactivar' && isset($_SESSION['id_usuario']) && (int) $_SESSION['id_usuario'] === $id) {
    terminar($volver, 'error', 'No puedes desactivar tu propia cuenta.');
}

try {
    if ($accion === 'restablecer') {
        $temporal = passwordTemporalUsuario();
        $guardar  = U_PASSWORD_PLANO ? $temporal : password_hash($temporal, PASSWORD_DEFAULT);

        $st = mysqli_prepare($cnx, 'UPDATE ' . U_TABLA . ' SET ' . U_PASSWORD . ' = ? WHERE ' . U_ID . ' = ?');
        mysqli_stmt_bind_param($st, 'si', $guardar, $id);
        mysqli_stmt_execute($st);
        $filas = mysqli_stmt_affected_rows($st);
        mysqli_stmt_close($st);

        if ($filas < 1) {
            terminar($volver, 'error', 'No se encontró el usuario.');
        }
        terminar($volver, 'ok', 'Se asignó una contraseña temporal. Compártela de forma segura; no se volverá a mostrar.', $temporal);
    }

    $nuevo = ($accion === 'activar') ? 'Activo' : 'Inactivo';
    $st = mysqli_prepare($cnx, 'UPDATE ' . U_TABLA . ' SET ' . U_ESTADO . ' = ? WHERE ' . U_ID . ' = ?');
    mysqli_stmt_bind_param($st, 'si', $nuevo, $id);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    terminar($volver, 'ok', $accion === 'activar' ? 'La cuenta se activó correctamente.' : 'La cuenta se desactivó correctamente.');

} catch (Throwable $ex) {
    error_log('[usuario-accion.php] ' . $ex->getMessage());
    terminar($volver, 'error', 'No se pudo completar la acción. Inténtalo de nuevo más tarde.');
}
