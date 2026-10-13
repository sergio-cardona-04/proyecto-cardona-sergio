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
