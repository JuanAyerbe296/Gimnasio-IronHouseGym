<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pueden consultar el detalle el administrador y recepción; solo el administrador puede aprobar.
$rolSesion = $_SESSION['rol'] ?? '';
if (!in_array($rolSesion, ['Administrador', 'Recepción', 'Recepcionista'], true)) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
$puedeAprobar = ($rolSesion === 'Administrador');

// Token CSRF para el botón de aprobar
if (empty($_SESSION['csrf_solicitud'])) {
    $_SESSION['csrf_solicitud'] = bin2hex(random_bytes(32));
}

$usuario = [
    'nombre' => $_SESSION['nombre_completo'] ?? 'Usuario',
    'rol'    => $rolSesion
];

$paginaActual = 'solicitudes';
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

$roles = ['Mensual', 'Trimestral', 'Semestral', 'Anual'];

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

// Filtros del formulario.
$q = trim((string)($_GET['q'] ?? ''));
$fTipo = trim((string)($_GET['tipo'] ?? ''));
$fEstado = trim((string)($_GET['estado'] ?? ''));

$solicitudes = [];
$errorDb = '';

try {
    $sql = "SELECT 
                b.id_baja, 
                b.fecha_baja, 
                b.motivo, 
                b.estado, 
                CONCAT(c.nombre, ' ', c.apellido) AS nombre_completo, 
                c.fecha_registro, 
                m.nombre_plan
            FROM 
                bajas_membresia b
            INNER JOIN 
                suscripciones_cliente s ON b.id_suscripcion = s.id_suscripcion
            INNER JOIN 
                clientes c ON s.id_cliente = c.id_cliente
            INNER JOIN 
                membresias m ON s.id_membresia = m.id_membresia
            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        $sql .= " AND (
            CAST(b.id_baja AS CHAR) LIKE ?
            OR c.nombre LIKE ?
            OR c.apellido LIKE ?
            OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
            OR b.estado LIKE ?
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

    if (in_array($fTipo, $roles, true)) {
        $sql .= " AND m.nombre_plan = ?";
        $tipos .= 's';
        $parametros[] = $fTipo;
    }

    if ($fEstado === 'pendiente') {
        $sql .= " AND b.estado = 'Pendiente'";
    } elseif ($fEstado === 'dada de baja') {
        $sql .= " AND b.estado = 'Dada de baja'";
    }

    $sql .= " ORDER BY b.id_baja DESC";

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

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $solicitudes[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar las solicitudes.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitudes · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
  <link rel="stylesheet" href="css/solicitudes.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>SOLICITUDES DE BAJA</b></div>
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
      <div class="page-head">
        <div>
          <p class="eyebrow">REVISIÓN ADMINISTRATIVA</p>
          <h1>Solicitudes de baja</h1>
          <p class="lead lead-tight">Revisa y aprueba las solicitudes pendientes.</p>
        </div>
      </div>

      <section class="panel panel-table">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por cliente...">
          </label>

          <label class="input input-select">
            <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado">
              <option value="">Todos los estados</option>
              <option value="pendiente"   <?= $fEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
              <option value="dada de baja" <?= $fEstado === 'dada de baja' ? 'selected' : '' ?>>Dada de baja</option>
            </select>
          </label>
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Membresía</th>
                <th>Fecha Registro</th>
                <th>Motivo Baja</th>
                <th>Estado</th>
                <th>Fecha Baja</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($errorDb !== ''): ?>
              <tr>
                <td colspan="8"><?= e($errorDb) ?></td>
              </tr>

            <?php elseif (empty($solicitudes)): ?>
              <tr>
                <td colspan="8">
                  No se encontraron solicitudes.
                  <a href="solicitudes.php">Limpiar filtros</a>
                </td>
              </tr>

            <?php else: ?>
              <?php foreach ($solicitudes as $u): ?>
                <?php
                $id = (int)$u['id_baja'];
                $nombreCompleto = (string)$u['nombre_completo'];
                $activo = $u['estado'] === 'Pendiente';
                ?>

                <tr>
                  <td class="mono">
                    <?= $id ?>
                  </td>

                  <td>
                    <div class="person">
                      <span class="avatar avatar-md">
                        <?= e(iniciales($nombreCompleto)) ?>
                      </span>
                      <strong><?= e($nombreCompleto) ?></strong>
                    </div>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['nombre_plan']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['fecha_registro']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['motivo']) ?>
                  </td>

                  <td>
                    <span class="pill <?= $activo ? 'pill-warn' : 'pill-bad' ?>">
                      <i></i>
                      <?= $activo ? 'Pendiente' : 'Dada de baja' ?>
                    </span>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['fecha_baja']) ?>
                  </td>

                  <td class="text-center">
                    <div class="row-actions">
                      <a href="solicitud-ver.php?id=<?= $id ?>"
                        class="icon-btn"
                        title="Ver detalle">
                        <?= icono('eye', 20) ?>
                      </a>
                    
                      <form method="post" action="solicitud-accion.php">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_solicitud'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button name="accion" value="aprobar" class="icon-btn" title="Aprobar"
                          onclick="return confirm('¿Aprobar esta solicitud?')">
                          <?= icono('check', 20) ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>  
          </table>
        </div>
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