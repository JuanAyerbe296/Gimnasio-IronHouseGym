<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tipos_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo el administrador puede editar los tipos de membresía.
if (!in_array($_SESSION['rol'] ?? '', TM_ROLES_PERMITIDOS, true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

// Token CSRF (el mismo que usan tipos.php, el modal y tipo-ver.php).
if (empty($_SESSION['csrf_tipo'])) {
    $_SESSION['csrf_tipo'] = bin2hex(random_bytes(32));
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'tiposd';
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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$t = null;
$errorDb = '';

/* ---------- Cargar el tipo ---------- */
try {
    if ($id > 0) {
        $sql = "SELECT "
             . TM_ID . " AS id, "
             . TM_NOMBRE . " AS nombre, "
             . TM_ESTADO . " AS estado"
             . (TM_CODIGO !== null ? ", " . TM_CODIGO . " AS codigo" : "")
             . (TM_DESCRIPCION !== null ? ", " . TM_DESCRIPCION . " AS descripcion" : "")
             . " FROM " . TM_TABLA . " WHERE " . TM_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $t = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información del tipo de membresía.';
}

if ($t === null && $errorDb === '') {
    http_response_code(404);
}

$errores = [];
$errorGeneral = '';

if ($t !== null) {
    $nombre = (string)$t['nombre'];
    $activo = $t['estado'] === 'Activo';
    $codigo = codigoTipo((int)$t['id'], isset($t['codigo']) ? (string)$t['codigo'] : null);

    $descripcion = trim((string)($t['descripcion'] ?? ''));
    if ($descripcion === '') {
        $descripcion = 'Membresía configurada para el periodo seleccionado.';
    }

    // Valores del formulario: los actuales, o lo que se envió si hubo errores
    $v = ['nombre' => $nombre, 'estado' => (string)$t['estado']];

    /* ---------- Guardar cambios ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $v = [
            'nombre' => trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? ''))),
            'estado' => (string)($_POST['estado'] ?? ''),
        ];

        if (!hash_equals((string)$_SESSION['csrf_tipo'], (string)($_POST['csrf'] ?? ''))) {
            $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
        } else {
            if (!preg_match('/^[\p{L}\p{N} .\-]{3,60}$/u', $v['nombre'])) {
                $errores['nombre'] = 'Usa de 3 a 60 caracteres (letras, números, espacios, puntos o guiones).';
            }
            if (!in_array($v['estado'], ['Activo', 'Inactivo'], true)) {
                $errores['estado'] = 'Selecciona un estado válido.';
            }

            if (!$errores) {
                try {
                    // El nombre no puede pertenecer a otro tipo (sin distinguir mayúsculas)
                    $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . TM_TABLA
                        . ' WHERE LOWER(' . TM_NOMBRE . ') = LOWER(?) AND ' . TM_ID . ' <> ? LIMIT 1');
                    mysqli_stmt_bind_param($st, 'si', $v['nombre'], $id);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_store_result($st);
                    $repetido = mysqli_stmt_num_rows($st) > 0;
                    mysqli_stmt_close($st);

                    if ($repetido) {
                        $errores['nombre'] = 'Ya existe otro tipo de membresía con este nombre.';
                    } else {
                        $st = mysqli_prepare($cnx, 'UPDATE ' . TM_TABLA . ' SET '
                            . TM_NOMBRE . ' = ?, ' . TM_ESTADO . ' = ? WHERE ' . TM_ID . ' = ?');
                        mysqli_stmt_bind_param($st, 'ssi', $v['nombre'], $v['estado'], $id);
                        mysqli_stmt_execute($st);
                        mysqli_stmt_close($st);

                        header('Location: tipo-ver.php?id=' . $id . '&editado=1');
                        exit;
                    }
                } catch (Throwable $ex) {
                    error_log($ex->getMessage());
                    $errorGeneral = 'No se pudieron guardar los cambios. Inténtalo de nuevo más tarde.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar tipo de membresía · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
  <link rel="stylesheet" href="css/tipo_editar.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>TIPOS DE MEMBRESÍA</b></div>
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
        <a href="tipos.php">Tipos de membresía</a>
        <?= icono('chevron', 14) ?>
        <span>Detalle del tipo de membresía</span>
      </nav>

      <a href="tipos.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>

      <?php if ($errorDb !== ''): ?>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($t === null): ?>
        <h1>Tipo de membresía no encontrado</h1>
        <p class="lead">El tipo que buscas no existe o fue eliminado. <a href="tipos.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <div class="detail-head">
          <div>
            <h1>Detalle del tipo de membresía</h1>
            <p class="lead lead-tight">Configuración utilizada al registrar membresías.</p>
          </div>

          <div class="detail-actions">
            <form method="post" action="tipo_estado.php">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_tipo']) ?>">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="volver" value="detalle">
              <?php if ($activo): ?>
                <button name="accion" value="desactivar" class="btn btn-soft-red"
                        onclick="return confirm('¿Desactivar el tipo «<?= e($nombre) ?>»?')">
                  Desactivar
                </button>
              <?php else: ?>
                <button name="accion" value="activar" class="btn btn-soft-ok">Activar</button>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <?php if ($errorGeneral !== ''): ?>
          <p class="page-alert page-alert-error" role="alert"><?= e($errorGeneral) ?></p>
        <?php elseif (isset($_GET['actualizado'])): ?>
          <p class="page-alert page-alert-ok" role="status">El estado del tipo de membresía se actualizó.</p>
        <?php elseif (isset($_GET['error'])): ?>
          <p class="page-alert page-alert-error" role="alert">No se pudo completar la acción. Inténtalo de nuevo.</p>
        <?php endif; ?>

        <!-- ===== Detalle actual (igual que tipo-ver.php) ===== -->
        <section class="panel audit-card">
          <div class="audit-head">
            <span class="audit-icon" aria-hidden="true"><?= icono('doc', 26) ?></span>
            <div class="audit-title">
              <h2><?= e($nombre) ?></h2>
              <small><?= e($codigo) ?></small>
            </div>
            <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
              <i></i><?= $activo ? 'Activo' : 'Inactivo' ?>
            </span>
          </div>

          <dl class="data-grid">
            <div><dt>Código</dt><dd><?= e($codigo) ?></dd></div>
            <div><dt>Nombre Plan</dt><dd><?= e($nombre) ?></dd></div>

            <div><dt>Descripción</dt><dd><?= e($descripcion) ?></dd></div>
            <div><dt>Estado</dt><dd><?= $activo ? 'Activo' : 'Inactivo' ?></dd></div>

            <div>
              <dt>Disponibilidad</dt>
              <dd><?= $activo ? 'Visible al registrar una nueva membresía' : 'No disponible al registrar una nueva membresía' ?></dd>
            </div>
          </dl>

          <div class="audit-note">
            <?= icono('shield', 22) ?>
            <p>Este tipo define una categoría de membresía. No incorpora pagos, cobros ni facturación.</p>
          </div>
        </section>

        <!-- ===== Formulario de edición ===== -->
        <section class="panel tipo-edit-card" id="editar">
          <div class="tipo-edit-head">
            <h2>Editar tipo de membresía</h2>
            <p>Actualiza la configuración disponible.</p>
          </div>

          <form method="post" action="tipo-editar.php?id=<?= (int)$t['id'] ?>#editar" novalidate autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_tipo']) ?>">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">

            <div class="m-grid tipo-edit-grid">
              <div class="m-field <?= isset($errores['nombre']) ? 'has-error' : '' ?>">
                <label for="et-nombre">Nombre Plan</label>
                <input type="text" id="et-nombre" name="nombre" value="<?= e($v['nombre']) ?>" maxlength="60" required>
                <?php if (isset($errores['nombre'])): ?><p class="m-error"><?= e($errores['nombre']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['estado']) ? 'has-error' : '' ?>">
                <label for="et-estado">Estado</label>
                <select id="et-estado" name="estado">
                  <option value="Activo"   <?= $v['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                  <option value="Inactivo" <?= $v['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <?php if (isset($errores['estado'])): ?><p class="m-error"><?= e($errores['estado']) ?></p><?php endif; ?>
              </div>
            </div>

            <div class="tipo-edit-foot">
              <a href="tipo-ver.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline">Cancelar</a>
              <button type="submit" class="btn btn-red">Guardar cambios</button>
            </div>
          </form>
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
