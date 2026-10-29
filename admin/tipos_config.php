<?php
/**
 * Mapeo con tu base de datos para "Tipos de membresía".
 * Lo usan tipos.php, tipo_crear.php y tipo_estado.php. Ajusta los nombres si difieren.
 *
 * Por tu consulta de clientes.php, el catálogo de planes parece estar en la tabla
 * `membresias` (columnas id_membresia, nombre_plan, estado).
 */
const TM_TABLA    = 'membresias';
const TM_ID       = 'id_membresia';
const TM_NOMBRE   = 'nombre_plan';        // p. ej. Plan Semestral
const TM_DESCRIPCION = 'descripcion';     // p. ej. 'descripcion' si existe la columna; null = se muestra un texto por defecto
const TM_ESTADO   = 'estado';             // 'Activo' | 'Inactivo'
const TM_CODIGO   = null;                 // p. ej. 'codigo' si la tabla tiene columna TMP-001; null = se calcula desde el id

/** Roles que pueden administrar los tipos (valor exacto de $_SESSION['rol']). */
const TM_ROLES_PERMITIDOS = ['Administrador', 'Recepcionista'];

/** Código visible: el de la columna, o TMP-001 calculado desde el id. */
function codigoTipo(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('TMP-%03d', $id);
}
