# Seguridad — verificación en el entorno local

**Entorno de las evidencias:** Windows · XAMPP · **PHP 8.2.12** · **MariaDB 10.4.32**, servidor PHP en `http://127.0.0.1:8139/` sobre una base aislada `bookgo_lite_evidencias`, importada desde `database/bookgo.sql` e inicializada con `bin/create-demo.php`. Fecha: **12 de noviembre de 2026**. El mismo código se ejecuta bajo Apache en `htdocs/Parcial2DesarrolloWeb/public/`.

Las capturas y salidas reales están en [`evidencias/`](evidencias/README.md). La suite automatizada `tests/smoke.py` pasó completa; su salida se conserva en `evidencias/pruebas-smoke-2026-11-12.txt`.

## Resultado de las siete pruebas

| Prueba / objetivo | Pasos ejecutados | Resultado observado | Evidencia |
| --- | --- | --- | --- |
| 1. SQLi en login/búsqueda | Correo `x' OR '1'='1` y búsqueda `' OR 1=1 --` | Login rechazado con «Correo inválido»; la búsqueda responde 200 sin alterar datos. PDO preparado en `app/bootstrap.php` y `app/actions.php`. | `01-sqli.jpeg` |
| 2. XSS en reseña | Reserva realizada y reseña con `<script>alert(1)</script>` | El texto se muestra escapado; no se ejecuta y el HTML no contiene el script crudo. `e()` usa `htmlspecialchars` y la CSP bloquea scripts externos. | `02-xss.jpeg` |
| 3. Cliente en ADMIN | CLIENTE autenticado abre `?route=admin` y `?route=admin-reservas` | **HTTP 403** «Acceso denegado»; `must_login(1)` en `public/index.php`. | `03-cliente-admin.jpeg` |
| 4. Reserva ajena | Segundo cliente envía `?route=cancel` con el ID de una reserva ajena y su propio CSRF | **HTTP 422** «Reserva no encontrada»; no cambia estado ni cupo. Verificación de `cliente_id` en `change_booking()`. | `04-reserva-ajena.jpeg` |
| 5. CSRF | POST a `?route=cancel` con token inválido | **HTTP 403** «Solicitud rechazada: token CSRF inválido»; sin modificar datos. `require_post()`. | `05-csrf.jpeg` |
| 6. Cinco intentos | Cinco claves erróneas y luego la clave correcta | Quinto intento: aviso genérico. Sexto intento: «Demasiados intentos. Espere 15 minutos». Bloqueo por cuenta en `usuarios.intentos_fallidos` / `bloqueado_hasta`. | `06-cinco-intentos.jpeg` |
| 7. Cabeceras HTTP | `curl -I` a la respuesta principal | `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy` y cookie `HttpOnly; SameSite=Lax`. | `07-cabeceras.txt` |

## Capturas del flujo y de los módulos

`00-home.jpeg`, `00b-login.jpeg`, `cambio-obligatorio.jpeg`, `flujo-01-servicio.jpeg` a `flujo-06-formulario-resena.jpeg`, `modulo-admin.jpeg`, `modulo-admin-reservas.jpeg`, `modulo-admin-centers.jpeg` y `modo-oscuro.jpeg`. Corresponden a la secuencia completa: reserva del cliente → confirmación y cierre por el administrador → reseña.

## Comprobaciones adicionales (automatizadas)

`tests/smoke.py` verifica, además de lo anterior: reenvío de cancelación con un solo cupo devuelto, reserva duplicada activa, reseña duplicada, precio/duración inválidos, horario pasado, relación servicio–horario, cabeceras, propiedad y cupos. Salida: `evidencias/pruebas-smoke-2026-11-12.txt`.

El instalador se verificó con 11 pruebas: `python -m unittest discover -s tests -p "test_installer.py"` (salida en `evidencias/pruebas-instalador-2026-11-12.txt`) y `bin/check-install.php` (`evidencias/check-install-2026-11-12.txt`).

## Cambio obligatorio en el primer acceso

Las tres cuentas de demostración tienen claves iniciales públicas definidas en `app/demo-accounts.php` y documentadas en README. **Solo desarrollo local:** no satisfacen la prohibición académica de publicar contraseñas ni son apropiadas para producción; confirmar esta variante con el docente. Las claves definitivas jamás se publican y en MySQL solo se almacenan hashes. `bin/create-demo.php` no restablece cuentas ya inicializadas; `bin/migrate-passwords.php` actualiza el esquema de bases previas sin borrar datos.

Hasta cambiar la contraseña, PHP permite únicamente la pantalla de cambio y salir; bloquea también POST y URL directas a reservas y ADMIN. La operación exige sesión activa, CSRF, confirmación, mínimo 12 caracteres con al menos una mayúscula, una minúscula, un número y un carácter especial, y máximo 72 bytes, y rechaza las claves públicas. Un UPDATE condicionado por versión evita dos cambios simultáneos; al guardar se renuevan sesión y CSRF, se desactiva la obligación y se invalida cualquier otra sesión con la versión anterior. Tras salir se debe ingresar con la contraseña personal. La clave inicial nunca se acepta como alternativa de recuperación.

**Verificación automatizada del cambio (11/11/2026):** `tests/smoke.py` pasó en PHP 8.2 / MariaDB 10.4 de XAMPP sobre una base aislada, incluyendo las tres cuentas, redirección de GET/POST directos, token inválido, claves que incumplen la política (longitud mínima, mayúscula, minúscula, número y carácter especial), confirmación incorrecta, rechazo de claves iniciales, límite de bytes, invalidación de otra sesión y rechazo de la contraseña inicial después del cambio. La misma política se aplica al registro y a las claves temporales creadas o restablecidas desde Administración de Usuarios. También pasó el flujo previo de reservas y seguridad. Se comprobó por separado la migración repetida de una base del esquema anterior: conserva identificadores, reservas y cupos; repetir la inicialización no cambia los hashes personales. Las capturas de navegador se añadieron el 12/11/2026 (ver `evidencias/`).

## Administración de Usuarios

- Solo el rol ADMIN accede a `?route=admin-centers`; un CLIENTE recibe 403.
- Crear cuentas, cambiar rol, restablecer claves temporales, desbloquear y deshabilitar están protegidos por CSRF y validación de rol.
- Restablecer una clave exige cambio en el siguiente ingreso e invalida sesiones abiertas (`password_version+1`).
- Deshabilitar una cuenta la marca `INACTIVO` y revoca sus sesiones.
- El administrador no puede deshabilitar ni degradar su propia cuenta, y el sistema exige al menos un administrador activo.
- Las contraseñas temporales no pueden coincidir con las claves iniciales públicas y cumplen la política de 12 caracteres con mayúscula, minúscula, número y símbolo.

## Reproducción

1. Importar `database/bookgo.sql` en una base aislada.
2. Configurar `.env` y ejecutar `bin/create-demo.php`.
3. Servir `public/` con PHP 8.2 (XAMPP) o Apache.
4. Ejecutar `python tests/smoke.py http://127.0.0.1:8139/`.
5. Repetir las siete pruebas manuales y guardar capturas nuevas en `evidencias/`.
