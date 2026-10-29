<?php
/**
 * Modal "Nuevo tipo de membresía". Se incluye al final de tipos.php:
 *     <?php include __DIR__ . '/modal_nuevo_tipo.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_tipo'])) {
    $_SESSION['csrf_tipo'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/helpers.php';   // función e()
?>
<dialog id="modal-nuevo-tipo" class="modal" aria-labelledby="mnt-titulo"
        data-form-modal data-endpoint="tipo_crear.php" data-redirect="tipos.php?creado=1">
  <div class="modal-card">

    <!-- ===== Formulario ===== -->
    <div data-view="form">
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">CONFIGURACIÓN</p>
          <h2 id="mnt-titulo">Nuevo tipo de membresía</h2>
        </div>
        <button type="button" class="modal-close" data-cerrar-modal aria-label="Cerrar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
      </header>

      <form novalidate autocomplete="off" class="modal-form">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_tipo']) ?>">

        <p class="m-error m-error-general" data-error-general role="alert" hidden></p>

        <div class="m-grid">
          <div class="m-field">
            <label for="mnt-nombre">Nombre del tipo de membresía <span class="req">*</span></label>
            <input type="text" id="mnt-nombre" name="nombre" placeholder="Ej. Plan bimestral" maxlength="60" required>
            <p class="m-error" data-error-for="nombre" hidden></p>
          </div>

          <div class="m-field">
            <label for="mnt-estado">Estado</label>
            <select id="mnt-estado" name="estado">
              <option value="Activo" selected>Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
            <p class="m-error" data-error-for="estado" hidden></p>
          </div>
        </div>

        <footer class="modal-foot">
          <button type="button" class="m-btn m-btn-outline" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="m-btn m-btn-red" data-submit>Registrar tipo</button>
        </footer>
      </form>
    </div>

    <!-- ===== Confirmación ===== -->
    <div data-view="ok" hidden>
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">REGISTRO COMPLETO</p>
          <h2>Tipo registrado</h2>
        </div>
      </header>
      <p class="modal-lead" data-ok-text></p>
      <footer class="modal-foot">
        <button type="button" class="m-btn m-btn-red" data-listo>Listo</button>
      </footer>
    </div>

  </div>
</dialog>
