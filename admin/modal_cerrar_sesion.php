<?php
/**
 * Modal de confirmación "Cerrar sesión".
 * Se incluye una vez por página, antes de </body>:
 *     <?php include __DIR__ . '/modal_cerrar_sesion.php'; ?>
 * Y el enlace de cerrar sesión del menú lleva:  data-abrir-modal="modal-cerrar-sesion"
 */
?>
<dialog id="modal-cerrar-sesion" class="modal modal-confirm" role="alertdialog"
        aria-labelledby="mcs-titulo" aria-describedby="mcs-texto">
  <div class="modal-card">
    <span class="confirm-icon" aria-hidden="true">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>
      </svg>
    </span>

    <p class="modal-eyebrow">CONFIRMACIÓN REQUERIDA</p>
    <h2 id="mcs-titulo">Cerrar sesión</h2>
    <p class="confirm-text" id="mcs-texto">¿Estás seguro de que deseas cerrar sesión?</p>

    <footer class="modal-foot">
      <button type="button" class="m-btn m-btn-outline" data-cerrar-modal autofocus>Cancelar</button>
      <a href="logout.php" class="m-btn m-btn-soft">Cerrar sesión</a>
    </footer>
  </div>
</dialog>
<script>
(function () {
  var modal = document.getElementById('modal-cerrar-sesion');
  if (!modal || typeof modal.showModal !== 'function') return;   // sin soporte: el enlace funciona normal

  document.querySelectorAll('[data-abrir-modal="modal-cerrar-sesion"]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      ev.preventDefault();          // evita ir directo a logout.php
      modal.showModal();
    });
  });

  modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) {
    b.addEventListener('click', function () { modal.close(); });
  });

  // Clic en el fondo oscuro cierra el modal (Esc ya lo cierra de forma nativa)
  modal.addEventListener('click', function (ev) { if (ev.target === modal) modal.close(); });
})();
</script>
