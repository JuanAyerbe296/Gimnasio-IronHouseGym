<?php
/**
 * Mapeo con tu base de datos para el detalle del cliente (cliente-ver.php).
 * Por tu consulta de clientes.php, la tabla `clientes` tiene id_cliente, nombre, apellido,
 * documento, email y estado. Ajusta el resto si difiere.
 */
const CLI_TABLA     = 'clientes';
const CLI_ID        = 'id_cliente';
const CLI_NOMBRE    = 'nombre';
const CLI_APELLIDO  = 'apellido';
const CLI_DOCUMENTO = 'documento';
const CLI_CORREO    = 'email';
const CLI_TELEFONO  = 'telefono';
const CLI_FECHA     = 'fecha_registro';     // p. ej. 'fecha_registro'
const CLI_ESTADO    = 'estado';             // 'Activo' | 'Inactivo'

// Columnas OPCIONALES: pon el nombre de la columna o null si no existen.
const CLI_CODIGO    = null;                 // p. ej. 'codigo' (CLI-0001); null = se calcula desde el id

// --- Bitácora de entrenamientos (tabla `bitacora`) ---
// De aquí salen el programa del cliente (campo detalles_rutina) y la pestaña "Entrenamiento".
const ENT_TABLA         = 'bitacora';
const ENT_ID            = 'id_bitacora';
const ENT_ID_CLIENTE    = 'id_cliente';
const ENT_FECHA         = 'fecha';
const ENT_GRUPO         = 'grupo_muscular';
const ENT_DETALLES      = 'detalles_rutina';      // programa / rutina asignada
const ENT_OBSERVACIONES = 'observaciones';        // puede ser NULL
// Entrenador: se asume que id_entrenador corresponde a usuarios.id_usuario. Ajusta el JOIN si es otra tabla.
const ENT_JOIN_ENTRENADOR   = 'LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario';
const ENT_NOMBRE_ENTRENADOR = "CONCAT(u.nombre, ' ', u.apellido)";

/** Código visible: el de la columna, o CLI-0001 calculado desde el id. */
function codigoCliente(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('CLI-%04d', $id);
}
