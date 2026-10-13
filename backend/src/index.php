<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Las respuestas de nuestra API serán JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Función para enviar respuestas HTTP
function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Obtener la ruta y método de la petición
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

// Ruta no encontrada
responder(404, [
    'error' => 'Ruta no encontrada'
]);
