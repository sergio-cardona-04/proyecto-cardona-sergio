<?php

declare(strict_types=1);

// Obtener el listado de incidencias
function listarIncidencias(): never
{
    // 1. Leer parámetros de paginación
    $pagina = filter_var(
        $_GET['pagina'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );

    $limite = filter_var(
        $_GET['limite'] ?? 5,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 50]]
    );

    if ($pagina === false || $limite === false) {
        responder(400, [
            'error' => 'Parámetros de paginación inválidos'
        ]);
    }

    try {
        // 2. Conectar con Redis
        $redis = conectarRedis();

        // Incrementar contador de consultas
        $contador = $redis->incr('contador_consultas');

        // Consultar versión actual de la caché
        $version = (int) ($redis->get('incidencias:version') ?: 0);

        // Clave única por versión, página y límite
        $clave = "incidencias:listado:v{$version}:p{$pagina}:l{$limite}";

        // 3. Buscar resultado en Redis
        $cache = $redis->get($clave);

        if ($cache !== false) {
            $respuesta = json_decode(
                $cache,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $respuesta['origen'] = 'redis';
            $respuesta['contador_consultas'] = $contador;

            responder(200, $respuesta);
        }

        // 4. Si no existe caché, consultar MariaDB
        $db = conectarMariaDB();

        // Contar registros totales
        $total = (int) $db
            ->query('SELECT COUNT(*) FROM incidencias')
            ->fetchColumn();

        // Calcular desde qué registro consultar
        $offset = ($pagina - 1) * $limite;

        // Consulta SQL paginada
        $sql = '
            SELECT
                id,
                titulo,
                descripcion,
                categoria,
                estado,
                fecha_creacion
            FROM incidencias
            ORDER BY id DESC
            LIMIT :limite OFFSET :offset
        ';

        $consulta = $db->prepare($sql);

        $consulta->bindValue(
            ':limite',
            $limite,
            PDO::PARAM_INT
        );

        $consulta->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $consulta->execute();

        $incidencias = $consulta->fetchAll();

        // 5. Preparar respuesta con paginación
        $respuesta = [
            'datos' => $incidencias,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'limite' => $limite,
                'total_registros' => $total,
                'total_paginas' => (int) ceil($total / $limite)
            ]
        ];

        // 6. Guardar resultado en Redis por 60 segundos
        $redis->setex(
            $clave,
            60,
            json_encode(
                $respuesta,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        $respuesta['origen'] = 'mariadb';
        $respuesta['contador_consultas'] = $contador;

        responder(200, $respuesta);

    } catch (Throwable $error) {

        error_log($error->getMessage());

        responder(503, [
            'error' => 'No fue posible consultar las incidencias'
        ]);
    }
}


/**
 * Crear una nueva incidencia
 */
function crearIncidencia(): never
{
    // 1. Comprobar el tipo de contenido
    $tipo = strtolower(trim(explode(
        ';',
        $_SERVER['CONTENT_TYPE'] ?? ''
    )[0]));

    if ($tipo !== 'application/json') {
        responder(415, [
            'error' => 'Se requiere Content-Type application/json'
        ]);
    }

    // 2. Leer y decodificar el JSON recibido
    try {
        $datos = json_decode(
            file_get_contents('php://input'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $error) {
        responder(400, [
            'error' => 'El JSON enviado no es válido'
        ]);
    }

    if (!$datos instanceof stdClass) {
        responder(400, [
            'error' => 'Se esperaba un objeto JSON'
        ]);
    }

    // 3. Obtener los campos
    $titulo = $datos->titulo ?? null;
    $descripcion = $datos->descripcion ?? null;
    $categoria = $datos->categoria ?? null;

    // 4. Validar tipos de datos
    if (
        !is_string($titulo) ||
        !is_string($descripcion) ||
        !is_string($categoria)
    ) {
        responder(422, [
            'error' => 'Los campos titulo, descripcion y categoria son obligatorios y deben ser texto'
        ]);
    }

    // 5. Eliminar espacios innecesarios
    $titulo = trim($titulo);
    $descripcion = trim($descripcion);
    $categoria = trim($categoria);

    // 6. Validar longitud del título
    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
        responder(422, [
            'error' => 'El título debe tener entre 3 y 120 caracteres'
        ]);
    }

    // 7. Validar descripción
    if (
        mb_strlen($descripcion) < 10 ||
        mb_strlen($descripcion) > 2000
    ) {
        responder(422, [
            'error' => 'La descripción debe tener entre 10 y 2000 caracteres'
        ]);
    }

    // 8. Validar categorías permitidas
    $categoriasValidas = [
        'hardware',
        'software',
        'red',
        'otro'
    ];

    if (!in_array($categoria, $categoriasValidas, true)) {
        responder(422, [
            'error' => 'Categoría no válida'
        ]);
    }

    try {
        // 9. Conectar con Redis y MariaDB
        $redis = conectarRedis();
        $db = conectarMariaDB();

        // 10. Insertar incidencia
        $sql = '
            INSERT INTO incidencias
                (titulo, descripcion, categoria)
            VALUES
                (:titulo, :descripcion, :categoria)
        ';

        $consulta = $db->prepare($sql);

        $consulta->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':categoria' => $categoria
        ]);

        // 11. Obtener el ID generado
        $id = (int) $db->lastInsertId();

        // 12. Consultar el registro creado
        $consulta = $db->prepare(
            'SELECT * FROM incidencias WHERE id = :id'
        );

        $consulta->execute([':id' => $id]);

        $incidencia = $consulta->fetch();

        // 13. Invalidar caché de listados
        $redis->incr('incidencias:version');

        // 14. Responder con HTTP 201
        responder(201, [
            'mensaje' => 'Incidencia creada correctamente',
            'incidencia' => $incidencia
        ]);

    } catch (Throwable $error) {

        error_log($error->getMessage());

        responder(503, [
            'error' => 'No fue posible completar la creación de la incidencia'
        ]);
    }
}

