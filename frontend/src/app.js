"use strict";

// =======================================
// 1. CONFIGURACIÓN Y ELEMENTOS DEL HTML
// =======================================

const API_URL = "/api/incidencias";
const LIMITE = 5;

// Tabla e indicadores
const tabla = document.getElementById("tabla-incidencias");
const mensaje = document.getElementById("mensaje");
const total = document.getElementById("total");
const origen = document.getElementById("origen");
const contador = document.getElementById("contador");

// Paginación
const textoPagina = document.getElementById("pagina");
const btnAnterior = document.getElementById("anterior");
const btnSiguiente = document.getElementById("siguiente");
const btnRecargar = document.getElementById("recargar");

// Botón para crear
const btnNueva = document.getElementById("nueva-incidencia");

// Formulario
const modal = document.getElementById("modal-incidencia");
const formulario = document.getElementById("form-incidencia");
const tituloModal = document.getElementById("titulo-modal");

const campoTitulo = document.getElementById("campo-titulo");
const campoDescripcion = document.getElementById("campo-descripcion");
const campoCategoria = document.getElementById("campo-categoria");
const campoEstado = document.getElementById("campo-estado");
const grupoEstado = document.getElementById("grupo-estado");

const errorFormulario = document.getElementById("error-formulario");
const btnGuardar = document.getElementById("guardar-incidencia");
const btnCancelar = document.getElementById("cancelar-formulario");

// Estado de la aplicación
let paginaActual = 1;
let totalPaginas = 1;
let cargando = false;
let procesando = false;
let idEditando = null;
let registrosActuales = [];


// =======================================
// 2. MENSAJES Y CONTROLES
// =======================================

