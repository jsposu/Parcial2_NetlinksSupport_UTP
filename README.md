# Netlinks · Book&Go Lite

Aplicación de reservas de **soporte técnico** (PHP 8.2+, MySQL 8+ o MariaDB 10.4+, HTML5, CSS3 y JavaScript). Proyecto Parcial 2, Desarrollo Web, 2026.

**Equipo:** Joseph Gantes, Henry Ramirez, Omar Herrera y Paul Zerpa.

## Instalar en XAMPP (Windows)

1. Inicia **Apache** y **MySQL** en el panel de XAMPP. Copia la carpeta completa como `C:\xampp\htdocs\Parcial2DesarrolloWeb` (sin cambiar su nombre). Para otros sistemas, usa el `htdocs` correspondiente.
2. En `http://localhost/phpmyadmin/`, pestaña **Importar**, selecciona `database/bookgo.sql` y ejecútalo. El SQL crea `bookgo_lite`, ocho tablas (las siete obligatorias más `intentos_login`), tres categorías, seis servicios y seis horarios futuros. Si phpMyAdmin no permite crear bases, crea `bookgo_lite` en la interfaz e importa el archivo dentro de esa base.
3. Copia `.env.example` a `.env`. Ajusta el usuario y contraseña **de tu instalación local**: XAMPP sin configurar suele usar `DB_USER=root` y `DB_PASSWORD=`. Nunca subas `.env` al repositorio. Para MariaDB con puerto distinto, modifica `DB_PORT`.
4. En CMD de Windows: `cd C:\xampp\htdocs\Parcial2DesarrolloWeb` y después `C:\xampp\php\php.exe bin\create-demo.php` (Linux XAMPP: desde la carpeta del proyecto, `/opt/lampp/bin/php bin/create-demo.php`). Este paso crea los tres usuarios y escribe **`README.local.md` con sus correos y contraseñas de prueba**. Ese archivo está en `.gitignore` y no debe publicarse. Volver a ejecutarlo cambia las contraseñas de esos usuarios; si lo haces, consulta el README local actualizado.
5. Abre **http://localhost/Parcial2DesarrolloWeb/public/**. Si la carpeta tiene otro nombre, ajusta la URL. `public/` es el único directorio accesible por HTTP: `.htaccess` bloquea la raíz del proyecto en XAMPP y permite `public/` (requiere `AllowOverride` habilitado). En un despliegue real, apunta directamente el `DocumentRoot` a `public/`; nunca publiques `app/`, `.env`, `bin/` o `database/`.

### Usuarios para iniciar sesión

| Rol | Correo | Contraseña |
| --- | --- | --- |
| ADMIN | `admin@netlinks.pa` | La generada en **`README.local.md`** |
| CLIENTE | `cliente@netlinks.pa` | La generada en **`README.local.md`** |
| CLIENTE adicional | `otro@netlinks.pa` | La generada en **`README.local.md`** |

Las contraseñas **no figuran en este README público**: el enunciado prohíbe publicarlas. Para verlas en tu propio equipo abre `README.local.md` después del paso 4. Los usuarios también pueden registrarse desde la interfaz, siempre con rol CLIENTE.

### Ejecutar sin Apache (opcional)

Con una base MySQL en funcionamiento: `php -S 127.0.0.1:8000 -t public` y abrir `http://127.0.0.1:8000/`. En algunas instalaciones Linux se requiere habilitar la extensión `pdo_mysql` de PHP; en XAMPP viene normalmente incluida.

## Flujo de demostración

1. Cliente: catálogo → buscar/filtrar → detalle → horario futuro → reservar (PENDIENTE, -1 cupo) → Mis reservas.
2. Admin: Reservas → CONFIRMADA → REALIZADA. Cliente: Mis reservas → dejar una reseña (1–5). Volver al detalle para verla publicada.
3. Para probar cancelación: reservar otro horario → cancelar antes de que comience → comprobar que vuelve **un solo cupo**. Repetir el envío no vuelve a sumarlo.
4. Admin: crear/editar/desactivar categorías, servicios y horarios; comprobar que el catálogo solo muestra activos. Un horario con reservas conserva sus datos históricos y sus cupos ocupados.
5. Para probar la propiedad de recursos, entrar como `otro@netlinks.pa` y tratar de actuar sobre el identificador de una reserva del primer cliente: el servidor la rechaza.

