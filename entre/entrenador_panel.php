<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';

/* ---------- Datos (reemplaza por consultas a tu base de datos) ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'inicio';

$menu = [
    'inicio'      => ['Mis clientes',         'users',    'entrenador_panel.php'],
    'evaluacion'  => ['Evaluaciones y planificación',  'doc',      'evaluaciones.php'],
    'rutinas'     => ['Rutinas',                       'dumbbell', 'rutinas.php'],
    'seguimiento' => ['Seguimiento de entrenamientos', 'trend',    'seguimiento.php'],
    'perfil'      => ['Mi perfil',                     'user',     'perfil.php'],
];

// nombre, objetivo, programa, evaluación, estado de planificación
$clientes = [
    ['Lucía Herrera',  'Aumentar fuerza general', 'Build',   'Registrada', 'Publicada'],
    ['Nicolás Peña',   'Mejorar movilidad',       'Balance', 'Registrada', 'Borrador'],
    ['Andrea Rojas',   'Perder grasa corporal',   'Burn',    'Registrada', 'Publicada'],
    ['Jorge Salas',    'Ganar masa muscular',     'Breathe',   'Pendiente',  'Borrador'],
];

$estados   = ['Publicada', 'Pendiente', 'Borrador'];
$programas = array_values(array_unique(array_column($clientes, 2)));

/* ---------- Filtros (GET) ---------- */
$q        = trim((string) ($_GET['q'] ?? ''));
$fEstado  = (string) ($_GET['estado'] ?? '');
$fProg    = (string) ($_GET['programa'] ?? '');

$norm = fn(string $s): string => mb_strtolower(strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));

$visibles = array_filter($clientes, function (array $c) use ($q, $fEstado, $fProg, $norm): bool {
    if ($q !== '' && !str_contains($norm($c[0]), $norm($q))) return false;
    if ($fEstado !== '' && $c[4] !== $fEstado) return false;
    if ($fProg !== '' && $c[2] !== $fProg) return false;
    return true;
});

$primerNombre = explode(' ', $usuario['nombre'])[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inicio · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>INICIO</b></div>
      <div class="topbar-right">
        <span class="demo-label">VISTA DE DEMO</span>
        <select class="role-select" aria-label="Rol de la vista de demo" onchange="redirigir(this)">
          <!--<option value="../admin/administrador_panel.php">Administrador</option>
          <option value="../recep/recepcion_panel.php">Recepción</option>-->
          <option value="entrenador_panel.php" selected>Entrenador</option>
          <!--<option value="../clien/cliente_panel.php">Cliente</option>-->
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
      <p class="eyebrow">ENTRENADOR</p>
      <h1>¡Bienvenido, <?= e($primerNombre) ?>!</h1>
      <p class="lead">Gestiona a tus clientes y planifica sus entrenamientos desde un solo lugar.</p>

      <!-- Resumen -->
      <section class="summary">
        <span class="kpi-icon kpi-icon-red"><?= icono('users', 24) ?></span>
        <strong class="summary-num"><?= count($clientes) ?></strong>
        <div>
          <p class="summary-title">Clientes asignados</p>
          <p class="summary-note">Únicamente clientes autorizados para tu cuenta</p>
        </div>
      </section>

      <!-- Mis clientes asignados -->
      <section class="panel panel-clients">
        <div class="panel-head">
          <div>
            <h2>Mis clientes asignados</h2>
            <p>Consulta su objetivo y continúa con su planificación.</p>
          </div>
        </div>

        <form method="get" class="filters">
          <label class="field field-search">
            <span>Buscar cliente</span>
            <div class="input">
              <?= icono('search', 20) ?>
              <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre…">
            </div>
          </label>

          <label class="field">
            <span>Estado de planificación</span>
            <div class="input">
              <select name="estado" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <?php foreach ($estados as $est): ?>
                  <option value="<?= e($est) ?>" <?= $fEstado === $est ? 'selected' : '' ?>><?= e($est) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <label class="field">
            <span>Programa</span>
            <div class="input">
              <select name="programa" onchange="this.form.submit()">
                <option value="">Todos los programas</option>
                <?php foreach ($programas as $prog): ?>
                  <option value="<?= e($prog) ?>" <?= $fProg === $prog ? 'selected' : '' ?>><?= e($prog) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>
        </form>

        <?php if (!$visibles): ?>
          <p class="empty">No hay clientes que coincidan con los filtros. <a href="entrenador.php">Limpiar filtros</a></p>
        <?php else: ?>
          <div class="client-grid">
            <?php foreach ($visibles as [$nombre, $objetivo, $programa, $evaluacion, $estado]): ?>
              <article class="client-card">
                <div class="client-top">
                  <span class="avatar avatar-xl"><?= e(iniciales($nombre)) ?></span>
                  <span class="pill <?= $estado === 'Publicada' ? 'pill-ok' : '' ?>"><i></i><?= e($estado) ?></span>
                </div>

                <h3><?= e($nombre) ?></h3>
                <p class="goal"><?= e($objetivo) ?></p>

                <dl class="client-meta">
                  <div><dt>Programa</dt><dd><?= e($programa) ?></dd></div>
                  <div><dt>Evaluación</dt><dd><?= e($evaluacion) ?></dd></div>
                </dl>

                <div class="client-actions">
                  <a href="planificacion.php?cliente=<?= urlencode($nombre) ?>" class="btn btn-outline">Ver planificación</a>
                  <a href="evaluaciones.php?cliente=<?= urlencode($nombre) ?>" class="btn btn-red">Gestionar entrenamiento</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
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