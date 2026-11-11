<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/actions.php';

$route = $_GET['route'] ?? 'home';
if (!is_string($route)) { http_response_code(404); exit('Página no encontrada'); }
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_post();
        switch ($route) {
            case 'register': register_user(); break;
            case 'login': login_user(); break;
            case 'logout':
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$p['path'],'domain'=>$p['domain'],
                        'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']]);
                }
                session_destroy(); go('home');
            case 'admin-save': save_entity(input('entity', 20)); break;
            case 'admin-toggle': toggle_entity(input('entity', 20)); break;
            case 'book': book_slot(must_login(2)); break;
            case 'cancel': change_booking(must_login(2), false); break;
            case 'admin-status': change_booking(must_login(1), true); break;
            case 'review': add_review(must_login(2)); break;
            default: http_response_code(404); exit('Acción no encontrada');
        }
    }
    $user = me();
    $template = match ($route) {
        'home' => 'home', 'login' => 'login', 'register' => 'register',
        'service' => 'service', 'reservas' => 'reservas',
        'admin' => 'admin', 'admin-reservas' => 'admin-reservas',
        default => null,
    };
    if (!$template) { http_response_code(404); exit('Página no encontrada'); }
    if (in_array($route, ['admin','admin-reservas'], true)) $user = must_login(1);
    if ($route === 'reservas') $user = must_login(2);
    require dirname(__DIR__) . '/views/layout-top.php';
    require dirname(__DIR__) . '/views/' . $template . '.php';
    require dirname(__DIR__) . '/views/layout-bottom.php';
} catch (DomainException $ex) {
    http_response_code(422);
    $user = me();
    require dirname(__DIR__) . '/views/layout-top.php';
    echo '<main class="container narrow"><div class="panel"><h1>No se pudo completar la operación</h1><p>' . e($ex->getMessage()) . '</p><a class="button" href="' . url('home') . '">Volver al inicio</a></div></main>';
    require dirname(__DIR__) . '/views/layout-bottom.php';
} catch (PDOException $ex) {
    error_log('Netlinks database error: ' . $ex->getMessage());
    http_response_code(500);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title><p>No se pudo completar la operación. Revise la configuración de la base de datos o contacte al administrador.</p><a href="index.php">Inicio</a></html>';
}
