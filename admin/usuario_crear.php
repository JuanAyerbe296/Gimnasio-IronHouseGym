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
const T_USUARIOS   = 'usuarios';
const C_ID         = 'id_usuario';
const C_NOMBRE     = 'nombre';
const C_APELLIDO   = 'apellido';
const C_CORREO     = 'email';
const C_ROL        = 'rol';
const C_ESTADO     = 'estado';          // guarda 'Activo' / 'Inactivo'
const C_PASSWORD   = 'password';
const C_FECHA      = 'fecha_creacion';  // se llena con NOW()
const C_CODIGO     = null;          // p. ej. USR-0006. Pon null si tu tabla no tiene esta columna

const PASSWORD_PLANO = true;            // true = texto plano (como tu base actual); false = password_hash()

/** Texto del formulario => valor que se guarda en la columna de rol (texto o id numérico). */
$MAPA_ROLES = [
    'Administrador' => 'Administrador',
    'Recepcionista'     => 'Recepcionista',
    'Entrenador'    => 'Entrenador',
    'Cliente'       => 'Cliente',
];

/* ---------- Utilidades ---------- */
function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Contraseña temporal de 10 caracteres con mayúscula, minúscula y número. */
function passwordTemporal(): string
{
    $may = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $min = 'abcdefghijkmnpqrstuvwxyz';
    $num = '23456789';
    $todo = $may . $min . $num;

    $chars = [$may[random_int(0, strlen($may) - 1)], $min[random_int(0, strlen($min) - 1)], $num[random_int(0, strlen($num) - 1)]];
    while (count($chars) < 10) {
        $chars[] = $todo[random_int(0, strlen($todo) - 1)];
    }
    shuffle($chars);
    return implode('', $chars);
}

/* ---------- Acceso y CSRF ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    responder(['ok' => false, 'mensaje' => 'No tienes permiso para crear usuarios.'], 403);
}
if (!hash_equals((string) ($_SESSION['csrf_usuario'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    responder(['ok' => false, 'mensaje' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.'], 419);
}

/* ---------- Validación ---------- */
$nombres   = trim((string) ($_POST['nombres'] ?? ''));
$apellidos = trim((string) ($_POST['apellidos'] ?? ''));
$correo    = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
$rol       = (string) ($_POST['rol'] ?? '');
$estado    = (string) ($_POST['estado'] ?? 'Activo');

$errores = [];
if ($nombres === '' || mb_strlen($nombres) > 60)     $errores['nombres']   = 'Ingresa los nombres (máx. 60 caracteres).';
if ($apellidos === '' || mb_strlen($apellidos) > 60) $errores['apellidos'] = 'Ingresa los apellidos (máx. 60 caracteres).';
if ($correo === '' || mb_strlen($correo) > 120 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores['correo'] = 'Ingresa un correo electrónico válido.';
}
if (!array_key_exists($rol, $MAPA_ROLES))            $errores['rol']       = 'Selecciona un rol válido.';
if (!in_array($estado, ['Activo', 'Inactivo'], true)) $errores['estado']   = 'Estado no válido.';

if ($errores) {
    responder(['ok' => false, 'errores' => $errores], 422);
}

/* ---------- Guardado ---------- */
try {
    // ¿Correo ya registrado?
    $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . T_USUARIOS . ' WHERE ' . C_CORREO . ' = ? LIMIT 1');
    mysqli_stmt_bind_param($st, 's', $correo);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $existe = mysqli_stmt_num_rows($st) > 0;
    mysqli_stmt_close($st);

    if ($existe) {
        responder(['ok' => false, 'errores' => ['correo' => 'Ya existe una cuenta con este correo.']], 422);
    }

    $temporal  = passwordTemporal();
    $guardar   = PASSWORD_PLANO ? $temporal : password_hash($temporal, PASSWORD_DEFAULT);
    $valorRol  = (string) $MAPA_ROLES[$rol];

    $sql = 'INSERT INTO ' . T_USUARIOS . ' ('
         . implode(', ', [C_NOMBRE, C_APELLIDO, C_CORREO, C_ROL, C_ESTADO, C_PASSWORD, C_FECHA])
         . ') VALUES (?, ?, ?, ?, ?, ?, NOW())';

    $st = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($st, 'ssssss', $nombres, $apellidos, $correo, $valorRol, $estado, $guardar);
    mysqli_stmt_execute($st);
    $id = (int) mysqli_insert_id($cnx);
    mysqli_stmt_close($st);

    // Código visible tipo USR-0006
    $codigo = sprintf('USR-%04d', $id);
    if (C_CODIGO !== null) {
        $st = mysqli_prepare($cnx, 'UPDATE ' . T_USUARIOS . ' SET ' . C_CODIGO . ' = ? WHERE ' . C_ID . ' = ?');
        mysqli_stmt_bind_param($st, 'si', $codigo, $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // Nuevo token para el siguiente envío
    $_SESSION['csrf_usuario'] = bin2hex(random_bytes(32));

    responder([
        'ok'                => true,
        'usuario'           => ['id' => $id, 'codigo' => $codigo, 'nombre' => $nombres . ' ' . $apellidos, 'correo' => $correo],
        'password_temporal' => $temporal,
    ]);

} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() === 1062) {   // clave duplicada (índice único)
        responder(['ok' => false, 'errores' => ['correo' => 'Ya existe una cuenta con este correo.']], 422);
    }
    error_log('[usuario_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo crear el usuario. Inténtalo de nuevo más tarde.'], 500);
} catch (Throwable $ex) {
    error_log('[usuario_crear.php] ' . $ex->getMessage());
    responder(['ok' => false, 'mensaje' => 'No se pudo crear el usuario. Inténtalo de nuevo más tarde.'], 500);
}
