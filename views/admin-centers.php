<?php
$accounts = q("SELECT u.id,u.nombre,u.apellido,u.correo,u.estado,u.debe_cambiar_password,
        u.intentos_fallidos,u.bloqueado_hasta,u.creado_en,r.nombre AS rol
    FROM usuarios u JOIN roles r ON r.id=u.rol_id
    ORDER BY (u.rol_id=1) DESC, u.nombre, u.apellido")->fetchAll();
?>
<main class="container section">
<span class="eyebrow">ADMIN CENTERS</span><h1>Cuentas y accesos</h1>
<p class="lead">Crea cuentas, ajusta permisos, restablece contraseñas y desbloquea accesos.</p>
<div class="admin-grid">
<section class="panel">
<h2>Nueva cuenta</h2>
<form method="post" action="<?= url('admin-user-create') ?>"><?= csrf_field() ?>
<div class="form-grid">
<label>Nombre<input required name="nombre" maxlength="80" autocomplete="off"></label>
<label>Apellido<input required name="apellido" maxlength="80" autocomplete="off"></label>
</div>
<label>Correo electrónico<input required type="email" name="correo" maxlength="190" autocomplete="off"></label>
<label>Rol<select name="rol_id"><option value="2">Cliente</option><option value="1">Administrador</option></select></label>
<div class="form-grid">
<label>Contraseña temporal<input required type="password" name="password" minlength="12" autocomplete="new-password"></label>
<label>Repetir contraseña<input required type="password" name="password_confirm" minlength="12" autocomplete="new-password"></label>
</div>
<p class="small">Mínimo 12 caracteres con mayúscula, minúscula, número y carácter especial. La persona deberá cambiar esta contraseña en su primer ingreso.</p>
<button class="button full" type="submit">Crear cuenta</button>
</form>
</section>
<section class="admin-list">
<h2>Cuentas <span class="count"><?= count($accounts) ?></span></h2>
<?php foreach ($accounts as $account):
    $isSelf = (int)$account['id'] === (int)$user['id'];
    $attempts = (int)$account['intentos_fallidos'];
    $blocked = $account['bloqueado_hasta'] !== null && strtotime($account['bloqueado_hasta']) > time();
    $statusText = $blocked
        ? 'Bloqueado hasta ' . date('d/m/Y H:i', strtotime($account['bloqueado_hasta']))
        : ($account['estado'] === 'ACTIVO' ? 'Sin bloqueo' : 'Cuenta deshabilitada');
?>
<article class="user-card">
<div class="user-head">
<div>
<span class="pill <?= $account['rol'] === 'ADMIN' ? '' : 'muted' ?>"><?= e($account['rol']) ?></span>
<span class="pill <?= $account['estado'] === 'ACTIVO' ? 'status-realizada' : 'status-cancelada' ?>"><?= e($account['estado']) ?></span>
<?php if ($blocked): ?><span class="pill status-bloqueado">BLOQUEADO</span><?php endif; ?>
<?php if ($isSelf): ?><span class="pill">TU CUENTA</span><?php endif; ?>
<h3><?= e($account['nombre'] . ' ' . $account['apellido']) ?></h3>
<p class="user-mail"><?= e($account['correo']) ?></p>
</div>
<dl class="user-stats">
<div><dt>Intentos fallidos</dt><dd><?= $attempts ?></dd></div>
<div><dt>Estado de acceso</dt><dd><?= e($statusText) ?></dd></div>
<div><dt>Alta</dt><dd><?= e(date('d/m/Y', strtotime($account['creado_en']))) ?></dd></div>
</dl>
</div>
<div class="user-actions">
<?php if ($blocked || $attempts > 0): ?>
<form method="post" action="<?= url('admin-user-unlock') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($account['id']) ?>"><button class="button-outline small-button" type="submit">Desbloquear</button></form>
<?php endif; ?>
<?php if (!$isSelf): ?>
<form class="user-inline" method="post" action="<?= url('admin-user-role') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($account['id']) ?>">
<select name="rol_id" aria-label="Rol de <?= e($account['nombre']) ?>"><option value="2" <?= $account['rol'] === 'CLIENTE' ? 'selected' : '' ?>>Cliente</option><option value="1" <?= $account['rol'] === 'ADMIN' ? 'selected' : '' ?>>Administrador</option></select>
<button class="button-outline small-button" type="submit">Cambiar rol</button>
</form>
<?php endif; ?>
<form class="user-inline" method="post" action="<?= url('admin-user-password') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($account['id']) ?>">
<input type="password" name="password" placeholder="Contraseña temporal" minlength="12" required autocomplete="new-password" aria-label="Nueva contraseña temporal para <?= e($account['nombre']) ?>">
<input type="password" name="password_confirm" placeholder="Repetir" minlength="12" required autocomplete="new-password" aria-label="Repetir la contraseña temporal">
<button class="button-outline small-button" type="submit">Restablecer clave</button>
</form>
<?php if (!$isSelf): ?>
<form method="post" action="<?= url('admin-user-status') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($account['id']) ?>"><button class="button-outline small-button" type="submit" <?= $account['estado'] === 'ACTIVO' ? 'data-confirm="¿Deshabilitar esta cuenta? Se cerrarán sus sesiones abiertas."' : '' ?>><?= $account['estado'] === 'ACTIVO' ? 'Deshabilitar' : 'Habilitar' ?></button></form>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
<?php if (!$accounts): ?><p class="empty">No hay cuentas todavía.</p><?php endif; ?>
</section>
</div>
</main>
