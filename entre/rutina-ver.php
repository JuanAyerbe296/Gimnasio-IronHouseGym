<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/clientes_config.php';
require_once __DIR__ . '/rutinas_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden consultar el detalle de una rutina el administrador y los entrenadores.
$rolSesion = $_SESSION['rol'] ?? '';
if (!in_array($rolSesion, ['Administrador', 'Entrenador'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $rolSesion
];

$paginaActual = 'rutinas';
$solicitudesBaja = 3;

$menu = [
    'inicio'      => ['Mis clientes',         'users',    'entrenador_panel.php'],
    'evaluacion'  => ['Evaluaciones y planificación',  'doc',      'evaluaciones.php'],
    'rutinas'     => ['Rutinas',                       'dumbbell', 'rutinas.php'],
    'seguimiento' => ['Seguimiento de entrenamientos', 'trend',    'seguimiento.php'],
    'perfil'      => ['Mi perfil',                     'user',     'perfil.php'],
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
$r = null;
$ejercicios = [];
$errorDb = '';
$errorEj = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . "r." . RUT_ID . " AS id, "
             . "r." . RUT_NOMBRE . " AS nombre, "
             . "r." . RUT_ID_ENTRENADOR . " AS id_entrenador, "
             . "r." . RUT_ESTADO . " AS estado, "
             . "CONCAT(c." . CLI_NOMBRE . ", ' ', c." . CLI_APELLIDO . ") AS cliente, "
             . ENTRENADOR_NOMBRE . " AS entrenador"
             . (RUT_CODIGO !== null ? ", r." . RUT_CODIGO . " AS codigo" : "")
             . (RUT_PROGRAMA !== null ? ", r." . RUT_PROGRAMA . " AS programa" : "")
             . (RUT_FECHA !== null ? ", DATE(r." . RUT_FECHA . ") AS fecha_creacion" : "")
             . " FROM " . RUT_TABLA . " r"
             . " LEFT JOIN " . CLI_TABLA . " c ON c." . CLI_ID . " = r." . RUT_ID_CLIENTE
             . " " . ENTRENADOR_JOIN
             . " WHERE r." . RUT_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información de la rutina.';
}

// Un entrenador solo puede ver sus propias rutinas (si la sesión guarda su id_usuario).
if ($r !== null && $rolSesion === 'Entrenador' && isset($_SESSION['id_usuario'])
    && (int)$r['id_entrenador'] !== (int)$_SESSION['id_usuario']) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

if ($r === null && $errorDb === '') {
    http_response_code(404);
}

if ($r !== null) {
    $publicada = $r['estado'] === 'Publicada';
    $codigo    = codigoRutina((int)$r['id'], isset($r['codigo']) ? (string)$r['codigo'] : null);
    $programa  = trim((string)($r['programa'] ?? ''));

    // Ejercicios de la rutina (información secundaria: si falla, el resto de la pantalla sigue funcionando)
    try {
        $sqlE = "SELECT " . EJ_ID . " AS id, " . EJ_NOMBRE . " AS nombre, " . EJ_SERIES . " AS series, " . EJ_REPS . " AS repeticiones"
              . (EJ_DESCANSO !== null ? ", " . EJ_DESCANSO . " AS descanso" : "")
              . (EJ_NOTAS !== null ? ", " . EJ_NOTAS . " AS notas" : "")
              . " FROM " . EJ_TABLA . " WHERE " . EJ_ID_RUTINA . " = ?"
              . " ORDER BY " . (EJ_ORDEN !== null ? EJ_ORDEN . ", " : "") . EJ_ID . " ASC";
        $st = mysqli_prepare($cnx, $sqlE);
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($fila = mysqli_fetch_assoc($res)) {
            $ejercicios[] = $fila;
        }
        mysqli_stmt_close($st);
    } catch (Throwable $e) {
        error_log('[rutina-ver.php] ejercicios: ' . $e->getMessage());
        $errorEj = 'No se pudieron cargar los ejercicios de la rutina.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle de la rutina · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
  <link rel="stylesheet" href="css/cliente_detalle.css">
  <link rel="stylesheet" href="css/rutina_detalle.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>RUTINAS</b></div>
      <div class="topbar-right">
        <span class="demo-label">VISTA DE DEMO</span>
        <select class="role-select" aria-label="Rol de la vista de demo" onchange="redirigir(this)">
          <option value="entrenador_panel.php" selected>Entrenador</option>
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
        <a href="rutinas.php">Rutinas</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle de la rutina</span>
      </nav>

      <a href="rutinas.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($r === null): ?>
        <h1>Rutina no encontrada</h1>
        <p class="lead">La rutina que buscas no existe. <a href="rutinas.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <h1>Detalle de la rutina</h1>
        <p class="lead">Planificación asignada y contenido publicado.</p>

        <!-- ===== Datos de la rutina ===== -->
        <section class="panel audit-card">
          <div class="audit-head">
            <span class="audit-icon" aria-hidden="true"><?= icono('dumbbell', 26) ?></span>
            <div class="audit-title">
              <h2><?= e((string)$r['nombre']) ?></h2>
              <small><?= e($codigo) ?></small>
            </div>
            <span class="pill <?= $publicada ? 'pill-ok' : '' ?>">
              <i></i><?= e((string)$r['estado']) ?>
            </span>
          </div>

          <dl class="data-grid">
            <div><dt>Cliente asignado</dt><dd><?= trim((string)$r['cliente']) !== '' ? e((string)$r['cliente']) : '—' ?></dd></div>
            <div><dt>Entrenador responsable</dt><dd><?= trim((string)$r['entrenador']) !== '' ? e((string)$r['entrenador']) : '—' ?></dd></div>

            <div><dt>Programa B-Experience</dt><dd><?= $programa !== '' ? e($programa) : '—' ?></dd></div>
            <div>
              <dt>Fecha de creación</dt>
              <dd><?= !empty($r['fecha_creacion']) ? e(fechaCorta((string)$r['fecha_creacion'], $mesesCortos)) : '—' ?></dd>
            </div>

            <div><dt>Estado de publicación</dt><dd><?= e((string)$r['estado']) ?></dd></div>
          </dl>
        </section>

        <!-- ===== Ejercicios ===== -->
        <section class="panel tab-panel exercises-card">
          <div class="tab-panel-head">
            <h2>Ejercicios de la rutina</h2>
            <?php if ($ejercicios): ?>
              <span class="count"><?= count($ejercicios) === 1 ? '1 ejercicio' : count($ejercicios) . ' ejercicios' ?></span>
            <?php endif; ?>
          </div>
          <p>Contenido planificado para esta rutina.</p>

          <?php if ($errorEj !== ''): ?>
            <p class="page-alert page-alert-error" role="alert"><?= e($errorEj) ?></p>

          <?php elseif (!$ejercicios): ?>
            <p class="tab-empty">Esta rutina todavía no tiene ejercicios registrados.</p>

          <?php else: ?>
            <div class="table-wrap">
              <table class="table table-ex">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Ejercicio</th>
                    <th>Series</th>
                    <th>Repeticiones</th>
                    <?php if (EJ_DESCANSO !== null): ?><th>Descanso</th><?php endif; ?>
                    <?php if (EJ_NOTAS !== null): ?><th>Indicaciones</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ejercicios as $i => $ej): ?>
                    <tr>
                      <td><span class="ex-num"><?= $i + 1 ?></span></td>
                      <td class="ex-name"><?= e((string)$ej['nombre']) ?></td>
                      <td><?= e((string)$ej['series']) ?></td>
                      <td><?= e((string)$ej['repeticiones']) ?></td>
                      <?php if (EJ_DESCANSO !== null): ?>
                        <td><?= trim((string)$ej['descanso']) !== '' ? e((string)$ej['descanso']) : '—' ?></td>
                      <?php endif; ?>
                      <?php if (EJ_NOTAS !== null): ?>
                        <td class="wrap-text"><?= trim((string)$ej['notas']) !== '' ? e((string)$ej['notas']) : '—' ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
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
