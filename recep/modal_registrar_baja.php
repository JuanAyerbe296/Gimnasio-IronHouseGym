<?php
/**
 * Modal "Registrar baja" (solicitudes de baja). Se incluye al final de solicitudes.php:
 *     <?php include __DIR__ . '/modal_registrar_baja.php'; ?>
 * El botón que lo abre lleva:  data-abrir-modal="modal-registrar-baja"
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conexion.php';          // deja disponible $cnx (mysqli)
require_once __DIR__ . '/helpers.php';           // función e()
require_once __DIR__ . '/bajas_config.php';
require_once __DIR__ . '/clientes_config.php';

if (empty($_SESSION['csrf_solicitud'])) {
    $_SESSION['csrf_solicitud'] = bin2hex(random_bytes(32));
}

// Suscripciones que se pueden dar de baja: de clientes activos y sin una baja pendiente o aprobada.
$clientesModal = [];      // id => nombre
$suscripcionesModal = []; // [id_suscripcion, id_cliente, plan]
try {
    $sql = "SELECT s." . SUS_ID . ", c." . CLI_ID . ", CONCAT(c." . CLI_NOMBRE . ", ' ', c." . CLI_APELLIDO . "), m." . PLAN_NOMBRE
         . " FROM " . SUS_TABLA . " s"
         . " INNER JOIN " . CLI_TABLA . " c ON c." . CLI_ID . " = s." . SUS_ID_CLIENTE
         . " INNER JOIN " . PLAN_TABLA . " m ON m." . PLAN_ID . " = s." . SUS_ID_MEMBRESIA
         . " LEFT JOIN " . BAJ_TABLA . " b ON b." . BAJ_ID_SUSCRIPCION . " = s." . SUS_ID
         .   " AND b." . BAJ_ESTADO . " IN ('" . BAJ_ESTADO_PENDIENTE . "', '" . BAJ_ESTADO_APROBADA . "')"
         . " WHERE c." . CLI_ESTADO . " = 'Activo' AND b." . BAJ_ID . " IS NULL"
         . " ORDER BY c." . CLI_NOMBRE . ", c." . CLI_APELLIDO . ", s." . SUS_ID;
    $res = mysqli_query($cnx, $sql);
    while ($res && ($f = mysqli_fetch_row($res))) {
        $suscripcionesModal[] = [(int)$f[0], (int)$f[1], (string)$f[3]];
        $clientesModal[(int)$f[1]] = (string)$f[2];
    }
} catch (Throwable $ex) {
    error_log('[modal_registrar_baja.php] ' . $ex->getMessage());
}

$esAdminModal = (($_SESSION['rol'] ?? '') === 'Administrador');
?>
<dialog id="modal-registrar-baja" class="modal" aria-labelledby="mrb-titulo"
        data-form-modal data-endpoint="baja_crear.php" data-redirect="solicitudes.php?creado=1">
  <div class="modal-card">

    <!-- ===== Formulario ===== -->
    <div data-view="form">
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">NUEVA SOLICITUD</p>
          <h2 id="mrb-titulo">Registrar baja</h2>
        </div>
        <button type="button" class="modal-close" data-cerrar-modal aria-label="Cerrar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
      </header>

      <form novalidate autocomplete="off" class="modal-form">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_solicitud']) ?>">

        <p class="m-error m-error-general" data-error-general role="alert" hidden></p>

        <div class="m-grid">
          <div class="m-field">
            <label for="mrb-cliente">Cliente</label>
            <select id="mrb-cliente" name="cliente" required>
              <option value="">Selecciona un cliente</option>
              <?php foreach ($clientesModal as $idCli => $nombreCli): ?>
                <option value="<?= (int)$idCli ?>"><?= e($nombreCli) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="m-error" data-error-for="cliente" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrb-membresia">Membresía relacionada</label>
            <select id="mrb-membresia" name="membresia" required>
              <option value="">Selecciona una membresía</option>
              <?php foreach ($suscripcionesModal as [$idSus, $idCli, $plan]): ?>
                <option value="<?= (int)$idSus ?>" data-cliente="<?= (int)$idCli ?>" hidden disabled><?= e($plan) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="m-error" data-error-for="membresia" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrb-fecha">Fecha de solicitud</label>
            <input type="date" id="mrb-fecha" name="fecha" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            <p class="m-error" data-error-for="fecha" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrb-estado">Estado inicial</label>
            <select id="mrb-estado" name="estado">
              <option value="<?= e(BAJ_ESTADO_PENDIENTE) ?>" selected><?= e(BAJ_ESTADO_PENDIENTE) ?></option>
              <?php if ($esAdminModal): ?>
                <option value="<?= e(BAJ_ESTADO_APROBADA) ?>"><?= e(BAJ_ESTADO_APROBADA) ?></option>
              <?php endif; ?>
            </select>
            <p class="m-error" data-error-for="estado" hidden></p>
          </div>

          <div class="m-field m-field-full">
            <label for="mrb-motivo">Motivo de la baja</label>
            <textarea id="mrb-motivo" name="motivo" maxlength="500" required></textarea>
            <p class="m-error" data-error-for="motivo" hidden></p>
          </div>
        </div>

        <footer class="modal-foot">
          <button type="button" class="m-btn m-btn-outline" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="m-btn m-btn-red" data-submit>Registrar solicitud</button>
        </footer>
      </form>
    </div>

    <!-- ===== Confirmación ===== -->
    <div data-view="ok" hidden>
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">SOLICITUD REGISTRADA</p>
          <h2>Baja registrada</h2>
        </div>
      </header>
      <p class="modal-lead" data-ok-text></p>
      <footer class="modal-foot">
        <button type="button" class="m-btn m-btn-red" data-listo>Listo</button>
      </footer>
    </div>

  </div>
</dialog>
<script>
// La lista de membresías muestra solo las del cliente elegido.
(function () {
  var modal = document.getElementById('modal-registrar-baja');
  if (!modal) return;
  var cliente = modal.querySelector('#mrb-cliente');
  var membresia = modal.querySelector('#mrb-membresia');
  var opciones = [].slice.call(membresia.querySelectorAll('option[data-cliente]'));

  function filtrar() {
    var id = cliente.value;
    membresia.value = '';
    opciones.forEach(function (o) {
      var visible = id !== '' && o.getAttribute('data-cliente') === id;
      o.hidden = !visible;
      o.disabled = !visible;
    });
    // Si el cliente tiene una sola membresía elegible, se selecciona sola
    var visibles = opciones.filter(function (o) { return !o.disabled; });
    if (visibles.length === 1) membresia.value = visibles[0].value;
  }

  cliente.addEventListener('change', filtrar);
  modal.querySelector('form').addEventListener('reset', function () { setTimeout(filtrar, 0); });
  filtrar();
})();
</script>
