<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();
    foreach (require dirname(__DIR__) . '/app/demo-accounts.php' as [$role,$name,$surname,$email,$password]) {
        // No restablecer contraseñas ni elevar roles de cuentas ya inicializadas.
        $user = q('SELECT id,password_version FROM usuarios WHERE correo=? FOR UPDATE', [$email])->fetch();
        if ($user && (int)$user['password_version'] !== 0) continue;
        if ($user) {
            q('UPDATE usuarios SET password_hash=?,debe_cambiar_password=1,password_version=1 WHERE id=? AND password_version=0',
                [password_hash($password,PASSWORD_DEFAULT),$user['id']]);
        } else {
            q('INSERT INTO usuarios(rol_id,nombre,apellido,correo,password_hash,debe_cambiar_password,password_version) VALUES(?,?,?,?,?,1,1)',
                [$role,$name,$surname,$email,password_hash($password,PASSWORD_DEFAULT)]);
        }
    }
    $pdo->commit();
    echo "Cuentas de demo inicializadas. Consulte las claves de primer acceso en README.md. Debe cambiarlas al ingresar. Las cuentas ya inicializadas no se restablecen.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "No se pudieron inicializar las cuentas. Revise .env e importe el SQL o ejecute bin/migrate-passwords.php para una base existente.\n");
    exit(1);
}
