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

$clientes = [];
$errorDb = '';

try {
    $sql = "SELECT 
                id_cliente, 
                CONCAT(nombre, ' ', apellido) AS nombre_completo, 
                documento, 
                email, 
                telefono, 
                estado
            FROM clientes
            WHERE 1=1";

    $tipos = '';
    $parametros = [];

    if ($q !== '') {
        $sql .= " AND (
            CAST(id_cliente AS CHAR) LIKE ?
            OR nombre LIKE ?
            OR apellido LIKE ?
            OR CONCAT(nombre, ' ', apellido) LIKE ?
            OR documento LIKE ?
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

    if ($fEstado === 'activo') {
        $sql .= " AND estado = 'Activo'";
    } elseif ($fEstado === 'inactivo') {
        $sql .= " AND estado = 'Inactivo'";
    }

    $sql .= " ORDER BY id_cliente DESC";

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
        $clientes[] = $fila;
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorDb = 'No se pudieron cargar los clientes.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clientes · Iron House Gym</title>
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

    <main class="content">
      <div class="page-head">
        <div>
          <p class="eyebrow">RECEPCIÓN</p>
          <h1>Gestión de clientes</h1>
          <p class="lead lead-tight">Consulta y actualiza la información autorizada de los clientes.</p>
        </div>
        <button type="button" class="btn btn-red btn-icon" data-abrir-modal="modal-registrar-cliente">
          <?= icono('plus', 20) ?>Registrar cliente
        </button>
      </div>

      <section class="panel panel-table">
        <form method="get" class="toolbar">
          <label class="input input-search">
            <?= icono('search', 20) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por código, nombre o documento…">
          </label>

          <label class="input input-select">
            <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado">
              <option value="">Todos los estados</option>
              <option value="activo"   <?= $fEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= $fEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </label>
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre completo</th>
                <th>Documento</th>
                <th>Correo electrónico</th>
                <th>Teléfono</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($errorDb !== ''): ?>
              <tr>
                <td colspan="7"><?= e($errorDb) ?></td>
              </tr>

            <?php elseif (empty($clientes)): ?>
              <tr>
                <td colspan="7">
                  No se encontraron clientes.
                  <a href="clientes.php">Limpiar filtros</a>
                </td>
              </tr>

            <?php else: ?>
              <?php foreach ($clientes as $u): ?>
                <?php
                $id = (int)$u['id_cliente'];
                $nombreCompleto = (string)$u['nombre_completo'];
                $activo = $u['estado'] === 'Activo';
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
                    <?= e((string)$u['documento']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['email']) ?>
                  </td>

                  <td class="muted-strong">
                    <?= e((string)$u['telefono']) ?>
                  </td>

                  <td>
                    <span class="pill <?= $activo ? 'pill-ok' : 'pill-off' ?>">
                      <i></i>
                      <?= $activo ? 'Activo' : 'Inactivo' ?>
                    </span>
                  </td>

                  <td class="text-center">
                    <div class="row-actions">
                      <a href="cliente-ver.php?id=<?= $id ?>"
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

<?php include __DIR__ . '/modal_registrar_cliente.php'; ?>
<script src="js/modal_form.js"></script>

<?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
</body>
</html>