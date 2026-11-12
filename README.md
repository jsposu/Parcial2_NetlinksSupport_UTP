![Netlinks — catálogo de servicios de soporte técnico](docs/images/netlinks-home.png)

# Netlinks · Book&Go Lite

Reservas de soporte técnico con PHP 8.2+, MySQL/MariaDB y diseño adaptable. Proyecto de Desarrollo Web · UTP · **JHOP**: Joseph Gantes, Henry Ramirez, Omar Herrera y Paul Zerpa.

## Instalar — recomendado

Necesitas **XAMPP instalado**, Apache/MySQL iniciados y **Python 3.9+**. Descarga o clona este repositorio **fuera de `htdocs`**, abre una terminal en él y ejecuta:

**Se usa MySQL/MariaDB que ya viene con XAMPP**, el mismo que administras desde phpMyAdmin. No instales otro servidor de base de datos.

| Sistema | Comando |
| --- | --- |
| Windows | `py -3 instalar.py` |
| Linux / macOS | `python3 instalar.py` |

Confirma una vez y listo: **detecta XAMPP → crea/reutiliza `bookgo_lite` → copia el sitio → muestra el enlace e intenta abrir el navegador**. Usa los valores normales de XAMPP (`root`, sin contraseña); solo pide la contraseña de MySQL si hace falta. Si ya existe `.env` en el destino, conserva su conexión.

**No borra bases, no crea respaldos ni guarda nada en `/root`.** Solo copia a `htdocs/Parcial2DesarrolloWeb`, conserva archivos locales y no restablece contraseñas personales ya activadas. Si la base existe, la reutiliza y aplica las migraciones necesarias.

Ruta distinta: `python3 instalar.py --xampp /ruta/a/xampp` (Windows: `py -3 instalar.py --xampp "D:\xampp"`). Si faltan permisos, usa una terminal de administrador o `sudo python3 instalar.py`. Con `sudo`, abre el enlace desde tu navegador habitual, no como root. `--no-browser` evita abrir el navegador y `--no-color` desactiva los colores.

Al terminar abre **http://localhost/Parcial2DesarrolloWeb/public/**. [Detalles, recuperación y pruebas del instalador](docs/INSTALACION.md).

<details>
<summary><strong>Instalación manual — Windows, Linux y macOS</strong></summary>

Inicia Apache y MySQL. Usa estas rutas según tu sistema:

| Sistema | Carpeta de destino | Ejecutable PHP |
| --- | --- | --- |
| Windows | `C:\xampp\htdocs\Parcial2DesarrolloWeb` | `C:\xampp\php\php.exe` |
| Linux | `/opt/lampp/htdocs/Parcial2DesarrolloWeb` | `/opt/lampp/bin/php` |
| macOS | `/Applications/XAMPP/xamppfiles/htdocs/Parcial2DesarrolloWeb` | `/Applications/XAMPP/xamppfiles/bin/php` |

1. Copia el proyecto completo al destino, incluidos `.htaccess` y `.env.example`.
2. En [phpMyAdmin](http://localhost/phpmyadmin/), importa `database/bookgo.sql`. Crea **`bookgo_lite`** automáticamente.
3. Copia `.env.example` como `.env` y ajusta la conexión MySQL (XAMPP local suele usar `root` sin contraseña).
4. Desde la carpeta destino, ejecuta **el PHP de tu sistema** seguido de `bin/create-demo.php`. Ejemplo Linux: `/opt/lampp/bin/php bin/create-demo.php`.
5. Abre **http://localhost/Parcial2DesarrolloWeb/public/**.

**¿Actualizas una instalación existente?** Respalda primero archivos y base. No reimportes el SQL: copia el código conservando `.env` y ejecuta, en este orden, `PHP bin/migrate-passwords.php` y `PHP bin/create-demo.php`, sustituyendo `PHP` por el ejecutable de la tabla. [Más ayuda](docs/INSTALACION.md).
</details>

## Primer acceso

| Rol | Correo | Clave temporal |
| --- | --- | --- |
| ADMIN | `admin@netlinks.pa` | `NetlinksAdmin2026!` |
| CLIENTE | `cliente@netlinks.pa` | `NetlinksCliente2026!` |
| CLIENTE adicional | `otro@netlinks.pa` | `NetlinksOtro2026!` |

Al ingresar debes cambiar la clave antes de usar el sistema: mínimo 12 caracteres, al menos una mayúscula, una minúscula, un número y un carácter especial, máximo 72 bytes y confirmación. La temporal deja de funcionar después del cambio. **Estas cuentas públicas son solo para desarrollo local, no para producción.** Nunca publiques `.env`, contraseñas personales ni respaldos. El enunciado prohíbe publicar contraseñas: consulta con el docente esta variante de demostración.

## Qué incluye

- Catálogo con búsqueda, categorías, seis servicios y horarios futuros.
- ADMIN: mantenimiento de categorías, servicios y horarios; confirmación, atención y cancelación de reservas; **Admin Centers** para crear cuentas, cambiar roles, restablecer contraseñas temporales, ver intentos fallidos y bloquear/desbloquear accesos.
- CLIENTE: registro, reservas propias, cancelaciones y reseñas de atenciones realizadas.
- Cupos transaccionales, permisos PHP, hashes, CSRF, PDO preparado, salida codificada y bloqueo de login.

**Demo:** reservar como CLIENTE → confirmar y marcar REALIZADA como ADMIN → reseñar como CLIENTE. Los horarios del SQL se crean relativos al día de importación; si vencen, crea nuevos como ADMIN. Hora: Panamá (UTC−05:00).

## Documentación y pruebas

- [Instalador y recuperación](docs/INSTALACION.md) · [Arquitectura](docs/ARQUITECTURA.md) · [Pruebas de seguridad](security/SECURITY.md).
- Pruebas del instalador: `python3 -m unittest discover -s tests -p "test_installer.py"`.
- Flujo web sobre una **base local recién importada e inicializada**: `python3 tests/smoke.py http://localhost/Parcial2DesarrolloWeb/public/` (cambia claves de demo y crea reservas).
- Informe: `docs/Informe.html` y `docs/Informe.pdf`. Capturas de seguridad y reflexiones individuales pendientes del equipo.
- Empaquetar: `php bin/package.php` → `entrega/PW2026_JHOP_BookGoLite.zip`, sin credenciales privadas.

**Uso de IA:** El equipo utilizó OpenCode con varios modelos como apoyo para desarrollar la estructura principal del código. Las ideas, los requisitos y la información del proyecto se proporcionaron manualmente a la IA. El equipo revisó y probó el código de este repositorio y asume la responsabilidad por cada línea de código entregada.