/**
 * Actualizar una incidencia existente
 */
function actualizarIncidencia(int $id): never
{
    // 1. Verificar que recibimos JSON
    $tipo = strtolower(trim(explode(
        ';',
        $_SERVER['CONTENT_TYPE'] ?? ''
    )[0]));

    if ($tipo !== 'application/json') {
        responder(415, [
            'error' => 'Se requiere Content-Type application/json'
        ]);
    }

    // 2. Leer y decodificar los datos enviados
    try {
        $datos = json_decode(
            file_get_contents('php://input'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $error) {
        responder(400, [
            'error' => 'El JSON enviado no es válido'
        ]);
    }

    if (!$datos instanceof stdClass) {
        responder(400, [
            'error' => 'Se esperaba un objeto JSON'
        ]);
    }

    // 3. Convertir el objeto en un arreglo de campos
    $campos = get_object_vars($datos);

    if (empty($campos)) {
        responder(422, [
            'error' => 'Debes enviar al menos un campo para actualizar'
        ]);
    }

    // 4. Definir los campos permitidos
    $camposPermitidos = [
        'titulo',
        'descripcion',
        'categoria',
        'estado'
    ];

    $categoriasValidas = [
        'hardware',
        'software',
        'red',
        'otro'
    ];

    $estadosValidos = [
        'pendiente',
        'en_proceso',
        'resuelta'
    ];

    // 5. Validar todos los campos recibidos
    foreach ($campos as $nombre => $valor) {

        if (!in_array($nombre, $camposPermitidos, true)) {
            responder(422, [
                'error' => "El campo $nombre no se puede modificar"
            ]);
        }

        if (!is_string($valor)) {
            responder(422, [
                'error' => "El campo $nombre debe ser texto"
            ]);
        }

        $valor = trim($valor);

        if ($nombre === 'titulo') {
            if (mb_strlen($valor) < 3 || mb_strlen($valor) > 120) {
                responder(422, [
                    'error' => 'El título debe tener entre 3 y 120 caracteres'
                ]);
            }
        }

        if ($nombre === 'descripcion') {
            if (mb_strlen($valor) < 10 || mb_strlen($valor) > 2000) {
                responder(422, [
                    'error' => 'La descripción debe tener entre 10 y 2000 caracteres'
                ]);
            }
        }

        if ($nombre === 'categoria') {
            if (!in_array($valor, $categoriasValidas, true)) {
                responder(422, [
                    'error' => 'Categoría no válida'
                ]);
            }
        }

        if ($nombre === 'estado') {
            if (!in_array($valor, $estadosValidos, true)) {
                responder(422, [
                    'error' => 'Estado no válido'
                ]);
            }
        }

        $campos[$nombre] = $valor;
    }

    try {
        // 6. Conectar con MariaDB y Redis
        $db = conectarMariaDB();
        $redis = conectarRedis();

        // 7. Verificar si la incidencia existe
        $consulta = $db->prepare(
            'SELECT id FROM incidencias WHERE id = :id'
        );

        $consulta->execute([':id' => $id]);

        if (!$consulta->fetch()) {
            responder(404, [
                'error' => 'La incidencia no existe'
            ]);
        }

        // 8. Construir dinámicamente los campos a actualizar
        $asignaciones = [];
        $parametros = [':id' => $id];

        foreach ($campos as $nombre => $valor) {
            $asignaciones[] = "$nombre = :$nombre";
            $parametros[":$nombre"] = $valor;
        }

        // 9. Ejecutar la actualización
        $sql = 'UPDATE incidencias SET ' .
            implode(', ', $asignaciones) .
            ' WHERE id = :id';

        $consulta = $db->prepare($sql);
        $consulta->execute($parametros);

        // 10. Invalidar la caché anterior
        $redis->incr('incidencias:version');

        // 11. Consultar la incidencia actualizada
        $consulta = $db->prepare(
            'SELECT * FROM incidencias WHERE id = :id'
        );

        $consulta->execute([':id' => $id]);

        $incidencia = $consulta->fetch();

        // 12. Devolver respuesta
        responder(200, [
            'mensaje' => 'Incidencia actualizada correctamente',
            'incidencia' => $incidencia
        ]);

    } catch (Throwable $error) {

        error_log($error->getMessage());

        responder(503, [
            'error' => 'No fue posible completar la actualización'
        ]);
    }
}