Los horarios del SQL son relativos al día de importación. Si ya vencieron, crea nuevos desde Administración → Horarios. El huso horario de la aplicación y la conexión es **America/Panama (UTC−05:00)**; verifica el reloj del equipo anfitrión.

## Diseño técnico

- `public/index.php`: rutas de lectura y acciones POST; `public/assets/`: estilos y confirmación JS.
- `app/bootstrap.php`: configuración, PDO preparado, sesión, cabeceras, CSRF y validaciones comunes; `app/actions.php`: lógica de negocio.
- `views/`: páginas públicas, cliente y ADMIN. `database/bookgo.sql`: esquema, integridad referencial y datos de ejemplo.
- `bin/create-demo.php`: inicialización CLI de cuentas locales con hashes; `security/SECURITY.md`: guion de pruebas y resultados por completar con pruebas reales.
- `docs/Informe.html` y `docs/Informe.pdf`: informe base con diagrama ER y secciones de evidencias/reflexiones **pendientes de completar por el equipo**.
- Sin bibliotecas ni plantillas visuales externas: estilos CSS propios, tipografía del sistema y dibujo CSS. No necesita Internet.

**Integridad de reservas:** `reservas(horario_id,servicio_id)` referencia `horarios(id,servicio_id)`; una clave única generada impide dos reservas no canceladas por cliente/horario; `resenas(reserva_id)` es única y la FK compuesta comprueba que pertenezca al cliente. Una transacción bloquea primero el horario, comprueba cupos y luego actualiza de forma condicionada y registra la reserva. Las cancelaciones bloquean en el mismo orden, comprueban estado y devuelven un único cupo. Los cupos y valores básicos tienen `CHECK` en la base. Los registros asociados a otros se **desactivan** en vez de borrarse.

## Seguridad y pruebas

Se usa `password_hash`/`password_verify`, mínimo 8 caracteres, renovación del ID de sesión, cookies `HttpOnly` y `SameSite=Lax` (`Secure` bajo HTTPS), bloqueo por 15 minutos tras cinco fallos, PDO sin emulación, `htmlspecialchars(ENT_QUOTES, UTF-8)`, tokens CSRF, comprobación de roles y propiedad en PHP, CSP, `nosniff` y `X-Frame-Options: DENY`. Los errores SQL se registran en el servidor y no se muestran al visitante. **Las capturas y resultados observados deben realizarse en el XAMPP del equipo**, siguiendo `security/SECURITY.md`.

Prueba automatizada adicional (sobre una instalación de desarrollo **recién importada**, pues crea reservas/reseñas): `python tests/smoke.py http://localhost/Parcial2DesarrolloWeb/public/`. Lee `README.local.md`, no necesita paquetes externos de Python. Para regenerar el PDF después de editar el HTML: `chromium --headless --no-sandbox --no-pdf-header-footer --print-to-pdf=docs/Informe.pdf file:///RUTA/ABSOLUTA/Parcial2DesarrolloWeb/docs/Informe.html` o imprimir el HTML a PDF desde el navegador.

## Entrega y honestidad académica

El PDF del curso exige un ZIP del proyecto. El identificador de este equipo es **JHOP** (Joseph, Henry, Omar y Paul), por lo que el archivo se llama `PW2026_JHOP_BookGoLite.zip`. Ejecutar `php bin/package.php` para regenerarlo en `entrega/`. No incluye `.env` ni `README.local.md`. El informe PDF debe contener diagrama ER, módulos, capturas y **reflexión individual auténtica**; además se requieren evidencias reales de seguridad, un repositorio GitHub compartido y contribuciones verificables de los cuatro integrantes. **Después de insertar capturas reales y completar/refrescar el informe, volver a generar el ZIP.** No se han inventado capturas, reflexiones ni historial. **Uso de IA:** se utilizó OpenCode como apoyo para proponer e implementar esta base de código; antes de entregarla cada integrante debe comprenderla, probarla, modificarla según sus aportes y documentar los fragmentos y verificaciones en el informe, conforme a la sección 11 del enunciado. No presentar como trabajo propio código generado íntegramente por IA.
