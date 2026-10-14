<?php

declare(strict_types=1);

// Obtener una variable de entorno obligatoria
function entorno(string $nombre): string
{
    $valor = getenv($nombre);

    if ($valor === false || $valor === '') {
        throw new RuntimeException(
            "Variable de entorno faltante: $nombre"
        );
    }

    return $valor;
}

function conectarMariaDB(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        entorno('DB_HOST'),
        (int) entorno('DB_PORT'),
        entorno('DB_NAME')
    );

    return new PDO(
        $dsn,
        entorno('DB_USER'),
        entorno('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ]
    );
}

function conectarRedis(): Redis
{
    $redis = new Redis();

    $redis->connect(
        entorno('REDIS_HOST'),
        (int) entorno('REDIS_PORT'),
        3.0
    );

    return $redis;
}
