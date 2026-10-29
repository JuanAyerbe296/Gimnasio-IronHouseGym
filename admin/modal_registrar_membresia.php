<?php
/**
 * Modal "Registrar membresía". Se incluye al final de membresias.php:
 *     <?php include __DIR__ . '/modal_registrar_membresia.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conexion.php';           // deja disponible $cnx (mysqli)
require_once __DIR__ . '/membresias_config.php';
require_once __DIR__ . '/helpers.php';            // función e() para escapar HTML

if (empty($_SESSION['csrf_membresia'])) {
    $_SESSION['csrf_membresia'] = bin2hex(random_bytes(32));
}

$clientesModal = [];
$tiposModal    = [];
try {
    $clientesModal = clientesParaSelect($cnx);
    $tiposModal    = tiposParaSelect($cnx);
} catch (Throwable $ex) {
    error_log('[modal_registrar_membresia.php] ' . $ex->getMessage());
}

?>
<dialog id="modal-registrar-membresia" class="modal" aria-labelledby="mrm-titulo"
        data-form-modal data-endpoint="membresia_crear.php" data-redirect="membresias.php?creado=1">
  <div class="modal-card">

    <!-- ===== Formulario ===== -->
    <div data-view="form">
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">NUEVA MEMBRESÍA</p>
          <h2 id="mrm-titulo">Registrar membresía</h2>
        </div>
        <button type="button" class="modal-close" data-cerrar-modal aria-label="Cerrar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
      </header>

      <form novalidate autocomplete="off" class="modal-form">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_membresia']) ?>">

        <p class="m-error m-error-general" data-error-general role="alert" hidden></p>

        <div class="m-grid">
          <div class="m-field">
            <label for="mrm-cliente">Cliente asociado</label>
            <select id="mrm-cliente" name="cliente" required>
              <option value="">Selecciona un cliente</option>
              <?php foreach ($clientesModal as [$id, $nombre]): ?>
                <option value="<?= (int) $id ?>"><?= e((string) $nombre) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="m-error" data-error-for="cliente" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrm-tipo">Tipo de membresía</label>
            <select id="mrm-tipo" name="tipo" required>
              <option value="">Selecciona un tipo</option>
              <?php foreach ($tiposModal as [$id, $nombre]): ?>
                <option value="<?= (int) $id ?>"><?= e((string) $nombre) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="m-error" data-error-for="tipo" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrm-inicio">Fecha de inicio</label>
            <input type="date" id="mrm-inicio" name="fecha_inicio" required>
            <p class="m-error" data-error-for="fecha_inicio" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrm-venc">Fecha de vencimiento</label>
            <input type="date" id="mrm-venc" name="fecha_vencimiento" required>
            <p class="m-error" data-error-for="fecha_vencimiento" hidden></p>
          </div>
        </div>

        <footer class="modal-foot">
          <button type="button" class="m-btn m-btn-outline" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="m-btn m-btn-red" data-submit>Registrar membresía</button>
        </footer>
      </form>
    </div>

    <!-- ===== Confirmación ===== -->
    <div data-view="ok" hidden>
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">REGISTRO COMPLETO</p>
          <h2>Membresía registrada</h2>
        </div>
      </header>
      <p class="modal-lead" data-ok-text></p>
      <footer class="modal-foot">
        <button type="button" class="m-btn m-btn-red" data-listo>Listo</button>
      </footer>
    </div>

  </div>
</dialog>
