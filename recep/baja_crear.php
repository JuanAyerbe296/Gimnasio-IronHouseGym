<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';          // deja disponible $cnx (mysqli)
require_once __DIR__ . '/bajas_config.php';
require_once __DIR__ . '/clientes_config.php';

/** Roles que pueden registrar solicitudes de baja (valor exacto guardado en $_SESSION['rol']). */
const ROLES_PERMITIDOS = ['Administrador', 'Recepcionista'];

/* ---------- Utilidades ---------- */
function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Devuelve la fecha si es válida en formato Y-m-d; si no, null. */
function fechaValida(string $valor): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $valor);
    return ($d && $d->format('Y-m-d') === $valor) ? $d : null;
}

/** Devuelve el primer valor de la consulta (o null si no hay filas). */
function valorUnico(mysqli $cnx, string $sql, string $tipos, array $params): ?string
{
    $st = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($st, $tipos, ...$params);
    mysqli_stmt_execute($st);
    mysqli_stmt_bind_result($st, $valor);
    $hay = mysqli_stmt_fetch($st);
    mysqli_stmt_close($st);
    return $hay ? (string) $valor : null;
}

/* ---------- Acceso y CSRF ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ROLES_PERMITIDOS, true)) {
    responder(['ok' => false, 'mensaje' => 'No tienes permiso para registrar solicitudes de baja.'], 403);
}
if (!hash_equals((string) ($_SESSION['csrf_solicitud'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    responder(['ok' => false, 'mensaje' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.'], 419);
}

/* ---------- Validación ---------- */
$idCliente = (int) ($_POST['cliente'] ?? 0);
$idSus     = (int) ($_POST['membresia'] ?? 0);
$fecha     = fechaValida(trim((string) ($_POST['fecha'] ?? '')));
$estado    = (string) ($_POST['estado'] ?? BAJ_ESTADO_PENDIENTE);
$motivo    = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['motivo'] ?? '')));

// Solo el administrador puede registrar una baja ya aprobada; los demás, solo pendientes.
$estadosPermitidos = [BAJ_ESTADO_PENDIENTE];
if ($rol === 'Administrador') {
    $estadosPermitidos[] = BAJ_ESTADO_APROBADA;
}

$errores = [];
if ($idCliente <= 0) $errores['cliente']   = 'Selecciona un cliente.';
if ($idSus <= 0)     $errores['membresia'] = 'Selecciona una membresía.';
if (!$fecha) {
    $errores['fecha'] = 'Ingresa una fecha válida.';
} elseif ($fecha->format('Y-m-d') > date('Y-m-d')) {
    $errores['fecha'] = 'La fecha de solicitud no puede ser futura.';
}
if (!in_array($estado, $estadosPermitidos, true)) {
    $errores['estado'] = 'Estado no válido.';
}
if (mb_strlen($motivo) < 5) {
    $errores['motivo'] = 'Describe el motivo de la baja (mínimo 5 caracteres).';
} elseif (mb_strlen($motivo) > 500) {
    $errores['motivo'] = 'El motivo no puede superar 500 caracteres.';
}

if ($errores) {
    responder(['ok' => false, 'errores' => $errores], 422);
}

$fFecha = $fecha->format('Y-m-d');

/* ---------- Guardado ---------- */
try {
    // La membresía debe pertenecer al cliente elegido, y el cliente debe estar activo
    $nombreCliente = valorUnico(
        $cnx,
        'SELECT CONCAT(c.' . CLI_NOMBRE . ", ' ', c." . CLI_APELLIDO . ') FROM ' . SUS_TABLA . ' s'
        . ' INNER JOIN ' . CLI_TABLA . ' c ON c.' . CLI_ID . ' = s.' . SUS_ID_CLIENTE
        . ' WHERE s.' . SUS_ID . ' = ? AND s.' . SUS_ID_CLIENTE . ' = ? AND c.' . CLI_ESTADO . " = 'Activo' LIMIT 1",
        'ii', [$idSus, $idCliente]
    );
    if ($nombreCliente === null) {
        responder(['ok' => false, 'errores' => ['membresia' => 'La membresía no corresponde al cliente seleccionado.']], 422);
    }

    // No puede haber otra baja pendiente o aprobada para la misma membresía
    $pendiente = BAJ_ESTADO_PENDIENTE;
    $aprobada  = BAJ_ESTADO_APROBADA;
    $duplicada = valorUnico(
        $cnx,
        'SELECT 1 FROM ' . BAJ_TABLA . ' WHERE ' . BAJ_ID_SUSCRIPCION . ' = ? AND ' . BAJ_ESTADO . ' IN (?, ?) LIMIT 1',
        'iss', [$idSus, $pendiente, $aprobada]
    );
    if ($duplicada !== null) {
        responder(['ok' => false, 'errores' => ['membresia' => 'Esta membresía ya tiene una solicitud de baja registrada.']], 422);
    }

    // Inserción (si configuraste la columna del usuario que registra, también se guarda)
    $columnas = [BAJ_ID_SUSCRIPCION, BAJ_FECHA, BAJ_MOTIVO, BAJ_ESTADO];
    $tipos    = 'isss';
    $valores  = [$idSus, $fFecha, $motivo, $estado];
    if (BAJ_ID_USUARIO !== null) {
        $columnas[] = BAJ_ID_USUARIO;
        $tipos     .= 'i';
        $valores[]  = (int) ($_SESSION['id_usuario'] ?? 0);
    }

    $sql = 'INSERT INTO ' . BAJ_TABLA . ' (' . implode(', ', $columnas) . ') VALUES ('
         . implode(', ', array_fill(0, count($columnas), '?')) . ')';
    $st = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($st, $tipos, ...$valores);
    mysqli_stmt_execute($st);
    $id = (int) mysqli_insert_id($cnx);
    mysqli_stmt_close($st);

    // Nuevo token para el siguiente envío
    $_SESSION['csrf_solicitud'] = bin2hex(random_bytes(32));

    $codigo = codigoBaja($id);
    responder([
        'ok'        => true,
        'mensaje'   => 'La solicitud ' . $codigo . ' se registró para ' . $nombreCliente . ' con estado ' . $estado . '.',
        'solicitud' => ['id' => $id, 'codigo' => $codigo],
    ]);

} catch (Throwable $ex) {
    error_log('[baja_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo registrar la solicitud. Inténtalo de nuevo más tarde.'], 500);
}
