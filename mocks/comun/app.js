/*
 * app.js — Mocks de Lavandi
 *
 * Sin dependencias ni compilación: funciona abierto con doble clic (file://).
 * Lo que hace:
 *   1. Inyecta el juego de iconos (SVG <symbol>) que usan las pantallas con <use href="#i-...">.
 *   2. Dibuja la barra de herramientas del mock: catálogo, anterior y siguiente del recorrido,
 *      plataforma (iPhone o Android) y el botón que oculta las anotaciones.
 *   3. Maneja las variantes: cuando el vault deja una decisión abierta con una recomendación escrita,
 *      la pantalla muestra las opciones y el panel deja cambiar entre ellas.
 *   4. Guarda el pedido de ejemplo que se va armando (lavandería, servicio, ventana, dirección) para
 *      que el resumen y la confirmación muestren lo que se eligió.
 *   5. Utilidades: brindis (mensaje breve), aviso push simulado y formato de fechas en español.
 *
 * Todo lo que se guarda vive en el navegador de quien mira (localStorage). Si el navegador no lo
 * permite, el mock funciona igual con los valores de ejemplo.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /* Almacenamiento con respaldo en memoria                              */
  /* ------------------------------------------------------------------ */
  var PREFIJO = 'mockLavandi.';
  var memoria = {};
  function leer(clave, defecto) {
    try {
      var v = window.localStorage.getItem(PREFIJO + clave);
      if (v === null) return (clave in memoria) ? memoria[clave] : defecto;
      return JSON.parse(v);
    } catch (e) {
      return (clave in memoria) ? memoria[clave] : defecto;
    }
  }
  function escribir(clave, valor) {
    memoria[clave] = valor;
    try { window.localStorage.setItem(PREFIJO + clave, JSON.stringify(valor)); } catch (e) { /* sin almacenamiento */ }
  }

  /* ------------------------------------------------------------------ */
  /* Iconos                                                              */
  /* ------------------------------------------------------------------ */
  var ICONOS = {
    'atras': '<polyline points="15 18 9 12 15 6"/>',
    'adelante': '<polyline points="9 18 15 12 9 6"/>',
    'abajo': '<polyline points="6 9 12 15 18 9"/>',
    'cerrar': '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    'buscar': '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    'filtros': '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
    'mapa': '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
    'lista': '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    'casa': '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'pedidos': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'soporte': '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'perfil': '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'ubicacion': '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    'mi-ubicacion': '<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><line x1="12" y1="1" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="1" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="23" y2="12"/>',
    'reloj': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'calendario': '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'tarjeta': '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    'efectivo': '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>',
    'palomita': '<polyline points="20 6 9 17 4 12"/>',
    'palomita-circulo': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    'info': '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    'alerta': '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'estrella': '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'camara': '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    'imagen': '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
    'campana': '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'mensaje': '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    'correo': '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
    'candado': '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'ojo': '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'salir': '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'basura': '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    'editar': '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
    'mas': '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    'copiar': '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    'repetir': '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    'refrescar': '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    'externo': '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    'bolsa': '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'camion': '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    'lavadora': '<rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="13" r="5"/><line x1="8" y1="6" x2="8.01" y2="6"/><line x1="11" y1="6" x2="16" y2="6"/>',
    'escudo': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'enviar': '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
    'flecha': '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
    'cargando': '<path d="M21 12a9 9 0 1 1-6.22-8.56"/>',
    'documento': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    'senal': '<path d="M2 20h.01M7 20v-4M12 20v-8M17 20V8M22 20V4"/>',
    'wifi': '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
    'bateria': '<rect x="1" y="6" width="18" height="12" rx="2" ry="2"/><line x1="23" y1="13" x2="23" y2="11"/><rect x="3.5" y="8.5" width="11" height="7" rx="1" fill="currentColor" stroke="none"/>',
    'navegar': '<polygon points="3 11 22 2 13 21 11 13 3 11"/>'
  };

  function inyectarIconos() {
    if (document.getElementById('juego-iconos')) return;
    var simbolos = Object.keys(ICONOS).map(function (k) {
      return '<symbol id="i-' + k + '" viewBox="0 0 24 24">' + ICONOS[k] + '</symbol>';
    }).join('');
    var contenedor = document.createElement('div');
    contenedor.innerHTML = '<svg id="juego-iconos" xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">' + simbolos + '</svg>';
    document.body.insertBefore(contenedor.firstChild, document.body.firstChild);
  }

  function icono(nombre, clase) {
    return '<svg class="icono ' + (clase || '') + '" aria-hidden="true"><use href="#i-' + nombre + '"/></svg>';
  }

  /* ------------------------------------------------------------------ */
  /* Datos de ejemplo. Todo es ficticio y así se rotula en cada pantalla */
  /* ------------------------------------------------------------------ */
  var MARCA = 'Lavandi';
  var FOLIO = 'EJ-000123';

  var SERVICIOS = {
    estandar: { nombre: 'Lavado estándar', corto: 'Estándar' },
    planchado: { nombre: 'Lavado con planchado', corto: 'Planchado' },
    seco: { nombre: 'Lavado en seco', corto: 'En seco' }
  };

  var LAVANDERIAS = {
    centro: { id: 'centro', nombre: 'Lavandería Ejemplo Centro', distancia: 1.2, calificacion: 4.8, resenas: 23,
      estado: 'abierta', servicios: ['estandar', 'planchado', 'seco'], x: 52, y: 47 },
    jardines: { id: 'jardines', nombre: 'Lavandería Ejemplo Jardines', distancia: 0.8, calificacion: 4.6, resenas: 11,
      estado: 'abierta', servicios: ['estandar', 'seco'], x: 30, y: 30 },
    norte: { id: 'norte', nombre: 'Lavandería Ejemplo Norte', distancia: 2.6, calificacion: 4.5, resenas: 17,
      estado: 'sin-cupo', servicios: ['estandar', 'planchado'], x: 70, y: 18 },
    sur: { id: 'sur', nombre: 'Lavandería Ejemplo Sur', distancia: 3.4, calificacion: 4.2, resenas: 8,
      estado: 'cerrada', servicios: ['estandar'], x: 78, y: 72 }
  };

  var ESTADO_LAVANDERIA = {
    'abierta': { texto: 'Abierta', clase: 'e-exito' },
    'sin-cupo': { texto: 'Sin cupo hoy', clase: 'e-atencion' },
    'cerrada': { texto: 'Cerrada', clase: 'e-error' }
  };

  var DIRECCIONES = {
    casa: { id: 'casa', alias: 'Casa', linea: 'Calle Ejemplo 123', colonia: 'Col. Centro (ejemplo)', municipio: 'Durango, Dgo.', referencias: 'Portón gris, junto a la tienda' },
    oficina: { id: 'oficina', alias: 'Oficina', linea: 'Avenida Ejemplo 456, int. 2', colonia: 'Col. Jardines (ejemplo)', municipio: 'Durango, Dgo.', referencias: 'Recepción, planta baja' }
  };

  var USUARIO = { nombre: 'Usuario Ejemplo', correo: 'usuario@ejemplo.com' };

  /* Los cinco estados son literales del contrato (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, numeral 12).
     "aviso" marca los cuatro momentos que enumera el numeral 17; En proceso no tiene aviso en el contrato. */
  var ESTADOS = [
    { clave: 'confirmado', nombre: 'Confirmado', clase: 'e-confirmado',
      mensaje: 'Tu pedido está confirmado. Pasaremos por tu ropa en la ventana que elegiste.',
      aviso: { momento: 'Confirmación', titulo: 'Pedido confirmado', texto: 'Tu pedido ' + FOLIO + ' está confirmado. Pasaremos por tu ropa en la ventana que elegiste.' } },
    { clave: 'recolectado', nombre: 'Recolectado', clase: 'e-recolectado',
      mensaje: 'Tu ropa va en camino a la lavandería.',
      aviso: { momento: 'Recolección', titulo: 'Recogimos tu ropa', texto: 'Va en camino a la lavandería. Te avisamos cuando esté lista.' } },
    { clave: 'en-proceso', nombre: 'En proceso', clase: 'e-en-proceso',
      mensaje: 'La lavandería está lavando tu ropa.',
      aviso: null },
    { clave: 'listo', nombre: 'Listo', clase: 'e-listo',
      mensaje: 'Tu ropa está lista. Vamos para allá.',
      aviso: { momento: 'Listo para entrega', titulo: 'Tu ropa está lista', texto: 'Pronto la llevamos a tu domicilio.' } },
    { clave: 'entregado', nombre: 'Entregado', clase: 'e-entregado',
      mensaje: 'Entregado. Gracias por tu pedido.',
      aviso: { momento: 'Entregado', titulo: 'Pedido entregado', texto: '¿Cómo te fue? Califica tu servicio.' } }
  ];

  /* ------------------------------------------------------------------ */
  /* Fechas en español                                                   */
  /* ------------------------------------------------------------------ */
  var DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function aIso(f) {
    var m = f.getMonth() + 1, d = f.getDate();
    return f.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (d < 10 ? '0' : '') + d;
  }
  function deIso(iso) {
    var p = String(iso).split('-');
    return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
  }
  function hoy() { var f = new Date(); return new Date(f.getFullYear(), f.getMonth(), f.getDate()); }
  function sumarDias(f, n) { return new Date(f.getFullYear(), f.getMonth(), f.getDate() + n); }
  function capitalizar(t) { return t.charAt(0).toUpperCase() + t.slice(1); }
  function fechaTexto(iso, conRelativo) {
    var f = deIso(iso);
    var base = DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()];
    if (conRelativo) {
      var dif = Math.round((f - hoy()) / 86400000);
      if (dif === 0) return 'Hoy, ' + base;
      if (dif === 1) return 'Mañana, ' + base;
    }
    return capitalizar(base);
  }
  /* En el mock, el domingo es "día sin servicio" para enseñar ese estado del componente. No es una regla. */
  function esDiaSinServicio(iso) { return deIso(iso).getDay() === 0; }
  function primerDiaConServicio(desde) {
    var f = desde;
    for (var i = 0; i < 7; i++) { if (!esDiaSinServicio(aIso(f))) return aIso(f); f = sumarDias(f, 1); }
    return aIso(desde);
  }

  /* ------------------------------------------------------------------ */
  /* Pedido de ejemplo que se arma a lo largo del recorrido               */
  /* ------------------------------------------------------------------ */
  function pedidoPorDefecto() {
    return {
      lavanderia: 'centro',
      servicios: ['estandar'],
      fecha: primerDiaConServicio(sumarDias(hoy(), 1)),
      ventana: '11:00 – 12:00',
      recogida: 'casa',
      entrega: 'casa',
      mismaDireccion: true,
      estado: 0
    };
  }
  function pedido() {
    var guardado = leer('pedido', null) || {};
    var base = pedidoPorDefecto();
    Object.keys(guardado).forEach(function (k) { base[k] = guardado[k]; });
    if (!LAVANDERIAS[base.lavanderia]) base.lavanderia = 'centro';
    if (!Array.isArray(base.servicios) || !base.servicios.length) base.servicios = ['estandar'];
    if (!DIRECCIONES[base.recogida]) base.recogida = 'casa';
    if (!DIRECCIONES[base.entrega]) base.entrega = base.recogida;
    if (deIso(base.fecha) < hoy()) base.fecha = pedidoPorDefecto().fecha;
    return base;
  }
  function guardarPedido(parcial) {
    var actual = pedido();
    Object.keys(parcial).forEach(function (k) { actual[k] = parcial[k]; });
    escribir('pedido', actual);
    pintarDatos();
    return actual;
  }

  function direccionTexto(id) {
    var d = DIRECCIONES[id] || DIRECCIONES.casa;
    return d.linea + ', ' + d.colonia + ', ' + d.municipio;
  }

  function valorDato(nombre) {
    var p = pedido();
    var lav = LAVANDERIAS[p.lavanderia];
    switch (nombre) {
      case 'marca': return MARCA;
      case 'folio': return FOLIO;
      case 'usuario.nombre': return USUARIO.nombre;
      case 'usuario.correo': return USUARIO.correo;
      case 'lavanderia.nombre': return lav.nombre;
      case 'lavanderia.meta': return 'A ' + lav.distancia.toFixed(1) + ' km · Calificación ' + lav.calificacion.toFixed(1);
      case 'servicios.texto': return p.servicios.map(function (s) { return SERVICIOS[s].nombre; }).join(' y ');
      case 'fecha.texto': return fechaTexto(p.fecha, true);
      case 'ventana': return p.ventana;
      case 'recogida.alias': return DIRECCIONES[p.recogida].alias;
      case 'recogida.texto': return direccionTexto(p.recogida);
      case 'entrega.alias': return DIRECCIONES[p.mismaDireccion ? p.recogida : p.entrega].alias;
      case 'entrega.texto': return p.mismaDireccion ? 'La misma de recogida' : direccionTexto(p.entrega);
      default: return '';
    }
  }

  function pintarDatos(raiz) {
    (raiz || document).querySelectorAll('[data-dato]').forEach(function (el) {
      el.textContent = valorDato(el.getAttribute('data-dato'));
    });
  }

  /* ------------------------------------------------------------------ */
  /* Recorrido del módulo A: orden de la barra "anterior / siguiente"     */
  /* ------------------------------------------------------------------ */
  var RECORRIDO = [
    { id: 'a01-bienvenida', f: 'F-A01', titulo: 'Bienvenida' },
    { id: 'a01-informativas', f: 'F-A01', titulo: 'Tres pantallas informativas' },
    { id: 'a02-registro', f: 'F-A02', titulo: 'Registro' },
    { id: 'a02-inicio-sesion', f: 'F-A02', titulo: 'Inicio de sesión' },
    { id: 'a02-recuperar', f: 'F-A02', titulo: 'Recuperar contraseña' },
    { id: 'a03-principal', f: 'F-A03', titulo: 'Pantalla principal' },
    { id: 'a04-busqueda', f: 'F-A04', titulo: 'Búsqueda y filtros' },
    { id: 'a05-lavanderia', f: 'F-A05', titulo: 'Perfil de la lavandería' },
    { id: 'a06-servicio', f: 'F-A06', titulo: 'Selección de servicio' },
    { id: 'a07-fecha-hora', f: 'F-A07', titulo: 'Fecha y hora de recolección' },
    { id: 'a08-direccion', f: 'F-A08', titulo: 'Dirección de recogida y entrega' },
    { id: 'a09-resumen', f: 'F-A09', titulo: 'Resumen del pedido' },
    { id: 'a10-pago', f: 'F-A10', titulo: 'Pago' },
    { id: 'a11-confirmacion', f: 'F-A11', titulo: 'Confirmación' },
    { id: 'a12-seguimiento', f: 'F-A12', titulo: 'Seguimiento' },
    { id: 'a13-historial', f: 'F-A13', titulo: 'Historial de pedidos' },
    { id: 'a14-calificacion', f: 'F-A14', titulo: 'Calificación' },
    { id: 'a15-perfil', f: 'F-A15', titulo: 'Perfil de usuario' },
    { id: 'a16-soporte', f: 'F-A16', titulo: 'Soporte' },
    { id: 'a17-notificaciones', f: 'F-A17', titulo: 'Notificaciones push' }
  ];

  function dibujarHerramientas() {
    var id = document.body.getAttribute('data-pantalla');
    if (!id) return;
    var i = -1;
    RECORRIDO.forEach(function (p, k) { if (p.id === id) i = k; });
    if (i < 0) return;
    var actual = RECORRIDO[i], ant = RECORRIDO[i - 1], sig = RECORRIDO[i + 1];
    var barra = document.createElement('header');
    barra.className = 'herramientas';
    barra.innerHTML =
      '<a class="btn btn-texto" href="../index.html">' + icono('atras') + 'Catálogo</a>' +
      '<div class="titulo-mock"><span class="monograma chico" aria-hidden="true">L</span>' +
      '<span>' + actual.f + ' · ' + actual.titulo + ' <small>· pantalla ' + (i + 1) + ' de ' + RECORRIDO.length + ' del módulo A</small></span></div>' +
      (ant ? '<a class="btn" href="' + ant.id + '.html" title="' + ant.titulo + '">' + icono('atras') + 'Anterior</a>'
           : '<span class="btn" aria-disabled="true">' + icono('atras') + 'Anterior</span>') +
      (sig ? '<a class="btn" href="' + sig.id + '.html" title="' + sig.titulo + '">Siguiente' + icono('adelante') + '</a>'
           : '<a class="btn" href="../index.html">Catálogo' + icono('adelante') + '</a>') +
      '<div class="grupo-segmentado" role="group" aria-label="Plataforma del mock">' +
        '<button type="button" data-plataforma-boton="ios">iPhone</button>' +
        '<button type="button" data-plataforma-boton="android">Android</button></div>' +
      '<button type="button" class="btn" id="boton-anotaciones" aria-controls="anotaciones">Anotaciones</button>';
    document.body.insertBefore(barra, document.body.firstChild.nextSibling);
  }

  /* ------------------------------------------------------------------ */
  /* Plataforma: iPhone o Android                                        */
  /* ------------------------------------------------------------------ */
  function aplicarPlataforma(p) {
    document.documentElement.setAttribute('data-plataforma-activa', p);
    document.querySelectorAll('[data-plataforma-boton]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-plataforma-boton') === p));
    });
  }
  function iniciarPlataforma() {
    aplicarPlataforma(leer('plataforma', 'ios'));
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-plataforma-boton]');
      if (!b) return;
      var p = b.getAttribute('data-plataforma-boton');
      escribir('plataforma', p);
      aplicarPlataforma(p);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Panel de anotaciones                                                */
  /* ------------------------------------------------------------------ */
  function aplicarAnotaciones(visibles) {
    document.body.classList.toggle('sin-anotaciones', !visibles);
    var b = document.getElementById('boton-anotaciones');
    if (b) {
      b.setAttribute('aria-pressed', String(visibles));
      b.textContent = visibles ? 'Ocultar anotaciones' : 'Mostrar anotaciones';
    }
  }
  function iniciarAnotaciones() {
    if (!document.getElementById('anotaciones')) {
      var b0 = document.getElementById('boton-anotaciones');
      if (b0) b0.hidden = true;
      return;
    }
    aplicarAnotaciones(leer('anotaciones', true));
    document.addEventListener('click', function (ev) {
      if (ev.target.closest('#boton-anotaciones')) {
        var v = document.body.classList.contains('sin-anotaciones');
        escribir('anotaciones', v);
        aplicarAnotaciones(v);
      } else if (ev.target.closest('[data-cerrar-anotaciones]')) {
        escribir('anotaciones', false);
        aplicarAnotaciones(false);
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Variantes: opciones abiertas en el vault                            */
  /*  - El control vive en el panel: <div class="variante-control" data-grupo="cobro">                */
  /*      con botones <button data-valor="al-pedir">.                                                 */
  /*  - Lo que cambia en la pantalla lleva data-variante="cobro:al-pedir" (o varios, separados).      */
  /* ------------------------------------------------------------------ */
  /* Valor con el que abre cada grupo. Siempre es lo que el contrato dice literal o lo que ya decidió un ADR;
     la recomendación de Claude se ve al cambiar la variante en el panel. */
  var VARIANTES_DEFECTO = {
    cobro: 'al-pedir',          // DD-012: abre con el texto del canvas del Resumen ("Confirmar y pagar")
    efectivo: 'literal',        // DD-056: el contrato dice OXXO Pay
    tarjeta: 'cada-vez',        // DD-055: sin tarjeta guardada, ADR-0010 punto 5
    calificacion: 'literal',    // DD-052: una sola calificación, como el contrato
    socios: 'literal',          // DD-053: el contrato no dice cómo entra el socio
    'vista-a17': 'bloqueo'      // A17: no es decisión abierta, solo cambia lo que se mira
  };
  function variantesGuardadas() { return leer('variantes', {}); }
  function valorVariante(grupo) {
    var v = variantesGuardadas()[grupo];
    if (v) return v;
    if (VARIANTES_DEFECTO[grupo]) return VARIANTES_DEFECTO[grupo];
    var control = document.querySelector('.variante-control[data-grupo="' + grupo + '"]');
    var b = control ? control.querySelector('[data-valor]') : null;
    return b ? b.getAttribute('data-valor') : null;
  }
  function aplicarVariantes() {
    document.querySelectorAll('.variante-control').forEach(function (c) {
      var g = c.getAttribute('data-grupo'), v = valorVariante(g);
      c.querySelectorAll('[data-valor]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(b.getAttribute('data-valor') === v));
      });
    });
    document.querySelectorAll('[data-variante]').forEach(function (el) {
      var visible = el.getAttribute('data-variante').split(/\s+/).some(function (par) {
        var partes = par.split(':');
        return valorVariante(partes[0]) === partes[1];
      });
      el.hidden = !visible;
    });
    document.dispatchEvent(new CustomEvent('variantes-aplicadas'));
  }
  function iniciarVariantes() {
    aplicarVariantes();
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('.variante-control [data-valor]');
      if (!b) return;
      var g = b.closest('.variante-control').getAttribute('data-grupo');
      var todas = variantesGuardadas();
      todas[g] = b.getAttribute('data-valor');
      escribir('variantes', todas);
      aplicarVariantes();
    });
  }

  /* ------------------------------------------------------------------ */
  /* Mensajes dentro del teléfono                                        */
  /* ------------------------------------------------------------------ */
  function pantalla() { return document.querySelector('.pantalla'); }

  function brindis(texto, ms) {
    var p = pantalla(); if (!p) return;
    var previo = p.querySelector('.brindis'); if (previo) previo.remove();
    var el = document.createElement('div');
    el.className = 'brindis'; el.setAttribute('role', 'status'); el.textContent = texto;
    p.appendChild(el);
    setTimeout(function () { el.remove(); }, ms || 3200);
  }

  function avisoPush(datos) {
    var p = pantalla(); if (!p) return;
    var previo = p.querySelector('.aviso-push'); if (previo) previo.remove();
    var el = document.createElement('a');
    el.className = 'aviso-push';
    el.href = datos.enlace || 'a12-seguimiento.html';
    el.setAttribute('role', 'status');
    el.innerHTML = '<span class="monograma chico" aria-hidden="true">L</span><span class="cuerpo-push">' +
      '<span class="app-push"><span>' + MARCA + '</span><span>ahora</span></span>' +
      '<span class="titulo-push" style="display:block">' + datos.titulo + '</span>' +
      '<span class="texto-push" style="display:block">' + datos.texto + '</span></span>';
    p.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, datos.ms || 6000);
  }

  /* ------------------------------------------------------------------ */
  /* Componente 1 · Tarjeta de lavandería (componentes.md §1)            */
  /* Estados: abierta (acción primaria), sin cupo hoy ("Ver horarios"),  */
  /* cerrada (acción deshabilitada y próxima apertura).                  */
  /* ------------------------------------------------------------------ */
  function tarjetaLavanderia(l) {
    var e = ESTADO_LAVANDERIA[l.estado];
    var etiquetas = l.servicios.map(function (s) { return '<span class="etiqueta">' + SERVICIOS[s].corto + '</span>'; }).join('');
    var guardar = ' data-guardar=\'{"lavanderia":"' + l.id + '"}\'';
    var pie;
    if (l.estado === 'abierta') {
      pie = '<span class="precio">Desde <span class="precio-pendiente">$ —</span> por kilo</span>' +
            '<a class="btn btn-primario" href="a05-lavanderia.html"' + guardar + '>Elegir</a>';
    } else if (l.estado === 'sin-cupo') {
      pie = '<span class="precio">Desde <span class="precio-pendiente">$ —</span> por kilo</span>' +
            '<a class="btn" href="a05-lavanderia.html#horario"' + guardar + '>Ver horarios</a>';
    } else {
      pie = '<span class="precio">Abre mañana a las 9:00</span><button class="btn" type="button" disabled>Elegir</button>';
    }
    return '<article class="tarjeta-lavanderia" data-lavanderia="' + l.id + '">' +
      '<div class="cabeza"><h3 class="nombre">' + l.nombre + '</h3>' +
      '<span class="insignia ' + e.clase + '"><span class="punto"></span>' + e.texto + '</span></div>' +
      '<div class="meta">A ' + l.distancia.toFixed(1) + ' km · Calificación ' + l.calificacion.toFixed(1) + '</div>' +
      '<div class="servicios">' + etiquetas + '</div>' +
      '<div class="pie">' + pie + '</div></article>';
  }

  /* ------------------------------------------------------------------ */
  /* Dibujo del mapa de ejemplo. En la app el mapa es nativo: Apple Maps */
  /* en iPhone y Google Maps en Android (ADR-0009, RN-29). Aquí no se    */
  /* llama a ningún servicio: es un SVG con calles inventadas.           */
  /* ------------------------------------------------------------------ */
  function dibujoMapa() {
    var s = '<svg class="dibujo" viewBox="0 0 390 712" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">' +
      '<rect width="390" height="712" style="fill:var(--mock-mapa-calle)"/>';
    for (var y = 6; y < 712; y += 64) {
      for (var x = 6; x < 390; x += 78) {
        if (y > 440 && y < 600) continue;
        s += '<rect class="m-suelo" x="' + x + '" y="' + y + '" width="66" height="52" rx="4"/>';
      }
    }
    s += '<path class="m-agua" d="M-10 500 C 70 470, 130 540, 210 520 S 340 470, 400 495 L 400 560 C 330 535, 270 590, 200 585 S 60 530, -10 560 Z"/>' +
      '<line class="m-avenida" x1="-10" y1="110" x2="400" y2="430" stroke-width="20"/>' +
      '<line class="m-calle" x1="-10" y1="110" x2="400" y2="430" stroke-width="2" stroke-dasharray="8 8"/>' +
      '<line class="m-avenida" x1="240" y1="-10" x2="240" y2="722" stroke-width="16"/>' +
      '<text class="m-texto" x="34" y="122" transform="rotate(38 34 122)">Av. Ejemplo</text>' +
      '<text class="m-texto" x="250" y="250" transform="rotate(90 250 250)">Calle Ejemplo</text>' +
      '<text class="m-texto" x="20" y="532">Río de ejemplo</text>' +
      '</svg>';
    return s;
  }

  function pinSvg() {
    return '<svg viewBox="0 0 34 42" aria-hidden="true"><path class="cuerpo-pin" d="M17 1C8.2 1 1 8 1 16.6 1 28.4 17 41 17 41s16-12.6 16-24.4C33 8 25.8 1 17 1z"/><circle class="centro-pin" cx="17" cy="16.5" r="6"/></svg>';
  }

  /* Enlaces que guardan algo del pedido antes de navegar: data-guardar='{"lavanderia":"norte"}' */
  function iniciarGuardarAlNavegar() {
    document.addEventListener('click', function (ev) {
      var el = ev.target.closest('[data-guardar]');
      if (!el) return;
      try { guardarPedido(JSON.parse(el.getAttribute('data-guardar'))); } catch (e) { /* JSON mal formado */ }
    });
  }

  /* Enlaces a módulos que todavía no tienen mock */
  function iniciarPorHacer() {
    document.addEventListener('click', function (ev) {
      var el = ev.target.closest('[data-por-hacer]');
      if (!el) return;
      ev.preventDefault();
      brindis(el.getAttribute('data-por-hacer'));
    });
  }

  /* ------------------------------------------------------------------ */
  /* Arranque                                                            */
  /* ------------------------------------------------------------------ */
  function iniciar() {
    inyectarIconos();
    dibujarHerramientas();
    iniciarPlataforma();
    iniciarAnotaciones();
    iniciarVariantes();
    iniciarGuardarAlNavegar();
    iniciarPorHacer();
    pintarDatos();
  }

  window.Mock = {
    MARCA: MARCA, FOLIO: FOLIO, SERVICIOS: SERVICIOS, LAVANDERIAS: LAVANDERIAS, ESTADO_LAVANDERIA: ESTADO_LAVANDERIA,
    DIRECCIONES: DIRECCIONES, USUARIO: USUARIO, ESTADOS: ESTADOS, RECORRIDO: RECORRIDO,
    leer: leer, escribir: escribir, icono: icono,
    pedido: pedido, guardarPedido: guardarPedido, pintarDatos: pintarDatos, direccionTexto: direccionTexto,
    valorVariante: valorVariante, aplicarVariantes: aplicarVariantes,
    brindis: brindis, avisoPush: avisoPush, tarjetaLavanderia: tarjetaLavanderia, dibujoMapa: dibujoMapa, pinSvg: pinSvg,
    fecha: { hoy: hoy, sumarDias: sumarDias, aIso: aIso, deIso: deIso, texto: fechaTexto, sinServicio: esDiaSinServicio, DIAS: DIAS, MESES: MESES }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
