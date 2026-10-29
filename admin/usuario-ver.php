<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/usuarios_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo el administrador puede ver el detalle de las cuentas.
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

// Token CSRF para los formularios de esta página (el mismo que usa el modal de usuarios).
if (empty($_SESSION['csrf_usuario'])) {
    $_SESSION['csrf_usuario'] = bin2hex(random_bytes(32));
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
    'tiposd'      => ['Tipos de membresía', 'doc', 'tipos.php'],
    'rutinas'     => ['Rutinas', 'dumbbell', 'rutinas.php'],
    'solicitudes' => ['Solicitudes de baja', 'x-circle', 'solicitudes.php'],
    'bitacora'    => ['Bitácora de auditoría', 'shield', 'bitacora.php'],
    'perfil'      => ['Mi perfil', 'user', 'perfil.php'],
];

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

// Aviso guardado por usuario-accion.php (se muestra una sola vez).
$flash = $_SESSION['flash_usuario'] ?? null;
unset($_SESSION['flash_usuario']);

$id = (int)($_GET['id'] ?? 0);
$u = null;
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . U_ID . " AS id, "
             . U_NOMBRE . " AS nombre, "
             . U_APELLIDO . " AS apellido, "
             . U_CORREO . " AS correo, "
             . U_ROL . " AS rol, "
             . U_ESTADO . " AS estado, "
             . "DATE(" . U_FECHA . ") AS fecha_creacion"
             . (U_CODIGO !== null ? ", " . U_CODIGO . " AS codigo" : "")
             . " FROM " . U_TABLA . " WHERE " . U_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $u = mysqli_fetch_assoc($resultado) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información del usuario.';
}

if ($u === null && $errorDb === '') {
    http_response_code(404);
}

if ($u !== null) {
    $nombreCompleto = trim($u['nombre'] . ' ' . $u['apellido']);
    $activo   = $u['estado'] === 'Activo';
    $codigo   = codigoUsuario((int)$u['id'], isset($u['codigo']) ? (string)$u['codigo'] : null);
    $permisos = permisosPorRol((string)$u['rol']);
    $esMiCuenta = isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === (int)$u['id'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle del usuario · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
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
      <a href="logout.php" class="nav-item" data-abrir-modal="modal-cerrar-sesion"><?= icono('logout', 22) ?><span>Cerrar sesión</span></a>
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

    <main class="content detail">

      <nav class="crumbs" aria-label="Ruta de navegación">
        <a href="usuarios.php">Usuarios</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle del usuario</span>
      </nav>

      <a href="usuarios.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($u === null): ?>
        <h1>Usuario no encontrado</h1>
        <p class="lead">La cuenta que buscas no existe o fue eliminada. <a href="usuarios.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <div class="detail-head">
          <div>
            <h1>Detalle del usuario</h1>
            <p class="lead lead-tight">Información de la cuenta, rol y permisos autorizados.</p>
          </div>

          <div class="detail-actions">
            <a href="usuario-editar.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline">Editar usuario</a>

            <form method="post" action="usuario-accion.php">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_usuario']) ?>">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="volver" value="detalle">
              <?php if ($activo): ?>
                <button name="accion" value="desactivar" class="btn btn-soft-red"
                        <?= $esMiCuenta ? 'disabled title="No puedes desactivar tu propia cuenta"' : '' ?>
                        onclick="return confirm('¿Desactivar la cuenta de <?= e($nombreCompleto) ?>?')">
                  Desactivar cuenta
                </button>
              <?php else: ?>
                <button name="accion" value="activar" class="btn btn-soft-ok">Activar cuenta</button>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="page-alert <?= $flash['tipo'] === 'ok' ? 'page-alert-ok' : 'page-alert-error' ?>" role="status">
            <?= e($flash['texto']) ?>
            <?php if (!empty($flash['password'])): ?>
              <div class="temp-pass">
                <span>Contraseña temporal</span>
                <code><?= e($flash['password']) ?></code>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="detail-grid">

          <!-- ===== Datos de la cuenta ===== -->
          <section class="panel card-account">
            <div class="account-head">
              <span class="avatar avatar-xl"><?= e(iniciales($nombreCompleto)) ?></span>
              <div class="account-id">
                <h2><?= e($nombreCompleto) ?></h2>
                <small><?= e($codigo) ?></small>
              </div>
              <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
                <i></i><?= $activo ? 'Activo' : 'Inactivo' ?>
              </span>
            </div>

            <dl class="data-grid">
              <div><dt>Código del usuario</dt><dd><?= e($codigo) ?></dd></div>
              <div><dt>Nombre completo</dt><dd><?= e($nombreCompleto) ?></dd></div>
              <div><dt>Correo electrónico</dt><dd><?= e((string)$u['correo']) ?></dd></div>
              <div><dt>Rol asignado</dt><dd><?= e((string)$u['rol']) ?></dd></div>
              <div><dt>Estado de la cuenta</dt><dd><?= $activo ? 'Activo' : 'Inactivo' ?></dd></div>
              <div><dt>Fecha de creación</dt><dd><?= $u['fecha_creacion'] ? e(fechaCorta((string)$u['fecha_creacion'], $mesesCortos)) : '—' ?></dd></div>
            </dl>
          </section>

          <!-- ===== Permisos asociados ===== -->
          <aside class="panel card-perms">
            <p class="perms-eyebrow">PERMISOS ASOCIADOS</p>
            <h2><?= e((string)$u['rol']) ?></h2>

            <?php if ($permisos): ?>
              <ul class="perms-list">
                <?php foreach ($permisos as $p): ?>
                  <li><?= icono('check', 16) ?><span><?= e($p) ?></span></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="perms-empty">Este rol no tiene permisos definidos.</p>
            <?php endif; ?>

            <div class="perms-note">
              <?= icono('lock', 22) ?>
              <p>La contraseña actual nunca se muestra ni se almacena en esta interfaz.</p>
            </div>

            <form method="post" action="usuario-accion.php">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_usuario']) ?>">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="volver" value="detalle">
              <button name="accion" value="restablecer" class="btn btn-outline btn-block"
                      onclick="return confirm('¿Asignar una contraseña temporal a <?= e($nombreCompleto) ?>?')">
                Restablecer contraseña temporal
              </button>
            </form>
          </aside>

        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
<script>
function redirigir(elemento) {
  const url = elemento.value;
  if (url) {
    window.location.href = url;
  }
}
</script>

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>
