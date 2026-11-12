<main class="container narrow section"><div class="panel"><span class="eyebrow">PRIMER ACCESO · SEGURIDAD</span><h1>Cambia tu contraseña</h1>
<p>La contraseña inicial es pública y solo sirve para activar tu cuenta. Debes reemplazarla antes de reservar o administrar servicios.</p>
<form method="post" action="<?= url('change-password') ?>"><?= csrf_field() ?>
<label>Nueva contraseña (mínimo 12 caracteres)<input required type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password"></label>
<label>Confirmar nueva contraseña<input required type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password"></label>
<p class="small">Debe incluir al menos una mayúscula, una minúscula, un número y un carácter especial. Usa una clave personal distinta de las contraseñas iniciales. Máximo 72 bytes (algunos caracteres ocupan más de un byte).</p>
<button class="button full" type="submit">Guardar y continuar</button></form></div></main>
