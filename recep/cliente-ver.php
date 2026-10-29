<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/clientes_config.php';
require_once __DIR__ . '/membresias_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden consultar el detalle de un cliente el administrador y recepción.
$rolSesion = $_SESSION['rol'] ?? '';
if (!in_array($rolSesion, ['Administrador', 'Recepcionista'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $rolSesion
];

$paginaActual = 'clientes';
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

/** Estado de una membresía: la "Dada de baja" se respeta; las demás se calculan por la fecha de vencimiento. */
function estadoMembresia(string $estadoBd, string $vencimiento): string
{
    if ($estadoBd === 'Dada de baja') {
        return 'Dada de baja';
    }
    return ($vencimiento >= date('Y-m-d')) ? 'Vigente' : 'Vencida';
}

function clasePillMembresia(string $estado): string
{
    return match ($estado) {
        'Vigente'      => 'pill-ok',
        'Dada de baja' => 'pill-bad',
        default        => 'pill-warn',
    };
}

// Pestañas disponibles (la pestaña se elige con ?tab=)
$tabs = [
    'general'      => 'Información general',
    'membresias'   => 'Membresías',
    'entrenamiento' => 'Entrenamiento',
    'solicitudes'  => 'Solicitudes de baja',
];
$id  = (int)($_GET['id'] ?? 0);
$tab = (string)($_GET['tab'] ?? 'general');
if (!isset($tabs[$tab])) {
    $tab = 'general';
}

$c = null;
$membresias = [];
$entrenamientos = [];
$solicitudes = [];
$errorDb = '';

try {
    if ($id > 0) {
        // Cada columna lleva alias para leerla por nombre simple en el HTML.
        $sql = "SELECT "
             . CLI_ID . " AS id, "
             . CLI_NOMBRE . " AS nombre, "
             . CLI_APELLIDO . " AS apellido, "
             . CLI_DOCUMENTO . " AS documento, "
             . CLI_CORREO . " AS correo, "
             . CLI_TELEFONO . " AS telefono, "
             . CLI_ESTADO . " AS estado"
             . (CLI_CODIGO !== null ? ", " . CLI_CODIGO . " AS codigo" : "")
             . (CLI_FECHA !== null ? ", DATE(" . CLI_FECHA . ") AS fecha_registro" : "")
             . " FROM " . CLI_TABLA . " WHERE " . CLI_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información del cliente.';
}

if ($c === null && $errorDb === '') {
    http_response_code(404);
}

$avisoTab = '';   // mensaje si falla la consulta de una pestaña secundaria

if ($c !== null) {
    $nombreCompleto = trim($c['nombre'] . ' ' . $c['apellido']);
    $activo = $c['estado'] === 'Activo';
    $codigo = codigoCliente((int)$c['id'], isset($c['codigo']) ? (string)$c['codigo'] : null);

    // Membresías del cliente (la más reciente primero). Sirve para el subtítulo y para la pestaña.
    try {
        $sqlM = "SELECT t." . TP_ID . " AS id, m." . M_NOMBRE . " AS tipo, "
              . "DATE(t." . TP_FECHA_INICIO . ") AS fecha_inicio, DATE(t." . TP_FECHA_VENC . ") AS fecha_vencimiento, "
              . "t." . TP_ESTADO . " AS estado"
              . (TP_CODIGO !== null ? ", t." . TP_CODIGO . " AS codigo" : "")
              . " FROM " . T_TIPOS_M . " t"
              . " INNER JOIN " . T_MEMBRESIAS . " m ON m." . M_ID . " = t." . TP_ID_TIPO
              . " WHERE t." . TP_ID_CLIENTE . " = ? ORDER BY t." . TP_FECHA_INICIO . " DESC, t." . TP_ID . " DESC LIMIT 50";
        $st = mysqli_prepare($cnx, $sqlM);
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($fila = mysqli_fetch_assoc($res)) {
            $membresias[] = $fila;
        }
        mysqli_stmt_close($st);
    } catch (Throwable $e) {
        error_log('[cliente-ver.php] membresías: ' . $e->getMessage());
        if ($tab === 'membresias') {
            $avisoTab = 'No se pudieron cargar las membresías del cliente.';
        }
    }

    // Solicitudes de baja del cliente (solo si se abre esa pestaña)
    if ($tab === 'solicitudes') {
        try {
            $sqlS = "SELECT s." . SOLB_ID . " AS id, "
                  . (SOLB_CODIGO !== null ? "s." . SOLB_CODIGO : "NULL") . " AS codigo, "
                  . "DATE(s." . SOLB_FECHA . ") AS fecha, s." . SOLB_MOTIVO . " AS motivo, s." . SOLB_ESTADO . " AS estado"
                  . " FROM " . SOLB_TABLA . " s"
                  . " INNER JOIN " . T_MEMBRESIAS . " m ON m." . M_ID . " = s." . SOLB_ID_MEMBRESIA
                  . " WHERE m." . M_ID_CLIENTE . " = ? ORDER BY s." . SOLB_ID . " DESC";
            $st = mysqli_prepare($cnx, $sqlS);
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($fila = mysqli_fetch_assoc($res)) {
                $solicitudes[] = $fila;
            }
            mysqli_stmt_close($st);
        } catch (Throwable $e) {
            error_log('[cliente-ver.php] solicitudes: ' . $e->getMessage());
            $avisoTab = 'No se pudieron cargar las solicitudes de baja del cliente.';
        }
    }

    // Entrenamientos del cliente (tabla bitácora), el más reciente primero.
    // Sirven para el subtítulo (programa asignado) y para la pestaña "Entrenamiento".
    try {
        $sqlE = "SELECT b." . ENT_ID . " AS id, DATE(b." . ENT_FECHA . ") AS fecha, "
              . "b." . ENT_GRUPO . " AS grupo, b." . ENT_DETALLES . " AS detalles, "
              . "b." . ENT_OBSERVACIONES . " AS observaciones, "
              . ENT_NOMBRE_ENTRENADOR . " AS entrenador"
              . " FROM " . ENT_TABLA . " b " . ENT_JOIN_ENTRENADOR
              . " WHERE b." . ENT_ID_CLIENTE . " = ? ORDER BY b." . ENT_FECHA . " DESC, b." . ENT_ID . " DESC LIMIT 50";
        $st = mysqli_prepare($cnx, $sqlE);
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($fila = mysqli_fetch_assoc($res)) {
            $entrenamientos[] = $fila;
        }
        mysqli_stmt_close($st);
    } catch (Throwable $e) {
        error_log('[cliente-ver.php] entrenamientos: ' . $e->getMessage());
        if ($tab === 'entrenamiento') {
            $avisoTab = 'No se pudieron cargar los entrenamientos del cliente.';
        }
    }

    // Subtítulo del perfil: el programa asignado (detalles de la rutina más reciente).
    // Si no hay, se usa la membresía más reciente.
    $programa = '';
    if ($entrenamientos) {
        $lineas = preg_split('/\R/u', trim((string)$entrenamientos[0]['detalles']));
        $programa = trim((string)($lineas[0] ?? ''));
        $programa = mb_strimwidth($programa, 0, 80, '…');
    }
    if ($programa !== '') {
        // Evita repetir la palabra si el dato ya empieza con "Programa"
        $subtitulo = preg_match('/^programa\b/iu', $programa) ? $programa : 'Programa ' . $programa;
    } elseif ($membresias) {
        $subtitulo = (string)$membresias[0]['tipo'];
    } else {
        $subtitulo = 'Sin programa asignado';
    }

    $puedeEditar = in_array($rolSesion, ['Administrador', 'Recepcionista'], true);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle del cliente · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
  <link rel="stylesheet" href="css/cliente_detalle.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>CLIENTES</b></div>
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
        <a href="clientes.php">Clientes</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle del cliente</span>
      </nav>

      <a href="clientes.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($c === null): ?>
        <h1>Cliente no encontrado</h1>
        <p class="lead">El cliente que buscas no existe. <a href="clientes.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <div class="detail-head">
          <div>
            <h1>Detalle del cliente</h1>
            <p class="lead lead-tight"><?= e($codigo) ?> · Registro autorizado para <?= e($rolSesion) ?></p>
          </div>

          <?php if ($puedeEditar): ?>
            <div class="detail-actions">
              <a href="cliente-editar.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline">Editar datos del cliente</a>
            </div>
          <?php endif; ?>
        </div>

        <?php if (isset($_GET['editado'])): ?>
          <p class="page-alert page-alert-ok" role="status">Los datos del cliente se actualizaron correctamente.</p>
        <?php endif; ?>

        <!-- ===== Perfil ===== -->
        <section class="panel client-profile">
          <span class="avatar avatar-xxl"><?= e(iniciales($nombreCompleto)) ?></span>
          <div class="client-profile-text">
            <p class="client-eyebrow">CLIENTE</p>
            <h2><?= e($nombreCompleto) ?></h2>
            <p class="client-sub"><?= e($subtitulo) ?></p>
          </div>
          <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
            <i></i><?= $activo ? 'Activo' : 'Inactivo' ?>
          </span>
        </section>

        <!-- ===== Pestañas ===== -->
        <nav class="tabs" aria-label="Secciones del cliente">
          <?php foreach ($tabs as $clave => $titulo): ?>
            <a href="cliente-ver.php?id=<?= (int)$c['id'] ?>&amp;tab=<?= e($clave) ?>"
               <?= $clave === $tab ? 'aria-current="page"' : '' ?>><?= e($titulo) ?></a>
          <?php endforeach; ?>
        </nav>

        <?php if ($avisoTab !== ''): ?>
          <p class="page-alert page-alert-error" role="alert"><?= e($avisoTab) ?></p>
        <?php endif; ?>

        <?php if ($tab === 'general'): ?>
          <!-- ===== Información general ===== -->
          <section class="panel personal-card">
            <div class="personal-head">
              <div>
                <h2>Datos personales</h2>
                <p>Información autorizada para el rol actual</p>
              </div>
              <?= icono('user', 24) ?>
            </div>

            <dl class="data-grid">
              <div><dt>Código del cliente</dt><dd><?= e($codigo) ?></dd></div>
              <div><dt>Documento</dt><dd><?= e((string)$c['documento']) ?></dd></div>

              <div><dt>Nombres y apellidos</dt><dd><?= e($nombreCompleto) ?></dd></div>
              <div><dt>Correo electrónico</dt><dd><?= e((string)$c['correo']) ?></dd></div>

              <div><dt>Teléfono</dt><dd><?= trim((string)$c['telefono']) !== '' ? e((string)$c['telefono']) : '—' ?></dd></div>
              <div>
                <dt>Estado del cliente</dt>
                <dd><span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>"><i></i><?= $activo ? 'Activo' : 'Inactivo' ?></span></dd>
              </div>

              <?php if (!empty($c['fecha_registro'])): ?>
                <div><dt>Fecha de registro</dt><dd><?= e(fechaCorta((string)$c['fecha_registro'], $mesesCortos)) ?></dd></div>
              <?php endif; ?>
            </dl>
          </section>

        <?php elseif ($tab === 'membresias'): ?>
          <!-- ===== Membresías ===== -->
          <section class="panel tab-panel">
            <h2>Membresías</h2>
            <p>Historial de membresías registradas para este cliente.</p>

            <?php if (!$membresias): ?>
              <p class="tab-empty">Este cliente aún no tiene membresías registradas.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Código</th><th>Tipo</th><th>Inicio</th><th>Vencimiento</th><th>Estado</th><th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($membresias as $m): ?>
                      <?php $est = estadoMembresia((string)$m['estado'], (string)$m['fecha_vencimiento']); ?>
                      <tr>
                        <td class="mono"><?= e(codigoMembresia((int)$m['id'], isset($m['codigo']) ? (string)$m['codigo'] : null)) ?></td>
                        <td><strong><?= e((string)$m['tipo']) ?></strong></td>
                        <td><?= e(fechaCorta((string)$m['fecha_inicio'], $mesesCortos)) ?></td>
                        <td><?= e(fechaCorta((string)$m['fecha_vencimiento'], $mesesCortos)) ?></td>
                        <td><span class="pill <?= clasePillMembresia($est) ?>"><i></i><?= e($est) ?></span></td>
                        <td>
                          <div class="row-actions">
                            <a href="membresia-ver.php?id=<?= (int)$m['id'] ?>" class="icon-btn"
                               title="Ver detalle" aria-label="Ver detalle de la membresía">
                              <?= icono('eye', 20) ?>
                            </a>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>

        <?php elseif ($tab === 'entrenamiento'): ?>
          <!-- ===== Entrenamiento ===== -->
          <section class="panel tab-panel">
            <h2>Entrenamiento</h2>
            <p>Registro de rutinas realizadas por el cliente.</p>

            <?php if (!$entrenamientos): ?>
              <p class="tab-empty">Este cliente aún no tiene entrenamientos registrados.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table table-train">
                  <thead>
                    <tr><th>Fecha</th><th>Grupo muscular</th><th>Rutina</th><th>Entrenador</th><th>Observaciones</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($entrenamientos as $en): ?>
                      <tr>
                        <td class="nowrap"><?= $en['fecha'] ? e(fechaCorta((string)$en['fecha'], $mesesCortos)) : '—' ?></td>
                        <td><span class="tag"><?= e((string)$en['grupo']) ?></span></td>
                        <td class="wrap-text"><?= e((string)$en['detalles']) ?></td>
                        <td class="nowrap"><?= trim((string)$en['entrenador']) !== '' ? e((string)$en['entrenador']) : '—' ?></td>
                        <td class="wrap-text"><?= trim((string)$en['observaciones']) !== '' ? e((string)$en['observaciones']) : '—' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>

        <?php else: ?>
          <!-- ===== Solicitudes de baja ===== -->
          <section class="panel tab-panel">
            <h2>Solicitudes de baja</h2>
            <p>Solicitudes registradas sobre las membresías del cliente.</p>

            <?php if (!$solicitudes): ?>
              <p class="tab-empty">Este cliente no tiene solicitudes de baja.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr><th>Código</th><th>Fecha</th><th>Motivo</th><th>Estado</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($solicitudes as $s): ?>
                      <tr>
                        <td class="mono"><?= e(($s['codigo'] ?? '') !== '' ? (string)$s['codigo'] : sprintf('SOL-%04d', (int)$s['id'])) ?></td>
                        <td><?= $s['fecha'] ? e(fechaCorta((string)$s['fecha'], $mesesCortos)) : '—' ?></td>
                        <td><?= e((string)$s['motivo']) ?></td>
                        <td>
                          <span class="pill <?= match ((string)$s['estado']) { 'Aprobada' => 'pill-ok', 'Rechazada' => 'pill-bad', default => 'pill-warn' } ?>">
                            <i></i><?= e((string)$s['estado']) ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

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
