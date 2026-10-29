<?php
/**
 * Mapeo con tu base de datos para el detalle de la rutina (rutina-ver.php).
 * Los nombres son SUPUESTOS: ajústalos a los de tus tablas.
 * Las columnas marcadas como OPCIONALES aceptan null si no existen (se mostrará "—").
 */

// --- Rutinas ---
const RUT_TABLA         = 'bitacora';
const RUT_ID            = 'id_bitacora';
const RUT_ID_CLIENTE    = 'id_cliente';          // -> CLI_TABLA (clientes_config.php)
const RUT_ID_ENTRENADOR = 'id_entrenador';       // -> usuarios.id_usuario (ver ENTRENADOR_* abajo)
const RUT_FECHA         = 'fecha';               // p. ej. 'fecha_creacion'
const RUT_NOMBRE        = 'grupo_muscular';      // p. ej. "Fuerza base · Semana 1"
const RUT_PROGRAMA      = 'detalles_rutina';     // p. ej. 'programa' (Build, Balance, Burn...)
const RUT_ESTADO        = 'estado';              // 'Publicada' | 'Borrador'

// OPCIONALES
const RUT_CODIGO   = null;                       // p. ej. 'codigo' (RUT-0148); null = se calcula desde el id

// Entrenador responsable: nombre completo desde la tabla de usuarios
const ENTRENADOR_JOIN   = 'LEFT JOIN usuarios u ON u.id_usuario = r.id_entrenador';
const ENTRENADOR_NOMBRE = "CONCAT(u.nombre, ' ', u.apellido)";

// --- Ejercicios de la rutina ---
const EJ_TABLA    = 'rutina_ejercicios';
const EJ_ID       = 'id_ejercicio';
const EJ_ID_RUTINA = 'id_rutina';
const EJ_NOMBRE   = 'nombre';
const EJ_SERIES   = 'series';
const EJ_REPS     = 'repeticiones';

// OPCIONALES
const EJ_DESCANSO = null;                        // p. ej. 'descanso'
const EJ_NOTAS    = null;                        // p. ej. 'indicaciones'
const EJ_ORDEN    = null;                        // p. ej. 'orden'; null = se ordena por el id

/** Código visible: el de la columna, o RUT-0148 calculado desde el id. */
function codigoRutina(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('RUT-%04d', $id);
}
