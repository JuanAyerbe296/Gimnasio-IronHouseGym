<?php
/**
 * Modal "Crear usuario". Se incluye al final de usuarios.php:
 *     <?php include __DIR__ . '/modal_nuevo_usuario.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_usuario'])) {
    $_SESSION['csrf_usuario'] = bin2hex(random_bytes(32));
}

// Debe coincidir con las llaves de $MAPA_ROLES en usuario_crear.php
$rolesModal = ['Administrador', 'Recepcionista', 'Entrenador', 'Cliente'];
?>
<dialog id="modal-nuevo-usuario" class="modal" aria-labelledby="mnu-titulo">
  <div class="modal-card">

    <!-- ===== Formulario ===== -->
    <div id="mnu-formulario">
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">NUEVA CUENTA</p>
          <h2 id="mnu-titulo">Crear usuario</h2>
        </div>
        <button type="button" class="modal-close" data-cerrar-modal aria-label="Cerrar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
      </header>

      <p class="modal-lead">Los permisos de la cuenta dependerán del rol seleccionado.</p>

      <form id="form-nuevo-usuario" novalidate autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_usuario'], ENT_QUOTES, 'UTF-8') ?>">

        <p class="m-error m-error-general" id="mnu-error-general" role="alert" hidden></p>

        <div class="m-grid">
          <div class="m-field">
            <label for="mnu-nombres">Nombres <span class="req">*</span></label>
            <input type="text" id="mnu-nombres" name="nombres" placeholder="Nombres" maxlength="60" required>
            <p class="m-error" data-error-for="nombres" hidden></p>
          </div>

          <div class="m-field">
            <label for="mnu-apellidos">Apellidos <span class="req">*</span></label>
            <input type="text" id="mnu-apellidos" name="apellidos" placeholder="Apellidos" maxlength="60" required>
            <p class="m-error" data-error-for="apellidos" hidden></p>
          </div>

          <div class="m-field">
            <label for="mnu-correo">Correo electrónico <span class="req">*</span></label>
            <input type="email" id="mnu-correo" name="correo" placeholder="nombre@correo.com" maxlength="120" required>
            <p class="m-error" data-error-for="correo" hidden></p>
          </div>

          <div class="m-field">
            <label for="mnu-rol">Rol <span class="req">*</span></label>
            <select id="mnu-rol" name="rol" required>
              <option value="">Selecciona un rol</option>
              <?php foreach ($rolesModal as $r): ?>
                <option value="<?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <p class="m-error" data-error-for="rol" hidden></p>
          </div>

          <div class="m-field">
            <label for="mnu-estado">Estado inicial</label>
            <select id="mnu-estado" name="estado">
              <option value="Activo" selected>Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
            <p class="m-error" data-error-for="estado" hidden></p>
          </div>
        </div>

        <footer class="modal-foot">
          <button type="button" class="m-btn m-btn-outline" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="m-btn m-btn-red" id="mnu-enviar">Crear usuario</button>
        </footer>
      </form>
    </div>

    <!-- ===== Confirmación (se muestra tras crear la cuenta) ===== -->
    <div id="mnu-exito" hidden>
      <header class="modal-head">
        <div>
          <p class="modal-eyebrow">CUENTA CREADA</p>
          <h2>Usuario creado</h2>
        </div>
      </header>

      <p class="modal-lead" id="mnu-exito-texto"></p>

      <div class="m-pass">
        <span class="m-pass-label">Contraseña temporal</span>
        <div class="m-pass-row">
          <code id="mnu-pass"></code>
          <button type="button" class="m-btn m-btn-outline m-btn-sm" id="mnu-copiar">Copiar</button>
        </div>
        <p class="m-pass-note">Compártela de forma segura. No se volverá a mostrar y se recomienda cambiarla en el primer acceso.</p>
      </div>

      <footer class="modal-foot">
        <button type="button" class="m-btn m-btn-red" id="mnu-listo">Listo</button>
      </footer>
    </div>

  </div>
</dialog>
