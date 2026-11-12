<?php
declare(strict_types=1);

date_default_timezone_set('America/Panama');
// El directorio público debe ser la única raíz web. No cargar archivos .env por HTTP.
$env = dirname(__DIR__) . '/.env';
if (is_file($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (preg_match('/^[A-Z_]+$/', $key)) putenv($key . '=' . trim($value));
    }
}

function db(): PDO {
    static $pdo;
    if (!isset($pdo)) {
        $pdo = new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'bookgo_lite') . ';charset=utf8mb4',
            getenv('DB_USER') ?: 'bookgo', getenv('DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}
function q(string $sql, array $args = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $route = 'home', array $params = []): string {
    return e($_SERVER['SCRIPT_NAME'] ?? '/index.php') . '?' . http_build_query(['route' => $route] + $params);
}
function go(string $route = 'home', array $params = []): never {
    header('Location: ' . html_entity_decode(url($route, $params), ENT_QUOTES, 'UTF-8'), true, 303);
    exit;
}
function notice(string $message, string $type = 'ok'): void { $_SESSION['flash'] = [$message, $type]; }
function token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(token()) . '">'; }
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(token(), $_POST['csrf'])) {
        http_response_code(403); exit('Solicitud rechazada: token CSRF inválido.');
    }
}
function input(string $name, int $max = 500): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) throw new DomainException('Datos inválidos.');
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') > $max) throw new DomainException('El campo ' . $name . ' es demasiado largo.');
    return $value;
}
function positive(mixed $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new DomainException('Identificador inválido.');
    return $id;
}
function me(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $user = q('SELECT u.id,u.nombre,u.apellido,u.rol_id,u.debe_cambiar_password,u.password_version FROM usuarios u WHERE u.id=? AND u.estado=\'ACTIVO\'', [$_SESSION['uid']])->fetch();
    if (!$user || (int)$user['password_version'] !== ($_SESSION['password_version'] ?? -1)) {
        unset($_SESSION['uid'], $_SESSION['password_version']);
        return null;
    }
    return $user;
}
function must_login(?int $role = null): array {
    $user = me();
    if (!$user) go('login');
    if ((int)$user['debe_cambiar_password'] === 1) go('change-password');
    if ($role !== null && (int)$user['rol_id'] !== $role) {
        http_response_code(403); exit('Acceso denegado.');
    }
    return $user;
}
function valid_email(string $email): string {
    $email = mb_strtolower(trim($email));
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Correo inválido.');
    return $email;
}
// Política única de contraseñas para todos los roles y claves temporales.
const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_MAX_BYTES = 72;
function password_policy_errors(string $password): array {
    $errors = [];
    if (mb_strlen($password, 'UTF-8') < PASSWORD_MIN_LENGTH) $errors[] = 'mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres';
    if (strlen($password) > PASSWORD_MAX_BYTES) $errors[] = 'máximo ' . PASSWORD_MAX_BYTES . ' bytes';
    if (!preg_match('/\p{Lu}/u', $password)) $errors[] = 'al menos una letra mayúscula';
    if (!preg_match('/\p{Ll}/u', $password)) $errors[] = 'al menos una letra minúscula';
    if (!preg_match('/\p{N}/u', $password)) $errors[] = 'al menos un número';
    if (!preg_match('/[^\p{L}\p{N}\s]/u', $password)) $errors[] = 'al menos un carácter especial';
    return $errors;
}
function assert_password_policy(string $password, string $label = 'La contraseña'): void {
    $errors = password_policy_errors($password);
    if ($errors) throw new DomainException($label . ' no cumple la política de seguridad: ' . implode('; ', $errors) . '.');
}
function valid_slot(string $date, string $start, string $end): void {
    $a = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $end);
    if (!$a || !$b || $a->format('Y-m-d H:i') !== "$date $start" || $b->format('Y-m-d H:i') !== "$date $end"
        || $a <= new DateTimeImmutable() || $b <= $a) throw new DomainException('Indique un horario futuro con hora final posterior a la inicial.');
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax', 'path' => '/']);
    session_start();
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
