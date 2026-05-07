// ── MAPA ──


proj4.defs('EPSG:32613', '+proj=utm +zone=13 +datum=WGS84 +units=m +no_defs');
const CENTRO_MUNICIPIO = [22.228, -102.320];
const map = L.map('mapa', { zoomControl: true, scrollWheelZoom: true, tap: true }).setView(CENTRO_MUNICIPIO, 14);
let marker = null;
const utmXInput = document.getElementById('lat');
const utmYInput = document.getElementById('lng');

// Definir capas base
const openStreetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap colaboradores',
    maxZoom: 22
});

const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
    maxZoom: 22
});

// Capa por defecto
openStreetMap.addTo(map);

// Control de capas
const baseLayers = {
    "Mapa": openStreetMap,
    "Satelital": satellite
};

const overlays = {};

const layerControl = L.control.layers(baseLayers, overlays, {
    position: 'topright',
    collapsed: false
}).addTo(map);



// Cargar puntos de trámites (tu código original, corregido)


// Variable para rastrear el polígono seleccionado
let selectedPolygon = null;
// Variable para almacenar la capa GeoJSON
let parcelasLayer = null;

// Estilos para polígonos normales y seleccionados
const normalStyle = {
  color: '#7b0f2b',
  weight: 1,
  opacity: 1,
  fillColor: 'lightblue',
  fillOpacity: 0.3
};

const selectedStyle = {
  color: '#7b0f2b',  // Color vino del tema
  weight: 3,
  opacity: 1,
  fillColor: '#a61c3c',  // Color vino claro
  fillOpacity: 0.5
};

// Función para buscar y resaltar un polígono por cuenta catastral
function buscarYResaltarPoligono(cuentaCatastral) {
  if (!parcelasLayer) return false;

  let encontrado = false;
  parcelasLayer.eachLayer(function(layer) {
    if (layer.feature && layer.feature.properties &&
        layer.feature.properties.CVE_CAT_OR === cuentaCatastral) {

      // Resetear el estilo del polígono anteriormente seleccionado
      if (selectedPolygon && selectedPolygon !== layer) {
        selectedPolygon.setStyle(normalStyle);
      }

      // Aplicar estilo de selección al polígono encontrado
      layer.setStyle(selectedStyle);
      selectedPolygon = layer;

      // Centrar el mapa en el polígono
      const bounds = layer.getBounds();
      map.fitBounds(bounds, { padding: [20, 20], maxZoom: 18, animate: true });

      // Crear marcador en el centro
      const center = bounds.getCenter();
      if (marker) {
        map.removeLayer(marker);
      }
      marker = L.marker(center).addTo(map);

      // Mostrar coordenadas
      const lat = center.lat.toFixed(5);
      const lon = center.lng.toFixed(5);
      const utmCoords = proj4('EPSG:4326', 'EPSG:32613', [parseFloat(lon), parseFloat(lat)]);
      const utmX = utmCoords[0].toFixed(2);
      const utmY = utmCoords[1].toFixed(2);

      document.getElementById('coords-display').classList.remove('d-none');
      document.getElementById('coords-texto').textContent = `UTM X: ${utmX} | UTM Y: ${utmY} | Lat: ${lat}, Lon: ${lon}`;

      // Actualizar campos del formulario
      utmXInput.value = utmX;
      utmYInput.value = utmY;

      encontrado = true;
      return false; // Salir del eachLayer
    }
  });

  return encontrado;
}

// Cargar poligonos de TRAMITES_reprojected.geojson
fetch('./Geojson/TRAMITES_reprojected.geojson')
  .then(response => response.json())
  .then(data => {

    parcelasLayer = L.geoJSON(data, {
      style: normalStyle,
      onEachFeature: function (feature, layer) {
        if (feature.properties) {
          let popupContent = `
            <strong>Clave Catastral:</strong> ${feature.properties.CVE_CAT_OR || 'N/A'}<br>
          `;
          layer.bindPopup(popupContent);

          // Agregar evento de clic para resaltar polígono y mostrar coordenadas UTM
          layer.on('click', function(e) {
            // Resetear el estilo del polígono anteriormente seleccionado
            if (selectedPolygon && selectedPolygon !== layer) {
              selectedPolygon.setStyle(normalStyle);
            }

            // Aplicar estilo de selección al polígono actual
            layer.setStyle(selectedStyle);
            selectedPolygon = layer;

            // Obtener el centro del polígono
            let center = layer.getBounds().getCenter();
            let lat = center.lat.toFixed(5);
            let lon = center.lng.toFixed(5);

            // Convertir a UTM
            let utmCoords = proj4('EPSG:4326', 'EPSG:32613', [parseFloat(lon), parseFloat(lat)]);
            let utmX = utmCoords[0].toFixed(2);
            let utmY = utmCoords[1].toFixed(2);

            // Actualizar la visualización de coordenadas
            document.getElementById('coords-display').classList.remove('d-none');
            document.getElementById('coords-texto').textContent = `UTM X: ${utmX} | UTM Y: ${utmY} | Lat: ${lat}, Lon: ${lon}`;

            // Remover marcador anterior si existe
            if (marker) {
              map.removeLayer(marker);
            }

            // Crear nuevo marcador en el centro del polígono
            marker = L.marker(center).addTo(map);

            // Actualizar campos del formulario
            utmXInput.value = utmX;
            utmYInput.value = utmY;

            // Llenar automáticamente la cuenta catastral
            const cuentaCatastral = feature.properties.CVE_CAT_OR || '';
            document.getElementById('cuenta_catastral').value = cuentaCatastral;
          });
        }
      }
    }).addTo(map);

    // Agregar a overlays
    overlays["Poligonos"] = parcelasLayer;
    layerControl.addOverlay(parcelasLayer, "Poligonos");
  })
  .catch(error => console.error('Error cargando GeoJSON de parcelas:', error));

