<?php
declare(strict_types=1);

/**
 * Activa o desactiva un tipo de membresía (formulario POST desde tipos.php).
 * Después redirige de vuelta a la lista.
 */
session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tipos_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($_SESSION['rol'] ?? '', TM_ROLES_PERMITIDOS, true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
if (!hash_equals((string) ($_SESSION['csrf_tipo'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('La sesión del formulario expiró. Vuelve a la lista e inténtalo de nuevo.');
}

$id     = (int) ($_POST['id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');
// Desde la pantalla de detalle se vuelve al detalle; desde la lista, a la lista.
$base   = (($_POST['volver'] ?? '') === 'detalle' && $id > 0) ? 'tipo-ver.php?id=' . $id . '&' : 'tipos.php?';
$nuevo  = match ($accion) { 'activar' => 'Activo', 'desactivar' => 'Inactivo', default => null };

if ($id <= 0 || $nuevo === null) {
    header('Location: ' . $base . 'error=1');
    exit;
}

try {
    $st = mysqli_prepare($cnx, 'UPDATE ' . TM_TABLA . ' SET ' . TM_ESTADO . ' = ? WHERE ' . TM_ID . ' = ?');
    mysqli_stmt_bind_param($st, 'si', $nuevo, $id);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    header('Location: ' . $base . 'actualizado=1');
} catch (Throwable $ex) {
    error_log('[tipo_estado.php] ' . $ex->getMessage());
    header('Location: ' . $base . 'error=1');
}
exit;
