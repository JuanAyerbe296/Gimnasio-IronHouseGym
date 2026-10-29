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

$paginaActual = 'membresias';
$solicitudesBaja = 0;

$menu = [
    'inicio'      => ['Inicio',              'home',     'recepcion_panel.php'],
    'clientes'    => ['Clientes',            'user',     'clientes.php'],
    'membresias'  => ['Membresías',          'card',     'membresias.php'],
    'tiposd'       => ['Tipos de membresía',  'doc',      'tipos.php'],
    'solicitudes' => ['Solicitudes de baja', 'x-circle', 'solicitudes.php'],
    'perfil'      => ['Mi perfil',           'user',     'perfil.php'],
];

$roles = ['Plan Mensual', 'Plan Trimestral', 'Plan Semestral', 'Plan Anual'];

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

$membresias = [];
$errorDb = '';

try {
    $sql = "SELECT 
                s.id_suscripcion, 
                CONCAT(c.nombre, ' ', c.apellido) AS nombre_completo, 
                m.nombre_plan, 
                s.fecha_inicio, 
                s.fecha_fin, 
                s.estado_suscripcion
            FROM 
                suscripciones_cliente s
            LEFT JOIN 
                clientes c ON s.id_cliente = c.id_cliente
            LEFT JOIN 
                membresias m ON s.id_membresia = m.id_membresia
            WHERE 1=1";

//    $sql = "SELECT id_usuario, nombre, apellido, email,
//                   rol, estado, fecha_creacion
//            FROM usuarios
//            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        $sql .= " AND (
            CAST(s.id_suscripcion AS CHAR) LIKE ?
            OR c.nombre LIKE ?
            OR c.apellido LIKE ?
            OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
            OR s.estado_suscripcion LIKE ?
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

    if ($fEstado === 'vigente') {
        $sql .= " AND s.estado_suscripcion = 'Vigente'";
    } elseif ($fEstado === 'vencida') {
        $sql .= " AND s.estado_suscripcion = 'Vencida'";
    } elseif ($fEstado === 'dada de baja') {
        $sql .= " AND s.estado_suscripcion = 'Dada de baja'";
    }

    $sql .= " ORDER BY s.id_suscripcion DESC";

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
        $membresias[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar las membresias.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Membresias · Iron House Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/usuarios.css">
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>MEMBRESÍAS</b></div>
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
      <div class="page-head">
        <div>
          <p class="eyebrow">ADMINISTRACIÓN</p>
          <h1>Membresías</h1>
          <p class="lead lead-tight">Registra membresías y consulta su vigencia e historial.</p>
        </div>
        <button type="button" class="btn btn-red btn-icon" data-abrir-modal="modal-registrar-membresia">
          <?= icono('plus', 20) ?>Nueva membresía
        </button>
      </div>

      <section class="panel panel-table">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar cliente...">
          </label>

          <label class="input input-select">
            <select name="tipo" onchange="this.form.submit()" aria-label="Filtrar por tipo">
              <option value="">Todos los tipos</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>" <?= $fTipo === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="input input-select">
            <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado">
              <option value="">Todos los estados</option>
              <option value="vigente"   <?= $fEstado === 'vigente' ? 'selected' : '' ?>>Vigente</option>
              <option value="vencida" <?= $fEstado === 'vencida' ? 'selected' : '' ?>>Vencida</option>
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
                <th>Tipo</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($errorDb !== ''): ?>
              <tr>
                <td colspan="7"><?= e($errorDb) ?></td>
              </tr>

            <?php elseif (empty($membresias)): ?>
              <tr>
                <td colspan="7">
                  No se encontraron membresias.
                  <a href="membresias.php">Limpiar filtros</a>
                </td>
              </tr>

            <?php else: ?>
              <?php foreach ($membresias as $u): ?>
                <?php
                $id = (int)$u['id_suscripcion'];
                $nombreCompleto = (string)$u['nombre_completo'];
                $activo = $u['estado_suscripcion'] === 'Vigente';
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
                    <?= e((string)$u['fecha_inicio']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['fecha_fin']) ?>
                  </td>

                  <td>
                    <span class="pill <?= $activo ? 'pill-ok' : 'pill-warn' ?>">
                      <i></i>
                      <?= $activo ? 'Vigente' : 'Vencida' ?>
                    </span>
                  </td>

                  <td class="text-center">
                    <div class="row-actions">
                      <a href="membresia-ver.php?id=<?= $id ?>"
                        class="icon-btn"
                        title="Ver detalle">
                        <?= icono('eye', 20) ?>
                      </a>
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

<?php include __DIR__ . '/modal_registrar_membresia.php'; ?>
<script src="js/modal_form.js"></script>

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>