// Cargar capa de trámites
fetch('./Geojson/TRAMITES.geojson')
  .then(response => response.json())
  .then(data => {
    tramitesLayer = L.geoJSON(data, {
      pointToLayer: function(feature, latlng) {
        const marker = L.marker(latlng);
        const props = feature.properties;
        const popupContent = `
          <div style="max-width: 300px;">
            <h6 class="mb-2"><i class="bi bi-file-earmark-text me-1"></i>Trámite ${props.FOLIO_INGR || 'N/A'}</h6>
            <strong>Solicitante:</strong> ${props.NOM_SOLI || 'N/A'}<br>
            <strong>Tipo de Trámite:</strong> ${props.TIP_TRAMIT || 'N/A'}<br>
            <strong>Ubicación:</strong> ${props.UBICACION || 'N/A'}<br>
            <strong>Fecha Ingreso:</strong> ${props.FECH_INGRE || 'N/A'}<br>
            <strong>Estatus:</strong> <span class="badge bg-${props.ESTATUS === 'ENTREGADO' ? 'success' : 'warning'}">${props.ESTATUS || 'N/A'}</span><br>
            <strong>UTM X:</strong> ${props.X ? props.X.toFixed(2) : 'N/A'}<br>
            <strong>UTM Y:</strong> ${props.Y ? props.Y.toFixed(2) : 'N/A'}<br>
            <strong>Contacto:</strong> ${props.CONTACTO || 'N/A'}
          </div>
        `;
        marker.bindPopup(popupContent);
        return marker;
      }
    });

    // Agregar a overlays (inicialmente no visible)
    overlays["Trámites"] = tramitesLayer;
    layerControl.addOverlay(tramitesLayer, "Trámites");
  })
  .catch(error => console.error('Error cargando TRAMITES.geojson:', error));

// ── MAYÚSCULAS ──
document.querySelectorAll('.mayusculas').forEach(i=>{
  i.addEventListener('input',function(){
    const p=this.selectionStart;
    this.value=this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ0-9\s\.,#\/\-]/gi,'');
    try{this.setSelectionRange(p,p);}catch(e){}
  });
  i.addEventListener('blur',function(){this.value=this.value.toUpperCase().trim();});
});

// ── DOCUMENTOS ADICIONALES ──
document.getElementById('tipo_doc_adicional')?.addEventListener('change', function(){
  const otroDesc = document.getElementById('otro_doc_desc');
  if(this.value === 'otro'){
    otroDesc.style.display = 'block';
  } else {
    otroDesc.style.display = 'none';
    document.querySelector('input[name="otro_doc_descripcion"]').value = '';
  }
});

// ── DOCUMENTOS ADICIONALES EN MODAL ──
document.getElementById('sec_tipo_doc_adicional')?.addEventListener('change', function(){
  const otroDesc = document.getElementById('sec_otro_doc_desc');
  if(this.value === 'otro'){
    otroDesc.style.display = 'block';
  } else {
    otroDesc.style.display = 'none';
    document.querySelector('#sec_otro_doc_desc input[name="otro_doc_descripcion"]').value = '';
  }
});

// ── FECHAS ──
const hoy=new Date(),yyyy=hoy.getFullYear(),mm=String(hoy.getMonth()+1).padStart(2,'0'),dd=String(hoy.getDate()).padStart(2,'0');
document.getElementById('fechaIngreso').value=`${yyyy}-${mm}-${dd}`;
function calcDiasHabiles(f,n){const d=new Date(f+'T00:00:00');let c=0;while(c<n){d.setDate(d.getDate()+1);if(d.getDay()!==0&&d.getDay()!==6)c++;}return d.toISOString().split('T')[0];}
function actFechaEntrega(){const v=document.getElementById('fechaIngreso').value;if(v)document.getElementById('fechaEntrega').value=calcDiasHabiles(v,10);}
actFechaEntrega();
document.getElementById('fechaIngreso').addEventListener('change',actFechaEntrega);

// ── SELECCIÓN DE TRÁMITE ──
const reqPorTramite={
  1:{titulo:'Constancia de Número Oficial',documentos:['ine','escritura','predial'],nota:''},
  2:{titulo:'Constancia de Compatibilidad Urbanística',documentos:['ine','escritura','predial','formato_constancia'],nota:'Predios <10,000m²: plano catastral. Mayores: levantamiento topográfico.'},
  3:{titulo:'Fusión de Predios',documentos:['ine','escritura','predial'],nota:''},
  4:{titulo:'Subdivisión de Predio',documentos:['ine','escritura','predial'],nota:''},
  5:{titulo:'Informe CU',documentos:['ine'],nota:'Requiere cuenta catastral.'},
  6:{titulo:'Terminación de Obra',documentos:['solicitud_por_escrito','licencia_de_construccion','bitacora_de_obra'],nota:''},
  7:{titulo:'Licencia de Construcción',documentos:['ine','escritura','predial'],nota:''},
  8:{titulo:'Anuncios Publicitarios',documentos:['ine','predial','contrato_arrendamiento','memoria_descriptiva'],nota:'Se requiere memoria descriptiva o calculo de superficie, si es Empresa se requiere Poder Notariado y Acta Constitutiva.'}
};
const labelsDoc={'ine':'INE o Pasaporte','escritura':'Escritura / Título','predial':'Boleta Predial Vigente','formato_constancia':'Formato de Constancia','contrato_arrendamiento':'Contrato de Arrendamiento o Escritura','memoria_descriptiva':'Memoria Descriptiva / Cálculo de Superficie','poder_notariado':'Poder Notariado (opcional para empresas)','acta_constitutiva':'Acta Constitutiva (opcional para empresas)','solicitud_por_escrito':'Solicitud por Escrito','licencia_de_construccion':'Licencia de Construcción','bitacora_de_obra':'Bitácora de Obra'};
function seleccionarTramite(id,nombre){
  document.getElementById('tipo_tramite_id_hidden').value=id;
  document.getElementById('tipo_tramite_id').value=id;
  document.getElementById('label-tipo-tramite-form').textContent=nombre;
  document.getElementById('titulo-tramite-paso2').textContent=nombre;
  const t=reqPorTramite[id];
  if(t){
    let h=t.documentos.map(d=>`<div><i class="bi bi-check2-circle me-2 text-success"></i>${labelsDoc[d]||d}</div>`).join('');
    if(t.nota)h+=`<div class="mt-1 text-warning"><i class="bi bi-exclamation-triangle me-2"></i>${t.nota}</div>`;
    document.getElementById('lista-req-recordatorio').innerHTML=h;
    actualizarReqs(id);
  }
  document.getElementById('paso1-seleccion').style.display='none';
  document.getElementById('paso2-formulario').style.display='block';
  document.getElementById('tramite').scrollIntoView({behavior:'smooth',block:'start'});
}
function volverSeleccion(){
  document.getElementById('paso2-formulario').style.display='none';
  document.getElementById('paso1-seleccion').style.display='block';
  document.getElementById('tramite').scrollIntoView({behavior:'smooth',block:'start'});
}
function actualizarReqs(id){
  const sec=document.getElementById('seccion-documentos');
  document.querySelectorAll('[id^="grupo-"]').forEach(g=>{g.style.display='none';});
  if(!id||!reqPorTramite[id]){sec.style.display='none';return;}
  const t=reqPorTramite[id];
  sec.style.display='block';
  document.getElementById('titulo-tramite-seleccionado').textContent=t.titulo;
  document.getElementById('lista-requisitos').innerHTML=t.documentos.map(d=>`<li class="list-group-item d-flex align-items-center"><i class="bi bi-check-circle text-success me-2"></i>${labelsDoc[d]||d}</li>`).join('');
  t.documentos.forEach(d=>{const g=document.getElementById('grupo-'+d);if(g)g.style.display='block';});
  // Additional fields for specific tramites
  if(id === 8){
    // Anuncios Publicitarios: optional for empresas
    ['poder_notariado','acta_constitutiva'].forEach(d=>{const g=document.getElementById('grupo-'+d);if(g)g.style.display='block';});
  }
}

