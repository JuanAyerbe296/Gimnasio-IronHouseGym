<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

/**
 * ============================================================
 *  MAPEO CON TU BASE DE DATOS  (único lugar que debes ajustar)
 * ============================================================
 * La bitácora es de solo lectura: este archivo únicamente hace SELECT.
 */
const BIT_FROM = 'bitacora_gym b INNER JOIN usuarios u ON u.id_usuario = b.id_usuario';

const BIT_ID      = 'b.id_bitacora';
const BIT_CODIGO  = 'b.codigo';          // p. ej. AUD-0001
const BIT_TIPO    = 'b.tipo';            // p. ej. Aprobación de baja
const BIT_DESC    = 'b.descripcion';
const BIT_USUARIO = "CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo"; // quien realizó la acción
const BIT_FECHA   = 'b.fecha_hora';      // DATETIME

/**
 * Registros de la bitácora, más recientes primero.
 * Cada fila: [id, codigo, tipo, descripcion, usuario, fecha_hora 'Y-m-d H:i:s']
 */
function listarBitacora(string $q = '', string $fecha = '', string $tipo = '', int $limite = 100): array
{
    $sql = 'SELECT ' . implode(', ', [BIT_ID, BIT_CODIGO, BIT_TIPO, BIT_DESC, BIT_USUARIO, BIT_FECHA])
         . ' FROM ' . BIT_FROM . ' WHERE 1=1';
    $params = [];

    if ($q !== '') {
        $sql .= ' AND (' . BIT_USUARIO . ' LIKE :q1 OR ' . BIT_CODIGO . ' LIKE :q2 OR ' . BIT_DESC . ' LIKE :q3)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $sql .= ' AND DATE(' . BIT_FECHA . ') = :fecha';
        $params[':fecha'] = $fecha;
    }
    if ($tipo !== '') {
        $sql .= ' AND ' . BIT_TIPO . ' = :tipo';
        $params[':tipo'] = $tipo;
    }

    $sql .= ' ORDER BY ' . BIT_FECHA . ' DESC, ' . BIT_ID . ' DESC LIMIT ' . max(1, $limite);

    $st = db()->prepare($sql);
    $st->execute($params);

    return array_map(
        fn(array $f): array => [(int) $f[0], (string) $f[1], (string) $f[2], (string) $f[3], (string) $f[4], (string) $f[5]],
        $st->fetchAll()
    );
}

/** Tipos distintos de acción, para llenar el desplegable de filtro. */
function listarTiposBitacora(): array
{
    $st = db()->query('SELECT DISTINCT ' . BIT_TIPO . ' FROM ' . BIT_FROM . ' ORDER BY ' . BIT_TIPO);
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}
