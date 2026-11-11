#!/usr/bin/env python3
"""Instalación rápida en el XAMPP local. Python 3.9+, sin paquetes externos."""
from __future__ import annotations

import argparse
import getpass
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import webbrowser

PROJECT = 'Parcial2DesarrolloWeb'
FILES = ['.htaccess', '.env.example', '.gitignore', 'README.md', 'instalar.py',
         'app', 'bin', 'database', 'docs', 'public', 'security', 'tests', 'views']
COLOR = False


class InstallError(Exception):
    pass


def colors(enabled: bool):
    global COLOR
    COLOR = enabled and sys.stdout.isatty() and 'NO_COLOR' not in os.environ
    if COLOR and os.name == 'nt':
        try:
            import ctypes
            handle = ctypes.windll.kernel32.GetStdHandle(-11)
            mode = ctypes.c_ulong()
            if not ctypes.windll.kernel32.GetConsoleMode(handle, ctypes.byref(mode)) or not ctypes.windll.kernel32.SetConsoleMode(handle, mode.value | 4):
                COLOR = False
        except (AttributeError, OSError):
            COLOR = False


def say(text: str, code: str = '1;36'):
    print(f'\033[{code}m{text}\033[0m' if COLOR else text)


def confirm(label: str) -> bool:
    while True:
        value = input(label + ' [S/n; Enter = sí]: ').strip().lower()
        if value in ('', 's', 'si', 'sí'):
            return True
        if value in ('n', 'no'):
            return False
        say('S = continuar · N = salir', '33')


def php_path(root: Path, system: str) -> Path:
    return root / ('php/php.exe' if system == 'Windows' else 'bin/php')


def find_xampp(system: str, custom: str | None = None) -> Path:
    defaults = {'Windows': ['C:/xampp', 'D:/xampp'], 'Linux': ['/opt/lampp'],
                'Darwin': ['/Applications/XAMPP/xamppfiles']}
    if system not in defaults:
        raise InstallError('Se requiere Windows, Linux o macOS.')
    for value in ([custom] if custom else defaults[system]):
        root = Path(value).expanduser()
        if (root / 'htdocs').is_dir() and php_path(root, system).is_file():
            return root.resolve()
    raise InstallError('No encontré XAMPP. Instálalo primero o indica su ruta con --xampp "RUTA".')


def read_env(path: Path) -> dict[str, str]:
    if not path.is_file():
        return {}
    return dict(line.split('=', 1) for line in path.read_text(encoding='utf-8').splitlines()
                if line.startswith('DB_') and '=' in line)


def settings(target: Path) -> dict[str, str]:
    old = read_env(target / '.env')
    values = dict(DB_HOST=old.get('DB_HOST') or '127.0.0.1', DB_PORT=old.get('DB_PORT') or '3306',
                  DB_USER=old.get('DB_USER') or 'root', DB_PASSWORD=old.get('DB_PASSWORD', ''),
                  DB_NAME=old.get('DB_NAME') or 'bookgo_lite')
    if values['DB_HOST'].lower() not in ('localhost', '127.0.0.1', '::1'):
        raise InstallError('La configuración debe usar el MySQL local de XAMPP, no un servidor externo.')
    return values


def write_env(path: Path, values: dict[str, str]):
    if all(read_env(path).get(key) == value for key, value in values.items()):
        return
    previous = path.read_text(encoding='utf-8').splitlines() if path.exists() else []
    lines = [line for line in previous if line.split('=', 1)[0] not in values]
    path.write_text('\n'.join(lines + [f'{key}={value}' for key, value in values.items()]) + '\n', encoding='utf-8')
    path.chmod(0o644)  # Apache debe poder leerlo; .htaccess bloquea el acceso HTTP.


