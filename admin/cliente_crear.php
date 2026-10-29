<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';   // debe dejar disponible la conexión mysqli en $cnx

/**
 * ============================================================
 *  MAPEO CON TU BASE DE DATOS  (ajusta estos nombres)
 * ============================================================
 */
const T_CLIENTES   = 'clientes';
const C_ID         = 'id_cliente';
const C_NOMBRE     = 'nombre';
const C_APELLIDO   = 'apellido';
const C_DOCUMENTO  = 'documento';
const C_CORREO     = 'email';
const C_TELEFONO   = 'telefono';
const C_ESTADO     = 'estado';          // guarda 'Activo' / 'Inactivo'
const C_FECHA      = 'fecha_registro';  // se llena con NOW()
const C_CODIGO     = null;          // p. ej. CLI-0006. Pon null si tu tabla no tiene esta columna

/** Roles que pueden registrar clientes (valor exacto guardado en $_SESSION['rol']). */
const ROLES_PERMITIDOS = ['Administrador', 'Recepción', 'Recepcionista'];

/* ---------- Utilidades ---------- */
function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** ¿Ya existe un cliente con este valor en la columna indicada? */
function yaExiste(mysqli $cnx, string $columna, string $valor): bool
{
    $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . T_CLIENTES . ' WHERE ' . $columna . ' = ? LIMIT 1');
    mysqli_stmt_bind_param($st, 's', $valor);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $existe = mysqli_stmt_num_rows($st) > 0;
    mysqli_stmt_close($st);
    return $existe;
}

/* ---------- Acceso y CSRF ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
if (!in_array($_SESSION['rol'] ?? '', ROLES_PERMITIDOS, true)) {
    responder(['ok' => false, 'mensaje' => 'No tienes permiso para registrar clientes.'], 403);
}
if (!hash_equals((string) ($_SESSION['csrf_cliente'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    responder(['ok' => false, 'mensaje' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.'], 419);
}

/* ---------- Validación ---------- */
$nombres   = trim((string) ($_POST['nombres'] ?? ''));
$apellidos = trim((string) ($_POST['apellidos'] ?? ''));
$documento = trim((string) ($_POST['documento'] ?? ''));
$correo    = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
$telefono  = trim((string) ($_POST['telefono'] ?? ''));
$estado    = (string) ($_POST['estado'] ?? 'Activo');

$errores = [];
if ($nombres === '' || mb_strlen($nombres) > 60)     $errores['nombres']   = 'Ingresa los nombres (máx. 60 caracteres).';
if ($apellidos === '' || mb_strlen($apellidos) > 60) $errores['apellidos'] = 'Ingresa los apellidos (máx. 60 caracteres).';
if (!preg_match('/^[A-Za-z0-9\-\.]{4,20}$/', $documento)) {
    $errores['documento'] = 'Usa de 4 a 20 caracteres: letras, números, puntos o guiones.';
}
if ($correo === '' || mb_strlen($correo) > 120 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores['correo'] = 'Ingresa un correo electrónico válido.';
}
if ($telefono !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $telefono)) {
    $errores['telefono'] = 'Ingresa un teléfono válido (solo números, espacios, + y guiones).';
}
if (!in_array($estado, ['Activo', 'Inactivo'], true)) $errores['estado'] = 'Estado no válido.';

if ($errores) {
    responder(['ok' => false, 'errores' => $errores], 422);
}

/* ---------- Guardado ---------- */
try {
    if (yaExiste($cnx, C_DOCUMENTO, $documento)) $errores['documento'] = 'Ya existe un cliente con este documento.';
    if (yaExiste($cnx, C_CORREO, $correo))       $errores['correo']    = 'Ya existe un cliente con este correo.';
    if ($errores) {
        responder(['ok' => false, 'errores' => $errores], 422);
    }

    $sql = 'INSERT INTO ' . T_CLIENTES . ' ('
         . implode(', ', [C_NOMBRE, C_APELLIDO, C_DOCUMENTO, C_CORREO, C_TELEFONO, C_ESTADO, C_FECHA])
         . ') VALUES (?, ?, ?, ?, ?, ?, NOW())';

    $st = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($st, 'ssssss', $nombres, $apellidos, $documento, $correo, $telefono, $estado);
    mysqli_stmt_execute($st);
    $id = (int) mysqli_insert_id($cnx);
    mysqli_stmt_close($st);

    // Código visible tipo CLI-0006
    $codigo = sprintf('CLI-%04d', $id);
    if (C_CODIGO !== null) {
        $st = mysqli_prepare($cnx, 'UPDATE ' . T_CLIENTES . ' SET ' . C_CODIGO . ' = ? WHERE ' . C_ID . ' = ?');
        mysqli_stmt_bind_param($st, 'si', $codigo, $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // Nuevo token para el siguiente envío
    $_SESSION['csrf_cliente'] = bin2hex(random_bytes(32));

    responder([
        'ok'      => true,
        'mensaje' => 'El cliente ' . $nombres . ' ' . $apellidos . ' (' . $codigo . ') se registró correctamente.',
        'cliente' => ['id' => $id, 'codigo' => $codigo],
    ]);

} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() === 1062) {   // clave duplicada (índice único)
        responder(['ok' => false, 'errores' => ['documento' => 'Ya existe un cliente con estos datos.']], 422);
    }
    error_log('[cliente_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo registrar el cliente. Inténtalo de nuevo más tarde.'], 500);
} catch (Throwable $ex) {
    error_log('[cliente_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo registrar el cliente. Inténtalo de nuevo más tarde.'], 500);
}
