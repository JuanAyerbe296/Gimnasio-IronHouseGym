<?php
/**
 * Modal "Registrar cliente". Se incluye al final de clientes.php:
 *     <?php include __DIR__ . '/modal_registrar_cliente.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_cliente'])) {
    $_SESSION['csrf_cliente'] = bin2hex(random_bytes(32));
}
?>
<dialog id="modal-registrar-cliente" class="modal" aria-labelledby="mrc-titulo"
        data-form-modal data-endpoint="cliente_crear.php" data-redirect="clientes.php?creado=1">
  <div class="modal-card">

    <!-- ===== Formulario ===== -->
    <div data-view="form">
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">NUEVO REGISTRO</p>
          <h2 id="mrc-titulo">Registrar cliente</h2>
        </div>
        <button type="button" class="modal-close" data-cerrar-modal aria-label="Cerrar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
      </header>

      <form novalidate autocomplete="off" class="modal-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente'], ENT_QUOTES, 'UTF-8') ?>">

        <p class="m-error m-error-general" data-error-general role="alert" hidden></p>

        <div class="m-grid">
          <div class="m-field">
            <label for="mrc-nombres">Nombres <span class="req">*</span></label>
            <input type="text" id="mrc-nombres" name="nombres" placeholder="Nombres del cliente" maxlength="60" required>
            <p class="m-error" data-error-for="nombres" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrc-apellidos">Apellidos <span class="req">*</span></label>
            <input type="text" id="mrc-apellidos" name="apellidos" placeholder="Apellidos del cliente" maxlength="60" required>
            <p class="m-error" data-error-for="apellidos" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrc-documento">Documento de identificación <span class="req">*</span></label>
            <input type="text" id="mrc-documento" name="documento" placeholder="Número de documento" maxlength="20" required>
            <p class="m-error" data-error-for="documento" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrc-correo">Correo electrónico <span class="req">*</span></label>
            <input type="email" id="mrc-correo" name="correo" placeholder="nombre@correo.com" maxlength="120" required>
            <p class="m-error" data-error-for="correo" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrc-telefono">Teléfono</label>
            <input type="tel" id="mrc-telefono" name="telefono" placeholder="+00 000 000 000" maxlength="9" required>
            <p class="m-error" data-error-for="telefono" hidden></p>
          </div>

          <div class="m-field">
            <label for="mrc-estado">Estado del registro</label>
            <select id="mrc-estado" name="estado">
              <option value="Activo" selected>Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
            <p class="m-error" data-error-for="estado" hidden></p>
          </div>
        </div>

        <footer class="modal-foot">
          <button type="button" class="m-btn m-btn-outline" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="m-btn m-btn-red" data-submit>Guardar cliente</button>
        </footer>
      </form>
    </div>

    <!-- ===== Confirmación ===== -->
    <div data-view="ok" hidden>
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">REGISTRO COMPLETO</p>
          <h2>Cliente registrado</h2>
        </div>
      </header>
      <p class="modal-lead" data-ok-text></p>
      <footer class="modal-foot">
        <button type="button" class="m-btn m-btn-red" data-listo>Listo</button>
      </footer>
    </div>

  </div>
</dialog>
