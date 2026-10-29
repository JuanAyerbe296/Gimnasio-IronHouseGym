<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/bajas_config.php';
require_once __DIR__ . '/clientes_config.php';
require_once __DIR__ . '/usuarios_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden consultar el detalle el administrador y recepción; solo el administrador puede aprobar.
$rolSesion = $_SESSION['rol'] ?? '';
if (!in_array($rolSesion, ['Administrador', 'Recepcionista'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
$puedeAprobar = ($rolSesion === 'Administrador');

// Token CSRF para el botón de aprobar
if (empty($_SESSION['csrf_solicitud'])) {
    $_SESSION['csrf_solicitud'] = bin2hex(random_bytes(32));
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $rolSesion
];

$paginaActual = 'solicitudes';
$solicitudesBaja = 0;

$menu = [
    'inicio'      => ['Inicio',              'home',     'recepcion_panel.php'],
    'clientes'    => ['Clientes',            'user',     'clientes.php'],
    'membresias'  => ['Membresías',          'card',     'membresias.php'],
    'tiposd'       => ['Tipos de membresía',  'doc',      'tipos.php'],
    'solicitudes' => ['Solicitudes de baja', 'x-circle', 'solicitudes.php'],
    'perfil'      => ['Mi perfil',           'user',     'perfil.php'],
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

$id = (int)($_GET['id'] ?? 0);
$s = null;
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . "b." . BAJ_ID . " AS id, "
             . "DATE(c." . CLI_FECHA . ") AS fecha, "
             . "b." . BAJ_MOTIVO . " AS motivo, "
             . "b." . BAJ_ESTADO . " AS estado, "
             . "CONCAT(c." . CLI_NOMBRE . ", ' ', c." . CLI_APELLIDO . ") AS cliente, "
             . "m." . PLAN_NOMBRE . " AS plan"
             . (BAJ_ID_USUARIO !== null
                 ? ", CONCAT(u." . U_NOMBRE . ", ' ', u." . U_APELLIDO . ") AS registro_nombre, u." . U_ROL . " AS registro_rol"
                 : "")
             . " FROM " . BAJ_TABLA . " b"
             . " INNER JOIN " . SUS_TABLA . " s ON b." . BAJ_ID_SUSCRIPCION . " = s." . SUS_ID
             . " INNER JOIN " . CLI_TABLA . " c ON s." . SUS_ID_CLIENTE . " = c." . CLI_ID
             . " INNER JOIN " . PLAN_TABLA . " m ON s." . SUS_ID_MEMBRESIA . " = m." . PLAN_ID
             . (BAJ_ID_USUARIO !== null ? " LEFT JOIN " . U_TABLA . " u ON u." . U_ID . " = b." . BAJ_ID_USUARIO : "")
             . " WHERE b." . BAJ_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $s = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información de la solicitud.';
}

if ($s === null && $errorDb === '') {
    http_response_code(404);
}

if ($s !== null) {
    $codigo    = codigoBaja((int)$s['id']);
    $pendiente = $s['estado'] === BAJ_ESTADO_PENDIENTE;

    // Etiqueta: Pendiente en ámbar; Dada de baja en rojo claro (igual que en el listado)
    $clasePill = $pendiente ? 'pill-warn' : 'pill-bad';

    // Mientras la solicitud está pendiente la membresía sigue vigente; al aprobarse queda dada de baja.
    $estadoMembresia = $pendiente ? 'Vigente' : 'Dada de baja';
    $notaMembresia   = $pendiente
        ? 'Registrar la solicitud no modifica automáticamente la membresía.'
        : 'La membresía se dio de baja al aprobar la solicitud.';

    $registro = '';
    if (BAJ_ID_USUARIO !== null) {
        $registro = trim((string)($s['registro_nombre'] ?? ''));
        if ($registro !== '' && trim((string)($s['registro_rol'] ?? '')) !== '') {
            $registro .= ' · ' . $s['registro_rol'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle de la solicitud de baja · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/solicitudes.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
  <link rel="stylesheet" href="css/membresia_detalle.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>SOLICITUDES DE BAJA</b></div>
      <div class="topbar-right">
        <span class="demo-label">VISTA DE DEMO</span>
        <select class="role-select" aria-label="Rol de la vista de demo" onchange="redirigir(this)">
          <option value="recepcion_panel.php" selected>Recepción</option>
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
        <a href="solicitudes.php">Solicitudes de baja</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle de la solicitud de baja</span>
      </nav>

      <a href="solicitudes.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($s === null): ?>
        <h1>Solicitud no encontrada</h1>
        <p class="lead">La solicitud que buscas no existe. <a href="solicitudes.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <div class="detail-head">
          <div>
            <h1>Detalle de la solicitud de baja</h1>
            <p class="lead lead-tight">Revisión de la solicitud y su membresía relacionada.</p>
          </div>

          <?php if ($pendiente && $puedeAprobar): ?>
            <div class="detail-actions">
              <form method="post" action="solicitud-accion.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_solicitud']) ?>">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="volver" value="detalle">
                <button name="accion" value="aprobar" class="btn btn-red"
                        onclick="return confirm('¿Aprobar la solicitud de baja de <?= e((string)$s['cliente']) ?>?')">
                  Aprobar solicitud
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <?php if (isset($_GET['aprobada'])): ?>
          <p class="page-alert page-alert-ok" role="status">La solicitud se aprobó correctamente.</p>
        <?php elseif (isset($_GET['error'])): ?>
          <p class="page-alert page-alert-error" role="alert">No se pudo completar la acción. Es posible que la solicitud ya no esté pendiente.</p>
        <?php endif; ?>

        <div class="detail-grid">

          <!-- ===== Datos de la solicitud ===== -->
          <section class="panel audit-card">
            <div class="audit-head">
              <span class="audit-icon" aria-hidden="true"><?= icono('x-circle', 26) ?></span>
              <div class="audit-title">
                <h2><?= e((string)$s['cliente']) ?></h2>
                <small><?= e($codigo) ?></small>
              </div>
              <span class="pill <?= $clasePill ?>">
                <i></i><?= e((string)$s['estado']) ?>
              </span>
            </div>

            <dl class="data-grid">
              <div><dt>Código de solicitud</dt><dd><?= e($codigo) ?></dd></div>
              <div><dt>Cliente</dt><dd><?= e((string)$s['cliente']) ?></dd></div>

              <div><dt>Membresía relacionada</dt><dd><?= e((string)$s['plan']) ?></dd></div>
              <div><dt>Tipo de membresía</dt><dd><?= e((string)$s['plan']) ?></dd></div>

              <div><dt>Fecha de solicitud</dt><dd><?= $s['fecha'] ? e(fechaCorta((string)$s['fecha'], $mesesCortos)) : '—' ?></dd></div>
              <div><dt>Motivo</dt><dd><?= e((string)$s['motivo']) ?></dd></div>

              <?php if (BAJ_ID_USUARIO !== null): ?>
                <div><dt>Usuario que registró</dt><dd><?= $registro !== '' ? e($registro) : '—' ?></dd></div>
              <?php endif; ?>
            </dl>
          </section>

          <!-- ===== Estado de la membresía ===== -->
          <aside class="panel card-related">
            <p class="perms-eyebrow">ESTADO DE LA MEMBRESÍA</p>
            <h2 class="rel-title"><?= e($estadoMembresia) ?></h2>
            <p class="rel-text"><?= e($notaMembresia) ?></p>
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
