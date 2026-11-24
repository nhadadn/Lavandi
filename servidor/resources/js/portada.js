// El comportamiento de la portada de la landing (openspec/changes/landing/). Sin dependencias: la portada se lee
// completa sin JavaScript, y esto solo agrega lo interactivo.

// ---------------------------------------------------------------------------------------------------------------
// El mapa de "Cobertura" (landing-E12 y landing-E13): acercar y alejar con sus botones, mover el mapa acercado
// arrastrándolo, y ver el nombre de la zona que se toca, se señala o se alcanza con el teclado. Todo cambia el viewBox
// del SVG que dibuja el servidor: no pide nada a ningún servicio de mapas (ADR-0009, decisión 5).
// ---------------------------------------------------------------------------------------------------------------

/** Cuánto acerca o aleja cada botón. */
const FACTOR = 1.5;

/** Hasta cuántas veces se acerca el mapa sobre el territorio completo. */
const ACERCAMIENTO_MAXIMO = 8;

/** Los píxeles que hay que arrastrar para que cuente como mover el mapa y no como tocar una zona. */
const UMBRAL_DE_ARRASTRE = 4;

function redondear(numero) {
    return Number(numero.toFixed(2));
}

function iniciarMapa(mapa) {
    const lienzo = mapa.querySelector('[data-mapa-lienzo]');
    const nombre = mapa.parentElement.querySelector('[data-mapa-nombre]');
    const zonas = [...mapa.querySelectorAll('[data-zona]')];
    const [x, y, ancho, alto] = lienzo.getAttribute('viewBox').trim().split(/\s+/).map(Number);
    const inicial = { x, y, ancho, alto };
    let vista = { ...inicial };

    /** La vista dentro del territorio completo: ni más lejos que al inicio ni más cerca que el máximo. */
    function limitar(propuesta) {
        const anchoLimitado = Math.min(inicial.ancho, Math.max(inicial.ancho / ACERCAMIENTO_MAXIMO, propuesta.ancho));
        const altoLimitado = (anchoLimitado * inicial.alto) / inicial.ancho;

        return {
            x: Math.min(Math.max(propuesta.x, inicial.x), inicial.x + inicial.ancho - anchoLimitado),
            y: Math.min(Math.max(propuesta.y, inicial.y), inicial.y + inicial.alto - altoLimitado),
            ancho: anchoLimitado,
            alto: altoLimitado,
        };
    }

    function aplicar(nueva) {
        vista = limitar(nueva);
        lienzo.setAttribute('viewBox', [vista.x, vista.y, vista.ancho, vista.alto].map(redondear).join(' '));
        mapa.classList.toggle('mapa--acercado', vista.ancho < inicial.ancho);
    }

    function escalar(factor) {
        const centroX = vista.x + vista.ancho / 2;
        const centroY = vista.y + vista.alto / 2;
        const anchoNuevo = vista.ancho / factor;
        const altoNuevo = vista.alto / factor;

        aplicar({ x: centroX - anchoNuevo / 2, y: centroY - altoNuevo / 2, ancho: anchoNuevo, alto: altoNuevo });
    }

    mapa.querySelector('[data-mapa-acercar]').addEventListener('click', () => escalar(FACTOR));
    mapa.querySelector('[data-mapa-alejar]').addEventListener('click', () => escalar(1 / FACTOR));

    // Arrastrar mueve el mapa solo cuando está acercado: con el territorio completo a la vista no hay a dónde moverlo,
    // y así el dedo sigue desplazando la página.
    let arrastre = null;

    lienzo.addEventListener('pointerdown', (evento) => {
        if (vista.ancho >= inicial.ancho) {
            return;
        }
        arrastre = { x: evento.clientX, y: evento.clientY, vista: { ...vista }, moviendo: false, puntero: evento.pointerId };
    });

    lienzo.addEventListener('pointermove', (evento) => {
        if (arrastre === null || evento.pointerId !== arrastre.puntero) {
            return;
        }
        const dx = evento.clientX - arrastre.x;
        const dy = evento.clientY - arrastre.y;

        if (!arrastre.moviendo && Math.hypot(dx, dy) < UMBRAL_DE_ARRASTRE) {
            return;
        }
        if (!arrastre.moviendo) {
            arrastre.moviendo = true;
            lienzo.setPointerCapture(evento.pointerId);
        }

        const caja = lienzo.getBoundingClientRect();
        const escala = Math.min(caja.width / arrastre.vista.ancho, caja.height / arrastre.vista.alto);
        aplicar({ ...arrastre.vista, x: arrastre.vista.x - dx / escala, y: arrastre.vista.y - dy / escala });
    });

    for (const fin of ['pointerup', 'pointercancel']) {
        lienzo.addEventListener(fin, () => {
            arrastre = null;
        });
    }

    // El nombre de la zona que se toca, se señala o se alcanza con el teclado.
    function mostrar(zona) {
        nombre.textContent = zona.getAttribute('data-zona');
        for (const otra of zonas) {
            otra.classList.toggle('mapa__zona--elegida', otra === zona);
        }
    }

    for (const zona of zonas) {
        zona.addEventListener('pointerenter', () => mostrar(zona));
        zona.addEventListener('focus', () => mostrar(zona));
        zona.addEventListener('click', () => mostrar(zona));
    }
}

for (const mapa of document.querySelectorAll('[data-mapa]')) {
    iniciarMapa(mapa);
}

// ---------------------------------------------------------------------------------------------------------------
// La medición del toque en un botón de descarga (medicion-E04): pone en la capa de datos del contenedor de etiquetas el
// evento descarga, con la tienda que dice el botón (data-tienda), y nada más. Solo si la página cargó el contenedor
// (resources/views/publico/medicion.blade.php): sin él no existe la capa de datos y no se mide nada (medicion-E02).
// ---------------------------------------------------------------------------------------------------------------

if (Array.isArray(window.dataLayer)) {
    document.addEventListener('click', (evento) => {
        const boton = evento.target instanceof Element ? evento.target.closest('[data-tienda]') : null;

        if (boton !== null) {
            window.dataLayer.push({ event: 'descarga', tienda: boton.getAttribute('data-tienda') });
        }
    });
}
