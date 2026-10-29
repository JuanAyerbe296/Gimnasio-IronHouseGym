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

$paginaActual = 'rutinas';
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

$roles = ['Build', 'Balance', 'Burn', 'Breathe'];

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
$fTipo = trim((string)($_GET['detalles_rutina'] ?? ''));
$fEstado = trim((string)($_GET['estado'] ?? ''));

$rutinas = [];
$errorDb = '';

try {
    $sql = "SELECT 
                b.id_bitacora, 
                CONCAT(c.nombre, ' ', c.apellido) AS nombre_completo, 
                b.grupo_muscular, 
                b.detalles_rutina, 
                b.observaciones,
                b.estado
            FROM 
                bitacora b
            INNER JOIN 
                clientes c ON b.id_cliente = c.id_cliente
            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        $sql .= " AND (
            CAST(b.id_bitacora AS CHAR) LIKE ?
            OR c.nombre LIKE ?
            OR c.apellido LIKE ?
            OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
            OR b.detalles_rutina LIKE ?
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
        $sql .= " AND detalles_rutina = ?";
        $tipos .= 's';
        $parametros[] = $fTipo;
    }

    if ($fEstado === 'publicada') {
        $sql .= " AND b.estado = 'Publicada'";
    } elseif ($fEstado === 'borrador') {
        $sql .= " AND b.estado = 'Borrador'";
    }

    $sql .= " ORDER BY b.id_bitacora DESC";

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
        $rutinas[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar las rutinas.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rutinas · Iron House Gym</title>
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
      <div class="breadcrumb"><span>IRON HOUSE /</span> <b>RUTINAS</b></div>
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
          <p class="eyebrow">PLANIFICACIÓN</p>
          <h1>Rutinas</h1>
          <p class="lead lead-tight">Consulta el estado de las rutinas registradas.</p>
        </div>
      </div>

      <section class="panel panel-table">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por código, rutina o cliente...">
          </label>

          <label class="input input-select">
            <select name="detalles_rutina" onchange="this.form.submit()" aria-label="Filtrar por detalles_rutina">
              <option value="">Todos los programas</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>" <?= $fTipo === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="input input-select">
            <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado">
              <option value="">Todos los estados</option>
              <option value="publicada"   <?= $fEstado === 'publicada' ? 'selected' : '' ?>>Publicada</option>
              <option value="borrador" <?= $fEstado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
            </select>
          </label>
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Nombre Rutina</th>
                <th>Programa</th>
                <th>Ejercicios</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($errorDb !== ''): ?>
              <tr>
                <td colspan="7"><?= e($errorDb) ?></td>
              </tr>

            <?php elseif (empty($rutinas)): ?>
              <tr>
                <td colspan="7">
                  No se encontraron rutinas.
                  <a href="rutinas.php">Limpiar filtros</a>
                </td>
              </tr>

            <?php else: ?>
              <?php foreach ($rutinas as $u): ?>
                <?php
                $id = (int)$u['id_bitacora'];
                $nombreCompleto = (string)$u['nombre_completo'];
                $activo = $u['estado'] === 'Publicada';
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
                    <?= e($u['grupo_muscular']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e($u['detalles_rutina']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e($u['observaciones']) ?>
                  </td>

                  <td>
                    <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
                      <i></i>
                      <?= $activo ? 'Publicada' : 'Borrador' ?>
                    </span>
                  </td>

                  <td class="text-center">
                    <div class="row-actions">
                      <a href="rutina-ver.php?id=<?= $id ?>"
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

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>