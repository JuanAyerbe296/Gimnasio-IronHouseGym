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
    'inicio'    => ['Inicio',                    'home',     'cliente_panel.php'],
    'rutinas'   => ['Mis rutinas',               'dumbbell', 'mis-rutinas.php'],
    'registrar' => ['Registrar entrenamiento',   'doc',      'registrar.php'],
    'historial' => ['Historial de entrenamientos', 'trend',  'historial.php'],
    'perfil'    => ['Mi perfil',                 'user',     'perfil.php'],
];

$rutina = [
    'semana'     => 1,
    'dia'        => 'Martes',
    'nombre'     => 'Espalda y bíceps',
    'ejercicios' => 2,
    'enfoque'    => 'Tren superior',
];

// Días del mes con asistencia registrada (ej. [3, 5, 12]). Vacío = sin asistencias.
$diasAsistidos = [];

$actividad = [
    ['Última asistencia',        'Sin asistencia registrada'],
    ['Entrenamientos este mes',  '0'],
    ['Último entrenamiento',     'Aún no hay registros'],
];

/* ---------- Calendario del mes actual ---------- */
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$ahora       = new DateTimeImmutable('today');
$anio        = (int) $ahora->format('Y');
$mes         = (int) $ahora->format('n');
$hoy         = (int) $ahora->format('j');
$diasDelMes  = (int) $ahora->format('t');
$primerDia   = (int) (new DateTimeImmutable("$anio-$mes-01"))->format('w'); // 0 = domingo
$tituloMes   = ucfirst($meses[$mes - 1]) . ' de ' . $anio;

$nombresDia = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$rutina['dia'] = $nombresDia[(int) $ahora->format('w')];

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
          <option value="cliente_panel.php" selected>Cliente</option>
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
          <p class="eyebrow">ÁREA DE CLIENTE</p>
          <h1>Hola, <?= e($primerNombre) ?></h1>
          <p class="lead lead-tight">Tu rutina, asistencia y registros en un solo lugar.</p>
        </div>
        <a href="registrar.php" class="btn btn-red">Registrar entrenamiento</a>
      </div>

      <!-- Rutina asignada -->
      <section class="hero">
        <div class="hero-text">
          <p class="hero-eyebrow">RUTINA ASIGNADA · SEMANA <?= (int) $rutina['semana'] ?></p>
          <h2><?= e($rutina['dia']) ?>: <?= e($rutina['nombre']) ?></h2>
          <p class="hero-meta">
            <?= (int) $rutina['ejercicios'] ?> ejercicios · <?= e($rutina['enfoque']) ?>
          </p>
        </div>
        <div class="hero-actions">
          <a href="mis-rutinas.php" class="btn btn-white">Ver mis rutinas</a>
          <a href="registrar.php?iniciar=1" class="btn btn-red">Comenzar entrenamiento</a>
        </div>
      </section>

      <div class="grid-client">
        <!-- Asistencia -->
        <section class="panel panel-cal">
          <div class="panel-head">
            <div>
              <h2>Asistencia</h2>
              <p><?= e($tituloMes) ?></p>
            </div>
            <ul class="legend">
              <li><i class="dot dot-red"></i>Asistencia</li>
              <li><i class="dot dot-ring"></i>Hoy</li>
            </ul>
          </div>

          <div class="calendar" role="grid" aria-label="Calendario de asistencia">
            <?php foreach (['D','L','M','M','J','V','S'] as $d): ?>
              <span class="cal-head"><?= $d ?></span>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $primerDia; $i++): ?>
              <span class="cal-day is-empty"></span>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $diasDelMes; $d++):
                $clases = 'cal-day';
                if ($d === $hoy) $clases .= ' is-today';
                if (in_array($d, $diasAsistidos, true)) $clases .= ' is-present';
            ?>
              <span class="<?= $clases ?>"><?= $d ?></span>
            <?php endfor; ?>
          </div>
        </section>

        <!-- Actividad registrada -->
        <section class="panel panel-activity">
          <div class="panel-head panel-head-line">
            <div>
              <h2>Actividad registrada</h2>
              <p>Resumen de tu bitácora personal</p>
            </div>
          </div>

          <dl class="activity">
            <?php foreach ($actividad as [$label, $valor]): ?>
              <div>
                <dt><?= e($label) ?></dt>
                <dd><?= e($valor) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>

          <a href="historial.php" class="btn btn-outline btn-block">Consultar historial</a>
        </section>
      </div>
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