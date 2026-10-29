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
$solicitudesBaja = 0;

$menu = [
    'inicio'      => ['Inicio',              'home',     'recepcion_panel.php'],
    'clientes'    => ['Clientes',            'user',     'clientes.php'],
    'membresias'  => ['Membresías',          'card',     'membresias.php'],
    'tiposd'       => ['Tipos de membresía',  'doc',      'tipos.php'],
    'solicitudes' => ['Solicitudes de baja', 'x-circle', 'solicitudes.php'],
    'perfil'      => ['Mi perfil',           'user',     'perfil.php'],
];

$kpis = [
    ['Clientes registrados', 94, '+12 este mes',  'user'],
    ['Membresías vigentes',  81, '86% del total', 'card'],
    ['Rutinas asignadas',    67, '8 por revisar', 'dumbbell'],
];

$solicitudes = [
    ['Mariana López', 'Plan Trimestral', '18 jun 2025', 'Pendiente'],
    ['Carlos Méndez', 'Plan Mensual',    '17 jun 2025', 'Pendiente'],
    ['Valeria Ruiz',  'Plan Anual',      '14 jun 2025', 'Pendiente'],
];

/* ---------- Fecha de hoy en español ---------- */
$mesesCortos = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
$hoy = new DateTimeImmutable('today');
$fechaHoy = mb_strtoupper($hoy->format('j') . ' ' . $mesesCortos[(int) $hoy->format('n') - 1] . ' ' . $hoy->format('Y'));
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

    <main class="content">
      <p class="eyebrow">RECEPCIÓN</p>
      <h1>Panel de operaciones</h1>
      <p class="lead">Registra clientes, membresías y solicitudes desde un solo lugar.</p>

      <!-- Banner del día -->
      <section class="hero hero-flat">
        <div class="hero-text">
          <p class="hero-eyebrow hero-eyebrow-sm">HOY · <?= e($fechaHoy) ?></p>
          <h2>Todo listo para comenzar</h2>
          <p class="hero-meta">Completa las operaciones pendientes y mantén los registros actualizados.</p>
        </div>
        <div class="hero-actions">
          <a href="clientes.php?nuevo=1" class="btn btn-white btn-sm">Registrar cliente</a>
          <a href="membresias.php?nueva=1" class="btn btn-red btn-sm">Registrar membresía</a>
        </div>
      </section>

      <!-- Indicadores -->
      <section class="kpis kpis-3">
        <?php foreach ($kpis as [$label, $valor, $nota, $ico]): ?>
          <article class="kpi kpi-ok">
            <span class="kpi-icon"><?= icono($ico, 22) ?></span>
            <span class="kpi-label"><?= e($label) ?></span>
            <span class="kpi-value"><?= number_format($valor) ?></span>
            <span class="kpi-note"><?= e($nota) ?></span>
          </article>
        <?php endforeach; ?>
      </section>

      <!-- Solicitudes recientes -->
      <section class="panel panel-wide">
        <div class="panel-head">
          <div>
            <h2>Solicitudes recientes</h2>
            <p>Seguimiento de registros creados por Recepción</p>
          </div>
          <a href="solicitudes.php" class="link-all link-all-inline">Ver todas</a>
        </div>

        <ul class="requests">
          <?php foreach ($solicitudes as [$nombre, $plan, $fecha, $estado]): ?>
            <li>
              <a href="solicitudes.php?cliente=<?= urlencode($nombre) ?>" class="request">
                <span class="avatar avatar-lg"><?= e(mb_substr($nombre, 0, 2)) ?></span>
                <div class="request-info">
                  <strong><?= e($nombre) ?></strong>
                  <small><?= e($plan) ?></small>
                </div>
                <time><?= e($fecha) ?></time>
                <span class="pill"><i></i><?= e($estado) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
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