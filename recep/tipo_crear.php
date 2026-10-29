<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';       // deja disponible $cnx (mysqli)
require_once __DIR__ . '/tipos_config.php';   // nombres de tabla y columnas

function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Acceso y CSRF ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
if (!in_array($_SESSION['rol'] ?? '', TM_ROLES_PERMITIDOS, true)) {
    responder(['ok' => false, 'mensaje' => 'No tienes permiso para administrar los tipos de membresía.'], 403);
}
if (!hash_equals((string) ($_SESSION['csrf_tipo'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    responder(['ok' => false, 'mensaje' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.'], 419);
}

/* ---------- Validación ---------- */
$nombre = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nombre'] ?? '')));
$estado = (string) ($_POST['estado'] ?? 'Activo');

$errores = [];
if (!preg_match('/^[\p{L}\p{N} .\-]{3,60}$/u', $nombre)) {
    $errores['nombre'] = 'Usa de 3 a 60 caracteres (letras, números, espacios, puntos o guiones).';
}
if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
    $errores['estado'] = 'Estado no válido.';
}
if ($errores) {
    responder(['ok' => false, 'errores' => $errores], 422);
}

/* ---------- Guardado ---------- */
try {
    // Nombre único, sin distinguir mayúsculas
    $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . TM_TABLA . ' WHERE LOWER(' . TM_NOMBRE . ') = LOWER(?) LIMIT 1');
    mysqli_stmt_bind_param($st, 's', $nombre);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $existe = mysqli_stmt_num_rows($st) > 0;
    mysqli_stmt_close($st);

    if ($existe) {
        responder(['ok' => false, 'errores' => ['nombre' => 'Ya existe un tipo de membresía con este nombre.']], 422);
    }

    $st = mysqli_prepare($cnx, 'INSERT INTO ' . TM_TABLA . ' (' . TM_NOMBRE . ', ' . TM_ESTADO . ') VALUES (?, ?)');
    mysqli_stmt_bind_param($st, 'ss', $nombre, $estado);
    mysqli_stmt_execute($st);
    $id = (int) mysqli_insert_id($cnx);
    mysqli_stmt_close($st);

    $codigo = codigoTipo($id);
    if (TM_CODIGO !== null) {
        $st = mysqli_prepare($cnx, 'UPDATE ' . TM_TABLA . ' SET ' . TM_CODIGO . ' = ? WHERE ' . TM_ID . ' = ?');
        mysqli_stmt_bind_param($st, 'si', $codigo, $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    $_SESSION['csrf_tipo'] = bin2hex(random_bytes(32));

    responder([
        'ok'      => true,
        'mensaje' => 'El tipo «' . $nombre . '» (' . $codigo . ') se registró correctamente.',
        'tipo'    => ['id' => $id, 'codigo' => $codigo],
    ]);

} catch (Throwable $ex) {
    error_log('[tipo_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo registrar el tipo. Inténtalo de nuevo más tarde.'], 500);
}
