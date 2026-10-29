<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'usuarios';
$solicitudesBaja = 3;

$menu = [
    'inicio'      => ['Inicio', 'home', 'administrador_panel.php'],
    'usuarios'    => ['Usuarios', 'users', 'usuarios.php'],
    'clientes'    => ['Clientes', 'user', 'clientes.php'],
    'membresias'  => ['Membresías', 'card', 'membresias.php'],
    'tiposd'       => ['Tipos de membresía', 'doc', 'tipos.php'],
    'rutinas'     => ['Rutinas', 'dumbbell', 'rutinas.php'],
    'solicitudes' => ['Solicitudes de baja', 'x-circle', 'solicitudes.php'],
    'bitacora'    => ['Bitácora de auditoría', 'shield', 'bitacora.php'],
    'perfil'      => ['Mi perfil', 'user', 'perfil.php'],
];

$roles = ['Administrador', 'Recepcionista', 'Entrenador', 'Cliente'];

$mesesCortos = [
    'ene', 'feb', 'mar', 'abr', 'may', 'jun',
    'jul', 'ago', 'sep', 'oct', 'nov', 'dic'
];

function fechaCorta(string $fecha, array $meses): string
{
    $d = new DateTimeImmutable($fecha);

    return $d->format('d') . ' '
        . $meses[(int)$d->format('n') - 1] . ' '
        . $d->format('Y');
}

// Filtros del formulario.
$q = trim((string)($_GET['q'] ?? ''));
$fRol = trim((string)($_GET['rol'] ?? ''));
$fEstado = trim((string)($_GET['estado'] ?? ''));

$usuarios = [];
$errorDb = '';

