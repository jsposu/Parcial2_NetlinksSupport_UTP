<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    // Compatible con XAMPP/MariaDB y MySQL. DDL fuera de transacciones.
    foreach (['debe_cambiar_password' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
              'password_version' => 'INT UNSIGNED NOT NULL DEFAULT 0',
              'intentos_fallidos' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
              'bloqueado_hasta' => 'DATETIME NULL DEFAULT NULL'] as $column => $definition) {
        $exists = q('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', ['usuarios', $column])->fetchColumn();
        if (!$exists) db()->exec('ALTER TABLE usuarios ADD COLUMN ' . $column . ' ' . $definition);
    }
    echo "Esquema actualizado sin borrar usuarios ni reservas (incluye el estado de bloqueo por cuenta). Ejecute bin/create-demo.php para inicializar las cuentas de demo.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo migrar. Revise .env, la base importada y los permisos ALTER del usuario de MySQL.\n");
    exit(1);
}
