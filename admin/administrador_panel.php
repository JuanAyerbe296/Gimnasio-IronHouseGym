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
$solicitudesBaja = 3;

$menu = [
    'inicio'      => ['Inicio',                'home',     'administrador_panel.php'],
    'usuarios'    => ['Usuarios',              'users',    'usuarios.php'],
    'clientes'    => ['Clientes',              'user',     'clientes.php'],
    'membresias'  => ['Membresías',            'card',     'membresias.php'],
    'tiposd'       => ['Tipos de membresía',    'doc',      'tipos.php'],
    'rutinas'     => ['Rutinas',               'dumbbell', 'rutinas.php'],
    'solicitudes' => ['Solicitudes de baja',   'x-circle', 'solicitudes.php'],
    'bitacora'    => ['Bitácora de auditoría', 'shield',   'bitacora.php'],
    'perfil'      => ['Mi perfil',             'user',     'perfil.php'],
];

$kpis = [
    ['Usuarios activos',      128, '+6 este mes',    'users',    'ok'],
    ['Clientes registrados',  94,  '+12 este mes',   'user',     'ok'],
    ['Membresías vigentes',   81,  '86% del total',  'card',     'ok'],
    ['Rutinas asignadas',     67,  '8 por revisar',  'dumbbell', 'ok'],
    ['Entrenamientos',        326, 'Este mes',       'doc',      'neutral'],
    ['Bajas pendientes',      $solicitudesBaja, 'Requieren revisión', 'x-circle', 'alert'],
];

$solicitudes = [
    ['Mariana López', 'Plan Trimestral', 'Cambio de domicilio',     '18 jun 2025', 'Pendiente'],
    ['Carlos Méndez', 'Plan Mensual',    'Motivos personales',      '17 jun 2025', 'Pendiente'],
    ['Valeria Ruiz',  'Plan Anual',      'Pausa de entrenamiento',  '14 jun 2025', 'Pendiente'],
];

$accesos = [
    ['Gestionar usuarios',    'users',  'usuarios.php'],
    ['Consultar clientes',    'user',   'clientes.php'],
    ['Gestionar membresías',  'card',   'membresias.php'],
    ['Bitácora de auditoría', 'shield', 'bitacora.php'],
];

$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>INICIO</b></div>
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
      <p class="eyebrow">VISTA GENERAL</p>
      <h1><?= e($saludo) ?>, <?= e($primerNombre) ?></h1>
      <p class="lead">Aquí tienes el estado operativo de Iron House Gym.</p>

      <!-- KPIs -->
      <section class="kpis">
        <?php foreach ($kpis as [$label, $valor, $nota, $ico, $tono]): ?>
          <article class="kpi kpi-<?= e($tono) ?>">
            <span class="kpi-icon"><?= icono($ico, 22) ?></span>
            <span class="kpi-label"><?= e($label) ?></span>
            <span class="kpi-value"><?= number_format($valor) ?></span>
            <span class="kpi-note"><?= e($nota) ?></span>
          </article>
        <?php endforeach; ?>
      </section>

      <div class="grid-2">
        <!-- Solicitudes pendientes -->
        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>Solicitudes pendientes</h2>
              <p>Revisa las solicitudes que requieren una decisión.</p>
            </div>
            <a href="solicitudes.php" class="link-all">Ver todas</a>
          </div>

          <ul class="requests">
            <?php foreach ($solicitudes as [$nombre, $plan, $motivo, $fecha, $estado]): ?>
              <li>
                <a href="solicitudes.php?cliente=<?= urlencode($nombre) ?>" class="request">
                  <span class="avatar avatar-lg"><?= e(iniciales($nombre)) ?></span>
                  <div class="request-info">
                    <strong><?= e($nombre) ?></strong>
                    <small><?= e($plan) ?> · <?= e($motivo) ?></small>
                  </div>
                  <time><?= e($fecha) ?></time>
                  <span class="pill"><i></i><?= e($estado) ?></span>
                  <?= icono('chevron', 20) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <!-- Accesos rápidos -->
        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>Accesos rápidos</h2>
              <p>Operaciones frecuentes</p>
            </div>
          </div>

          <ul class="quick">
            <?php foreach ($accesos as [$texto, $ico, $url]): ?>
              <li>
                <a href="<?= e($url) ?>">
                  <span class="quick-icon"><?= icono($ico, 20) ?></span>
                  <strong><?= e($texto) ?></strong>
                  <?= icono('chevron', 16) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
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