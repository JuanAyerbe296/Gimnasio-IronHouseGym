<?php
/**
 * Mapeo con tu base de datos para el módulo de membresías.
 * Lo usan modal_registrar_membresia.php (listas) y membresia_crear.php (guardado).
 * Ajusta aquí los nombres si tus tablas o columnas se llaman distinto.
 */

// --- Membresías ---
const T_MEMBRESIAS     = 'membresias';
const M_ID             = 'id_membresia';
const M_NOMBRE        = 'nombre_plan';
//const M_ID_CLIENTE     = 'id_cliente';
//const M_ID_TIPO        = 'id_tipo_membresia';
//const M_FECHA_INICIO   = 'fecha_inicio';
//const M_FECHA_VENC     = 'fecha_vencimiento';
//const M_ESTADO         = 'estado';            // Vigente | Vencida | Dada de baja

// --- Clientes ---
const T_CLIENTES_M     = 'clientes';
const CL_ID            = 'id_cliente';
const CL_NOMBRE        = 'nombre';
const CL_APELLIDO      = 'apellido';
const CL_ESTADO        = 'estado';            // solo se listan los 'Activo'

// --- Tipos de membresía ---
const T_TIPOS_M        = 'suscripciones_cliente';
const TP_ID            = 'id_suscripcion';
const TP_ID_CLIENTE     = 'id_cliente';
const TP_ID_TIPO        = 'id_membresia';
const TP_FECHA_INICIO   = 'fecha_inicio';
const TP_FECHA_VENC     = 'fecha_fin';
const TP_ESTADO         = 'estado_suscripcion';
const TP_CODIGO         = null;            // p. ej. MBR-0006. Pon null si no existe la columna
//const TP_NOMBRE        = 'nombre';            // p. ej. Plan Trimestral

/** Clientes activos para el desplegable: [[id, 'Nombre Apellido'], ...] */
function clientesParaSelect(mysqli $cnx): array
{
    $sql = 'SELECT ' . CL_ID . ", CONCAT(" . CL_NOMBRE . ", ' ', " . CL_APELLIDO . ') FROM ' . T_CLIENTES_M
         . ' WHERE ' . CL_ESTADO . " = 'Activo' ORDER BY " . CL_NOMBRE . ', ' . CL_APELLIDO;
    $res = mysqli_query($cnx, $sql);
    return $res ? mysqli_fetch_all($res, MYSQLI_NUM) : [];
}

/** Tipos de membresía para el desplegable: [[id, 'Plan X'], ...] */
function tiposParaSelect(mysqli $cnx): array
{
    $res = mysqli_query($cnx, 'SELECT ' . M_ID . ', ' . M_NOMBRE . ' FROM ' . T_MEMBRESIAS . ' ORDER BY ' . M_NOMBRE);
    return $res ? mysqli_fetch_all($res, MYSQLI_NUM) : [];
}

// --- Solicitudes de baja relacionadas (tarjeta "Solicitudes relacionadas" del detalle) ---
const SOLB_TABLA        = 'solicitudes_baja';
const SOLB_ID           = 'id_solicitud';
const SOLB_ID_MEMBRESIA = 'id_membresia';     // columna que enlaza la solicitud con la membresía
const SOLB_CODIGO       = null;               // p. ej. 'codigo' (SOL-0001); null = se calcula desde el id
const SOLB_FECHA        = 'fecha_solicitud';
const SOLB_MOTIVO       = 'motivo';
const SOLB_ESTADO       = 'estado';           // se buscan las que estén en 'Pendiente'

/** Código visible de la membresía: el de la columna, o MBR-0001 calculado desde el id. */
function codigoMembresia(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('MBR-%04d', $id);
}
