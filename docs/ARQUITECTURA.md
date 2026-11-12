# Arquitectura

- `instalar.py`: instalación rápida en XAMPP; `bin/install-local.php`: crea o reutiliza la base mediante PDO, sin backups ni borrado de datos.
- `public/index.php`: rutas GET y operaciones POST; `public/assets/`: CSS propio y confirmaciones JS, sin dependencias de Internet.
- `app/bootstrap.php`: configuración, PDO, sesiones, CSRF, cabeceras y helpers; `app/actions.php`: lógica de negocio; `app/demo-accounts.php`: claves públicas de arranque para demo local.
- `views/`: pantallas públicas, CLIENTE y ADMIN; `admin-centers.php` concentra la gestión de cuentas (alta, roles, claves temporales, desbloqueo y baja lógica).
- `database/bookgo.sql`: ocho tablas (las siete exigidas y `intentos_login`), relaciones y catálogo de ejemplo.
- `bin/`: inicialización, migración, comprobación y empaquetado.

## Integridad

`reservas(horario_id,servicio_id)` referencia `horarios(id,servicio_id)`; la clave única generada impide dos reservas no canceladas del mismo cliente/horario. `resenas(reserva_id)` es única y su FK compuesta comprueba la propiedad. Las reservas y cancelaciones bloquean primero el horario y actualizan cupos dentro de una transacción; cancelar dos veces no devuelve cupos adicionales. Se conservan dependencias mediante desactivación lógica. Los valores básicos tienen restricciones CHECK.

## Seguridad

Hashes `password_hash`/`password_verify`, sesiones regeneradas, cookies HttpOnly/SameSite y Secure bajo HTTPS, bloqueo de login tras cinco fallos por cuenta (más límite por correo/IP para correos desconocidos), PDO preparado, salida codificada, CSRF, permisos y propiedad en PHP, CSP y cabeceras de seguridad. `Admin Centers` permite al ADMIN desbloquear cuentas, restablecer claves temporales, cambiar roles y deshabilitar usuarios; nunca puede degradar ni deshabilitar su propia cuenta ni dejar el sistema sin administradores activos. El cambio de clave inicial es obligatorio, invalida otras sesiones y no puede omitirse mediante URL/POST directo. [Controles y evidencias](../security/SECURITY.md).

## Informe y entrega

Editar `docs/Informe.html` y regenerar el PDF mediante imprimir a PDF desde el navegador, o Chromium headless. Completar capturas reales, reflexiones individuales y referencias al historial de contribuciones. `php bin/package.php` genera `PW2026_JHOP_BookGoLite.zip` con el instalador y documentación, excluyendo `.env`, `README.local.md`, Git y respaldos locales. No inventar participación ni evidencias.
