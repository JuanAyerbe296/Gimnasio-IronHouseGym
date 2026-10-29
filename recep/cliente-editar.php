<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/clientes_config.php';
require_once __DIR__ . '/membresias_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden editar clientes el administrador y recepción.
$rolSesion = $_SESSION['rol'] ?? '';
if (!in_array($rolSesion, ['Administrador', 'Recepcionista'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

// Token CSRF (el mismo que usa el modal de registrar cliente).
if (empty($_SESSION['csrf_cliente'])) {
    $_SESSION['csrf_cliente'] = bin2hex(random_bytes(32));
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

$tabs = [
    'general'       => 'Información general',
    'membresias'    => 'Membresías',
    'entrenamiento' => 'Entrenamiento',
    'solicitudes'   => 'Solicitudes de baja',
];

/**
 * Separa "Nombres y apellidos" en [nombres, apellidos].
 *  2 palabras  -> 1 nombre y 1 apellido
 *  3 palabras  -> 1 nombre y 2 apellidos
 *  4 o más     -> las dos últimas son los apellidos
 */
function separarNombre(string $completo): array
{
    $p = preg_split('/\s+/u', trim($completo), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($p);
    if ($n < 2) {
        return [trim($completo), ''];
    }
    $nombres = ($n === 2) ? 1 : (($n === 3) ? 1 : $n - 2);
    return [implode(' ', array_slice($p, 0, $nombres)), implode(' ', array_slice($p, $nombres))];
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = null;
$errorDb = '';

/* ---------- Cargar el cliente ---------- */
try {
    if ($id > 0) {
        $sql = "SELECT "
             . CLI_ID . " AS id, "
             . CLI_NOMBRE . " AS nombre, "
             . CLI_APELLIDO . " AS apellido, "
             . CLI_DOCUMENTO . " AS documento, "
             . CLI_CORREO . " AS correo, "
             . CLI_TELEFONO . " AS telefono, "
             . CLI_ESTADO . " AS estado"
             . (CLI_CODIGO !== null ? ", " . CLI_CODIGO . " AS codigo" : "")
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

$errores = [];
$errorGeneral = '';

if ($c !== null) {
    $nombreCompleto = trim($c['nombre'] . ' ' . $c['apellido']);
    $activo = $c['estado'] === 'Activo';
    $codigo = codigoCliente((int)$c['id'], isset($c['codigo']) ? (string)$c['codigo'] : null);

    // Subtítulo del perfil: programa asignado (bitácora) o, si no hay, la membresía más reciente
    $subtitulo = 'Sin programa asignado';
    try {
        $st = mysqli_prepare($cnx, "SELECT b." . ENT_DETALLES . " FROM " . ENT_TABLA . " b WHERE b." . ENT_ID_CLIENTE
            . " = ? ORDER BY b." . ENT_FECHA . " DESC, b." . ENT_ID . " DESC LIMIT 1");
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $detalles);
        $hay = mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);

        $programa = '';
        if ($hay) {
            $lineas = preg_split('/\R/u', trim((string)$detalles));
            $programa = mb_strimwidth(trim((string)($lineas[0] ?? '')), 0, 80, '…');
        }
        if ($programa !== '') {
            $subtitulo = preg_match('/^programa\b/iu', $programa) ? $programa : 'Programa ' . $programa;
        } else {
            $st = mysqli_prepare($cnx, "SELECT t." . TP_NOMBRE . " FROM " . T_MEMBRESIAS . " m INNER JOIN " . T_TIPOS_M
                . " t ON t." . TP_ID . " = m." . M_ID_TIPO . " WHERE m." . M_ID_CLIENTE . " = ? ORDER BY m." . M_FECHA_INICIO . " DESC LIMIT 1");
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            mysqli_stmt_bind_result($st, $plan);
            if (mysqli_stmt_fetch($st)) {
                $subtitulo = (string)$plan;
            }
            mysqli_stmt_close($st);
        }
    } catch (Throwable $e) {
        error_log('[cliente-editar.php] subtítulo: ' . $e->getMessage());
    }

    // Valores del formulario: los actuales, o lo que se envió si hubo errores
    $v = [
        'nombre_completo' => $nombreCompleto,
        'documento'       => (string)$c['documento'],
        'correo'          => (string)$c['correo'],
        'telefono'        => (string)$c['telefono'],
    ];

    /* ---------- Guardar cambios ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $v = [
            'nombre_completo' => trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre_completo'] ?? ''))),
            'documento'       => trim((string)($_POST['documento'] ?? '')),
            'correo'          => mb_strtolower(trim((string)($_POST['correo'] ?? ''))),
            'telefono'        => trim((string)($_POST['telefono'] ?? '')),
        ];

        if (!hash_equals((string)$_SESSION['csrf_cliente'], (string)($_POST['csrf'] ?? ''))) {
            $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
        } else {
            // Si el nombre no cambió se conservan los valores originales; si cambió, se vuelve a separar.
            if ($v['nombre_completo'] === $nombreCompleto) {
                [$nom, $ape] = [(string)$c['nombre'], (string)$c['apellido']];
            } else {
                [$nom, $ape] = separarNombre($v['nombre_completo']);
            }

            if ($nom === '' || $ape === '') {
                $errores['nombre_completo'] = 'Ingresa al menos un nombre y un apellido.';
            } elseif (mb_strlen($nom) > 60 || mb_strlen($ape) > 60) {
                $errores['nombre_completo'] = 'Nombres y apellidos no pueden superar 60 caracteres cada uno.';
            }
            if (!preg_match('/^[A-Za-z0-9\-\.]{4,20}$/', $v['documento'])) {
                $errores['documento'] = 'Usa de 4 a 8 caracteres: letras, números, puntos o guiones.';
            }
            if ($v['correo'] === '' || mb_strlen($v['correo']) > 120 || !filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) {
                $errores['correo'] = 'Ingresa un correo electrónico válido.';
            }
            if ($v['telefono'] !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $v['telefono'])) {
                $errores['telefono'] = 'Ingresa un teléfono válido (solo números, espacios, + y guiones).';
            }

            if (!$errores) {
                try {
                    // Documento y correo no pueden pertenecer a otro cliente
                    foreach ([['documento', CLI_DOCUMENTO, 'Ya existe otro cliente con este documento.'],
                              ['correo', CLI_CORREO, 'Ya existe otro cliente con este correo.']] as [$campo, $col, $msg]) {
                        $st = mysqli_prepare($cnx, 'SELECT 1 FROM ' . CLI_TABLA . ' WHERE ' . $col . ' = ? AND ' . CLI_ID . ' <> ? LIMIT 1');
                        mysqli_stmt_bind_param($st, 'si', $v[$campo], $id);
                        mysqli_stmt_execute($st);
                        mysqli_stmt_store_result($st);
                        if (mysqli_stmt_num_rows($st) > 0) {
                            $errores[$campo] = $msg;
                        }
                        mysqli_stmt_close($st);
                    }

                    if (!$errores) {
                        $st = mysqli_prepare($cnx, 'UPDATE ' . CLI_TABLA . ' SET '
                            . CLI_NOMBRE . ' = ?, ' . CLI_APELLIDO . ' = ?, ' . CLI_DOCUMENTO . ' = ?, '
                            . CLI_CORREO . ' = ?, ' . CLI_TELEFONO . ' = ? WHERE ' . CLI_ID . ' = ?');
                        mysqli_stmt_bind_param($st, 'sssssi', $nom, $ape, $v['documento'], $v['correo'], $v['telefono'], $id);
                        mysqli_stmt_execute($st);
                        mysqli_stmt_close($st);

                        header('Location: cliente-ver.php?id=' . $id . '&editado=1');
                        exit;
                    }
                } catch (mysqli_sql_exception $ex) {
                    if ($ex->getCode() === 1062) {
                        $errores['documento'] = 'Ya existe otro cliente con estos datos.';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar datos del cliente · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/usuario_detalle.css">
  <link rel="stylesheet" href="css/bitacora_detalle.css">
  <link rel="stylesheet" href="css/cliente_detalle.css">
  <link rel="stylesheet" href="css/cliente_editar.css">
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
        </div>

        <?php if ($errorGeneral !== ''): ?>
          <p class="page-alert page-alert-error" role="alert"><?= e($errorGeneral) ?></p>
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
            <?php if ($clave === 'general'): ?>
              <a href="cliente-editar.php?id=<?= (int)$c['id'] ?>" aria-current="page"><?= e($titulo) ?></a>
            <?php else: ?>
              <a href="cliente-ver.php?id=<?= (int)$c['id'] ?>&amp;tab=<?= e($clave) ?>"><?= e($titulo) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>

        <!-- ===== Datos personales (igual que cliente-ver.php) ===== -->
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
          </dl>
        </section>

        <!-- ===== Formulario de edición ===== -->
        <section class="panel client-edit-card" id="editar">
          <div class="client-edit-head">
            <h2>Editar datos del cliente</h2>
            <p>Modifica únicamente la información autorizada.</p>
          </div>

          <form method="post" action="cliente-editar.php?id=<?= (int)$c['id'] ?>#editar" novalidate autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_cliente']) ?>">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

            <div class="m-grid client-edit-grid">
              <div class="m-field <?= isset($errores['nombre_completo']) ? 'has-error' : '' ?>">
                <label for="ec-nombre">Nombres y apellidos</label>
                <input type="text" id="ec-nombre" name="nombre_completo" value="<?= e($v['nombre_completo']) ?>" maxlength="121" required>
                <?php if (isset($errores['nombre_completo'])): ?><p class="m-error"><?= e($errores['nombre_completo']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['documento']) ? 'has-error' : '' ?>">
                <label for="ec-documento">Documento</label>
                <input type="text" id="ec-documento" name="documento" value="<?= e($v['documento']) ?>" maxlength="8" required>
                <?php if (isset($errores['documento'])): ?><p class="m-error"><?= e($errores['documento']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['correo']) ? 'has-error' : '' ?>">
                <label for="ec-correo">Correo electrónico</label>
                <input type="email" id="ec-correo" name="correo" value="<?= e($v['correo']) ?>" maxlength="120" required>
                <?php if (isset($errores['correo'])): ?><p class="m-error"><?= e($errores['correo']) ?></p><?php endif; ?>
              </div>

              <div class="m-field <?= isset($errores['telefono']) ? 'has-error' : '' ?>">
                <label for="ec-telefono">Teléfono</label>
                <input type="tel" id="ec-telefono" name="telefono" value="<?= e($v['telefono']) ?>" maxlength="9" placeholder="+00 000 000 000">
                <?php if (isset($errores['telefono'])): ?><p class="m-error"><?= e($errores['telefono']) ?></p><?php endif; ?>
              </div>
            </div>

            <div class="client-edit-foot">
              <a href="cliente-ver.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline">Cancelar</a>
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
