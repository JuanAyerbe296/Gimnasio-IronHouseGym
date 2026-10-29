<?php
/**
 * Mapeo con tu base de datos para el detalle de la bitácora (bitacora-detalle.php).
 * Usa los mismos nombres que tu consulta de bitacora.php.
 */
const AUD_FROM    = 'bitacora_gym b INNER JOIN usuarios u ON u.id_usuario = b.id_usuario';
const AUD_ID      = 'b.id_bitacora';
const AUD_CODIGO  = 'b.codigo';                              // p. ej. AUD-0001
const AUD_TIPO    = 'b.tipo';                                // p. ej. Aprobación de baja
const AUD_DESC    = 'b.descripcion';
const AUD_USUARIO = "CONCAT(u.nombre, ' ', u.apellido)";     // usuario responsable
const AUD_FECHA   = 'b.fecha_hora';                          // DATETIME

// Columnas OPCIONALES: pon el nombre de la columna (p. ej. 'b.registro_afectado') o null si no existen.
const AUD_REGISTRO  = null;   // código del registro afectado, p. ej. MBR-0291
const AUD_RESULTADO = null;   // texto del resultado, p. ej. "Operación completada correctamente"

/** Código visible: el de la columna, o AUD-0001 calculado desde el id. */
function codigoAuditoria(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('AUD-%04d', $id);
}