// ── MODAL CORRECCIÓN: poblar ──
document.querySelectorAll('.btn-editar-correccion').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const d=btn.dataset;
    document.getElementById('sec_folio').textContent=d.folio;
    document.getElementById('sec_folio_hidden').value=d.folio;
    document.getElementById('sec_propietario').textContent=d.propietario;
    document.getElementById('sec_solicitante').textContent=d.solicitante||'—';
    document.getElementById('sec_direccion').textContent=d.direccion;
    document.getElementById('sec_tramite').textContent=d.tramite;
    document.getElementById('sec_fecha').textContent=d.fecha;
    document.getElementById('sec_telefono').textContent=d.telefono||'—';
    document.getElementById('sec_correo').textContent=d.correo||'—';
    document.getElementById('sec_tipo_tramite_id').value=d.tipoTramiteId||'';
    // Mostrar documentos requeridos según tipo de trámite
    const tipoId = d.tipoTramiteId || '';
    // Ocultar todos los campos de subida primero
    const allInputFields = ['sec_ine', 'sec_escritura', 'sec_predial', 'sec_formato_constancia', 'sec_contrato_arrendamiento', 'sec_memoria_descriptiva', 'sec_poder_notariado', 'sec_acta_constitutiva', 'sec_solicitud_por_escrito', 'sec_licencia_de_construccion', 'sec_bitacora_de_obra'];
    allInputFields.forEach(field => {
      const el = document.getElementById(field);
      if (el) el.style.display = 'none';
    });
    // Mostrar campos requeridos según tipo
    switch(tipoId) {
      case '1':
        ['sec_ine', 'sec_escritura', 'sec_predial'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '2':
        ['sec_ine', 'sec_escritura', 'sec_predial', 'sec_formato_constancia'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '3':
        ['sec_ine', 'sec_escritura', 'sec_predial'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '4':
        ['sec_ine', 'sec_escritura', 'sec_predial'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '5':
        ['sec_ine'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '6':
        ['sec_solicitud_por_escrito', 'sec_licencia_de_construccion', 'sec_bitacora_de_obra'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '7':
        ['sec_ine', 'sec_escritura', 'sec_predial'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '7':
        ['sec_ine', 'sec_escritura', 'sec_predial'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      case '8':
        ['sec_ine', 'sec_predial', 'sec_contrato_arrendamiento', 'sec_memoria_descriptiva', 'sec_poder_notariado', 'sec_acta_constitutiva'].forEach(field => {
          const el = document.getElementById(field);
          if (el) el.style.display = 'block';
        });
        break;
      default:
        break;
    }

    document.getElementById('sec_nota').value='';
    document.getElementById('sec_observaciones').textContent=d.observaciones||'Sin indicaciones del verificador.';
    
    // CORRECCIÓN: Manejo de documentos
    const docs = {
      sec_doc_ine: d.ine,
      sec_doc_escritura: d.escritura,
      sec_doc_predial: d.predial,
      sec_doc_formato: d.formato,
      sec_doc_contrato_arrendamiento: d.contrato,
      sec_doc_memoria_descriptiva: d.memoria,
      sec_doc_poder_notariado: d.poder,
      sec_doc_acta_constitutiva: d.acta,
      sec_doc_solicitud_por_escrito: d.solicitud_por_escrito,
      sec_doc_licencia_de_construccion: d.licencia_de_construccion,
      sec_doc_bitacora_de_obra: d.bitacora_de_obra
    };
    
    let hayDocs = false;
    Object.entries(docs).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) {
        if (val && val.trim() && val !== 'null' && val !== 'undefined') {
          // Documento existe: mostrar y habilitar enlace
          el.href = 'uploads/' + val;
          el.style.display = 'flex';
          el.style.removeProperty('display');
          hayDocs = true;
          // Quitar cualquier clase que lo oculte
          el.classList.remove('d-none');
        } else {
          // Documento NO existe: ocultar completamente
          el.style.display = 'none';
          el.classList.add('d-none');
        }
      }
    });
    
    // Mostrar mensaje si no hay documentos
    const sinDocsEl = document.getElementById('sec_sin_docs');
    if (sinDocsEl) {
      sinDocsEl.style.display = hayDocs ? 'none' : 'block';
    }
    
    // Manejo de fotografías
    ['1','2'].forEach(n => {
      const val = d['foto' + n];
      const prev = document.getElementById('sec_prev' + n);
      const cont = document.getElementById('sec_prev' + n + '_container');
      const input = document.getElementById('sec_input_foto' + n);
      
      if (prev && cont) {
        if (val && val.trim() && val !== 'null' && val !== 'undefined') {
          prev.src = 'uploads/' + val;
          cont.style.display = 'block';
          cont.style.removeProperty('display');
        } else {
          prev.src = '';
          cont.style.display = 'none';
        }
      }
      if (input) input.value = '';
    });
    
    document.getElementById('sec_btn_ficha').href = 'ficha.php?folio=' + d.folio;
  });
});

// Preview fotos corrección
['1','2'].forEach(n=>{
  document.getElementById('sec_input_foto'+n)?.addEventListener('change',function(e){
    const f=e.target.files[0];
    if(f&&f.type.startsWith('image/')){
      const r=new FileReader();
      r.onload=ev=>{document.getElementById('sec_prev'+n).src=ev.target.result;document.getElementById('sec_prev'+n+'_container').style.display='block';};
      r.readAsDataURL(f);
    }
  });
});

// ── GUARDAR CORRECCIÓN ──
let _pendingNotif=null;
document.getElementById('formVentanilla')?.addEventListener('submit',function(e){
  e.preventDefault();
  Swal.fire({
    title:'¿Guardar cambios?',
    html:'El trámite regresará a <strong>En revisión</strong>.',
    icon:'question',showCancelButton:true,
    confirmButtonText:'Sí, guardar',cancelButtonText:'Cancelar',
    confirmButtonColor:'#7b0f2b',cancelButtonColor:'#6c757d'
  }).then(r=>{
    if(!r.isConfirmed) return;
    fetch('php/actualizarTramite.php',{method:'POST',body:new FormData(this),credentials:'same-origin'})
    .then(res=>{if(!res.ok)return res.text().then(t=>{throw new Error(t.substring(0,200));});return res.json();})
    .then(data=>{
      if(data.success){_pendingNotif=data;bootstrap.Modal.getInstance(document.getElementById('modalEditar'))?.hide();}
      else Swal.fire({icon:'error',title:'Error al guardar',text:data.message||'No se pudieron guardar los cambios.'});
    })
    .catch(err=>Swal.fire({icon:'error',title:'Error de conexión',html:err.message}));
  });
});

document.getElementById('modalEditar')?.addEventListener('hidden.bs.modal',()=>{if(_pendingNotif)setTimeout(_abrirNotif,150);});
document.getElementById('notifModal')?.addEventListener('hidden.bs.modal',()=>{_pendingNotif=null;location.reload();});

function _abrirNotif(){
  const data=_pendingNotif; if(!data) return;
  const n=data.notificacion||{};
  document.getElementById('notif-desc').innerHTML=`Trámite <strong>${data.folio}</strong> regresado a <strong>En revisión</strong>`;
  if(n.mensaje){document.getElementById('notif-texto').textContent=n.mensaje;document.getElementById('notif-preview').style.display='block';}

  const wa=document.getElementById('notif-wa'),gm=document.getElementById('notif-gm');
  if(n.wa_link){wa.href=n.wa_link;wa.style.opacity='1';wa.style.pointerEvents='auto';wa.querySelector('.notif-sub').textContent=n.telefono?'Enviar a: '+n.telefono:'Abrir WhatsApp';}
  else{wa.href='#';wa.style.opacity='0.35';wa.style.pointerEvents='none';wa.querySelector('.notif-sub').textContent='Sin número registrado';}
  if(n.gm_link){gm.href=n.gm_link;gm.style.opacity='1';gm.style.pointerEvents='auto';gm.querySelector('.notif-sub').textContent=n.correo?'Enviar a: '+n.correo:'Abrir correo';}
  else{gm.href='#';gm.style.opacity='0.35';gm.style.pointerEvents='none';gm.querySelector('.notif-sub').textContent='Sin correo registrado';}
  const el=document.getElementById('notifModal');
  el.removeAttribute('aria-hidden');
  new bootstrap.Modal(el,{backdrop:'static',keyboard:false}).show();
}

// ── DATATABLES ──
$(document).ready(function(){
  const lang={paginate:{previous:'Anterior',next:'Siguiente'},info:'Mostrando _START_ a _END_ de _TOTAL_',infoEmpty:'Sin registros',zeroRecords:'Sin resultados',search:'Buscar:',lengthMenu:'Mostrar _MENU_ registros'};



  if($('#tablaAprobadosSec').length && $('#tablaAprobadosSec tbody tr').length > 1) {
    try {
      $('#tablaAprobadosSec').DataTable({
        language: lang,
        order: [[7,'desc']],
        pageLength: 10
      });

    } catch(e) {
      console.error('Error initializing tablaAprobadosSec DataTable:', e);
    }
  }
  if($('#tablaCorreccion').length) {
    try {
      $('#tablaCorreccion').DataTable({
        language:lang,
        order:[[4,'asc']],
        columnDefs:[{orderable:false,targets:6}]
      });
    } catch(e) {
      console.error('Error initializing tablaCorreccion DataTable:', e);
    }
  }
  if($('#tablaSeguimiento').length) {
    try {
      $('#tablaSeguimiento').DataTable({
        language: lang,
        order: [[4,'desc']],
        pageLength: 10
      });

    } catch(e) {
      console.error('Error initializing tablaSeguimiento DataTable:', e);
    }
  }
  if($('#tablaFirmaDirector').length) {
    try {
      $('#tablaFirmaDirector').DataTable({
        language:lang,
        order:[[5,'asc']],
        columnDefs:[{orderable:false,targets:6}]
      });
    } catch(e) {
      console.error('Error initializing tablaFirmaDirector DataTable:', e);
    }
  }
});


// ── VARIABLES GLOBALES PARA CONSTANCIA ──
let _cs_croquis_guardado = false;
let _cs_folio_actual = '';



// ── Modal CONSTANCIA Ventanilla: poblar ──
$(document).on('click', '.btn-constancia-sec', function(){
  // GUARDAR FOLIO
  _cs_folio_actual = $(this).data('folio');
  document.getElementById('cs_folio_hidden').value = _cs_folio_actual;
  
  // DATOS GENERALES
  $('#cs_folio').text($(this).data('folio'));
  $('#cs_propietario').text($(this).data('propietario'));
  $('#cs_direccion').text($(this).data('direccion') || '—');
  $('#cs_localidad').text($(this).data('localidad') || '—');
  // ── FOLIO SALIDA ──
let folioSalidaNumero = $(this).data('folio-salida-numero');
let folioSalidaAnio   = $(this).data('folio-salida-anio');

if (folioSalidaNumero) {
  let folioSalida = String(folioSalidaNumero).padStart(3, '0') + "/" + folioSalidaAnio;
  $('#cs_folio_salida').text(folioSalida);
} else {
  $('#cs_folio_salida').text('—');
}

  // DATOS ESPECÍFICOS DE CONSTANCIA
  $('#cs_tipo_asignacion').text($(this).data('tipo-asignacion') || 'ASIGNACIÓN');
  $('#cs_numero_asignado').text($(this).data('numero-asignado') || '—');
  $('#cs_referencia_anterior').text($(this).data('referencia-anterior') || '—');
  $('#cs_entre_calle1').text($(this).data('entre-calle1') || '—');
  $('#cs_entre_calle2').text($(this).data('entre-calle2') || '—');
  $('#cs_cuenta_catastral').text($(this).data('cuenta-catastral') || '—');
  $('#cs_manzana').text($(this).data('manzana') || '—');
  $('#cs_lote').text($(this).data('lote') || '—');
  $('#cs_fecha_constancia').text($(this).data('fecha-constancia') || new Date().toLocaleDateString('es-MX'));

  // CROQUIS
  let croquis = $(this).data('croquis');
  _cs_croquis_guardado = !!croquis; // Determinar si hay croquis

  if(croquis && croquis.trim()) {
    $('#cs_preview_img').attr('src', croquis).show();
    $('#cs_no_img').hide();
  } else {
    $('#cs_preview_img').hide();
    $('#cs_no_img').show();
  }
});

// ── Imprimir constancia (CORREGIDO CON VALIDACIÓN DE FOLIO SALIDA) ──
document.getElementById('btnImprimirConstanciaSec').addEventListener('click', function() {
  const folio = _cs_folio_actual || document.getElementById('cs_folio_hidden').value;
  const folioSalida = document.getElementById('cs_folio_salida').textContent;
  
  if (!folio) {
    Swal.fire({ 
      icon: 'error', 
      title: 'Error', 
      text: 'No se pudo obtener el folio del trámite.',
      confirmButtonColor: '#7b0f2b' 
    });
    return;
  }

  // VALIDAR QUE TENGA FOLIO DE SALIDA
  if (!folioSalida || folioSalida === '—') {
    Swal.fire({ 
      icon: 'warning', 
      title: 'Sin folio de salida',
      text: 'Este trámite aún no ha sido firmado por el Director. No se puede generar la constancia.',
      confirmButtonColor: '#7b0f2b',
      timer: 6000,
      timerProgressBar: true,
      showConfirmButton: true
    });
    return;
  }

  if (!_cs_croquis_guardado) {
    Swal.fire({ 
      icon: 'warning', 
      title: 'Croquis requerido',
      text: 'La constancia requiere un croquis guardado para poder imprimirse.',
      confirmButtonColor: '#7b0f2b' 
    });
    return;
  }

  // Abrir constancia para impresión/firma en la MISMA PESTAÑA
  const url = 'constancia_numero.php?folio=' + encodeURIComponent(folio) + '&imprimir=1';
  window.location.href = url;
});

// ── Limpiar estado al cerrar modal ──
document.getElementById('modalConstanciaSec').addEventListener('hidden.bs.modal', function() {
  _cs_croquis_guardado = false;
  _cs_folio_actual = '';
  $('#cs_preview_img').hide();
  $('#cs_no_img').show();
});








document.querySelectorAll('.btn-firmar-director').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var d = btn.dataset;
    document.getElementById('fd_folio').textContent      = d.folio;
    var fdSalida = d.folioSalida || '';
    var fdSalidaEl = document.getElementById('fd_folio_salida');
    if (fdSalida) {
      fdSalidaEl.textContent = fdSalida;
      fdSalidaEl.className = 'badge bg-success fs-6';
    } else {
      fdSalidaEl.textContent = '—';
      fdSalidaEl.className = 'badge bg-secondary fs-6';
    }
    document.getElementById('fd_folio_hidden').value     = d.folio;
    document.getElementById('fd_propietario').textContent= d.propietario;
    document.getElementById('fd_tramite').textContent    = d.tramite;
    document.getElementById('fd_telefono').textContent   = d.telefono || '—';
    document.getElementById('fd_tipo_tramite_id').value  = d.tipoTramiteId || '';
    document.getElementById('fd_observaciones').value    = '';
    document.getElementById('fd_btn_ficha').href         = 'ficha.php?folio=' + d.folio;
    // Limpiar radios
    document.getElementById('rdAprobado').checked  = false;
    document.getElementById('rdRechazado').checked = false;
  });
});

