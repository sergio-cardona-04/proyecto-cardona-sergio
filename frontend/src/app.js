
"use strict";

// Configuración de nuestra API
const API_URL = "/api/incidencias";
const LIMITE = 5;

// Elementos del HTML
const tabla = document.getElementById("tabla-incidencias");
const mensaje = document.getElementById("mensaje");
const total = document.getElementById("total");
const origen = document.getElementById("origen");
const contador = document.getElementById("contador");

const textoPagina = document.getElementById("pagina");
const btnAnterior = document.getElementById("anterior");
const btnSiguiente = document.getElementById("siguiente");
const btnRecargar = document.getElementById("recargar");

// Estado actual de nuestra interfaz
let paginaActual = 1;
let totalPaginas = 1;
let cargando = false;

// Mostrar mensajes informativos o de error
function mostrarMensaje(texto, error = false) {
    mensaje.textContent = texto;

    mensaje.className = error
        ? "mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"
        : "mt-5 text-sm text-slate-500";
}

// Actualizar el estado de los botones
function actualizarBotones() {
    btnAnterior.disabled = cargando || paginaActual <= 1;
    btnSiguiente.disabled = cargando || paginaActual >= totalPaginas;
    btnRecargar.disabled = cargando;
}

// Estilos según el estado de cada incidencia
const clasesEstado = {
    pendiente:
        "bg-amber-100 text-amber-800 ring-amber-200",

    en_proceso:
        "bg-blue-100 text-blue-800 ring-blue-200",

    resuelta:
        "bg-emerald-100 text-emerald-800 ring-emerald-200"
};

// Construir las filas de la tabla
function mostrarIncidencias(incidencias) {
    tabla.replaceChildren();

    // Mostrar un mensaje si no existen registros
    if (incidencias.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");

        celda.colSpan = 6;

        celda.className =
            "px-5 py-10 text-center text-sm text-slate-500";

        celda.textContent =
            "No hay incidencias en esta página.";

        fila.appendChild(celda);
        tabla.appendChild(fila);

        return;
    }

    // Crear una fila por cada incidencia recibida
    for (const incidencia of incidencias) {
        const fila = document.createElement("tr");

        fila.className =
            "transition-colors hover:bg-slate-50";

        // Crear celdas de texto de forma segura
        const agregarCelda = (valor) => {
            const celda = document.createElement("td");

            celda.className =
                "px-5 py-4 align-top text-sm text-slate-600 break-words";

            celda.textContent = String(valor ?? "");

            fila.appendChild(celda);
        };

        agregarCelda(incidencia.id);
        agregarCelda(incidencia.titulo);
        agregarCelda(incidencia.descripcion);
        agregarCelda(incidencia.categoria);

        // Celda del estado
        const celdaEstado = document.createElement("td");

        celdaEstado.className =
            "px-5 py-4 align-top";

        const etiqueta = document.createElement("span");

        const colorEstado =
            clasesEstado[incidencia.estado] ??
            "bg-slate-100 text-slate-700 ring-slate-200";

        etiqueta.className =
            `inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${colorEstado}`;

        etiqueta.dataset.estado = incidencia.estado;

        etiqueta.textContent =
            incidencia.estado.replaceAll("_", " ");

        celdaEstado.appendChild(etiqueta);
        fila.appendChild(celdaEstado);

        agregarCelda(incidencia.fecha_creacion);

        tabla.appendChild(fila);
    }
}

// Obtener datos del backend
async function cargarIncidencias(pagina = 1) {
    if (cargando) return;

    cargando = true;
    actualizarBotones();
    mostrarMensaje("Consultando incidencias...");

    try {
        const respuesta = await fetch(
            `${API_URL}?pagina=${pagina}&limite=${LIMITE}`,
            { cache: "no-store" }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok) {
            throw new Error(
                datos.error || "No fue posible consultar las incidencias"
            );
        }

        // Validar la estructura que devuelve nuestra API
        if (!Array.isArray(datos.datos) || !datos.paginacion) {
            throw new Error("La respuesta del servidor no es válida");
        }

        // Actualizar la información de paginación
        paginaActual = Number(datos.paginacion.pagina_actual);
        totalPaginas = Math.max(
            1,
            Number(datos.paginacion.total_paginas)
        );

        // Mostrar indicadores
        total.textContent = datos.paginacion.total_registros;
        origen.textContent = datos.origen;
        contador.textContent = datos.contador_consultas;

        textoPagina.textContent =
            `Página ${paginaActual} de ${totalPaginas}`;

        // Dibujar los registros
        mostrarIncidencias(datos.datos);

        mostrarMensaje(
            `Mostrando ${datos.datos.length} incidencia(s) en esta página.`
        );

    } catch (error) {
        mostrarMensaje(error.message, true);

    } finally {
        cargando = false;
        actualizarBotones();
    }
}

// Navegación entre páginas
btnAnterior.addEventListener("click", () => {
    if (paginaActual > 1) {
        cargarIncidencias(paginaActual - 1);
    }
});

btnSiguiente.addEventListener("click", () => {
    if (paginaActual < totalPaginas) {
        cargarIncidencias(paginaActual + 1);
    }
});

// Actualización manual
btnRecargar.addEventListener("click", () => {
    cargarIncidencias(paginaActual);
});

// Cargar los primeros cinco registros al abrir la página
cargarIncidencias(1);