function mostrarMensaje(texto, tipo = "normal") {
    mensaje.textContent = texto;

    const clases = {
        normal: "mt-5 text-sm text-slate-500",
        error: "mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700",
        exito: "mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    };

    mensaje.className = clases[tipo] ?? clases.normal;
}

function actualizarBotones() {
    const ocupado = cargando || procesando;

    btnAnterior.disabled = ocupado || paginaActual <= 1;
    btnSiguiente.disabled = ocupado || paginaActual >= totalPaginas;
    btnRecargar.disabled = ocupado;
    btnNueva.disabled = ocupado;
}

function mostrarErrorFormulario(texto) {
    errorFormulario.textContent = texto;
    errorFormulario.classList.remove("hidden");
}

function limpiarErrorFormulario() {
    errorFormulario.textContent = "";
    errorFormulario.classList.add("hidden");
}


// =======================================
// 3. FUNCIÓN GENERAL PARA CONSUMIR LA API
// =======================================

async function peticionApi(url, opciones = {}) {
    const respuesta = await fetch(url, {
        cache: "no-store",
        ...opciones
    });

    let datos;

    try {
        datos = await respuesta.json();
    } catch {
        throw new Error(
            "El servidor devolvió una respuesta inesperada."
        );
    }

    if (!respuesta.ok) {
        throw new Error(
            datos.error || `Error HTTP ${respuesta.status}`
        );
    }

    return datos;
}


// =======================================
// 4. MOSTRAR LAS INCIDENCIAS EN LA TABLA
// =======================================

const clasesEstado = {
    pendiente:
        "bg-amber-100 text-amber-800 ring-amber-200",

    en_proceso:
        "bg-blue-100 text-blue-800 ring-blue-200",

    resuelta:
        "bg-emerald-100 text-emerald-800 ring-emerald-200"
};

function mostrarIncidencias(incidencias) {
    tabla.replaceChildren();

    if (incidencias.length === 0) {
        const fila = document.createElement("tr");
        const celda = document.createElement("td");

        celda.colSpan = 7;
        celda.className =
            "px-5 py-10 text-center text-sm text-slate-500";

        celda.textContent = "No hay incidencias registradas.";

        fila.appendChild(celda);
        tabla.appendChild(fila);
        return;
    }

    for (const incidencia of incidencias) {
        const fila = document.createElement("tr");
        fila.className = "transition-colors hover:bg-slate-50";

        // Crear una celda de texto
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

        // Estado con colores Tailwind
        const celdaEstado = document.createElement("td");
        celdaEstado.className = "px-5 py-4 align-top";

        const etiqueta = document.createElement("span");

        const colorEstado =
            clasesEstado[incidencia.estado] ??
            "bg-slate-100 text-slate-700 ring-slate-200";

        etiqueta.className =
            `inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${colorEstado}`;

        etiqueta.textContent =
            incidencia.estado.replaceAll("_", " ");

        celdaEstado.appendChild(etiqueta);
        fila.appendChild(celdaEstado);

        agregarCelda(incidencia.fecha_creacion);

        // Columna de acciones
        const celdaAcciones = document.createElement("td");
        celdaAcciones.className = "px-5 py-4 align-top";

        const contenedor = document.createElement("div");
        contenedor.className = "flex flex-wrap gap-2";

        // Botón Editar
        const btnEditar = document.createElement("button");
        btnEditar.type = "button";
        btnEditar.textContent = "Editar";

        btnEditar.className =
            "rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100";

        btnEditar.addEventListener("click", () => {
            abrirFormularioEditar(incidencia);
        });

        // Botón Eliminar
        const btnEliminar = document.createElement("button");
        btnEliminar.type = "button";
        btnEliminar.textContent = "Eliminar";

        btnEliminar.className =
            "rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100";

        btnEliminar.addEventListener("click", () => {
            eliminarIncidencia(incidencia);
        });

        contenedor.append(btnEditar, btnEliminar);
        celdaAcciones.appendChild(contenedor);
        fila.appendChild(celdaAcciones);

        tabla.appendChild(fila);
    }
}


// =======================================
// 5. GET: CONSULTAR Y PAGINAR
// =======================================

async function cargarIncidencias(pagina = 1, aviso = "") {
    if (cargando) return;

    cargando = true;
    actualizarBotones();
    mostrarMensaje("Consultando incidencias...");

    try {
        const datos = await peticionApi(
            `${API_URL}?pagina=${pagina}&limite=${LIMITE}`
        );

        if (!Array.isArray(datos.datos) || !datos.paginacion) {
            throw new Error(
                "La respuesta del servidor no tiene el formato esperado."
            );
        }

        paginaActual = Number(
            datos.paginacion.pagina_actual
        );

        totalPaginas = Math.max(
            1,
            Number(datos.paginacion.total_paginas)
        );

        registrosActuales = datos.datos;

        // Actualizar indicadores
        total.textContent = datos.paginacion.total_registros;
        origen.textContent = datos.origen;
        contador.textContent = datos.contador_consultas;

        textoPagina.textContent =
            `Página ${paginaActual} de ${totalPaginas}`;

        mostrarIncidencias(datos.datos);

        if (aviso) {
            mostrarMensaje(aviso, "exito");
        } else {
            mostrarMensaje(
                `Mostrando ${datos.datos.length} incidencia(s).`
            );
        }

    } catch (error) {
        mostrarMensaje(error.message, "error");

    } finally {
        cargando = false;
        actualizarBotones();
    }
}


// =======================================
// 6. ABRIR FORMULARIO DE CREACIÓN
// =======================================

function abrirFormularioCrear() {
    if (cargando || procesando) return;

    idEditando = null;

    formulario.reset();
    limpiarErrorFormulario();

    tituloModal.textContent = "Nueva incidencia";
    btnGuardar.textContent = "Crear incidencia";

    // Al crear, el backend asigna pendiente
    grupoEstado.classList.add("hidden");
    campoEstado.value = "pendiente";

    modal.showModal();
}


// =======================================
// 7. ABRIR FORMULARIO DE EDICIÓN
// =======================================

function abrirFormularioEditar(incidencia) {
    if (cargando || procesando) return;

    idEditando = incidencia.id;

    formulario.reset();
    limpiarErrorFormulario();

    tituloModal.textContent =
        `Editar incidencia #${incidencia.id}`;

    btnGuardar.textContent = "Guardar cambios";

    // Llenar los campos con los datos actuales
    campoTitulo.value = incidencia.titulo;
    campoDescripcion.value = incidencia.descripcion;
    campoCategoria.value = incidencia.categoria;
    campoEstado.value = incidencia.estado;

    // En edición sí permitimos cambiar el estado
    grupoEstado.classList.remove("hidden");

    modal.showModal();
}


// =======================================
// 8. POST Y PUT: GUARDAR EL FORMULARIO
// =======================================

async function guardarFormulario(evento) {
    evento.preventDefault();

    if (procesando || cargando) return;

    // Comprobar validaciones HTML
    if (!formulario.reportValidity()) return;

    const titulo = campoTitulo.value.trim();
    const descripcion = campoDescripcion.value.trim();
    const categoria = campoCategoria.value;

    // Complementar las validaciones HTML
    if (titulo.length < 3 || descripcion.length < 10) {
        mostrarErrorFormulario(
            "El título debe tener al menos 3 caracteres y la descripción al menos 10."
        );
        return;
    }

    // Datos que enviaremos a la API
    const datosEnviar = {
        titulo,
        descripcion,
        categoria
    };

    const editando = idEditando !== null;

    if (editando) {
        datosEnviar.estado = campoEstado.value;
    }

    const url = editando
        ? `${API_URL}/${idEditando}`
        : API_URL;

    const metodo = editando ? "PUT" : "POST";

    procesando = true;
    actualizarBotones();

    btnGuardar.disabled = true;
    btnCancelar.disabled = true;
    btnGuardar.textContent = "Guardando...";

    limpiarErrorFormulario();

    try {
        const resultado = await peticionApi(url, {
            method: metodo,

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify(datosEnviar)
        });

        // La operación terminó correctamente
        modal.close();

        let aviso;
        let paginaDestino;

        if (editando) {
            aviso = "Incidencia actualizada correctamente.";
            paginaDestino = paginaActual;

        } else {
            aviso = "Incidencia creada correctamente.";

            if (resultado.notificacion === "no_enviada") {
                aviso += " No se pudo enviar el correo.";
            } else if (resultado.notificacion === "enviada") {
                aviso += " Notificación enviada a Mailpit.";
            }

            // El listado está ordenado por ID descendente
            paginaDestino = 1;
        }

        await cargarIncidencias(paginaDestino, aviso);

    } catch (error) {
        mostrarErrorFormulario(error.message);

    } finally {
        procesando = false;

        btnGuardar.disabled = false;
        btnCancelar.disabled = false;

        btnGuardar.textContent = editando
            ? "Guardar cambios"
            : "Crear incidencia";

        actualizarBotones();
    }
}


// =======================================
// 9. DELETE: ELIMINAR UNA INCIDENCIA
// =======================================

async function eliminarIncidencia(incidencia) {
    if (cargando || procesando) return;

    const confirmado = window.confirm(
        `¿Seguro que deseas eliminar la incidencia #${incidencia.id}?\n\n` +
        `${incidencia.titulo}\n\n` +
        "Esta acción no se puede deshacer."
    );

    if (!confirmado) return;

    procesando = true;
    actualizarBotones();

    try {
        await peticionApi(
            `${API_URL}/${incidencia.id}`,
            {
                method: "DELETE"
            }
        );

        // Si era el único registro en esta página,
        // volver a la anterior cuando exista.
        const paginaDestino =
            registrosActuales.length === 1 && paginaActual > 1
                ? paginaActual - 1
                : paginaActual;

        await cargarIncidencias(
            paginaDestino,
            "Incidencia eliminada correctamente."
        );

    } catch (error) {
        mostrarMensaje(error.message, "error");

    } finally {
        procesando = false;
        actualizarBotones();
    }
}


// =======================================
// 10. EVENTOS DE LA INTERFAZ
// =======================================

// Abrir formulario de creación
btnNueva.addEventListener("click", abrirFormularioCrear);

// Guardar creación o edición
formulario.addEventListener("submit", guardarFormulario);

// Cerrar el formulario sin guardar
btnCancelar.addEventListener("click", () => {
    if (!procesando) {
        modal.close();
    }
});

// Evitar cerrar mediante Escape durante el guardado
modal.addEventListener("cancel", (evento) => {
    if (procesando) {
        evento.preventDefault();
    }
});

// Paginación
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

// Carga inicial
cargarIncidencias(1);