// ── Envío resolución Director ──
var _pendingNotifFD = null;

document.getElementById('formFirmaDirector').addEventListener('submit', function(e) {
  e.preventDefault();

  var estatusSeleccionado = document.querySelector('input[name="estatus"]:checked');
  if (!estatusSeleccionado) {
    Swal.fire({icon:'warning', title:'Selecciona una resolución', text:'Debes elegir Aprobado o Rechazado.'});
    return;
  }

  var estatus = estatusSeleccionado.value;
  var etiqueta = estatus === 'Aprobado' ? '✅ Aprobado — firmado por el Director' : '❌ Rechazado';

  Swal.fire({
    title: '¿Confirmar resolución?',
    html: 'Resolución: <strong>' + etiqueta + '</strong>',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, guardar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: estatus === 'Aprobado' ? '#198754' : '#dc3545',
    cancelButtonColor: '#6c757d'
  }).then(function(result) {
    if (!result.isConfirmed) return;

    fetch('php/actualizarTramite.php', {
      method: 'POST',
      body: new FormData(document.getElementById('formFirmaDirector')),
      credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        _pendingNotifFD = data;
        bootstrap.Modal.getInstance(document.getElementById('modalFirmaDirector')).hide();
      } else {
        Swal.fire({icon:'error', title:'Error', text: data.message || 'No se pudo guardar.'});
      }
    })
    .catch(function(err) {
      Swal.fire({icon:'error', title:'Error de conexión', text: err.message});
    });
  });
});

