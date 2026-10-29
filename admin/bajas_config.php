<?php
/**
 * Mapeo con tu base de datos para las solicitudes de baja (solicitud-ver.php y solicitud-accion.php).
 * Usa los mismos nombres que la consulta de tu solicitudes.php.
 */

// --- Solicitudes (tabla bajas_membresia) ---
const BAJ_TABLA          = 'bajas_membresia';
const BAJ_ID             = 'id_baja';
const BAJ_ID_SUSCRIPCION = 'id_suscripcion';
const BAJ_FECHA          = 'fecha_baja';
const BAJ_MOTIVO         = 'motivo';
const BAJ_ESTADO         = 'estado';

const BAJ_ESTADO_PENDIENTE = 'Pendiente';
const BAJ_ESTADO_APROBADA  = 'Dada de baja';   // estado que toma la solicitud al aprobarse

// OPCIONAL: columna con el usuario que registró la solicitud (p. ej. 'id_usuario'). null = no se muestra ese dato.
const BAJ_ID_USUARIO = null;

// --- Suscripción del cliente (une cliente y plan) ---
const SUS_TABLA        = 'suscripciones_cliente';
const SUS_ID           = 'id_suscripcion';
const SUS_ID_CLIENTE   = 'id_cliente';
const SUS_ID_MEMBRESIA = 'id_membresia';

// --- Plan de membresía ---
const PLAN_TABLA  = 'membresias';
const PLAN_ID     = 'id_membresia';
const PLAN_NOMBRE = 'nombre_plan';

// El cliente usa las constantes CLI_* de clientes_config.php; el usuario que registró, las U_* de usuarios_config.php.

/** Código visible de la solicitud: SOL-0001 calculado desde el id. */
function codigoBaja(int $id): string
{
    return sprintf('SOL-%04d', $id);
}