try {
    $sql = "SELECT id_usuario, nombre, apellido, email,
                   rol, estado, fecha_creacion
            FROM usuarios
            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        $sql .= " AND (
            CAST(id_usuario AS CHAR) LIKE ?
            OR nombre LIKE ?
            OR apellido LIKE ?
            OR CONCAT(nombre, ' ', apellido) LIKE ?
            OR email LIKE ?
        )";

        $busqueda = '%' . $q . '%';

        $tipos .= 'sssss';
        $parametros = [
            $busqueda,
            $busqueda,
            $busqueda,
            $busqueda,
            $busqueda
        ];
    }

    if (in_array($fRol, $roles, true)) {
        $sql .= " AND rol = ?";
        $tipos .= 's';
        $parametros[] = $fRol;
    }

    if ($fEstado === 'activo') {
        $sql .= " AND estado = 'Activo'";
    } elseif ($fEstado === 'inactivo') {
        $sql .= " AND estado = 'Inactivo'";
    }

    $sql .= " ORDER BY id_usuario DESC";

    $stmt = mysqli_prepare($cnx, $sql);

    if (!empty($parametros)) {
        $bind = [$tipos];

        foreach ($parametros as $i => &$valor) {
            $bind[] = &$valor;
        }

        unset($valor);

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bind
        );
    }

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $usuarios[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar los usuarios.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
</head>
<body>
<div class="layout">

  <!-- ============ SIDEBAR ============ -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">IH</div>
      <div class="brand-text">
        <span class="brand-name">IRON HOUSE</span>
        <span class="brand-sub">GYM</span>
      </div>
    </div>

    <p class="nav-title">Navegación</p>
    <nav class="nav">
      <?php foreach ($menu as $clave => [$texto, $ico, $url]): ?>
        <a href="<?= e($url) ?>" class="nav-item <?= $clave === $paginaActual ? 'is-active' : '' ?>">
          <?= icono($ico, 22) ?>
          <span><?= e($texto) ?></span>
          <?php if ($clave === 'solicitudes' && $solicitudesBaja > 0): ?>
            <span class="badge-count"><?= $solicitudesBaja ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <span class="avatar avatar-dark"><?= e(iniciales($usuario['nombre'])) ?></span>
        <div>
          <strong><?= e($usuario['nombre']) ?></strong>
          <small><?= e($usuario['rol']) ?></small>
        </div>
      </div>
      <a href="logout.php" class="nav-item" data-abrir-modal="modal-cerrar-sesion">
        <?= icono('logout', 22) ?><span>Cerrar sesión</span>
      </a>
    </div>
  </aside>

  <!-- ============ CONTENIDO ============ -->
  <div class="main">
    <header class="topbar">
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>USUARIOS</b></div>
      <div class="topbar-right">
        <span class="demo-label">VISTA DE DEMO</span>
        <select class="role-select" aria-label="Rol de la vista de demo" onchange="redirigir(this)">
          <option value="administrador_panel.php" selected>Administrador</option>
        </select>
        <button class="bell" aria-label="Notificaciones">
          <?= icono('bell', 22) ?><i class="bell-dot"></i>
        </button>
        <span class="divider"></span>
        <div class="user-chip">
          <span class="avatar avatar-dark"><?= e(iniciales($usuario['nombre'])) ?></span>
          <div>
            <strong><?= e($usuario['nombre']) ?></strong>
            <small><?= e($usuario['rol']) ?></small>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <div class="page-head">
        <div>
          <p class="eyebrow">ADMINISTRACIÓN</p>
          <h1>Gestión de usuarios</h1>
          <p class="lead lead-tight">Administra accesos, roles y estados de las cuentas.</p>
        </div>
        <button type="button" class="btn btn-red btn-icon" data-abrir-modal="modal-nuevo-usuario">
          <?= icono('plus', 20) ?>Nuevo usuario
        </button>
      </div>

      <section class="panel panel-table">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por código, nombre o correo…">
          </label>

          <label class="input input-select">
            <select name="rol" onchange="this.form.submit()" aria-label="Filtrar por rol">
              <option value="">Todos los roles</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>" <?= $fRol === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="input input-select">
            <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado">
              <option value="">Todos los estados</option>
              <option value="activo"   <?= $fEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= $fEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </label>
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre completo</th>
                <th>Correo electrónico</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Fecha de registro</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($errorDb !== ''): ?>
              <tr>
                <td colspan="7"><?= e($errorDb) ?></td>
              </tr>

            <?php elseif (empty($usuarios)): ?>
              <tr>
                <td colspan="7">
                  No se encontraron usuarios.
                  <a href="usuarios.php">Limpiar filtros</a>
                </td>
              </tr>

            <?php else: ?>
              <?php foreach ($usuarios as $u): ?>
                <?php
                $id = (int)$u['id_usuario'];
                $nombreCompleto = $u['nombre'] . ' ' . $u['apellido'];
                $activo = $u['estado'] === 'Activo';
                ?>

                <tr>
                  <td class="mono">
                    <?= $id ?>
                  </td>

                  <td>
                    <div class="person">
                      <span class="avatar avatar-md">
                        <?= e(iniciales($nombreCompleto)) ?>
                      </span>
                      <strong><?= e($nombreCompleto) ?></strong>
                    </div>
                  </td>

                  <td class="muted-strong">
                    <?= e($u['email']) ?>
                  </td>

                  <td>
                    <span class="tag"><?= e($u['rol']) ?></span>
                  </td>

                  <td>
                    <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
                      <i></i>
                      <?= $activo ? 'Activo' : 'Inactivo' ?>
                    </span>
                  </td>

                  <td class="muted-strong">
                    <?= e(fechaCorta(
                      $u['fecha_creacion'],
                      $mesesCortos
                    )) ?>
                  </td>

                  <td class="text-center">
                    <div class="row-actions">
                      <a href="usuario-ver.php?id=<?= $id ?>"
                        class="icon-btn"
                        title="Ver usuario">
                        <?= icono('eye', 20) ?>
                      </a>

                      <a href="usuario-editar.php?id=<?= $id ?>"
                        class="icon-btn"
                        title="Editar usuario">
                        <?= icono('edit', 20) ?>
                      </a>

                      <form method="post" action="usuario-accion.php">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_usuario'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <?php if ($activo): ?>
                            <button type="submit"
                                    name="accion"
                                    value="desactivar"
                                    class="icon-btn icon-btn-danger"
                                    onclick="return confirm('¿Desactivar este usuario?')"
                                    title="Desactivar">
                                <?= icono('power', 20) ?>
                            </button>
                        <?php else: ?>
                            <button type="submit"
                                    name="accion"
                                    value="activar"
                                    class="icon-btn icon-btn-ok"
                                    title="Activar">
                                <?= icono('check-circle', 20) ?>
                            </button>
                        <?php endif; ?>

                        <button type="submit"
                                name="accion"
                                value="restablecer"
                                class="icon-btn"
                                onclick="return confirm('¿Restablecer la contraseña?')"
                                title="Restablecer contraseña">
                            <?= icono('key', 20) ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>  
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
<script>
function redirigir(elemento) {
  // Obtiene la URL de la opción seleccionada
  const url = elemento.value; 
  
  // Verifica que la opción no esté vacía
  if (url) { 
    // Opción A: Abre el enlace en una NUEVA pestaña/ventana
    //window.open(url, '_blank');
    // Cambia la página actual por la URL seleccionada
    window.location.href = url;
    
    // Opción B: Si prefieres cambiar de página en la MISMA ventana, usa:
    // window.location.href = url;
  }
}
</script>

<?php include __DIR__ . '/modal_nuevo_usuario.php'; ?>
<script src="js/modal_usuario.js"></script>

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>