document.getElementById('modalFirmaDirector').addEventListener('hidden.bs.modal', function() {
  if (_pendingNotifFD) setTimeout(function() { _abrirNotifFD(_pendingNotifFD); }, 150);
});

function _abrirNotifFD(data) {
  var n = data.notificacion || {};
  document.getElementById('notif-desc').innerHTML =
    'Trámite <strong>' + data.folio + '</strong> — <strong>' + data.estatus + '</strong>';

  if (n.mensaje) {
    document.getElementById('notif-texto').textContent = n.mensaje;
    document.getElementById('notif-preview').style.display = 'block';
  }

  var wa = document.getElementById('notif-wa');
  var gm = document.getElementById('notif-gm');

  if (n.wa_link) {
    wa.href = n.wa_link; wa.style.opacity='1'; wa.style.pointerEvents='auto';
    wa.querySelector('.notif-sub').textContent = n.telefono ? 'Enviar a: '+n.telefono : 'Abrir WhatsApp';
  } else {
    wa.href='#'; wa.style.opacity='0.35'; wa.style.pointerEvents='none';
    wa.querySelector('.notif-sub').textContent = 'Sin número de teléfono registrado';
  }
  if (n.gm_link) {
    gm.href = n.gm_link; gm.style.opacity='1'; gm.style.pointerEvents='auto';
    gm.querySelector('.notif-sub').textContent = n.correo ? 'Enviar a: '+n.correo : 'Abrir correo';
  } else {
    gm.href='#'; gm.style.opacity='0.35'; gm.style.pointerEvents='none';
    gm.querySelector('.notif-sub').textContent = 'Sin correo registrado';
  }

  var el = document.getElementById('notifModal');
  el.removeAttribute('aria-hidden');
  new bootstrap.Modal(el, {backdrop:'static', keyboard:false}).show();
  _pendingNotifFD = null;
}

