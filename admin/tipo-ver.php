<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tipos_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo el administrador puede consultar y administrar los tipos de membresía.
if (!in_array($_SESSION['rol'] ?? '', TM_ROLES_PERMITIDOS, true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

// Token CSRF para el formulario de activar/desactivar (el mismo que usa tipos.php).
if (empty($_SESSION['csrf_tipo'])) {
    $_SESSION['csrf_tipo'] = bin2hex(random_bytes(32));
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'tiposd';
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

$id = (int)($_GET['id'] ?? 0);
$t = null;
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . TM_ID . " AS id, "
             . TM_NOMBRE . " AS nombre, "
             . TM_ESTADO . " AS estado"
             . (TM_CODIGO !== null ? ", " . TM_CODIGO . " AS codigo" : "")
             . (TM_DESCRIPCION !== null ? ", " . TM_DESCRIPCION . " AS descripcion" : "")
             . " FROM " . TM_TABLA . " WHERE " . TM_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $t = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información del tipo de membresía.';
}

if ($t === null && $errorDb === '') {
    http_response_code(404);
}

if ($t !== null) {
    $nombre = (string)$t['nombre'];
    $activo = $t['estado'] === 'Activo';
    $codigo = codigoTipo((int)$t['id'], isset($t['codigo']) ? (string)$t['codigo'] : null);

    $descripcion = trim((string)($t['descripcion'] ?? ''));
    if ($descripcion === '') {
        $descripcion = 'Membresía configurada para el periodo seleccionado.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle del tipo de membresía · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>TIPOS DE MEMBRESÍA</b></div>
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
        <a href="tipos.php">Tipos de membresía</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle del tipo de membresía</span>
      </nav>

      <a href="tipos.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($t === null): ?>
        <h1>Tipo de membresía no encontrado</h1>
        <p class="lead">El tipo que buscas no existe o fue eliminado. <a href="tipos.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <div class="detail-head">
          <div>
            <h1>Detalle del tipo de membresía</h1>
            <p class="lead lead-tight">Configuración utilizada al registrar membresías.</p>
          </div>

          <div class="detail-actions">
            <a href="tipo-editar.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline">Editar tipo</a>

            <form method="post" action="tipo_estado.php">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_tipo']) ?>">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="volver" value="detalle">
              <?php if ($activo): ?>
                <button name="accion" value="desactivar" class="btn btn-soft-red"
                        onclick="return confirm('¿Desactivar el tipo «<?= e($nombre) ?>»?')">
                  Desactivar
                </button>
              <?php else: ?>
                <button name="accion" value="activar" class="btn btn-soft-ok">Activar</button>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <?php if (isset($_GET['editado'])): ?>
          <p class="page-alert page-alert-ok" role="status">Los datos del tipo de membresía se actualizaron correctamente.</p>
        <?php elseif (isset($_GET['actualizado'])): ?>
          <p class="page-alert page-alert-ok" role="status">El estado del tipo de membresía se actualizó.</p>
        <?php elseif (isset($_GET['error'])): ?>
          <p class="page-alert page-alert-error" role="alert">No se pudo completar la acción. Inténtalo de nuevo.</p>
        <?php endif; ?>

        <section class="panel audit-card">
          <div class="audit-head">
            <span class="audit-icon" aria-hidden="true"><?= icono('doc', 26) ?></span>
            <div class="audit-title">
              <h2><?= e($nombre) ?></h2>
              <small><?= e($codigo) ?></small>
            </div>
            <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
              <i></i><?= $activo ? 'Activo' : 'Inactivo' ?>
            </span>
          </div>

          <dl class="data-grid">
            <div><dt>Código</dt><dd><?= e($codigo) ?></dd></div>
            <div><dt>Nombre Plan</dt><dd><?= e($nombre) ?></dd></div>

            <div><dt>Descripción</dt><dd><?= e($descripcion) ?></dd></div>
            <div><dt>Estado</dt><dd><?= $activo ? 'Activo' : 'Inactivo' ?></dd></div>

            <div>
              <dt>Disponibilidad</dt>
              <dd><?= $activo ? 'Visible al registrar una nueva membresía' : 'No disponible al registrar una nueva membresía' ?></dd>
            </div>
          </dl>

          <div class="audit-note">
            <?= icono('shield', 22) ?>
            <p>Este tipo define una categoría de membresía. No incorpora pagos, cobros ni facturación.</p>
          </div>
        </section>
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
