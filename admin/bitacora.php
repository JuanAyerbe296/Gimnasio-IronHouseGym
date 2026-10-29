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

$paginaActual = 'bitacora';
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

// CORREGIDO: esta función se usaba en la lista pero no estaba definida.
function fechaHora(string $fecha, array $meses): string
{
    $d = new DateTimeImmutable($fecha);

    return fechaCorta($fecha, $meses) . ' · ' . $d->format('H:i');
}

// Filtros del formulario.
// CORREGIDO: el nombre del campo es "tipo" (antes se leía 'b.tipo') y se agregó "fecha".
$q       = trim((string)($_GET['q'] ?? ''));
$fFecha  = trim((string)($_GET['fecha'] ?? ''));
$fTipo   = trim((string)($_GET['tipo'] ?? ''));

$bitacora   = [];
$listaTipos = [];   // CORREGIDO: tipos reales de la bitácora (antes se usaba la lista de roles)
$errorDb    = '';

try {
    // Tipos de acción para el desplegable.
    $resTipos = mysqli_query($cnx, "SELECT DISTINCT tipo FROM bitacora_gym ORDER BY tipo");
    while ($r = mysqli_fetch_row($resTipos)) {
        $listaTipos[] = $r[0];
    }

    // CORREGIDO: se agregó WHERE 1=1. Antes los filtros se pegaban al ON del JOIN.
    $sql = "SELECT 
                b.id_bitacora,
                b.codigo,
                b.tipo,
                b.descripcion, 
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                b.fecha_hora 
            FROM 
                bitacora_gym b
            INNER JOIN 
                usuarios u ON b.id_usuario = u.id_usuario
            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        // CORREGIDO: ahora busca por código y descripción (referencia) además del usuario.
        $sql .= " AND (
            b.codigo LIKE ?
            OR b.descripcion LIKE ?
            OR u.nombre LIKE ?
            OR u.apellido LIKE ?
            OR CONCAT(u.nombre, ' ', u.apellido) LIKE ?
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

    // NUEVO: filtro por fecha (el formulario lo enviaba pero no se aplicaba).
    if ($fFecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFecha)) {
        $sql .= " AND DATE(b.fecha_hora) = ?";
        $tipos .= 's';
        $parametros[] = $fFecha;
    }

    // CORREGIDO: se valida contra los tipos de la bitácora, no contra los roles.
    if (in_array($fTipo, $listaTipos, true)) {
        $sql .= " AND b.tipo = ?";
        $tipos .= 's';
        $parametros[] = $fTipo;
    }

    // ELIMINADO: filtro de "estado". La tabla de bitácora no tiene esa columna
    // y el formulario no tiene ese campo.

    $sql .= " ORDER BY b.id_bitacora DESC";

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

    // CORREGIDO: fetch_row (posicional). La lista usa [$id, $codigo, ...] y con
    // fetch_assoc esas posiciones no existían.
    while ($fila = mysqli_fetch_row($resultado)) {
        $bitacora[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar las acciones relevantes.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bitácora de auditoría · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/bitacora.css">
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

    <main class="content">
      <p class="eyebrow">SEGURIDAD</p>
      <h1>Bitácora de auditoría</h1>
      <p class="lead">Registro inmutable de acciones relevantes. Esta información no puede editarse ni eliminarse.</p>

      <section class="panel panel-log">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar usuario o referencia…">
          </label>

          <label class="input input-date">
            <input type="date" name="fecha" value="<?= e($fFecha) ?>" onchange="this.form.submit()" aria-label="Filtrar por fecha">
          </label>

          <label class="input input-select">
            <select name="tipo" onchange="this.form.submit()" aria-label="Filtrar por tipo">
              <option value="">Todos los tipos</option>
              <?php /* CORREGIDO: recorre los tipos de la bitácora y usa la variable correcta ($t) */ ?>
              <?php foreach ($listaTipos as $t): ?>
                <option value="<?= e($t) ?>" <?= $fTipo === $t ? 'selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>

        <?php if ($errorDb): ?>
          <p class="empty empty-error"><?= e($errorDb) ?></p>
        <?php elseif (!$bitacora): ?>
          <p class="empty">No se encontraron registros. <a href="bitacora.php">Limpiar filtros</a></p>
        <?php else: ?>
          <ul class="log-list">
            <?php foreach ($bitacora as [$id, $codigo, $tipo, $descripcion, $autor, $fechaHora]): ?>
              <li class="log-item">
                <div class="log-main">
                  <h3><?= e($tipo) ?></h3>
                  <p class="log-desc"><i class="log-dot"></i><?= e($descripcion) ?></p>
                  <p class="log-meta"><?= e($autor) ?> · <?= e(fechaHora($fechaHora, $mesesCortos)) ?></p>
                </div>
                <div class="log-side">
                  <span class="log-code"><?= e($codigo) ?></span>
                  <?php /* NUEVO: "Ver detalle" ahora es un botón con icono de ojo */ ?>
                  <a href="bitacora-detalle.php?id=<?= (int) $id ?>" class="log-detail"
                     title="Ver detalle" aria-label="Ver detalle del registro <?= e($codigo) ?>">
                    <?= icono('eye', 20) ?>
                  </a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
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

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>