// ── GRÁFICA REPORTE VENTANILLA ──
(function() {
  var labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  var datosApr = datosApr;
  var datosRev = datosRev;
  var datosRec = datosRec;
  var canvas = document.getElementById('chartReporteMesSec');
  if (canvas && typeof Chart !== 'undefined') {
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: 'Aprobados', data: datosApr, backgroundColor: '#198754' },
          { label: 'En Revisión', data: datosRev, backgroundColor: '#ffc107' },
          { label: 'Rechazados', data: datosRec, backgroundColor: '#dc3545' }
        ]
      },
      options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { x: { stacked: false }, y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
  }
})();

function imprimirReporteSec() {
  var t1 = document.getElementById('tablaReporteMesSec');
  var t2 = document.getElementById('tablaReporteTipoSec');
  if (!t1) { alert('No hay datos para imprimir.'); return; }
  var tabla1 = t1.outerHTML;
  var tabla2 = t2 ? '<h3 style="margin-top:24px;color:#7b0f2b;">Por Tipo de Trámite</h3>' + t2.outerHTML : '';
  var w = window.open('', '_blank');
  w.document.write(
    '<html><head><title>Reporte ' + anioFiltro + '</title><style>' +
    'body{font-family:Arial,sans-serif;padding:20px;color:#222;}' +
    'h2,h3{color:#7b0f2b;margin:6px 0;}' +
    'table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px;}' +
    'th,td{border:1px solid #bbb;padding:6px 10px;text-align:center;}' +
    'td:first-child{text-align:left;}' +
    'thead th{color:white;}' +
    'tfoot tr:first-child td{background:#e0e0e0;font-weight:bold;}' +
    'tfoot tr:last-child td{background:#d0d0d0;font-weight:bold;}' +
    '.badge{padding:2px 8px;border-radius:4px;font-size:12px;color:white;display:inline-block;}' +
    '.bg-primary{background:#0d6efd;}.bg-success{background:#198754;}' +
    '.bg-warning{background:#ffc107;color:#000!important;}' +
    '.bg-info{background:#0dcaf0;color:#000!important;}' +
    '.bg-danger{background:#dc3545;}.bg-dark{background:#212529;}' +
    '.text-muted{color:#888;}.progress{display:none;}' +
    '</style></head><body>' +
    '<h2>Reporte de Trámites — ' + anioFiltro + '</h2>' +
    '<p style="color:#555;margin-bottom:16px;">Generado: ' + new Date().toLocaleDateString('es-MX') + ' | Usuario: ' + usuarioSesion + '</p>' +
    tabla1 + tabla2 +
    '</body></html>'
  );
  w.document.close();
  setTimeout(function(){ w.print(); }, 400);
}

