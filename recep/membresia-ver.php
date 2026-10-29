<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/membresias_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden consultar el detalle de una membresía el administrador y recepción.
if (!in_array($_SESSION['rol'] ?? '', ['Administrador', 'Recepción', 'Recepcionista'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'membresias';
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
$m = null;
$solicitudes = [];
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . "t." . TP_ID . " AS id, "
             . "c." . CL_ID . " AS id_cliente, "
             . "CONCAT(c." . CL_NOMBRE . ", ' ', c." . CL_APELLIDO . ") AS cliente, "
             . "m." . M_NOMBRE . " AS tipo, "
             . "DATE(t." . TP_FECHA_INICIO . ") AS fecha_inicio, "
             . "DATE(t." . TP_FECHA_VENC . ") AS fecha_vencimiento, "
             . "t." . TP_ESTADO . " AS estado"
             . (TP_CODIGO !== null ? ", t." . TP_CODIGO . " AS codigo" : "")
             . " FROM " . T_TIPOS_M . " t"
             . " INNER JOIN " . T_CLIENTES_M . " c ON c." . CL_ID . " = t." . TP_ID_CLIENTE
             . " INNER JOIN " . T_MEMBRESIAS . " m ON m." . M_ID . " = t." . TP_ID_TIPO
             . " WHERE t." . TP_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $m = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información de la membresía.';
}

if ($m === null && $errorDb === '') {
    http_response_code(404);
}

if ($m !== null) {
    $codigo = codigoMembresia((int)$m['id'], isset($m['codigo']) ? (string)$m['codigo'] : null);

    // Estado actual: una membresía dada de baja se respeta; las demás se calculan por la fecha de vencimiento.
    if ($m['estado'] === 'Dada de baja') {
        $estadoActual = 'Dada de baja';
    } else {
        $estadoActual = ($m['fecha_vencimiento'] >= date('Y-m-d')) ? 'Vigente' : 'Vencida';
    }
    $clasePill = match ($estadoActual) {
        'Vigente'      => 'pill-ok',
        'Dada de baja' => 'pill-bad',
        default        => 'pill-warn',   // Vencida
    };

    // Solicitudes de baja pendientes relacionadas con esta membresía (información secundaria).
    try {
        $sqlSol = "SELECT " . SOLB_ID . " AS id, "
                . (SOLB_CODIGO !== null ? SOLB_CODIGO : "NULL") . " AS codigo, "
                . "DATE(" . SOLB_FECHA . ") AS fecha, "
                . SOLB_MOTIVO . " AS motivo"
                . " FROM " . SOLB_TABLA
                . " WHERE " . SOLB_ID_MEMBRESIA . " = ? AND " . SOLB_ESTADO . " = 'Pendiente'"
                . " ORDER BY " . SOLB_ID . " DESC";
        $st = mysqli_prepare($cnx, $sqlSol);
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($fila = mysqli_fetch_assoc($res)) {
            $solicitudes[] = $fila;
        }
        mysqli_stmt_close($st);
    } catch (Throwable $e) {
        // Si la tabla o columnas no coinciden, la pantalla sigue funcionando sin esta tarjeta.
        error_log('[membresia-ver.php] solicitudes relacionadas: ' . $e->getMessage());
        $solicitudes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle de la membresía · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
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
      <a href="logout.php" class="nav-item" data-abrir-modal="modal-cerrar-sesion"><?= icono('logout', 22) ?><span>Cerrar sesión</span></a>
    </div>
  </aside>

  <!-- ============ CONTENIDO ============ -->
  <div class="main">
    <header class="topbar">
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>MEMBRESÍAS</b></div>
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
        <a href="membresias.php">Membresías</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle de la membresía</span>
      </nav>

      <a href="membresias.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($m === null): ?>
        <h1>Membresía no encontrada</h1>
        <p class="lead">La membresía que buscas no existe. <a href="membresias.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <h1>Detalle de la membresía</h1>
        <p class="lead">Información de vigencia e historial relacionado.</p>

        <div class="detail-grid">

          <!-- ===== Datos de la membresía ===== -->
          <section class="panel audit-card">
            <div class="audit-head">
              <span class="audit-icon" aria-hidden="true"><?= icono('card', 26) ?></span>
              <div class="audit-title">
                <h2><?= e((string)$m['tipo']) ?></h2>
                <small><?= e($codigo) ?></small>
              </div>
              <span class="pill <?= $clasePill ?>"><i></i><?= e($estadoActual) ?></span>
            </div>

            <dl class="data-grid">
              <div><dt>Código de membresía</dt><dd><?= e($codigo) ?></dd></div>
              <div><dt>Cliente asociado</dt><dd><?= e((string)$m['cliente']) ?></dd></div>

              <div><dt>Tipo de membresía</dt><dd><?= e((string)$m['tipo']) ?></dd></div>
              <div><dt>Fecha de inicio</dt><dd><?= e(fechaCorta((string)$m['fecha_inicio'], $mesesCortos)) ?></dd></div>

              <div><dt>Fecha de vencimiento</dt><dd><?= e(fechaCorta((string)$m['fecha_vencimiento'], $mesesCortos)) ?></dd></div>
              <div><dt>Estado actual</dt><dd><?= e($estadoActual) ?></dd></div>
            </dl>

            <div class="detail-more">
              <a href="cliente-ver.php?id=<?= (int)$m['id_cliente'] ?>" class="btn btn-outline">Ver cliente asociado</a>
            </div>
          </section>

          <!-- ===== Solicitudes relacionadas ===== -->
          <aside class="panel card-related">
            <p class="perms-eyebrow">SOLICITUDES RELACIONADAS</p>

            <?php if (!$solicitudes): ?>
              <h2 class="rel-title">Sin solicitudes activas</h2>
              <p class="rel-text">Esta membresía no tiene solicitudes de baja pendientes.</p>
            <?php else: ?>
              <h2 class="rel-title">
                <?= count($solicitudes) === 1 ? '1 solicitud pendiente' : count($solicitudes) . ' solicitudes pendientes' ?>
              </h2>
              <p class="rel-text">Esta membresía tiene solicitudes de baja por revisar.</p>

              <ul class="rel-list">
                <?php foreach ($solicitudes as $s): ?>
                  <li>
                    <a href="solicitud-detalle.php?id=<?= (int)$s['id'] ?>">
                      <strong><?= e(($s['codigo'] ?? '') !== '' ? (string)$s['codigo'] : sprintf('SOL-%04d', (int)$s['id'])) ?></strong>
                      <small><?= e((string)$s['motivo']) ?><?= $s['fecha'] ? ' · ' . e(fechaCorta((string)$s['fecha'], $mesesCortos)) : '' ?></small>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
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
