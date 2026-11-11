<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Netlinks: reservas de soporte técnico sin complicaciones.">
<title>Netlinks · Book&Go Lite</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="site-header"><div class="container nav"><a class="brand" href="<?= url('home') ?>"><span class="brand-icon" aria-hidden="true">✳</span> Net<span>links</span></a>
<nav aria-label="Navegación principal"><a href="<?= url('home') ?>">Servicios</a>
<?php if ($user && (int)$user['debe_cambiar_password'] === 1): ?>
<a href="<?= url('change-password') ?>">Cambiar contraseña</a>
<?php elseif ($user && (int)$user['rol_id'] === 1): ?>
<a href="<?= url('admin') ?>">Administrar</a><a href="<?= url('admin-reservas') ?>">Reservas</a>
<?php elseif ($user): ?><a href="<?= url('reservas') ?>">Mis reservas</a><?php endif; ?>
<?php if ($user): ?><span class="greeting">Hola, <?= e($user['nombre']) ?></span><form class="inline" method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="nav-button" type="submit">Salir</button></form>
<?php else: ?><a href="<?= url('login') ?>">Ingresar</a><a class="nav-button" href="<?= url('register') ?>">Crear cuenta</a><?php endif; ?>
</nav></div></header>
<?php if (isset($_SESSION['flash'])): [$message,$type] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<div class="container"><p class="alert <?= e($type) ?>" role="status"><?= e($message) ?></p></div>
<?php endif; ?>
