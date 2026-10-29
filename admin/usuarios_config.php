<?php
/**
 * Mapeo con tu base de datos para el detalle y las acciones sobre usuarios.
 * Lo usan usuario-ver.php y usuario-accion.php. Mantén estos nombres iguales a los de usuario_crear.php.
 */
const U_TABLA    = 'usuarios';
const U_ID       = 'id_usuario';
const U_NOMBRE   = 'nombre';
const U_APELLIDO = 'apellido';
const U_CORREO   = 'email';
const U_ROL      = 'rol';               // texto: Administrador | Recepción | Entrenador | Cliente
const U_ESTADO   = 'estado';            // 'Activo' | 'Inactivo'
const U_PASSWORD = 'password';
const U_FECHA    = 'fecha_creacion';    // fecha de creación
const U_CODIGO   = null;                // p. ej. 'codigo' si existe la columna USR-0004; null = se calcula desde el id

/** Roles disponibles en los formularios (se guardan tal cual en la columna de rol). */
const U_ROLES = ['Administrador', 'Recepcionista', 'Entrenador', 'Cliente'];

const U_PASSWORD_PLANO = true;          // true = texto plano (como tu base actual); false = password_hash()

/** Código visible: el de la columna, o USR-0004 calculado desde el id. */
function codigoUsuario(int $id, ?string $codigoBd = null): string
{
    return ($codigoBd !== null && $codigoBd !== '') ? $codigoBd : sprintf('USR-%04d', $id);
}

/** Permisos que se muestran en la tarjeta "Permisos asociados" según el rol. */
function permisosPorRol(string $rol): array
{
    $mapa = [
        'Administrador' => [
            'Gestionar usuarios y cuentas',
            'Gestionar clientes y membresías',
            'Administrar tipos de membresía',
            'Aprobar solicitudes de baja',
            'Consultar la bitácora de auditoría',
            'Gestionar su perfil',
        ],
        'Recepcionista' => [
            'Registrar y consultar clientes',
            'Registrar membresías',
            'Consultar tipos de membresía',
            'Gestionar solicitudes de baja',
            'Gestionar su perfil',
        ],
        'Entrenador' => [
            'Consultar clientes asignados',
            'Evaluar y planificar entrenamientos',
            'Gestionar rutinas',
            'Dar seguimiento a entrenamientos',
            'Gestionar su perfil',
        ],
        'Cliente' => [
            'Consultar rutinas propias',
            'Registrar entrenamientos',
            'Gestionar su perfil',
        ],
    ];
    $mapa['Recepcionista'] = $mapa['Recepcionista'];   // alias que aparece en tu código

    return $mapa[$rol] ?? [];
}

/** Contraseña temporal de 10 caracteres con mayúscula, minúscula y número. */
function passwordTemporalUsuario(): string
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
