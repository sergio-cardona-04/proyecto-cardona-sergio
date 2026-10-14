<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/correo.php';
require __DIR__ . '/incidencias.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$ruta = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

// Endpoint de salud
if ($ruta === '/health' && $metodo === 'GET') {

    try {
        // Comprobar MariaDB
        $db = conectarMariaDB();
        $db->query('SELECT 1');

        // Comprobar Redis
        $redis = conectarRedis();
        $redis->ping();

        responder(200, [
            'estado' => 'ok',
            'mariadb' => 'ok',
            'redis' => 'ok'
        ]);

    } catch (Throwable $error) {

        error_log($error->getMessage());

        responder(503, [
            'estado' => 'error',
            'mensaje' => 'Dependencia no disponible'
        ]);
    }
}

if ($ruta === '/incidencias' && $metodo === 'GET') {
    listarIncidencias();
}

if ($ruta === '/incidencias' && $metodo === 'POST') {
    crearIncidencia();
}

if (
    $metodo === 'PUT' &&
    preg_match('#^/incidencias/([1-9][0-9]*)$#', $ruta, $coincidencias)
) {
    $id = filter_var(
        $coincidencias[1],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 4294967295]]
    );

    if ($id === false) {
        responder(400, [
            'error' => 'ID de incidencia inválido'
        ]);
    }

    actualizarIncidencia($id);
}

if (
    $metodo === 'DELETE' &&
    preg_match('#^/incidencias/([1-9][0-9]*)$#', $ruta, $coincidencias)
) {
    $id = filter_var(
        $coincidencias[1],
        FILTER_VALIDATE_INT,
        ['options' => [
            'min_range' => 1,
            'max_range' => 4294967295
        ]]
    );

    if ($id === false) {
        responder(400, [
            'error' => 'ID de incidencia inválido'
        ]);
    }

    eliminarIncidencia($id);
}

responder(404, [
    'error' => 'Ruta no encontrada'
]);
