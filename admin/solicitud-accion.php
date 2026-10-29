<?php
declare(strict_types=1);

/**
 * Aprueba una solicitud de baja (formulario POST desde solicitud-ver.php o solicitudes.php).
 * Solo el administrador puede aprobar. Después redirige con un aviso.
 */
session_start();
require_once __DIR__ . '/conexion.php';          // deja disponible $cnx (mysqli)
require_once __DIR__ . '/bajas_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso no permitido.');
}
if (!hash_equals((string) ($_SESSION['csrf_solicitud'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('La sesión del formulario expiró. Vuelve a la página anterior e inténtalo de nuevo.');
}

$id     = (int) ($_POST['id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');
$base   = (($_POST['volver'] ?? '') === 'detalle' && $id > 0) ? 'solicitud-ver.php?id=' . $id . '&' : 'solicitudes.php?';

if ($id <= 0 || $accion !== 'aprobar') {
    header('Location: ' . $base . 'error=1');
    exit;
}

try {
    // Solo se aprueba si la solicitud sigue pendiente (así un doble clic no la aprueba dos veces)
    // Al aprobar, la fecha de baja se actualiza con la fecha del momento en que se presiona el botón.
    $sql = 'UPDATE ' . BAJ_TABLA . ' SET ' . BAJ_ESTADO . ' = ?, ' . BAJ_FECHA . ' = NOW()'
         . ' WHERE ' . BAJ_ID . ' = ? AND ' . BAJ_ESTADO . ' = ?';
    $st = mysqli_prepare($cnx, $sql);
    $aprobada  = BAJ_ESTADO_APROBADA;
    $pendiente = BAJ_ESTADO_PENDIENTE;
    mysqli_stmt_bind_param($st, 'sis', $aprobada, $id, $pendiente);
    mysqli_stmt_execute($st);
    $filas = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);

    header('Location: ' . $base . ($filas > 0 ? 'aprobada=1' : 'error=1'));

} catch (Throwable $ex) {
    error_log('[solicitud-accion.php] ' . $ex->getMessage());
    header('Location: ' . $base . 'error=1');
}
exit;
