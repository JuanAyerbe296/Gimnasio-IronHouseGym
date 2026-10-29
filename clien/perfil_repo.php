<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

/**
 * ============================================================
 *  MAPEO CON TU BASE DE DATOS
 * ============================================================
 */
const PERFIL_TABLA      = 'usuarios';
const PERFIL_COL_ID     = 'id_usuario';
const PERFIL_COL_HASH   = 'password';   // columna donde se guarda la contraseña

/**
 * true  = las contraseñas están en TEXTO PLANO en tu base (como ahora).
 * false = están cifradas con password_hash() de PHP (recomendado).
 */
const PERFIL_PASSWORD_PLANO = true;

/**
 * Valida las reglas de la nueva contraseña. Devuelve un mensaje de error o null si es válida.
 */
function validarNuevaPassword(string $pass): ?string
{
    if (mb_strlen($pass) < 8)            return 'Debe tener al menos 8 caracteres.';
    if (!preg_match('/\p{Lu}/u', $pass)) return 'Debe incluir al menos una mayúscula.';
    if (!preg_match('/\d/', $pass))      return 'Debe incluir al menos un número.';
    return null;
}

/**
 * Cambia la contraseña del usuario si la actual es correcta.
 * Devuelve true si se actualizó, false si la contraseña actual no coincide.
 */
function cambiarPassword(int $idUsuario, string $actual, string $nueva): bool
{
    $pdo = db();

    $st = $pdo->prepare('SELECT ' . PERFIL_COL_HASH . ' FROM ' . PERFIL_TABLA . ' WHERE ' . PERFIL_COL_ID . ' = :id_usuario');
    $st->execute([':id_usuario' => $idUsuario]);
    $hash = $st->fetchColumn();

    if (!is_string($hash) || !passwordCoincide($actual, $hash)) {
        return false;
    }

    $up = $pdo->prepare('UPDATE ' . PERFIL_TABLA . ' SET ' . PERFIL_COL_HASH . ' = :p WHERE ' . PERFIL_COL_ID . ' = :id_usuario');
    $up->execute([':p' => prepararPassword($nueva), ':id_usuario' => $idUsuario]);
    return true;
}

/** Compara la contraseña escrita con la guardada, según el formato configurado. */
function passwordCoincide(string $ingresada, string $guardada): bool
{
    return PERFIL_PASSWORD_PLANO
        ? hash_equals($guardada, $ingresada)       // comparación de tiempo constante
        : password_verify($ingresada, $guardada);
}
 
/** Devuelve el valor a guardar en la base, según el formato configurado. */
function prepararPassword(string $password): string
{
    return PERFIL_PASSWORD_PLANO ? $password : password_hash($password, PASSWORD_DEFAULT);
}