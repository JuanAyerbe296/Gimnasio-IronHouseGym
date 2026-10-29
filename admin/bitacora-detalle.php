<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/auditoria_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// La bitácora es información sensible: solo el administrador puede consultarla.
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'bitacora';
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

$id = (int)($_GET['id'] ?? 0);
$a = null;
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . AUD_ID . " AS id, "
             . AUD_CODIGO . " AS codigo, "
             . AUD_TIPO . " AS tipo, "
             . AUD_DESC . " AS descripcion, "
             . AUD_USUARIO . " AS usuario_responsable, "
             . AUD_FECHA . " AS fecha_hora"
             . (AUD_REGISTRO !== null ? ", " . AUD_REGISTRO . " AS registro_afectado" : "")
             . (AUD_RESULTADO !== null ? ", " . AUD_RESULTADO . " AS resultado" : "")
             . " FROM " . AUD_FROM . " WHERE " . AUD_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $a = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar el detalle de la acción.';
}

if ($a === null && $errorDb === '') {
    http_response_code(404);
}

if ($a !== null) {
    $codigo    = codigoAuditoria((int)$a['id'], (string)$a['codigo']);
    $dt        = new DateTimeImmutable((string)$a['fecha_hora']);
    $registro  = trim((string)($a['registro_afectado'] ?? ''));
    $resultado = trim((string)($a['resultado'] ?? ''));

    if ($resultado === '') {
        $resultado = 'Operación completada correctamente';
    }
    // Si el texto del resultado indica un problema, la etiqueta pasa a "Con error".
    $conError = (bool)preg_match('/error|fall|rechaz/i', $resultado);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle de la acción · Iron House Gym</title>
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>BITÁCORA DE AUDITORÍA</b></div>
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
        <a href="bitacora.php">Bitácora de auditoría</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle de la acción</span>
      </nav>

      <a href="bitacora.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($a === null): ?>
        <h1>Registro no encontrado</h1>
        <p class="lead">La acción que buscas no existe. <a href="bitacora.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <h1>Detalle de la acción</h1>
        <p class="lead">Registro operativo de solo consulta.</p>

        <section class="panel audit-card">
          <div class="audit-head">
            <span class="audit-icon" aria-hidden="true"><?= icono('shield', 26) ?></span>
            <div class="audit-title">
              <h2><?= e((string)$a['tipo']) ?></h2>
              <small><?= e($codigo) ?></small>
            </div>
            <span class="pill <?= $conError ? 'pill-bad' : 'pill-ok' ?>">
              <i></i><?= $conError ? 'Con error' : 'Completada' ?>
            </span>
          </div>

          <dl class="data-grid">
            <div><dt>Código de acción</dt><dd><?= e($codigo) ?></dd></div>
            <div><dt>Usuario responsable</dt><dd><?= e((string)$a['usuario_responsable']) ?></dd></div>

            <div><dt>Tipo de acción</dt><dd><?= e((string)$a['tipo']) ?></dd></div>
            <div><dt>Descripción</dt><dd><?= e((string)$a['descripcion']) ?></dd></div>

            <div><dt>Fecha</dt><dd><?= e(fechaCorta($dt->format('Y-m-d'), $mesesCortos)) ?></dd></div>
            <div><dt>Hora</dt><dd><?= e($dt->format('H:i')) ?></dd></div>

            <div><dt>Registro afectado</dt><dd><?= $registro !== '' ? e($registro) : '—' ?></dd></div>
            <div><dt>Resultado</dt><dd><?= e($resultado) ?></dd></div>
          </dl>

          <div class="audit-note">
            <?= icono('lock', 22) ?>
            <p>Las entradas de auditoría no pueden editarse ni eliminarse. Las contraseñas nunca se registran.</p>
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
