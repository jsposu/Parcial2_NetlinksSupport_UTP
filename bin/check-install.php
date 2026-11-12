<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    foreach (['roles','usuarios','categorias','servicios','horarios','reservas','resenas','intentos_login'] as $table) {
        q('SELECT 1 FROM ' . $table . ' LIMIT 1');
    }
    q('SELECT debe_cambiar_password,password_version,intentos_fallidos,bloqueado_hasta FROM usuarios LIMIT 1');
    echo "Conexión y esquema de Netlinks comprobados.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo comprobar la base de Netlinks. Revise configuración y migraciones.\n");
    exit(1);
}
