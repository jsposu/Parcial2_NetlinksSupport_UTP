# Instalación rápida

1. Inicia **Apache y MySQL en XAMPP**.
2. Desde el proyecto, fuera de `htdocs`, ejecuta `python3 instalar.py` (Windows: `py -3 instalar.py`).
3. Confirma con Enter. Al terminar abre **http://localhost/Parcial2DesarrolloWeb/public/**.

El instalador tiene colores y títulos en negrita si tu terminal los admite. No requiere paquetes de Python externos. Detecta XAMPP en Windows, Linux y macOS; requiere Python 3.9+ y PHP 8.2+ con `pdo_mysql` y `mbstring`.

## Qué hace

- Usa **MySQL/MariaDB incluido en XAMPP**, el que administras en http://localhost/phpmyadmin/. No necesitas otro servidor SQL ni los comandos mysql/mysqldump.
- La conexión predeterminada es `127.0.0.1:3306`, usuario `root`, contraseña vacía, base `bookgo_lite`. Una instalación previa conserva los datos de `.env`; la contraseña MySQL se pide de forma oculta solo si falla la autenticación.
- Comprueba que el servidor corresponda al XAMPP elegido. Si la base no existe, importa el SQL; si existe y tiene las tablas esperadas, la conserva y aplica migraciones.
- Copia únicamente el proyecto a `htdocs/Parcial2DesarrolloWeb`, conserva `.env` y archivos locales, e inicializa las cuentas de prueba sin restablecer las contraseñas personales ya activadas.
- **No borra bases, no crea respaldos ni carpetas de recuperación y no guarda archivos en `/root`.** El antiguo instalador con gestión de respaldos fue sustituido, no se ejecuta.
- Muestra la URL e intenta abrirla. Con `sudo`, no inicia un navegador como root: abre el enlace en tu sesión habitual.

## Si hace falta ajustar algo

| Caso | Solución |
| --- | --- |
| XAMPP en otra ruta | `python3 instalar.py --xampp /ruta/a/xampp` |
| Sin permisos en htdocs | Terminal de administrador (Windows) o `sudo python3 instalar.py` (Linux/macOS) |
| No deseas navegador/colores | `--no-browser` / `--no-color` |
| Apache usa otro puerto | Cambia el puerto en el enlace al abrirlo; por ejemplo `http://localhost:8080/Parcial2DesarrolloWeb/public/` |
| MySQL usa otro puerto/usuario | Ajusta `DB_PORT` / `DB_USER` en el `.env` del sitio y vuelve a ejecutar el instalador |
| Base existente de otro proyecto | No se modifica. Revisa `DB_NAME` en `.env` o importa el SQL manualmente en una base nueva |

Si una importación se interrumpe, puede quedar una base incompleta: revisa phpMyAdmin antes de reintentar. Si falla la copia, algunos archivos pueden haberse actualizado. El script no elimina datos para intentar "arreglarlo"; tampoco realiza backups ni una restauración automática. Si necesitas conservar una versión anterior del código antes de actualizar, haz una copia manual. Las migraciones conservan usuarios, reservas y cupos; la primera activación de las cuentas de demo antiguas sustituye sus claves aleatorias por las temporales documentadas.

**Seguridad:** `.env` debe ser legible por Apache, pero nunca por HTTP. La raíz incluye `.htaccess` que deniega acceso, excepto `public/`; requiere Apache 2.4 y `AllowOverride`. No uses `chmod 777`. En producción apunta el `DocumentRoot` directamente a `public/` y no habilites las cuentas públicas de demo. No publiques `.env` ni contraseñas personales.

## Pruebas

`python3 -m unittest discover -s tests -p "test_installer.py"` comprueba detección por plataforma, confirmación, colores, credenciales por stdin, copia sin respaldos, conservación de archivos y errores. Windows/macOS se comprueban mediante simulaciones; requieren validación nativa. La captura `docs/images/netlinks-home.png` es real y muestra datos de ejemplo locales, no sustituye las evidencias de seguridad.

Verificado el 11/11/2026 en un entorno Linux aislado con PHP 8.2 / MariaDB 10.4 de XAMPP: importación SQL mediante PDO, instalación, primer acceso, flujo de reservas y actualización conservando claves personales y reservas, sin crear carpetas de respaldo. También se comprobó el rechazo de una instancia MySQL ajena al XAMPP seleccionado. Pasaron las 11 pruebas unitarias del nuevo instalador y `tests/smoke.py`. No se modificó el XAMPP real del usuario durante estas pruebas.
