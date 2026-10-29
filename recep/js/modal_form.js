/* Modal de formulario genérico.
 * Se activa en cada <dialog data-form-modal data-endpoint="..." data-redirect="..."> de la página.
 * Abre con cualquier elemento [data-abrir-modal="ID_DEL_DIALOG"]. */
(function () {
  'use strict';

  document.querySelectorAll('dialog[data-form-modal]').forEach(iniciar);

  function iniciar(modal) {
    const form     = modal.querySelector('form');
    const vistaForm = modal.querySelector('[data-view="form"]');
    const vistaOk   = modal.querySelector('[data-view="ok"]');
    const btnEnviar = modal.querySelector('[data-submit]');
    const errGeneral = modal.querySelector('[data-error-general]');
    const textoOk   = modal.querySelector('[data-ok-text]');
    const textoBoton = btnEnviar.textContent;
    const endpoint  = modal.dataset.endpoint;
    const destino   = modal.dataset.redirect;
    let hecho = false;

    /* ---------- Abrir / cerrar ---------- */
    document.querySelectorAll('[data-abrir-modal="' + modal.id + '"]').forEach(function (b) {
      b.addEventListener('click', abrir);
    });
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) {
      b.addEventListener('click', cerrar);
    });
    modal.addEventListener('click', function (ev) { if (ev.target === modal) cerrar(); });
    modal.addEventListener('close', function () { if (hecho) { window.location.href = destino; } else { limpiar(); } });

    function abrir() {
      limpiar();
      modal.showModal();
      const primero = form.querySelector('input:not([type="hidden"]), select');
      if (primero) primero.focus();
    }
    function cerrar() { modal.close(); }
    function limpiar() {
      form.reset();
      limpiarErrores();
      vistaOk.hidden = true;
      vistaForm.hidden = false;
      btnEnviar.disabled = false;
      btnEnviar.textContent = textoBoton;
    }

    /* ---------- Errores ---------- */
    function limpiarErrores() {
      errGeneral.hidden = true;
      errGeneral.textContent = '';
      form.querySelectorAll('.m-field').forEach(function (f) { f.classList.remove('has-error'); });
      form.querySelectorAll('[data-error-for]').forEach(function (p) { p.hidden = true; p.textContent = ''; });
    }
    function mostrarError(campo, mensaje) {
      const p = form.querySelector('[data-error-for="' + campo + '"]');
      if (!p) return;
      p.textContent = mensaje;
      p.hidden = false;
      p.closest('.m-field').classList.add('has-error');
    }

    /* ---------- Validación en el navegador (el servidor valida de nuevo) ---------- */
    function validar() {
      const errores = {};
      form.querySelectorAll('[required]').forEach(function (campo) {
        const valor = campo.value.trim();
        if (!valor) {
          errores[campo.name] = campo.tagName === 'SELECT' ? 'Selecciona una opción.' : 'Este campo es obligatorio.';
        } else if (campo.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
          errores[campo.name] = 'El correo no es válido.';
        }
      });
      return errores;
    }

    /* ---------- Envío ---------- */
    form.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      limpiarErrores();

      const errores = validar();
      const claves = Object.keys(errores);
      if (claves.length) {
        claves.forEach(function (c) { mostrarError(c, errores[c]); });
        form.querySelector('[name="' + claves[0] + '"]').focus();
        return;
      }

      btnEnviar.disabled = true;
      btnEnviar.textContent = 'Guardando…';

      try {
        const resp = await fetch(endpoint, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'fetch' },
          credentials: 'same-origin'
        });
        const data = await resp.json();

        if (data.ok) {
          hecho = true;
          textoOk.textContent = data.mensaje || 'El registro se guardó correctamente.';
          vistaForm.hidden = true;
          vistaOk.hidden = false;
          modal.querySelector('[data-listo]').focus();
          return;
        }

        Object.keys(data.errores || {}).forEach(function (c) { mostrarError(c, data.errores[c]); });
        if (data.mensaje) { errGeneral.textContent = data.mensaje; errGeneral.hidden = false; }
      } catch (e) {
        errGeneral.textContent = 'No se pudo conectar con el servidor. Inténtalo de nuevo.';
        errGeneral.hidden = false;
      }

      btnEnviar.disabled = false;
      btnEnviar.textContent = textoBoton;
    });

    modal.querySelector('[data-listo]').addEventListener('click', cerrar);
  }
})();