def prepare_database(php: Path, source: Path, root: Path, values: dict[str, str]) -> str:
    payload = {**values, 'xampp': str(root)}
    result = subprocess.run([str(php), str(source / 'bin/install-local.php')],
                            input=json.dumps(payload).encode('utf-8'), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    try:
        response = json.loads(result.stdout)
    except (ValueError, UnicodeError):
        raise InstallError('PHP no pudo preparar la base. Comprueba PHP 8.2+, pdo_mysql y mbstring en XAMPP.') from None
    if not result.returncode and response.get('ok'):
        return response['state']
    if response.get('auth'):
        raise PermissionError('MySQL requiere una contraseña válida.')
    raise InstallError(response.get('message', 'No se pudo preparar la base.'))


def no_links(path: Path):
    if path.is_symlink() or any(item.is_symlink() for item in path.rglob('*')):
        raise InstallError(f'No se copiarán enlaces simbólicos: {path}')


def validate_site(source: Path, target: Path):
    if source == target.resolve() or source in target.resolve().parents or target.resolve() in source.parents:
        raise InstallError('Ejecuta el instalador desde una copia fuera de la carpeta de destino.')
    if target.is_symlink() or target.exists() and not target.is_dir():
        raise InstallError('El destino no es una carpeta normal; no se modificará.')
    if target.exists():
        no_links(target)
        if not (target / 'app/bootstrap.php').is_file() or not (target / 'public/index.php').is_file():
            raise InstallError('La carpeta de destino no parece Netlinks. No se sobrescribirá otro proyecto.')
    for part in FILES:
        path = source / part
        if not path.exists():
            raise InstallError(f'El proyecto está incompleto: falta {part}.')
        no_links(path)
    for script in ('install-local.php', 'migrate-passwords.php', 'create-demo.php', 'check-install.php'):
        if not (source / 'bin' / script).is_file():
            raise InstallError(f'Falta bin/{script}. Descarga el proyecto completo.')


def copy_site(source: Path, target: Path, values: dict[str, str]):
    target.mkdir(exist_ok=True)
    for part in FILES:
        origin, destination = source / part, target / part
        if origin.is_dir():
            shutil.copytree(origin, destination, dirs_exist_ok=True,
                            ignore=shutil.ignore_patterns('__pycache__', '*.pyc'))
        else:
            shutil.copy2(origin, destination)
    write_env(target / '.env', values)


def finish_database(php: Path, target: Path):
    for script in ('migrate-passwords.php', 'create-demo.php', 'check-install.php'):
        result = subprocess.run([str(php), str(target / 'bin' / script)], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if result.returncode:
            raise InstallError(f'No se completó {script}. Revisa la conexión de .env y vuelve a ejecutar el instalador; no borres la base.')


def install(source: Path, root: Path, system: str, browser: bool = True):
    source = source.resolve()
    target = root / 'htdocs' / PROJECT
    php = php_path(root, system)
    validate_site(source, target)
    say('\n1. XAMPP encontrado')
    print(f'   {root}\n   Destino: {target}')
    print('   Se reutiliza tu base si existe. No se borra ni se crean respaldos.')
    if not confirm('¿Instalar o actualizar Netlinks?'):
        say('Cancelado. No se modificó nada.', '33')
        return
    if not os.access(target if target.exists() else target.parent, os.W_OK):
        raise InstallError('Faltan permisos. Usa una terminal de administrador o sudo python3 instalar.py.')
    values = settings(target)
    say('\n2. Preparando la base de XAMPP')
    print(f'   {values["DB_NAME"]} · {values["DB_HOST"]}:{values["DB_PORT"]} · usuario {values["DB_USER"]}')
    try:
        state = prepare_database(php, source, root, values)
    except PermissionError:
        values['DB_PASSWORD'] = getpass.getpass('Contraseña de MySQL de XAMPP (oculta; Enter = vacía): ')
        if values['DB_PASSWORD'] != values['DB_PASSWORD'].strip() or any(c in values['DB_PASSWORD'] for c in '\r\n\0'):
            raise InstallError('La configuración .env no admite esa contraseña. Usa una cuenta local sin saltos de línea ni espacios al inicio/final.')
        try:
            state = prepare_database(php, source, root, values)
        except PermissionError:
            raise InstallError('Contraseña MySQL incorrecta. No se copiaron archivos.') from None
    say('   Base creada.' if state == 'new' else '   Base existente conservada.', '32')
    say('\n3. Copiando el sitio')
    copy_site(source, target, values)
    finish_database(php, target)
    say('\nListo. Netlinks está instalado.', '1;32')
    url = f'http://localhost/{PROJECT}/public/'
    say(url, '1;36')
    print('Claves de primer acceso: README.md. Debes cambiarlas al ingresar.')
    elevated = bool(os.environ.get('SUDO_USER')) or (os.name != 'nt' and os.geteuid() == 0)
    if browser and not elevated:
        try:
            webbrowser.open(url)
        except webbrowser.Error:
            pass  # El enlace sigue disponible aunque no haya sesión gráfica.
    elif browser:
        print('Abre el enlace en tu navegador habitual (no como root).')


def main() -> int:
    parser = argparse.ArgumentParser(description='Instalación rápida de Netlinks con el MySQL incluido en XAMPP.')
    parser.add_argument('--xampp', help='Ruta alternativa de XAMPP.')
    parser.add_argument('--no-browser', action='store_true', help='No abrir el navegador.')
    parser.add_argument('--no-color', action='store_true', help='No usar colores ANSI.')
    args = parser.parse_args()
    colors(not args.no_color)
    say(' +---+  NETLINKS\n | >_|  Instalación rápida\n +---+', '1;36')
    print('Inicia Apache y MySQL en XAMPP. Enter continúa; Ctrl+C sale.')
    try:
        system = platform.system()
        root = find_xampp(system, args.xampp)
        install(Path(__file__).resolve().parent, root, system, not args.no_browser)
        return 0
    except (KeyboardInterrupt, EOFError):
        say('\nCancelado. Si ya comenzó la instalación, vuelve a ejecutarla para completarla.', '33')
        return 130
    except (InstallError, OSError) as error:
        say('\nNo se pudo completar la instalación.', '1;31')
        print(str(error))
        print('No se borraron bases ni se crearon respaldos. Si hubo un fallo al copiar, algunos archivos pueden haberse actualizado.')
        return 1


if __name__ == '__main__':
    sys.exit(main())
