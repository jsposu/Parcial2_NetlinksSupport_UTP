# Evidencias de seguridad y funcionamiento

Capturas y salidas reales del **12 de noviembre de 2026**. Entorno: Windows · XAMPP · PHP 8.2.12 · MariaDB 10.4.32, base aislada `bookgo_lite_evidencias` servida en `http://127.0.0.1:8139/`.

No se incluyen contraseñas personales, cookies visibles ni datos privados. Las claves de demostración del README son temporales y solo para desarrollo local.

## Pruebas de seguridad (apartado 6 de `SECURITY.md`)

| Archivo | Prueba | Resultado observado |
| --- | --- | --- |
| `01-sqli.jpeg` | Inyección SQL en login | Rechazado con «Correo inválido»; no autentica |
| `02-xss.jpeg` | XSS en reseña | El script se muestra escapado y no se ejecuta |
| `03-cliente-admin.jpeg` | CLIENTE en zona ADMIN | HTTP 403 «Acceso denegado» |
| `04-reserva-ajena.jpeg` | Reserva de otro cliente | HTTP 422 «Reserva no encontrada» |
| `05-csrf.jpeg` | Token CSRF inválido | HTTP 403 sin modificar datos |
| `06-cinco-intentos.jpeg` | Cinco fallos de contraseña | Bloqueo de 15 minutos por cuenta |
| `07-cabeceras.txt` | Cabeceras HTTP | CSP, `nosniff`, `DENY`, `Referrer-Policy`, cookie `HttpOnly; SameSite=Lax` |

## Flujo completo de una reserva

`flujo-01-servicio.jpeg` → `flujo-02-mis-reservas.jpeg` → `flujo-03-admin-pendiente.jpeg` → `flujo-04-admin-confirmada.jpeg` → `flujo-05-admin-realizada.jpeg` → `flujo-06-formulario-resena.jpeg`.

## Módulos e interfaz

`00-home.jpeg` (catálogo), `00b-login.jpeg` (ingreso), `cambio-obligatorio.jpeg` (primer acceso), `modulo-admin.jpeg` (administración), `modulo-admin-reservas.jpeg` (seguimiento), `modulo-admin-centers.jpeg` (cuentas y accesos) y `modo-oscuro.jpeg` (tema oscuro).

## Salidas de pruebas automatizadas

| Archivo | Comando | Resultado |
| --- | --- | --- |
| `pruebas-smoke-2026-11-12.txt` | `python tests/smoke.py http://127.0.0.1:8139/` | `OK: primer acceso, cambio obligatorio, validaciones, clave inicial invalidada, sesiones revocadas, catálogo, CRUD, horarios, login/bloqueo, roles, cabeceras, SQLi, CSRF, propiedad, transiciones, reseña/XSS y cupos.` |
| `pruebas-instalador-2026-11-12.txt` | `python -m unittest discover -s tests -p "test_installer.py"` | 11 pruebas, `OK` |
| `check-install-2026-11-12.txt` | `php bin/check-install.php` | Conexión y esquema comprobados |