// ── Guardar formato de constancia ──
function guardarConfigConstancia() {
  var form  = document.getElementById('formConfigConstancia');
  var msg   = document.getElementById('msg-config-constancia');
  var btn   = form.querySelector('button[onclick]');
  var fd    = new FormData(form);

  msg.textContent = 'Guardando...';
  msg.style.color = '#666';
  btn.disabled = true;

  fetch('php/actualizar_config_constancia.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    btn.disabled = false;
    if (data.success) {
      msg.textContent = '✅ ' + data.message;
      msg.style.color = '#198754';
    } else {
      msg.textContent = '❌ ' + data.message;
      msg.style.color = '#dc3545';
    }
    setTimeout(function(){ msg.textContent = ''; }, 4000);
  })
  .catch(function(){
    btn.disabled = false;
    msg.textContent = '❌ Error de conexión.';
    msg.style.color = '#dc3545';
  });
}
// ── FUNCIÓN PARA CARGAR DATOS DE TRÁMITE ANTERIOR EN VENTANILLA (CON COPIA DE DOCUMENTOS) ──
function cargarDatosTramiteVentanilla(tramite, folioOrigen) {
    // Datos generales
    document.querySelector('input[name="propietario"]').value = tramite.propietario || '';
    document.querySelector('input[name="direccion"]').value = tramite.direccion || '';
    document.querySelector('input[name="localidad"]').value = tramite.localidad || '';
    document.querySelector('select[name="colonia"]').value = tramite.colonia || '';
    document.querySelector('input[name="cp"]').value = tramite.cp || '';
    document.querySelector('select[name="calle"]').value = tramite.calle || '';
    document.querySelector('select[name="entre_calle1"]').value = tramite.entre_calle1 || '';
    document.querySelector('select[name="entre_calle2"]').value = tramite.entre_calle2 || '';
    document.querySelector('input[name="cuenta_catastral"]').value = tramite.cuenta_catastral || '';
    document.querySelector('input[name="superficie"]').value = tramite.superficie || '';
    document.querySelector('input[name="lat"]').value = tramite.lat || '';
    document.querySelector('input[name="lng"]').value = tramite.lng || '';
    document.querySelector('input[name="solicitante"]').value = tramite.solicitante || '';
    document.querySelector('input[name="telefono"]').value = tramite.telefono || '';
    document.querySelector('input[name="correo"]').value = tramite.correo || '';
    
    // Mostrar loading mientras se copian los documentos
    Swal.fire({
        title: 'Copiando documentos...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    // Obtener el folio actual del nuevo trámite
    const folioNumero = document.querySelector('input[name="folio_numero"]').value;
    const folioAnio = document.querySelector('input[name="folio_anio"]').value;
    const folioDestino = `${String(folioNumero).padStart(3, '0')}/${folioAnio}`;
    
    // Copiar los documentos del trámite anterior al nuevo
    fetch('php/copiar_documentos_tramite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `folio_origen=${encodeURIComponent(folioOrigen)}&folio_destino=${encodeURIComponent(folioDestino)}`
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            // Mostrar alerta corta
            let mensajeCorto = 'Documentos copiados correctamente.';
            
            // Mostrar información de constancia de forma más compacta
            if (tramite.constancia) {
                Swal.fire({
                    icon: 'success',
                    title: 'Datos cargados',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#7b0f2b',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Datos cargados',
                    text: 'Datos del trámite anterior cargados correctamente.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
            
            // Mostrar enlaces a los documentos copiados en el formulario
            mostrarDocumentosCopiados(data.archivos_copiados, folioOrigen);
            
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudieron copiar los documentos.',
                confirmButtonColor: '#7b0f2b'
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#7b0f2b'
        });
    });
}

// ── FUNCIÓN PARA MOSTRAR DOCUMENTOS COPIADOS CON OPCIÓN DE DESCARGAR ──
function mostrarDocumentosCopiados(archivos, tramiteOrigen) {
    if (!archivos) return;
    
    const nombres = {
        'ine': 'INE',
        'escritura': 'Escritura/Título',
        'predial': 'Boleta Predial',
    };
    
    // Crear o actualizar una sección para mostrar los documentos copiados
    let seccionDocs = document.getElementById('documentos-copiados');
    
    if (!seccionDocs) {
        // Crear la sección si no existe
        const seccionDocumentos = document.getElementById('seccion-documentos');
        if (seccionDocumentos) {
            const div = document.createElement('div');
            div.id = 'documentos-copiados';
            div.className = 'alert alert-info mb-3';
            div.style.fontSize = '0.85rem';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-archive me-1"></i>Documentos del trámite anterior:</strong>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnDescargarTodos" onclick="descargarTodosDocumentos()">
                        <i class="bi bi-download me-1"></i>Descargar todos
                    </button>
                </div>
                <div id="lista-docs-copiados" class="mt-2"></div>
                <small class="text-muted mt-2 d-block">⚠️ Puedes descargar estos documentos y luego subirlos manualmente en los campos faltantes si es necesario.</small>
            `;
            seccionDocumentos.insertBefore(div, seccionDocumentos.firstChild);
            seccionDocs = div;
        }
    }
    
    if (seccionDocs) {
        const lista = document.getElementById('lista-docs-copiados');
        if (lista) {
            lista.innerHTML = '';
            let documentos = [];
            
            for (const [tipo, archivo] of Object.entries(archivos)) {
                if (archivo) {
                    const nombreDoc = nombres[tipo] || tipo;
                    const li = document.createElement('div');
                    li.className = 'd-flex justify-content-between align-items-center mb-2 p-2 border rounded bg-white';
                    li.innerHTML = `
                        <div>
                            <i class="bi bi-file-earmark-check text-success me-2"></i>
                            <strong>${nombreDoc}</strong>
                            <small class="text-muted ms-2">(${archivo})</small>
                        </div>
                        <a href="uploads/${encodeURIComponent(archivo)}" 
                           download 
                           class="btn btn-sm btn-outline-primary"
                           target="_blank">
                            <i class="bi bi-download me-1"></i>Descargar
                        </a>
                    `;
                    lista.appendChild(li);
                    documentos.push({ tipo: nombreDoc, archivo: archivo });
                }
            }
            
            // Guardar la lista de documentos para la función de descargar todos
            window.documentosCopiados = documentos;
            
            if (documentos.length === 0) {
                lista.innerHTML = '<div class="text-muted text-center py-2">No se copiaron documentos</div>';
            }
            seccionDocs.style.display = 'block';
        }
    }
}

// ── FUNCIÓN PARA DESCARGAR TODOS LOS DOCUMENTOS EN UN ZIP ──
function descargarTodosDocumentos() {
    if (!window.documentosCopiados || window.documentosCopiados.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Sin documentos',
            text: 'No hay documentos para descargar.',
            confirmButtonColor: '#7b0f2b'
        });
        return;
    }
    
    Swal.fire({
        title: 'Preparando descarga...',
        text: 'Los documentos se descargarán individualmente. Revisa tu carpeta de descargas.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    // Descargar cada documento individualmente con un pequeño retraso
    let i = 0;
    function descargarSiguiente() {
        if (i >= window.documentosCopiados.length) {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'Descarga completada',
                text: `Se descargaron ${window.documentosCopiados.length} documentos.`,
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }
        
        const doc = window.documentosCopiados[i];
        const link = document.createElement('a');
        link.href = 'uploads/' + encodeURIComponent(doc.archivo);
        link.download = doc.archivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        i++;
        setTimeout(descargarSiguiente, 800);
    }
    
    descargarSiguiente();
}
// ── EVENTO PARA BUSCAR POR PROPIETARIO ──
document.getElementById('btnBuscarTramiteAnterior')?.addEventListener('click', function() {
    const propietario = document.getElementById('propietario_input').value.trim();
    const tipoTramiteId = document.getElementById('tipo_tramite_id_hidden').value;
    
    if (!propietario) {
        Swal.fire({ icon: 'warning', title: 'Nombre requerido', text: 'Escribe el nombre del propietario primero.', confirmButtonColor: '#7b0f2b' });
        return;
    }
    
    if (!tipoTramiteId) {
        Swal.fire({ icon: 'warning', title: 'Tipo de trámite', text: 'Selecciona el tipo de trámite primero.', confirmButtonColor: '#7b0f2b' });
        return;
    }
    
    Swal.fire({ title: 'Buscando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
    fetch(`php/obtener_tramite_anterior.php?propietario=${encodeURIComponent(propietario)}&tipo_tramite_id=${tipoTramiteId}&incluir_constancia=true`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.error) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error, confirmButtonColor: '#7b0f2b' });
                return;
            }
            if (data.tramite) {
                Swal.fire({
                    title: '¿Cargar datos?',
                    html: `Trámite encontrado:<br>
                           <strong>Folio Ingreso:</strong> ${data.tramite.folio}<br>
                           <strong>Folio Salida:</strong> ${data.tramite.folio_salida || '—'}<br>
                           <strong>Propietario:</strong> ${data.tramite.propietario}<br>
                           <strong>Dirección:</strong> ${data.tramite.direccion}<br><br>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cargar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754'
                }).then(result => {
                    if (result.isConfirmed) {
                        cargarDatosTramiteVentanilla(data.tramite, data.tramite.folio);
                    }
                });
            } else {
                Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No se encontraron trámites anteriores.', confirmButtonColor: '#7b0f2b' });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#7b0f2b' });
        });
});

