<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/perfil_repo.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Datos del usuario en sesión (reemplaza por tu login real) ---------- */
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 1);
$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? '',
    'correo'        => $_SESSION['email'] ?? '',
    'activo'        => true,
    'ultimo_acceso' => 'Hoy, ' . date('H:i'),
];

$paginaActual = 'perfil';
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

/* ---------- Procesar formulario ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errores = [];      // campo => mensaje
$exito   = false;
$errorGeneral = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual    = (string) ($_POST['actual'] ?? '');
    $nueva     = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        if ($actual === '')    $errores['actual'] = 'Ingresa tu contraseña actual.';
        if ($nueva === '')     $errores['nueva']  = 'Crea una contraseña nueva.';
        elseif ($msg = validarNuevaPassword($nueva)) $errores['nueva'] = $msg;
        elseif ($nueva === $actual) $errores['nueva'] = 'La nueva contraseña debe ser distinta de la actual.';
        if ($confirmar !== $nueva) $errores['confirmar'] = 'Las contraseñas no coinciden.';

        if (!$errores) {
            try {
                if (cambiarPassword($idUsuario, $actual, $nueva)) {
                    $exito = true;
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                } else {
                    $errores['actual'] = 'La contraseña actual no es correcta.';
                }
            } catch (PDOException $ex) {
                error_log('[perfil.php] ' . $ex->getMessage());
                $errorGeneral = 'No se pudo guardar el cambio. Inténtalo de nuevo más tarde.';
            }
        }
    }
}

/** Atributos comunes de un campo de contraseña. */
function campoPass(string $name, string $placeholder, array $errores, string $autocomplete): string
{
    $id  = 'f-' . $name;
    $err = isset($errores[$name]);
    return '<input type="password" id="' . $id . '" name="' . $name . '" placeholder="' . e($placeholder) . '"'
         . ' autocomplete="' . $autocomplete . '" required'
         . ($err ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : '') . '>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi perfil · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/perfil.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>MI PERFIL</b></div>
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

    <main class="content content-profile">
      <p class="eyebrow">CUENTA Y SEGURIDAD</p>
      <h1>Cambiar contraseña</h1>
      <p class="lead">Protege el acceso a tu cuenta desde Mi perfil.</p>

      <?php if ($exito): ?>
        <div class="alert alert-ok" role="status">Tu contraseña se actualizó correctamente.</div>
      <?php elseif ($errorGeneral): ?>
        <div class="alert alert-error" role="alert"><?= e($errorGeneral) ?></div>
      <?php endif; ?>

      <div class="profile-grid">
        <!-- Tarjeta de cuenta -->
        <aside class="panel profile-card">
          <span class="avatar avatar-profile"><?= e(iniciales($usuario['nombre'])) ?></span>
          <h2><?= e($usuario['nombre']) ?></h2>
          <p class="profile-role"><?= e($usuario['rol']) ?></p>
          <span class="pill <?= $usuario['activo'] ? 'pill-ok' : 'pill-off' ?>">
            <i></i><?= $usuario['activo'] ? 'Cuenta activa' : 'Cuenta inactiva' ?>
          </span>

          <dl class="profile-data">
            <div><dt>Correo electrónico</dt><dd><?= e($usuario['correo']) ?></dd></div>
            <div><dt>Último acceso</dt><dd><?= e($usuario['ultimo_acceso']) ?></dd></div>
          </dl>
        </aside>

        <!-- Formulario -->
        <section class="panel form-card">
          <div class="panel-head">
            <div>
              <h2>Cambiar contraseña</h2>
              <p>Utiliza al menos 8 caracteres, una mayúscula y un número.</p>
            </div>
            <?= icono('lock', 24) ?>
          </div>

          <form method="post" action="perfil.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

            <div class="form-grid">
              <div class="form-field <?= isset($errores['actual']) ? 'has-error' : '' ?>">
                <label for="f-actual">Contraseña actual</label>
                <?= campoPass('actual', 'Ingresa tu contraseña actual', $errores, 'current-password') ?>
                <?php if (isset($errores['actual'])): ?><p class="field-error" id="f-actual-err"><?= e($errores['actual']) ?></p><?php endif; ?>
              </div>

              <div class="form-field <?= isset($errores['nueva']) ? 'has-error' : '' ?>">
                <label for="f-nueva">Nueva contraseña</label>
                <?= campoPass('nueva', 'Crea una contraseña segura', $errores, 'new-password') ?>
                <?php if (isset($errores['nueva'])): ?><p class="field-error" id="f-nueva-err"><?= e($errores['nueva']) ?></p><?php endif; ?>
              </div>

              <div class="form-field <?= isset($errores['confirmar']) ? 'has-error' : '' ?>">
                <label for="f-confirmar">Confirmar nueva contraseña</label>
                <?= campoPass('confirmar', '', $errores, 'new-password') ?>
                <?php if (isset($errores['confirmar'])): ?><p class="field-error" id="f-confirmar-err"><?= e($errores['confirmar']) ?></p><?php endif; ?>
              </div>
            </div>

            <div class="form-actions">
              <a href="perfil.php" class="btn btn-outline">Cancelar</a>
              <button type="submit" class="btn btn-red">Guardar cambios</button>
            </div>
          </form>
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