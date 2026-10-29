/* Modal "Crear usuario": abrir/cerrar, validación, envío por fetch y confirmación. */
(function () {
  'use strict';

  const modal   = document.getElementById('modal-nuevo-usuario');
  if (!modal) return;

  const form    = document.getElementById('form-nuevo-usuario');
  const vistaForm = document.getElementById('mnu-formulario');
  const vistaOk   = document.getElementById('mnu-exito');
  const btnEnviar = document.getElementById('mnu-enviar');
  const errGeneral = document.getElementById('mnu-error-general');
  let creado = false;

  /* ---------- Abrir / cerrar ---------- */
  document.querySelectorAll('[data-abrir-modal="modal-nuevo-usuario"]').forEach(function (b) {
    b.addEventListener('click', abrir);
  });
  modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) {
    b.addEventListener('click', cerrar);
  });
  // Clic en el fondo oscuro (fuera de la tarjeta) cierra el modal
  modal.addEventListener('click', function (ev) {
    if (ev.target === modal) cerrar();
  });
  // Esc ya cierra el <dialog>; aquí solo limpiamos
  modal.addEventListener('close', limpiar);

  function abrir() {
    limpiar();
    modal.showModal();
    document.getElementById('mnu-nombres').focus();
  }
  function cerrar() {
    modal.close();
    if (creado) window.location.href = 'usuarios.php?creado=1';
  }
  function limpiar() {
    form.reset();
    limpiarErrores();
    vistaOk.hidden = true;
    vistaForm.hidden = false;
    btnEnviar.disabled = false;
    btnEnviar.textContent = 'Crear usuario';
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
    const v = Object.fromEntries(new FormData(form));
    const errores = {};
    if (!v.nombres.trim())   errores.nombres   = 'Ingresa los nombres.';
    if (!v.apellidos.trim()) errores.apellidos = 'Ingresa los apellidos.';
    if (!v.correo.trim())    errores.correo    = 'Ingresa el correo electrónico.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.correo.trim())) errores.correo = 'El correo no es válido.';
    if (!v.rol)              errores.rol       = 'Selecciona un rol.';
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
    btnEnviar.textContent = 'Creando…';

    try {
      const resp = await fetch('usuario_crear.php', {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      });
      const data = await resp.json();

      if (data.ok) {
        creado = true;
        document.getElementById('mnu-exito-texto').textContent =
          'La cuenta de ' + data.usuario.nombre + ' (' + data.usuario.codigo + ') se creó correctamente.';
        document.getElementById('mnu-pass').textContent = data.password_temporal;
        vistaForm.hidden = true;
        vistaOk.hidden = false;
        document.getElementById('mnu-listo').focus();
        return;
      }

      Object.keys(data.errores || {}).forEach(function (c) { mostrarError(c, data.errores[c]); });
      if (data.mensaje) { errGeneral.textContent = data.mensaje; errGeneral.hidden = false; }
    } catch (e) {
      errGeneral.textContent = 'No se pudo conectar con el servidor. Inténtalo de nuevo.';
      errGeneral.hidden = false;
    }

    btnEnviar.disabled = false;
    btnEnviar.textContent = 'Crear usuario';
  });

  /* ---------- Confirmación ---------- */
  document.getElementById('mnu-listo').addEventListener('click', cerrar);
  document.getElementById('mnu-copiar').addEventListener('click', function () {
    const btn = this;
    const texto = document.getElementById('mnu-pass').textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(texto) : Promise.reject())
      .then(function () { btn.textContent = '¡Copiada!'; })
      .catch(function () { btn.textContent = 'Selecciónala y copia'; });
    setTimeout(function () { btn.textContent = 'Copiar'; }, 2000);
  });
})();