// ── EVENTO PARA BUSCAR POR FOLIO (abrir modal) ──
document.getElementById('btnCargarPorFolio')?.addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('modalCargarPorFolio'));
    modal.show();
});

// ── EVENTO PARA CONFIRMAR CARGA POR FOLIO DE SALIDA ──
document.getElementById('btnConfirmarCargarFolio')?.addEventListener('click', function() {
    const folioSalida = document.getElementById('folio_cargar').value.trim();
    if (!folioSalida) {
        Swal.fire({ icon: 'warning', title: 'Folio requerido', text: 'Ingresa el folio de salida del trámite anterior.', confirmButtonColor: '#7b0f2b' });
        return;
    }

    Swal.fire({ title: 'Buscando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

    // Buscar por folio de salida (añadir parámetro buscar_por_folio_salida=true)
    fetch(`php/obtener_tramite_anterior.php?folio=${encodeURIComponent(folioSalida)}&incluir_constancia=true&buscar_por_folio_salida=true`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.error) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error, confirmButtonColor: '#7b0f2b' });
                return;
            }
            if (data.tramite) {
                Swal.fire({
                    title: '¿Cargar datos?',
                    html: `Trámite encontrado:<br>
                           <strong>Folio Ingreso:</strong> ${data.tramite.folio}<br>
                           <strong>Folio Salida:</strong> ${data.tramite.folio_salida || '—'}<br>
                           <strong>Propietario:</strong> ${data.tramite.propietario}<br>
                           <strong>Dirección:</strong> ${data.tramite.direccion}<br><br>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cargar',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) {
                        cargarDatosTramiteVentanilla(data.tramite, data.tramite.folio);
                        bootstrap.Modal.getInstance(document.getElementById('modalCargarPorFolio')).hide();
                        document.getElementById('folio_cargar').value = ''; // Limpiar campo
                    }
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin resultados',
                    text: `No se encontró ningún trámite con el folio de salida "${folioSalida}".`,
                    confirmButtonColor: '#7b0f2b'
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar.', confirmButtonColor: '#7b0f2b' });
        });
});

// ── FILTRAR COLONIAS POR CÓDIGO POSTAL ──
document.getElementById('cp')?.addEventListener('input', function() {
    const cp = this.value.trim();
    const coloniaSelect = document.getElementById('colonia_select');

    if (cp.length === 5) {
        fetch(`get_colonias.php?cp=${encodeURIComponent(cp)}`)
            .then(response => response.json())
            .then(colonias => {
                coloniaSelect.innerHTML = '<option value="">Seleccionar colonia...</option>';
                colonias.forEach(colonia => {
                    const option = document.createElement('option');
                    option.value = colonia;
                    option.textContent = colonia;
                    coloniaSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error cargando colonias:', error);
                coloniaSelect.innerHTML = '<option value="">Seleccionar colonia...</option>';
            });
    } else {
        coloniaSelect.innerHTML = '<option value="">Seleccionar colonia...</option>';
    }
});

// ── CARGAR CALLES ──
function cargarCalles() {
    fetch('get_calles.php')
        .then(response => response.json())
        .then(calles => {
            const calleSelect = document.getElementById('calle_select');
            const entreCalle1Select = document.getElementById('entre_calle1_select');
            const entreCalle2Select = document.getElementById('entre_calle2_select');

            const optionHtml = '<option value="">Seleccionar calle...</option>';
            if (calleSelect) calleSelect.innerHTML = optionHtml;
            if (entreCalle1Select) entreCalle1Select.innerHTML = optionHtml;
            if (entreCalle2Select) entreCalle2Select.innerHTML = optionHtml;

            calles.forEach(calle => {
                if (calleSelect) {
                    const option = document.createElement('option');
                    option.value = calle;
                    option.textContent = calle;
                    calleSelect.appendChild(option);
                }
                if (entreCalle1Select) {
                    const option = document.createElement('option');
                    option.value = calle;
                    option.textContent = calle;
                    entreCalle1Select.appendChild(option);
                }
                if (entreCalle2Select) {
                    const option = document.createElement('option');
                    option.value = calle;
                    option.textContent = calle;
                    entreCalle2Select.appendChild(option);
                }
            });
        })
        .catch(error => console.error('Error cargando calles:', error));
}

// ── BUSCAR CUENTA CATASTRAL ──
document.getElementById('btnBuscarCuenta').addEventListener('click', function() {
    const cuenta = document.getElementById('cuenta_catastral').value.trim();
    if (!cuenta) {
        Swal.fire({
            icon: 'warning',
            title: 'Cuenta requerida',
            text: 'Ingresa una cuenta catastral para buscar.',
            confirmButtonColor: '#7b0f2b'
        });
        return;
    }

    const encontrado = buscarYResaltarPoligono(cuenta);
    if (!encontrado) {
        Swal.fire({
            icon: 'info',
            title: 'No encontrada',
            text: 'No se encontró ninguna parcela con esa cuenta catastral.',
            confirmButtonColor: '#7b0f2b'
        });
    }
});

// También permitir buscar con Enter
document.getElementById('cuenta_catastral').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnBuscarCuenta').click();
    }
});

// Cargar calles al cargar la página
document.addEventListener('DOMContentLoaded', cargarCalles);

