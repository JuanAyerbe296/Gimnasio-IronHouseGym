<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';           // debe dejar disponible la conexión mysqli en $cnx
require_once __DIR__ . '/membresias_config.php';  // nombres de tablas y columnas

/** Roles que pueden registrar membresías (valor exacto guardado en $_SESSION['rol']). */
const ROLES_PERMITIDOS = ['Administrador', 'Recepción', 'Recepcionista'];

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
if (!in_array($_SESSION['rol'] ?? '', ROLES_PERMITIDOS, true)) {
    responder(['ok' => false, 'mensaje' => 'No tienes permiso para registrar membresías.'], 403);
}
if (!hash_equals((string) ($_SESSION['csrf_membresia'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    responder(['ok' => false, 'mensaje' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.'], 419);
}

/* ---------- Validación ---------- */
$idCliente = (int) ($_POST['cliente'] ?? 0);
$idTipo    = (int) ($_POST['tipo'] ?? 0);
$inicio    = fechaValida(trim((string) ($_POST['fecha_inicio'] ?? '')));
$venc      = fechaValida(trim((string) ($_POST['fecha_vencimiento'] ?? '')));

$errores = [];
if ($idCliente <= 0) $errores['cliente'] = 'Selecciona un cliente.';
if ($idTipo <= 0)    $errores['tipo']    = 'Selecciona un tipo de membresía.';
if (!$inicio)        $errores['fecha_inicio'] = 'Ingresa una fecha de inicio válida.';
if (!$venc)          $errores['fecha_vencimiento'] = 'Ingresa una fecha de vencimiento válida.';
if ($inicio && $venc && $venc <= $inicio) {
    $errores['fecha_vencimiento'] = 'El vencimiento debe ser posterior a la fecha de inicio.';
}

if ($errores) {
    responder(['ok' => false, 'errores' => $errores], 422);
}

$fInicio = $inicio->format('Y-m-d');
$fVenc   = $venc->format('Y-m-d');

/* ---------- Guardado ---------- */
try {
    // El cliente debe existir y estar activo
    $nombreCliente = valorUnico(
        $cnx,
        'SELECT CONCAT(' . CL_NOMBRE . ", ' ', " . CL_APELLIDO . ') FROM ' . T_CLIENTES_M
        . ' WHERE ' . CL_ID . ' = ? AND ' . CL_ESTADO . " = 'Activo' LIMIT 1",
        'i', [$idCliente]
    );
    if ($nombreCliente === null) $errores['cliente'] = 'El cliente seleccionado no existe o está inactivo.';

    // El tipo de membresía debe existir
    $nombreTipo = valorUnico(
        $cnx,
        'SELECT ' . M_NOMBRE . ' FROM ' . T_MEMBRESIAS . ' WHERE ' . M_ID . ' = ? LIMIT 1',
        'i', [$idTipo]
    );
    if ($nombreTipo === null) $errores['tipo'] = 'El tipo de membresía seleccionado no existe.';

    if ($errores) {
        responder(['ok' => false, 'errores' => $errores], 422);
    }

    // El cliente no puede tener otra membresía vigente que se cruce con estas fechas
    $cruce = valorUnico(
        $cnx,
        'SELECT 1 FROM ' . T_TIPOS_M
        . ' WHERE ' . TP_ID_CLIENTE . ' = ? AND ' . TP_ESTADO . " = 'Vigente'"
        . ' AND ' . TP_FECHA_INICIO . ' <= ? AND ' . TP_FECHA_VENC . ' >= ? LIMIT 1',
        'iss', [$idCliente, $fVenc, $fInicio]
    );
    if ($cruce !== null) {
        responder(['ok' => false, 'errores' => ['cliente' => 'Este cliente ya tiene una membresía vigente en esas fechas.']], 422);
    }

    // Estado según la fecha de vencimiento
    $estado = ($fVenc >= date('Y-m-d')) ? 'Vigente' : 'Vencida';

    $sql = 'INSERT INTO ' . T_TIPOS_M . ' ('
         . implode(', ', [TP_ID_CLIENTE, TP_ID_TIPO, TP_FECHA_INICIO, TP_FECHA_VENC, TP_ESTADO])
         . ') VALUES (?, ?, ?, ?, ?)';

    $st = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($st, 'iisss', $idCliente, $idTipo, $fInicio, $fVenc, $estado);
    mysqli_stmt_execute($st);
    $id = (int) mysqli_insert_id($cnx);
    mysqli_stmt_close($st);

    // Código visible tipo MBR-0006
    $codigo = sprintf('MBR-%04d', $id);
    if (TP_CODIGO !== null) {
        $st = mysqli_prepare($cnx, 'UPDATE ' . T_TIPOS_M . ' SET ' . TP_CODIGO . ' = ? WHERE ' . TP_ID . ' = ?');
        mysqli_stmt_bind_param($st, 'si', $codigo, $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // Nuevo token para el siguiente envío
    $_SESSION['csrf_membresia'] = bin2hex(random_bytes(32));

    responder([
        'ok'        => true,
        'mensaje'   => 'La membresía ' . $codigo . ' (' . $nombreTipo . ') se registró para ' . $nombreCliente . '.',
        'membresia' => ['id' => $id, 'codigo' => $codigo, 'estado' => $estado],
    ]);

} catch (Throwable $ex) {
    error_log('[membresia_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo registrar la membresía. Inténtalo de nuevo más tarde.'], 500);
}
