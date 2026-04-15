// =====================================================
// VERIFICAR.JS — Dashboard del Verificador
// Maneja los modales de detalle de trámite,
// el formulario de actualización de estatus,
// la constancia de número oficial, fotos y croquis
// =====================================================

document.addEventListener('DOMContentLoaded', () => {

  // =====================================================
  // VALIDACIONES DE INPUTS EN TIEMPO REAL
  // Para los campos del formulario de constancia
  // =====================================================

  // Forzar mayúsculas y filtrar caracteres raros en campos de dirección
  document.querySelectorAll('.input-mayusculas').forEach(input => {
    input.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
      this.value = this.value.replace(/[^A-Z0-9\s\-]/g, '');
    });
    input.addEventListener('paste', function() {
      setTimeout(() => {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s\-]/g, '');
      }, 10);
    });
  });

  // Cuenta catastral: solo números (nada de letras ni guiones)
  document.querySelectorAll('.input-solo-numeros').forEach(input => {
    input.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
    input.addEventListener('paste', function() {
      setTimeout(() => {
        this.value = this.value.replace(/[^0-9]/g, '');
      }, 10);
    });
    input.addEventListener('keypress', function(e) {
      if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab') {
        e.preventDefault();
      }
    });
  });

  // =====================================================
  // PANEL DE CORRECCIÓN
  // Cuando el verificador selecciona "En corrección",
  // aparece un panel para marcar qué documentos faltan
  // y se genera automáticamente el mensaje de WhatsApp
  // =====================================================

  // Textos de ayuda que aparecen al seleccionar cada estatus
  const hints = {
    'En revision':              'El trámite continúa en proceso de revisión.',
    'En correccion':            'Se notificará al ciudadano que debe corregir su expediente.',
    'Aprobado por Verificador': 'El trámite pasará a la Ventanilla para firma final del Director.',
    'Rechazado':                'El trámite se rechaza definitivamente.'
  };

  // Muestra u oculta el panel de corrección según el estatus elegido
  function actualizarPanelCorreccion() {
    const estatus = document.getElementById('m_estatus')?.value || '';
    const panel   = document.getElementById('panel-correccion');
    const obs     = document.getElementById('m_observaciones');
    if (!panel) return;

    if (estatus === 'En corrección') {
      panel.style.display = 'block';
      if (obs) obs.placeholder = 'Observaciones adicionales (opcional, ya incluidas arriba)...';
      construirMensajeCorreccion();
    } else {
      panel.style.display = 'none';
      if (obs) obs.placeholder = 'Escribe observaciones generales del trámite...';
    }
  }

  // Genera el texto del mensaje de WhatsApp con los docs marcados como faltantes
  function construirMensajeCorreccion() {
    const folio        = document.getElementById('m_folio')?.textContent || '';
    const nombre       = document.getElementById('m_propietario')?.textContent || '';
    const primerNombre = nombre.trim().split(' ')[0];
    const extra        = document.getElementById('correccion_extra')?.value.trim() || '';

    const checks = document.querySelectorAll('.check-correccion:checked');
    const docs   = Array.from(checks).map(c => '• ' + c.value);

    let msg = `Hola ${primerNombre}, su trámite *${folio}* requiere CORRECCIÓN para continuar con el proceso.\n`;
    if (docs.length > 0) {
      msg += `\nDocumentos/requisitos pendientes:\n${docs.join('\n')}\n`;
    }
    if (extra) {
      msg += `\nIndicación adicional: ${extra}\n`;
    }
    msg += `\nFavor de presentarse con los documentos indicados en las oficinas de la Dirección de Planeación y Desarrollo Urbano.\n— Dirección de Planeación y D.U.`;

    // También llenamos el campo de observaciones del formulario para que se guarde en BD
    const obsField = document.getElementById('m_observaciones');
    if (obsField) {
      const partesDoc = docs.length > 0 ? 'Documentos/requisitos: ' + docs.map(d => d.replace('• ', '')).join(', ') : '';
      obsField.value = [partesDoc, extra].filter(Boolean).join(' | ');
    }

    // Mostrar preview del mensaje en el mismo panel
    const preview    = document.getElementById('preview-msg-correccion');
    const previewTxt = document.getElementById('texto-preview-correccion');
    if (preview && previewTxt) {
      if (docs.length > 0 || extra) {
        previewTxt.textContent = msg;
        preview.style.display = 'block';
      } else {
        preview.style.display = 'none';
      }
    }

    return msg;
  }

  // Al cambiar el estatus, actualizar el hint y el panel de corrección
  document.getElementById('m_estatus')?.addEventListener('change', function() {
    const hint = document.getElementById('estatus-hint');
    if (hint) hint.textContent = hints[this.value] || '';
    actualizarPanelCorreccion();
  });

  // Reconstruir el mensaje cuando el verificador marca/desmarca docs o escribe indicaciones
  document.querySelectorAll('.check-correccion').forEach(chk => {
    chk.addEventListener('change', construirMensajeCorreccion);
  });
  document.getElementById('correccion_extra')?.addEventListener('input', construirMensajeCorreccion);

  // =====================================================
  // MODAL DE CONSTANCIA DE NÚMERO OFICIAL
  // Poblar los campos del modal cuando el verificador
  // hace clic en "Llenar Constancia" en la tabla
  // =====================================================
  document.querySelectorAll('.btn-generar-constancia').forEach(btn => {
    btn.addEventListener('click', () => {
      // Datos del trámite
      document.getElementById('c_folio').textContent     = btn.dataset.folio;
      document.getElementById('c_folio_hidden').value    = btn.dataset.folio;
      document.getElementById('c_propietario').textContent = btn.dataset.propietario;
      document.getElementById('c_direccion').textContent  = btn.dataset.direccion;
      document.getElementById('c_localidad').textContent  = btn.dataset.localidad;

      // Datos de la constancia (si ya tiene info guardada, se pre-llena)
      document.getElementById('c_numero_asignado').value     = (btn.dataset.numeroAsignado || '').toUpperCase();
      document.getElementById('c_tipo_asignacion').value     = (btn.dataset.tipoAsignacion || 'ASIGNACION').toUpperCase();
      document.getElementById('c_referencia_anterior').value = (btn.dataset.referenciaAnterior || '').toUpperCase();
      document.getElementById('c_entre_calle1').value        = (btn.dataset.entreCalle1 || '').toUpperCase();
      document.getElementById('c_entre_calle2').value        = (btn.dataset.entreCalle2 || '').toUpperCase();
      document.getElementById('c_cuenta_catastral').value    = btn.dataset.cuentaCatastral || '';
      document.getElementById('c_manzana').value             = (btn.dataset.manzana || '').toUpperCase();
      document.getElementById('c_lote').value                = (btn.dataset.lote || '').toUpperCase();
      document.getElementById('c_fecha_constancia').value    = btn.dataset.fechaConstancia || new Date().toISOString().split('T')[0];

      // Estado del croquis
      const croquis = btn.dataset.croquis || '';
      const inp  = document.getElementById('ver_inp_croquis');
      const msg  = document.getElementById('ver_msg_croquis');
      const btnS = document.getElementById('ver_btn_subir');
      if (inp)  inp.value = '';
      if (msg)  msg.textContent = '';
      if (btnS) btnS.style.display = 'none';

       croquis && croquis.trim() !== ''
         ? ver_mostrarEstado(true, croquis)
         : (() => {
            ver_mostrarEstado(false, null);
            const prev = document.getElementById('ver_prev_img');
            const ph   = document.getElementById('ver_prev_ph');
            if (prev) { prev.src = ''; prev.style.display = 'none'; }
            if (ph)   ph.style.display = 'block';
          })();
    });
  });

  // =====================================================
  // MODAL DE DETALLE DE TRÁMITE
  // El modal principal donde el verificador revisa todo
  // y puede cambiar el estatus del trámite
  // =====================================================
  document.querySelectorAll('[data-bs-target="#detalleTramite"]').forEach(btn => {
    btn.addEventListener('click', () => {

      // Datos básicos del trámite
      document.getElementById('m_folio').textContent       = btn.dataset.folio;
      document.getElementById('m_folio_hidden').value      = btn.dataset.folio;
      document.getElementById('m_propietario').textContent = btn.dataset.propietario;
      document.getElementById('m_direccion').textContent   = btn.dataset.direccion;
      document.getElementById('m_localidad').textContent   = btn.dataset.localidad;
      document.getElementById('m_tramites').textContent    = btn.dataset.tramites;
      document.getElementById('m_fecha').textContent       = btn.dataset.fecha;
      document.getElementById('m_telefono').textContent    = btn.dataset.telefono || '—';
      document.getElementById('m_correo').textContent      = btn.dataset.correo   || '—';

      const obsEl = document.getElementById('m_observaciones');
      if (obsEl) obsEl.value = btn.dataset.observaciones || '';

      // Estatus actual del trámite
      const estatusSelect = document.getElementById('m_estatus');
      if (estatusSelect) {
        estatusSelect.value = btn.dataset.estatus;
        const hint = document.getElementById('estatus-hint');
        if (hint) hint.textContent = hints[estatusSelect.value] || '';
      }

      const tipoTramiteId = btn.dataset.tipoTramiteId || '';
      const tipoTramiteInput = document.getElementById('m_tipo_tramite_id');
      if (tipoTramiteInput) tipoTramiteInput.value = tipoTramiteId;

      // ------------------------------------------------
      // DOCUMENTOS REQUERIDOS POR TIPO DE TRÁMITE
      // Cada tipo de trámite requiere documentos distintos
      // Solo mostramos como "faltante" lo que realmente se necesita
      // ------------------------------------------------
      const docsRequeridosPorTipo = {
        '1': ['ine', 'titulo', 'predial'],                        // Constancia de Nº Oficial
        '2': ['ine', 'titulo', 'predial', 'formato_constancia'],  // Compatibilidad Urbanística
        '3': ['ine', 'titulo', 'predial'],                        // Fusión
        '4': ['ine', 'titulo', 'predial'],                        // Subdivisión
        '5': ['ine'],                                             // Informe de Compatibilidad
      };
      const docsKeys = docsRequeridosPorTipo[tipoTramiteId] || ['ine', 'titulo', 'predial'];

      const docsFaltantesKeys = {
        'ine': 'doc_faltante_ine',
        'titulo': 'doc_faltante_titulo',
        'predial': 'doc_faltante_predial',
        'formato_constancia': 'doc_faltante_formato'
      };
      const todosLosDocs = ['ine', 'titulo', 'predial', 'formato_constancia'];

      const sinDocumentos      = document.getElementById('modal-sin-documentos');
      const seccionFaltantes   = document.getElementById('seccion-docs-faltantes');
      const comentarioFaltantes = document.getElementById('comentario-docs-faltantes');
      const textoComentario    = document.getElementById('texto-comentario-faltantes');

      // Resetear todo antes de mostrar el estado actual
      todosLosDocs.forEach(key => {
        const docEl      = document.getElementById('doc_' + key);
        const faltanteEl = document.getElementById(docsFaltantesKeys[key]);
        if (docEl)      docEl.style.display      = 'none';
        if (faltanteEl) faltanteEl.style.display = 'none';
      });
      if (sinDocumentos)      sinDocumentos.style.display      = 'none';
      if (seccionFaltantes)   seccionFaltantes.style.display   = 'none';
      if (comentarioFaltantes) comentarioFaltantes.style.display = 'none';

      let hayDocumentos = false;
      let hayFaltantes  = false;

      // Mostrar documentos que SÍ están cargados
      todosLosDocs.forEach(key => {
        const el      = document.getElementById('doc_' + key);
        const dataKey = key === 'formato_constancia' ? 'formatoConstancia' : key;
        const val     = btn.dataset[dataKey] || '';
        if (val && val.trim() !== '' && el) {
          el.href = `uploads/${val}`;
          el.style.display = 'flex';
          hayDocumentos = true;
        }
      });

      // Marcar cuáles documentos FALTAN (solo los requeridos para este tipo)
      docsKeys.forEach(key => {
        const dataKey = key === 'formato_constancia' ? 'formatoConstancia' : key;
        const val     = btn.dataset[dataKey] || '';
        if (!val || val.trim() === '') {
          const faltanteEl = document.getElementById(docsFaltantesKeys[key]);
          if (faltanteEl) { faltanteEl.style.display = 'block'; hayFaltantes = true; }
        }
      });

      if (hayFaltantes && seccionFaltantes) {
        seccionFaltantes.style.display = 'block';
        const comentario = btn.dataset.comentarioSinDoc || '';
        if (comentario.trim() !== '' && comentarioFaltantes && textoComentario) {
          comentarioFaltantes.style.display = 'block';
          textoComentario.textContent = comentario;
        }
      }

      if (!hayDocumentos && !hayFaltantes && sinDocumentos) {
        sinDocumentos.style.display = 'block';
      }

      // ------------------------------------------------
      // FOTOS DEL TRÁMITE
      // (se requieren para poder imprimir la constancia)
      // ------------------------------------------------
      const foto1Val      = btn.dataset['foto1'] || '';
      const foto2Val      = btn.dataset['foto2'] || '';
      const hayFotos      = (foto1Val.trim() !== '' || foto2Val.trim() !== '');
      const hayConstancia = (btn.dataset.numeroAsignado || '').trim() !== '';
      const esTipoNumOficial = (tipoTramiteId === '1');

      ['1', '2'].forEach(n => {
        const val  = btn.dataset['foto' + n];
        const prev = document.getElementById('preview_foto' + n);
        const cont = document.getElementById('preview_foto' + n + '_container');
        if (prev && cont) {
          if (val) { prev.src = `uploads/${val}`; cont.style.display = 'block'; }
          else     { prev.src = ''; cont.style.display = 'none'; }
        }
      });

      document.getElementById('input_foto1').value = '';
      document.getElementById('input_foto2').value = '';

      const alertaSinFotos = document.getElementById('alerta-sin-fotos');
      const alertaFotosOk  = document.getElementById('alerta-fotos-ok');
      if (alertaSinFotos) alertaSinFotos.style.display = hayFotos ? 'none' : 'block';
      if (alertaFotosOk)  alertaFotosOk.style.display  = hayFotos ? 'block' : 'none';

      // El botón "Ficha Fotografías" solo aparece si ya hay fotos
      const btnFotos = document.getElementById('btn_imprimir_fotos');
      if (btnFotos) {
        if (hayFotos) {
          btnFotos.href = `ficha_fotografias.php?folio=${btn.dataset.folio}`;
          btnFotos.style.display = 'inline-block';
        } else {
          btnFotos.style.display = 'none';
        }
      }

      // ------------------------------------------------
      // BLOQUE DE CONSTANCIA (solo para trámites tipo 1)
      // ------------------------------------------------
      const bloqueInfo     = document.getElementById('bloque-flujo-info');
      const bloquePaso2    = document.getElementById('bloque-paso2');
      const alertaPaso2Pen = document.getElementById('alerta-paso2-pendiente');
      const alertaConstOk  = document.getElementById('alerta-constancia-ok');
      const bloqueBtnConst = document.getElementById('bloque-btn-constancia-modal');
      const btnAbrirConst  = document.getElementById('btn_abrir_constancia_desde_detalle');

      if (bloqueInfo)  bloqueInfo.style.display  = esTipoNumOficial ? 'block' : 'none';
      if (bloquePaso2) bloquePaso2.style.display  = esTipoNumOficial ? 'block' : 'none';

      if (esTipoNumOficial) {
        if (alertaPaso2Pen) alertaPaso2Pen.style.display = (!hayFotos && !hayConstancia) ? 'block' : 'none';
        if (alertaConstOk)  alertaConstOk.style.display  = hayConstancia ? 'block' : 'none';
        if (bloqueBtnConst) bloqueBtnConst.style.display = (hayFotos || hayConstancia) ? 'block' : 'none';

        // Botón "Llenar / imprimir Constancia" dentro del modal de detalle
        if (btnAbrirConst) {
          btnAbrirConst.onclick = function() {
            // Cerrar el modal de detalle antes de abrir el de constancia
            const detalleModal = bootstrap.Modal.getInstance(document.getElementById('detalleTramite'));
            if (detalleModal) detalleModal.hide();

            // Un pequeño delay para que Bootstrap limpie el backdrop correctamente
            setTimeout(function() {
              document.getElementById('c_folio').textContent           = btn.dataset.folio;
              document.getElementById('c_folio_hidden').value          = btn.dataset.folio;
              document.getElementById('c_propietario').textContent     = btn.dataset.propietario;
              document.getElementById('c_direccion').textContent       = btn.dataset.direccion;
              document.getElementById('c_localidad').textContent       = btn.dataset.localidad;
              document.getElementById('c_numero_asignado').value       = (btn.dataset.numeroAsignado || '').toUpperCase();
              document.getElementById('c_tipo_asignacion').value       = (btn.dataset.tipoAsignacion || 'ASIGNACION').toUpperCase();
              document.getElementById('c_referencia_anterior').value   = (btn.dataset.referenciaAnterior || '').toUpperCase();
              document.getElementById('c_entre_calle1').value          = (btn.dataset.entreCalle1 || '').toUpperCase();
              document.getElementById('c_entre_calle2').value          = (btn.dataset.entreCalle2 || '').toUpperCase();
              document.getElementById('c_cuenta_catastral').value      = btn.dataset.cuentaCatastral || '';
              document.getElementById('c_manzana').value               = (btn.dataset.manzana || '').toUpperCase();
              document.getElementById('c_lote').value                  = (btn.dataset.lote || '').toUpperCase();
              document.getElementById('c_fecha_constancia').value      = btn.dataset.fechaConstancia || new Date().toISOString().split('T')[0];

              const croquis = btn.dataset.croquis || '';
              const inp = document.getElementById('ver_inp_croquis');
              const msgC = document.getElementById('ver_msg_croquis');
              const btnS = document.getElementById('ver_btn_subir');
              if (inp) inp.value = '';
              if (msgC) msgC.textContent = '';
              if (btnS) btnS.style.display = 'none';
               croquis && croquis.trim() !== ''
                 ? ver_mostrarEstado(true, croquis)
                 : (() => {
                    ver_mostrarEstado(false, null);
                    const prev = document.getElementById('ver_prev_img');
                    const ph   = document.getElementById('ver_prev_ph');
                    if (prev) { prev.src = ''; prev.style.display = 'none'; }
                    if (ph)   ph.style.display = 'block';
                  })();

              new bootstrap.Modal(document.getElementById('modalConstancia')).show();
            }, 350);
          };
        }
      }

      // Limpiar el panel de corrección al abrir el modal
      document.querySelectorAll('.check-correccion').forEach(c => c.checked = false);
      const extraField = document.getElementById('correccion_extra');
      if (extraField) extraField.value = '';
      const previewCorr = document.getElementById('preview-msg-correccion');
      if (previewCorr) previewCorr.style.display = 'none';
      actualizarPanelCorreccion();

      // Botones de la ficha
      const folio = btn.dataset.folio;
      document.getElementById('btn_imprimir_ficha').href = `ficha.php?folio=${folio}`;
      const btnVerDocs = document.getElementById('btn_ver_documentos');
      if (btnVerDocs) btnVerDocs.href = `imprimir_documentos.php?folio=${folio}`;
    });
  });

}); // fin DOMContentLoaded

