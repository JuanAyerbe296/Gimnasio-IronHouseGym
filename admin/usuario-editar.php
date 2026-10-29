<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/usuarios_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo el administrador puede editar cuentas.
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

// Token CSRF (el mismo que usan el modal y el detalle de usuarios).
if (empty($_SESSION['csrf_usuario'])) {
    $_SESSION['csrf_usuario'] = bin2hex(random_bytes(32));
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $_SESSION['rol'] ?? ''
];

$paginaActual = 'usuarios';
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
$u = null;
$errorDb = '';

/* ---------- Cargar el usuario ---------- */
try {
    if ($id > 0) {
        $sql = "SELECT "
             . U_ID . " AS id, "
             . U_NOMBRE . " AS nombre, "
             . U_APELLIDO . " AS apellido, "
             . U_CORREO . " AS correo, "
             . U_ROL . " AS rol, "
             . U_ESTADO . " AS estado"
             . (U_CODIGO !== null ? ", " . U_CODIGO . " AS codigo" : "")
             . " FROM " . U_TABLA . " WHERE " . U_ID . " = ? LIMIT 1";

        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudo cargar la información del usuario.';
}

if ($u === null && $errorDb === '') {
    http_response_code(404);
}

/* ---------- Valores del formulario (los actuales, o lo que se envió si hubo errores) ---------- */
$errores = [];
$errorGeneral = '';
$esMiCuenta = false;

if ($u !== null) {
    $codigo     = codigoUsuario((int)$u['id'], isset($u['codigo']) ? (string)$u['codigo'] : null);
    $esMiCuenta = isset($_SESSION['id_usuario']) && (int)$_SESSION['id_usuario'] === (int)$u['id'];

    $v = [
        'nombres'   => (string)$u['nombre'],
        'apellidos' => (string)$u['apellido'],
        'correo'    => (string)$u['correo'],
        'rol'       => (string)$u['rol'],
        'estado'    => (string)$u['estado'],
    ];

    // Roles disponibles (si el rol actual no está en la lista, se conserva como opción)
    $rolesDisponibles = U_ROLES;
    if ($v['rol'] !== '' && !in_array($v['rol'], $rolesDisponibles, true)) {
        array_unshift($rolesDisponibles, $v['rol']);
    }

    /* ---------- Guardar cambios ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $v = [
            'nombres'   => trim((string)($_POST['nombres'] ?? '')),
            'apellidos' => trim((string)($_POST['apellidos'] ?? '')),
            'correo'    => mb_strtolower(trim((string)($_POST['correo'] ?? ''))),
            'rol'       => (string)($_POST['rol'] ?? ''),
            'estado'    => (string)($_POST['estado'] ?? ''),
        ];

        if (!hash_equals((string)$_SESSION['csrf_usuario'], (string)($_POST['csrf'] ?? ''))) {
            $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
        } else {
            if ($v['nombres'] === '' || mb_strlen($v['nombres']) > 60)     $errores['nombres']   = 'Ingresa los nombres (máx. 60 caracteres).';
            if ($v['apellidos'] === '' || mb_strlen($v['apellidos']) > 60) $errores['apellidos'] = 'Ingresa los apellidos (máx. 60 caracteres).';
            if ($v['correo'] === '' || mb_strlen($v['correo']) > 120 || !filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) {
                $errores['correo'] = 'Ingresa un correo electrónico válido.';
            }
            if (!in_array($v['rol'], $rolesDisponibles, true))           $errores['rol']    = 'Selecciona un rol válido.';
            if (!in_array($v['estado'], ['Activo', 'Inactivo'], true))   $errores['estado'] = 'Selecciona un estado válido.';

            // Evita que el administrador se quite el acceso a sí mismo
            if ($esMiCuenta && !isset($errores['estado']) && $v['estado'] !== 'Activo') {
                $errores['estado'] = 'No puedes desactivar tu propia cuenta.';
            }
            if ($esMiCuenta && !isset($errores['rol']) && $v['rol'] !== 'Administrador') {
                $errores['rol'] = 'No puedes quitarte el rol de administrador.';
            }

            if (!$errores) {
                try {
                    // El correo no puede pertenecer a otra cuenta
                    $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . U_TABLA . ' WHERE ' . U_CORREO . ' = ? AND ' . U_ID . ' <> ? LIMIT 1');
                    mysqli_stmt_bind_param($st, 'si', $v['correo'], $id);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_store_result($st);
                    $repetido = mysqli_stmt_num_rows($st) > 0;
                    mysqli_stmt_close($st);

                    if ($repetido) {
                        $errores['correo'] = 'Ya existe otra cuenta con este correo.';
                    } else {
                        $sqlUp = 'UPDATE ' . U_TABLA . ' SET '
                               . U_NOMBRE . ' = ?, ' . U_APELLIDO . ' = ?, ' . U_CORREO . ' = ?, '
                               . U_ROL . ' = ?, ' . U_ESTADO . ' = ? WHERE ' . U_ID . ' = ?';
                        $st = mysqli_prepare($cnx, $sqlUp);
                        mysqli_stmt_bind_param($st, 'sssssi', $v['nombres'], $v['apellidos'], $v['correo'], $v['rol'], $v['estado'], $id);
                        mysqli_stmt_execute($st);
                        mysqli_stmt_close($st);

                        // Si se editó la propia cuenta, se actualiza el nombre mostrado en la sesión
                        if ($esMiCuenta) {
                            $_SESSION['nombre_completo'] = $v['nombres'] . ' ' . $v['apellidos'];
                        }

                        $_SESSION['flash_usuario'] = [
                            'tipo' => 'ok',
                            'texto' => 'Los datos de la cuenta se actualizaron correctamente.',
                            'password' => null,
                        ];
                        header('Location: usuario-ver.php?id=' . $id);
                        exit;
                    }
                } catch (mysqli_sql_exception $ex) {
                    if ($ex->getCode() === 1062) {
                        $errores['correo'] = 'Ya existe otra cuenta con este correo.';
                    } else {
                        error_log($ex->getMessage());
                        $errorGeneral = 'No se pudieron guardar los cambios. Inténtalo de nuevo más tarde.';
                    }
                } catch (Throwable $ex) {
                    error_log($ex->getMessage());
                    $errorGeneral = 'No se pudieron guardar los cambios. Inténtalo de nuevo más tarde.';
                }
            }
        }
    }
}

$estadoActivo = $u !== null && $u['estado'] === 'Activo';   // etiqueta superior: estado guardado actualmente
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar usuario · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/usuario_editar.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>USUARIOS</b></div>
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
        <a href="usuarios.php">Usuarios</a>
        <?= icono('chevron', 14) ?>
        <span>Editar usuario</span>
      </nav>

      <?php if ($errorDb !== ''): ?>
        <a href="usuarios.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>
        <p class="page-alert page-alert-error" role="alert"><?= e($errorDb) ?></p>

      <?php elseif ($u === null): ?>
        <a href="usuarios.php" class="back-link"><span aria-hidden="true">←</span> Volver al listado</a>
        <h1>Usuario no encontrado</h1>
        <p class="lead">La cuenta que buscas no existe o fue eliminada. <a href="usuarios.php" class="link-red">Volver al listado</a></p>

      <?php else: ?>
        <a href="usuario-ver.php?id=<?= (int)$u['id'] ?>" class="back-link"><span aria-hidden="true">←</span> Volver al detalle</a>

        <h1>Editar usuario</h1>
        <p class="lead lead-edit"><?= e($codigo) ?> · Modifica únicamente la información autorizada.</p>

        <?php if ($errorGeneral !== ''): ?>
          <p class="page-alert page-alert-error" role="alert"><?= e($errorGeneral) ?></p>
        <?php endif; ?>

        <section class="panel edit-card">
          <div class="edit-head">
            <div>
              <h2>Datos de la cuenta</h2>
              <p>Los campos marcados <span class="req">*</span> son obligatorios.</p>
            </div>
            <span class="pill <?= $estadoActivo ? 'pill-ok' : 'pill-off' ?>">
              <i></i><?= $estadoActivo ? 'Activo' : 'Inactivo' ?>
            </span>
          </div>

          <form method="post" action="usuario-editar.php?id=<?= (int)$u['id'] ?>" novalidate autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_usuario']) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

            <div class="m-grid edit-grid">
              <div class="m-field m-field-half">
                <label for="eu-codigo">Código del usuario</label>
                <input type="text" id="eu-codigo" value="<?= e($codigo) ?>" readonly tabindex="-1" aria-readonly="true">
              </div>

              <div class="m-field <?= isset($errores['nombres']) ? 'has-error' : '' ?>">
                <label for="eu-nombres">Nombres <span class="req">*</span></label>
                <input type="text" id="eu-nombres" name="nombres" value="<?= e($v['nombres']) ?>" maxlength="60" required>
                <?php if (isset($errores['nombres'])): ?><p class="m-error"><?= e($errores['nombres']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['apellidos']) ? 'has-error' : '' ?>">
                <label for="eu-apellidos">Apellidos <span class="req">*</span></label>
                <input type="text" id="eu-apellidos" name="apellidos" value="<?= e($v['apellidos']) ?>" maxlength="60" required>
                <?php if (isset($errores['apellidos'])): ?><p class="m-error"><?= e($errores['apellidos']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['correo']) ? 'has-error' : '' ?>">
                <label for="eu-correo">Correo electrónico <span class="req">*</span></label>
                <input type="email" id="eu-correo" name="correo" value="<?= e($v['correo']) ?>" maxlength="120" required>
                <?php if (isset($errores['correo'])): ?><p class="m-error"><?= e($errores['correo']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['rol']) ? 'has-error' : '' ?>">
                <label for="eu-rol">Rol asignado</label>
                <select id="eu-rol" name="rol">
                  <?php foreach ($rolesDisponibles as $r): ?>
                    <option value="<?= e($r) ?>" <?= $v['rol'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errores['rol'])): ?><p class="m-error"><?= e($errores['rol']) ?></p><?php endif; ?>
              </div>

              <div class="m-field m-field-half <?= isset($errores['estado']) ? 'has-error' : '' ?>">
                <label for="eu-estado">Estado de la cuenta</label>
                <select id="eu-estado" name="estado">
                  <option value="Activo"   <?= $v['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                  <option value="Inactivo" <?= $v['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <?php if (isset($errores['estado'])): ?><p class="m-error"><?= e($errores['estado']) ?></p><?php endif; ?>
              </div>
            </div>

            <div class="protected-note">
              <?= icono('lock', 22) ?>
              <div>
                <strong>Información protegida</strong>
                <p>La contraseña actual del usuario no se consulta ni se modifica desde este formulario.</p>
              </div>
            </div>

            <div class="edit-foot">
              <a href="usuario-ver.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline">Cancelar</a>
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
