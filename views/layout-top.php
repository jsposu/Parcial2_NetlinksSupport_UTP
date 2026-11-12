<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Netlinks: reservas de soporte técnico sin complicaciones.">
<title>Netlinks · Book&Go Lite</title>
<script src="assets/theme-init.js"></script>
<link rel="stylesheet" href="assets/style.css"></head><body>
<header class="site-header"><div class="container nav"><a class="brand" href="<?= url('home') ?>"><span class="brand-icon" aria-hidden="true">✳</span> Net<span>links</span></a>
<div class="nav-right"><nav aria-label="Navegación principal"><a href="<?= url('home') ?>">Servicios</a>
<?php if ($user && (int)$user['debe_cambiar_password'] === 1): ?>
<a href="<?= url('change-password') ?>">Cambiar contraseña</a>
<?php elseif ($user && (int)$user['rol_id'] === 1): ?>
<a href="<?= url('admin') ?>">Administrar</a><a href="<?= url('admin-reservas') ?>">Reservas</a><a href="<?= url('admin-centers') ?>">Admin Centers</a>
<?php elseif ($user): ?><a href="<?= url('reservas') ?>">Mis reservas</a><?php endif; ?>
<?php if ($user): ?><span class="greeting">Hola, <?= e($user['nombre']) ?></span><form class="inline" method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="nav-button" type="submit">Salir</button></form>
<?php else: ?><a href="<?= url('login') ?>">Ingresar</a><a class="nav-button" href="<?= url('register') ?>">Crear cuenta</a><?php endif; ?>
</nav><button class="theme-toggle" id="theme-toggle" type="button" role="switch" aria-checked="false" aria-label="Activar modo oscuro" title="Cambiar a modo oscuro"><span class="theme-toggle-track" aria-hidden="true"><span class="theme-toggle-thumb"><svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/></svg><svg class="icon-moon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg></span></span></button></div></div></header>
<?php if (isset($_SESSION['flash'])): [$message,$type] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<div class="container flash-messages"><p class="alert <?= e($type) ?>" role="status"><?= e($message) ?></p></div>
<?php endif; ?>
