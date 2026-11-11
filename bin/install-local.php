<?php
declare(strict_types=1);
// Solo CLI. Recibe la conexión por stdin, no por argumentos ni archivos de respaldo.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
function answer(array $data, int $code = 0): never {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit($code);
}
try {
    if (PHP_VERSION_ID < 80200 || !extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) {
        answer(['ok'=>false,'message'=>'XAMPP necesita PHP 8.2+, pdo_mysql y mbstring.'], 1);
    }
    $config = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($config)) throw new RuntimeException();
    foreach (['DB_HOST','DB_PORT','DB_USER','DB_PASSWORD','DB_NAME','xampp'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || strpbrk($config[$key], "\r\n\0") !== false) throw new RuntimeException();
    }
    $database = $config['DB_NAME'];
    if (!in_array(strtolower($config['DB_HOST']), ['localhost','127.0.0.1','::1'], true)
        || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $database)
        || in_array(strtolower($database), ['mysql','sys','phpmyadmin','information_schema','performance_schema'], true)
        || !ctype_digit($config['DB_PORT']) || (int)$config['DB_PORT'] < 1 || (int)$config['DB_PORT'] > 65535) throw new RuntimeException();
    $pdo = new PDO('mysql:host='.$config['DB_HOST'].';port='.$config['DB_PORT'].';charset=utf8mb4',
        $config['DB_USER'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $normalize = static function (string $path): string {
        $path = realpath($path) ?: $path;
        $path = rtrim(str_replace('\\', '/', $path), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    };
    $base = $normalize((string)$pdo->query('SELECT @@basedir')->fetchColumn());
    if (!in_array($base, [$normalize($config['xampp']),$normalize($config['xampp'].'/mysql')], true)) {
        answer(['ok'=>false,'message'=>'Ese puerto pertenece a otro MySQL. Usa el servidor y puerto de tu XAMPP.'], 1);
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $statement->execute([$database]);
    $exists = (bool)$statement->fetchColumn();
    if ($exists) {
        $statement = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
        $statement->execute([$database]);
        $required = ['roles','usuarios','categorias','servicios','horarios','reservas','resenas','intentos_login'];
        if (array_diff($required, $statement->fetchAll(PDO::FETCH_COLUMN))) {
            answer(['ok'=>false,'message'=>'La base existente no tiene el esquema de Netlinks. No se modificó. Revisa el nombre en .env.'], 1);
        }
        answer(['ok'=>true,'state'=>'existing']);
    }
    $sql = file_get_contents(dirname(__DIR__).'/database/bookgo.sql');
    if ($sql === false) throw new RuntimeException();
    $sql = preg_replace('/\bbookgo_lite\b/', $database, $sql);
    $pdo->exec("SET time_zone='-05:00'");
    // CREATE sin IF NOT EXISTS protege una base que aparezca concurrentemente.
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec($sql);
    answer(['ok'=>true,'state'=>'new']);
} catch (PDOException $error) {
    $number = $error->errorInfo[1] ?? 0;
    if (in_array((int)$number, [1044,1045], true)) {
        answer(['ok'=>false,'auth'=>true], 1);
    }
    answer(['ok'=>false,'message'=>'No se pudo preparar la base. Inicia MySQL en XAMPP y comprueba el puerto y los permisos. Si se interrumpió una importación, revisa la base en phpMyAdmin; no se eliminó nada.'], 1);
} catch (Throwable $error) {
    answer(['ok'=>false,'message'=>'Configuración o script SQL no válido. Revisa .env y descarga el proyecto completo.'], 1);
}
