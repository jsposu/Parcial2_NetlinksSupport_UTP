<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

// Solo para desarrollo: contraseñas únicas por ejecución, nunca dentro del SQL/repo.
$password = bin2hex(random_bytes(8));
$accounts = [
    [1, 'Admin', 'Netlinks', 'admin@netlinks.pa'],
    [2, 'Cliente', 'Prueba', 'cliente@netlinks.pa'],
    [2, 'Segundo', 'Cliente', 'otro@netlinks.pa'],
];
try {
    db()->beginTransaction();
    foreach ($accounts as [$role, $name, $surname, $email]) {
        q("INSERT INTO usuarios(rol_id,nombre,apellido,correo,password_hash,estado) VALUES(?,?,?,?,?,'ACTIVO')
           ON DUPLICATE KEY UPDATE rol_id=VALUES(rol_id),nombre=VALUES(nombre),apellido=VALUES(apellido),password_hash=VALUES(password_hash),estado='ACTIVO'",
            [$role,$name,$surname,$email,password_hash($password,PASSWORD_DEFAULT)]);
    }
    db()->commit();
    $readme = "# Credenciales locales de demostración — NO SUBIR A GITHUB\n\n"
        . "Generadas el " . date('Y-m-d H:i:s') . " (hora de Panamá). En cada ejecución cambian las contraseñas.\n\n"
        . "| Rol | Correo | Contraseña |\n| --- | --- | --- |\n"
        . "| ADMIN | admin@netlinks.pa | $password |\n"
        . "| CLIENTE | cliente@netlinks.pa | $password |\n"
        . "| CLIENTE (para prueba de propiedad) | otro@netlinks.pa | $password |\n\n"
        . "URL (XAMPP): http://localhost/Parcial2DesarrolloWeb/public/\n\n"
        . "Este archivo está excluido por .gitignore. Guárdalo solo en tu equipo.\n";
    $file = dirname(__DIR__) . '/README.local.md';
    if (file_put_contents($file, $readme, LOCK_EX) === false) throw new RuntimeException('No se pudo escribir README.local.md');
    @chmod($file, 0600);
    echo "Listo. Consulta README.local.md para los tres usuarios y sus contraseñas locales.\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, "No se crearon las cuentas. Comprueba .env y que importaste database/bookgo.sql.\n");
    exit(1);
}