// =====================================================
// GUARDAR ESTATUS DEL TRÁMITE
// Flujo: guardar → cerrar modal → abrir modal de notificación
// La notificación se abre solo cuando el modal ya cerró
// para que Bootstrap no se trabe con dos modales
// =====================================================
let _pendingNotifData = null; // Guardamos la respuesta aquí hasta que sea momento de usarla

document.getElementById('formActualizarTramite')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  const estatus  = document.getElementById('m_estatus')?.value || '';

  Swal.fire({
    title: '¿Guardar cambios?',
    html: `Se cambiará el estatus a: <strong>${estatus}</strong>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#7b0f2b',
    cancelButtonColor: '#6c757d'
  }).then(result => {
    if (!result.isConfirmed) return;

    fetch('php/actualizarTramite.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(res => {
        if (!res.ok) {
          return res.text().then(txt => { throw new Error(`HTTP ${res.status}: ${txt.substring(0, 200)}`); });
        }
        return res.json();
      })
      .then(data => {
        if (data.success) {
          _pendingNotifData = data;

          // Si se subieron fotos nuevas, activar el botón de fotos inmediatamente sin recargar
          const f1 = document.getElementById('input_foto1');
          const f2 = document.getElementById('input_foto2');
          if ((f1 && f1.files && f1.files.length > 0) || (f2 && f2.files && f2.files.length > 0)) {
            const btnFt   = document.getElementById('btn_imprimir_fotos');
            const folioVal = document.getElementById('m_folio_hidden')?.value || '';
            if (btnFt && folioVal) { btnFt.href = `ficha_fotografias.php?folio=${folioVal}`; btnFt.style.display = 'inline-block'; }

            const alertaSinFotos = document.getElementById('alerta-sin-fotos');
            const alertaFotosOk  = document.getElementById('alerta-fotos-ok');
            if (alertaSinFotos) alertaSinFotos.style.display = 'none';
            if (alertaFotosOk)  alertaFotosOk.style.display  = 'block';

            const bloqueBtnConst = document.getElementById('bloque-btn-constancia-modal');
            const alertaPaso2    = document.getElementById('alerta-paso2-pendiente');
            if (bloqueBtnConst) bloqueBtnConst.style.display = 'block';
            if (alertaPaso2)    alertaPaso2.style.display    = 'none';
          }

          // Cerrar el modal de trámite (el evento 'hidden' abrirá la notificación)
          const detalleModal = bootstrap.Modal.getInstance(document.getElementById('detalleTramite'));
          if (detalleModal) { detalleModal.hide(); } else { _abrirModalNotif(); }

        } else {
          Swal.fire({ icon: 'error', title: 'Error al guardar', text: data.message || 'No se pudieron guardar los cambios' });
        }
      })
      .catch(err => {
        console.error('[actualizarTramite]', err);
        Swal.fire({
          icon: 'error',
          title: 'Error de conexión',
          html: 'No se pudo conectar con el servidor.<br><small style="color:#999">' + err.message + '</small>'
        });
      });
  });
});

// Cuando el modal de trámite termina de cerrarse, abrimos la notificación
// (timeout de 150ms para que Bootstrap limpie el backdrop y el aria)
document.getElementById('detalleTramite')?.addEventListener('hidden.bs.modal', function() {
  if (_pendingNotifData) {
    setTimeout(_abrirModalNotif, 150);
  }
});

// Cuando se cierra la notificación, recargar la página para reflejar el cambio de estatus
document.getElementById('notifModal')?.addEventListener('hidden.bs.modal', function() {
  _pendingNotifData = null;
  location.reload();
});

// Construye y muestra el modal de notificación (WhatsApp / Correo)
function _abrirModalNotif() {
  const data = _pendingNotifData;
  if (!data) return;

  const n       = data.notificacion || {};
  const waEl    = document.getElementById('notif-wa-link');
  const gmEl    = document.getElementById('notif-gm-link');
  const titleEl = document.getElementById('notif-modal-title');
  const descEl  = document.getElementById('notif-modal-desc');
  const msgPreview = document.getElementById('notif-msg-preview');
  const msgTexto   = document.getElementById('notif-msg-texto');

  if (titleEl) titleEl.textContent = `Notificar a ${n.nombre || ''}`;
  if (descEl)  descEl.innerHTML    = `Estatus: <strong>${data.estatus}</strong> &nbsp;|&nbsp; Folio: <strong>${data.folio}</strong>`;

  if (msgPreview && msgTexto && n.mensaje) {
    msgTexto.textContent = n.mensaje;
    msgPreview.style.display = 'block';
  }

  // Botón WhatsApp — solo activo si hay teléfono
  if (waEl) {
    if (n.wa_link) {
      waEl.href = n.wa_link;
      waEl.style.opacity = '1';
      waEl.style.pointerEvents = 'auto';
      waEl.querySelector('.notif-sub').textContent = n.telefono ? `Enviar a: ${n.telefono}` : 'Abre WhatsApp con el mensaje ya escrito';
    } else {
      waEl.href = '#';
      waEl.style.opacity = '0.35';
      waEl.style.pointerEvents = 'none';
      waEl.querySelector('.notif-sub').textContent = 'Sin número de teléfono registrado';
    }
  }

  // Botón Correo — solo activo si hay email
  if (gmEl) {
    if (n.gm_link) {
      gmEl.href = n.gm_link;
      gmEl.style.opacity = '1';
      gmEl.style.pointerEvents = 'auto';
      gmEl.querySelector('.notif-sub').textContent = n.correo ? `Enviar a: ${n.correo}` : 'Abre tu cliente de correo con el mensaje listo';
    } else {
      gmEl.href = '#';
      gmEl.style.opacity = '0.35';
      gmEl.style.pointerEvents = 'none';
      gmEl.querySelector('.notif-sub').textContent = 'Sin correo electrónico registrado';
    }
  }

  // Abrir el modal de notificación limpio
  const notifEl = document.getElementById('notifModal');
  notifEl.removeAttribute('aria-hidden');
  new bootstrap.Modal(notifEl, { backdrop: 'static', keyboard: false }).show();
}

// =====================================================
// PREVIEW DE FOTOS
// Mostrar vista previa cuando el verificador selecciona
// imágenes para subir al trámite
// =====================================================
['1', '2'].forEach(n => {
  document.getElementById('input_foto' + n)?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const prev = document.getElementById('preview_foto' + n);
    const cont = document.getElementById('preview_foto' + n + '_container');
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = ev => { prev.src = ev.target.result; cont.style.display = 'block'; };
      reader.readAsDataURL(file);
    }
  });
});

// =====================================================
// CONSTANCIA DE NÚMERO OFICIAL
// Guardar los datos de la constancia (sin cerrar modal)
// para poder seguir editando antes de imprimir
// =====================================================
document.getElementById('formConstancia')?.addEventListener('submit', function(e) {
  e.preventDefault();
  guardarConstancia();
});

// Botón "Solo imprimir" — requiere que el croquis ya esté guardado
document.getElementById('btnSoloImprimir')?.addEventListener('click', function() {
  const folio = document.getElementById('c_folio_hidden')?.value || '';
  if (!folio) return;

  if (!_ver_croquis_ok) {
    Swal.fire({ icon: 'warning', title: 'Croquis requerido', text: 'Debes guardar la imagen del croquis antes de imprimir.', confirmButtonColor: '#7b0f2b' });
    return;
  }

  window.open(`constancia_numero.php?folio=${folio}`, '_blank');
});

// Al cerrar el modal de constancia, recargar para reflejar cambios en la tabla
document.getElementById('modalConstancia')?.addEventListener('hidden.bs.modal', function() {
  location.reload();
});

// Llama al PHP para guardar los datos de la constancia sin recargar la página
function guardarConstancia() {
  const form     = document.getElementById('formConstancia');
  const formData = new FormData(form);

  Swal.fire({ title: 'Guardando...', html: 'Por favor espere', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

  fetch('php/actualizarTramite.php', { method: 'POST', body: formData, credentials: 'same-origin' })
    .then(res => {
      if (!res.ok) { return res.text().then(txt => { throw new Error(`HTTP ${res.status}: ${txt.substring(0, 200)}`); }); }
      return res.json();
    })
    .then(data => {
      if (data.success) {
        // Toast chico en la esquina para no interrumpir el flujo
        Swal.fire({ icon: 'success', title: 'Guardado', text: 'Los datos han sido guardados correctamente.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true });
      } else {
        Swal.fire({ icon: 'error', title: 'Error al guardar', text: data.message || 'No se pudieron guardar los cambios' });
      }
    })
    .catch(err => {
      console.error('[guardarConstancia]', err);
      Swal.fire({ icon: 'error', title: 'Error de conexión', html: 'No se pudo conectar con el servidor.<br><small style="color:#999">' + err.message + '</small>' });
    });
}

// =====================================================
// CROQUIS DEL PREDIO
// El verificador puede subir una imagen del croquis
// del predio que se incluye en la constancia impresa
// =====================================================
var _ver_croquis_ok = false; // Bandera: true si hay croquis guardado válido

// Actualiza el estado visual del croquis (ok / pendiente)
function ver_mostrarEstado(ok, imgSrc) {
  const alerta = document.getElementById('ver_alerta_croquis');
  const okDiv  = document.getElementById('ver_ok_croquis');
  const prev   = document.getElementById('ver_prev_img');
  const ph     = document.getElementById('ver_prev_ph');

  if (ok) {
    if (alerta) alerta.style.display = 'none';
    if (okDiv)  okDiv.style.display  = 'flex';
    if (imgSrc && prev) { prev.src = imgSrc; prev.style.display = 'block'; if (ph) ph.style.display = 'none'; }
    _ver_croquis_ok = true;
  } else {
    if (alerta) alerta.style.display = 'flex';
    if (okDiv)  okDiv.style.display  = 'none';
    _ver_croquis_ok = false;
  }
}

// Mostrar preview local antes de subir (para que el verificador vea qué va a subir)
function ver_prevCroquis(input) {
  const file = input.files[0];
  if (!file) return;

  ver_mostrarPreviewCroquis(file);
}

// Función para mostrar preview del croquis
function ver_mostrarPreviewCroquis(file) {
  // Validar dimensiones mínimas: al menos 500x800 píxeles
  const img = new Image();
  img.onload = function() {
    const minWidth = 100;
    const minHeight = 100;

    if (this.width < minWidth || this.height < minHeight) {
      const msg = document.getElementById('ver_msg_croquis');
      if (msg) {
        msg.textContent = `❌ La imagen debe ser al menos ${minWidth}x${minHeight} píxeles. Actual: ${this.width}x${this.height}.`;
        msg.style.color = '#dc3545';
      }
      // Limpiar input
      const input = document.getElementById('ver_inp_croquis');
      if (input) input.value = '';
      return;
    }

    // Si pasa validación, mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
      const prev = document.getElementById('ver_prev_img');
      const ph   = document.getElementById('ver_prev_ph');
      if (prev) { prev.src = e.target.result; prev.style.display = 'block'; }
      if (ph)   ph.style.display = 'none';

      // Mostrar botón de guardar y aviso de que falta confirmar
      const btn = document.getElementById('ver_btn_subir');
      if (btn) btn.style.display = 'block';
      const msg = document.getElementById('ver_msg_croquis');
      if (msg) { msg.textContent = '⚠️ Haz clic en "Guardar croquis" para confirmar.'; msg.style.color = '#856404'; }

      // Desmarcar como ok mientras no se guarde
      _ver_croquis_ok = false;
      if (document.getElementById('ver_alerta_croquis')) document.getElementById('ver_alerta_croquis').style.display = 'flex';
      if (document.getElementById('ver_ok_croquis'))    document.getElementById('ver_ok_croquis').style.display    = 'none';
    };
    reader.readAsDataURL(file);
  };
  img.src = URL.createObjectURL(file);
}

// Función para redimensionar imagen a 500x800
function redimensionarImagen(file, targetWidth, targetHeight) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = function() {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');

      canvas.width = targetWidth;
      canvas.height = targetHeight;

      // Calcular el aspect ratio para ajustar la imagen
      const imgAspect = this.width / this.height;
      const targetAspect = targetWidth / targetHeight;

      let drawWidth, drawHeight, offsetX, offsetY;

      if (imgAspect > targetAspect) {
        // Imagen más ancha que el target, ajustar por altura
        drawHeight = targetHeight;
        drawWidth = drawHeight * imgAspect;
        offsetX = (targetWidth - drawWidth) / 2;
        offsetY = 0;
      } else {
        // Imagen más alta que el target, ajustar por ancho
        drawWidth = targetWidth;
        drawHeight = drawWidth / imgAspect;
        offsetX = 0;
        offsetY = (targetHeight - drawHeight) / 2;
      }

      // Dibujar imagen escalada
      ctx.drawImage(this, offsetX, offsetY, drawWidth, drawHeight);

      canvas.toBlob(resolve, 'image/jpeg', 0.9);
    };
    img.onerror = reject;
    img.src = URL.createObjectURL(file);
  });
}

// Subir el croquis al servidor via AJAX
function ver_subirCroquis() {
  const input = document.getElementById('ver_inp_croquis');
  const msg   = document.getElementById('ver_msg_croquis');
  const btn   = document.getElementById('ver_btn_subir');
  const folio = document.getElementById('c_folio_hidden')?.value || '';

  if (!input.files || !input.files[0]) {
    if (msg) { msg.textContent = '⚠️ Selecciona una imagen primero.'; msg.style.color = '#856404'; }
    return;
  }

  if (msg) { msg.textContent = 'Procesando imagen...'; msg.style.color = '#555'; }
  if (btn) btn.disabled = true;

  // Redimensionar la imagen a 2000x1500
  redimensionarImagen(input.files[0], 2000, 1500)
    .then(resizedBlob => {
      if (msg) { msg.textContent = 'Guardando...'; msg.style.color = '#555'; }

      const fd = new FormData();
      fd.append('folio', folio);
      fd.append('croquis', resizedBlob, 'croquis.jpg'); // Nombre fijo

      return fetch('php/guardar_croquis.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    })
    .then(r => r.json())
    .then(data => {
      if (btn) btn.disabled = false;
      if (data.success) {
        if (msg) { msg.textContent = '✅ Croquis guardado. Ya puedes imprimir.'; msg.style.color = '#198754'; }
        if (btn) btn.style.display = 'none';
        ver_mostrarEstado(true, null);
      } else {
        if (msg) { msg.textContent = '❌ ' + data.message; msg.style.color = '#dc3545'; }
      }
    })
    .catch(err => {
      console.error('Error procesando imagen:', err);
      if (btn) btn.disabled = false;
      if (msg) { msg.textContent = '❌ Error procesando la imagen.'; msg.style.color = '#dc3545'; }
    });
}

// =====================================================
// PEGAR IMÁGENES CON CTRL+V
// Permite pegar imágenes directamente en áreas de carga
// =====================================================

// Función auxiliar para manejar pegado de imágenes
function manejarPegadoImagen(e, inputId, previewFunction, mensaje) {
  const items = e.clipboardData?.items;
  if (!items) return false;

  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    if (item.type.indexOf('image') !== -1) {
      e.preventDefault();

      const file = item.getAsFile();
      if (file) {
        // Para croquis, validar dimensiones
        if (inputId === 'ver_inp_croquis') {
          const img = new Image();
          img.onload = function() {
            const minWidth = 200;
            const minHeight = 100;

            if (this.width < minWidth || this.height < minHeight) {
              const msg = document.getElementById('ver_msg_croquis');
              if (msg) {
                msg.textContent = `❌ La imagen pegada debe ser al menos ${minWidth}x${minHeight} píxeles. Actual: ${this.width}x${this.height}.`;
                msg.style.color = '#dc3545';
              }
              return;
            }

            // Si valida, asignar al input
            asignarArchivoPegado(inputId, file, mensaje);
          };
          img.src = URL.createObjectURL(file);
        } else {
          // Para otras imágenes (fotos), asignar directamente
          asignarArchivoPegado(inputId, file, mensaje);
        }
        return true; // Se encontró y manejó una imagen
      }
    }
  }
  return false;
}

// Función auxiliar para asignar archivo pegado al input
function asignarArchivoPegado(inputId, file, mensaje) {
  const input = document.getElementById(inputId);
  if (input) {
    // Crear un DataTransfer para asignar el archivo al input
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;

    // Disparar el evento change para mostrar preview
    input.dispatchEvent(new Event('change'));

    // Mostrar mensaje si se proporciona
    if (mensaje) {
      const msgEl = document.querySelector(mensaje.selector);
      if (msgEl) {
        msgEl.textContent = mensaje.texto;
        msgEl.style.color = mensaje.color || '#007bff';
      }
    }
  }
}

// Event listener global para pegar imágenes
document.addEventListener('paste', function(e) {
  // Solo manejar si estamos en un modal relevante
  const modalDetalle = document.getElementById('detalleTramite');
  const modalConstancia = document.getElementById('modalConstancia');
  const enModalDetalle = modalDetalle && modalDetalle.classList.contains('show');
  const enModalConstancia = modalConstancia && modalConstancia.classList.contains('show');

  if (!enModalDetalle && !enModalConstancia) return;

  // Intentar pegar en croquis si estamos en modal de constancia
  if (enModalConstancia) {
    const pegado = manejarPegadoImagen(e, 'ver_inp_croquis', null, {
      selector: '#ver_msg_croquis',
      texto: '🖼️ Imagen pegada. Haz clic en "Guardar croquis" para confirmar.',
      color: '#007bff'
    });
    if (pegado) return;
  }

  // Intentar pegar en fotos si estamos en modal de detalle
  if (enModalDetalle) {
    // Primero intentar foto1
    const pegado1 = manejarPegadoImagen(e, 'input_foto1', null, null);
    if (pegado1) return;

    // Luego foto2
    const pegado2 = manejarPegadoImagen(e, 'input_foto2', null, null);
    if (pegado2) return;
  }
});

// =====================================================
// DATATABLES — Tabla principal de trámites
// =====================================================
$(document).ready(function() {
  if ($('#tablaTramites').length) {
    $('#tablaTramites').DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Todos']],
      ordering: true,
      responsive: true,
      columnDefs: [{ orderable: false, targets: -1 }], // Columna "Acciones" no ordenable
      language: {
        paginate: { previous: 'Anterior', next: 'Siguiente' },
        info:         'Mostrando _START_ a _END_ de _TOTAL_ trámites',
        infoEmpty:    'No hay trámites',
        infoFiltered: '(filtrado de _MAX_)',
        zeroRecords:  'No se encontraron resultados',
        search:       'Buscar:',
        lengthMenu:   'Mostrar _MENU_ registros'
      },
      dom: '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });
  }
});
