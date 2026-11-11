"""Pruebas funcionales HTTP sobre una instalación LOCAL DE DESARROLLO.

Uso: python tests/smoke.py http://127.0.0.1:8000/
Lee contraseñas de README.local.md (excluido de Git). Crea reservas y una reseña.
No usar en producción: utiliza una base recién importada y horarios con cupos.
"""
import http.cookiejar
import datetime
import pathlib
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8000/').rstrip('/') + '/'
LOCAL = pathlib.Path(__file__).resolve().parents[1] / 'README.local.md'
text = LOCAL.read_text(encoding='utf-8')
password = re.search(r'admin@netlinks\.pa\s*\|\s*([^|\s]+)', text).group(1)


class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(self, route='home', **params):
        return self.request(route, None, **params)

    def post(self, route, data, **params):
        return self.request(route, data, **params)

    def request(self, route, data, **params):
        url = BASE + 'index.php?' + urllib.parse.urlencode({'route': route, **params})
        if data is not None:
            body = urllib.parse.urlencode(data).encode()
            request = urllib.request.Request(url, body, method='POST')
        else:
            request = urllib.request.Request(url)
        try:
            response = self.client.open(request)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode('utf-8'), response.headers

    def csrf(self):
        status, html, _ = self.get('login')
        assert status == 200
        return re.search(r'name="csrf" value="([a-f0-9]+)"', html).group(1)

    def login(self, email):
        status, html, _ = self.post('login', {'correo': email, 'password': password, 'csrf': self.csrf()})
        assert status == 200 and ('Mis reservas' in html or 'Administrar' in html), (status, html[:200])


client, other, admin, anonymous = Browser(), Browser(), Browser(), Browser()
status, html, headers = anonymous.get()
assert status == 200 and 'Diagnóstico de computadora' in html
assert 'Content-Security-Policy' in headers and headers['X-Content-Type-Options'] == 'nosniff'
assert anonymous.get('home', q="' OR 1=1 --")[0] == 200
assert anonymous.post('login', {'correo': 'x\' OR 1=1 --', 'password': 'x', 'csrf': anonymous.csrf()})[0] != 200
fresh = Browser()
fresh_email = 'nuevo_' + __import__('secrets').token_hex(5) + '@example.test'
assert fresh.post('register', {'csrf': fresh.csrf(), 'nombre': 'Nuevo', 'apellido': 'Cliente',
    'correo': fresh_email, 'password': password})[0] == 200
fresh.login(fresh_email)
assert fresh.get('admin')[0] == 403
assert fresh.post('logout', {'csrf': fresh.csrf()})[0] == 200
assert 'Crear cuenta' in fresh.get('reservas')[1]
client.login('cliente@netlinks.pa')
other.login('otro@netlinks.pa')
admin.login('admin@netlinks.pa')
assert client.get('admin')[0] == 403
assert client.post('book', {'csrf': 'incorrecto', 'servicio_id': 1, 'horario_id': 1})[0] == 403

status, html, _ = client.get('service', id=1)
assert status == 200
slot = re.search(r'name="horario_id" value="(\d+)"', html).group(1)
assert client.post('book', {'csrf': client.csrf(), 'servicio_id': 1, 'horario_id': slot})[0] == 200
assert client.post('book', {'csrf': client.csrf(), 'servicio_id': 1, 'horario_id': slot})[0] == 422
status, html, _ = client.get('reservas')
booking = re.search(r'Reserva #(\d+)', html).group(1)
assert status == 200 and 'PENDIENTE' in html
assert other.post('cancel', {'csrf': other.csrf(), 'id': booking})[0] == 422
assert admin.post('admin-status', {'csrf': admin.csrf(), 'id': booking, 'estado': 'CONFIRMADA'})[0] == 200
assert admin.post('admin-status', {'csrf': admin.csrf(), 'id': booking, 'estado': 'REALIZADA'})[0] == 200
assert client.post('cancel', {'csrf': client.csrf(), 'id': booking})[0] == 422
script = '<script>alert(1)</script>'
assert client.post('review', {'csrf': client.csrf(), 'reserva_id': booking, 'calificacion': 5, 'comentario': script})[0] == 200
assert client.post('review', {'csrf': client.csrf(), 'reserva_id': booking, 'calificacion': 5, 'comentario': script})[0] != 200
status, html, _ = anonymous.get('service', id=1)
assert status == 200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in html and script not in html

status, html, _ = client.get('service', id=2)
slot2 = re.search(r'name="horario_id" value="(\d+)"', html).group(1)
assert client.post('book', {'csrf': client.csrf(), 'servicio_id': 2, 'horario_id': slot2})[0] == 200
status, html, _ = client.get('reservas')
booking2 = re.search(r'Reserva #(\d+)', html).group(1)
assert client.post('cancel', {'csrf': client.csrf(), 'id': booking2})[0] == 200
assert client.post('cancel', {'csrf': client.csrf(), 'id': booking2})[0] == 422
status, html, _ = client.get('service', id=2)
assert '3 de 3 cupos disponibles' in html

assert admin.post('admin-save', {'csrf': admin.csrf(), 'entity': 'categorias', 'nombre': 'Pruebas locales', 'descripcion': 'Categoría de prueba automática'})[0] == 200
status, html, _ = admin.get('admin', entity='servicios')
category = re.search(r'<option value="(\d+)"\s*>Pruebas locales</option>', html).group(1)
assert admin.post('admin-save', {'csrf': admin.csrf(), 'entity': 'servicios', 'categoria_id': category,
    'nombre': 'Prueba de dispositivo', 'descripcion': 'Servicio de prueba', 'precio': '-1', 'duracion_min': 30})[0] == 422
assert admin.post('admin-save', {'csrf': admin.csrf(), 'entity': 'servicios', 'categoria_id': category,
    'nombre': 'Prueba de dispositivo', 'descripcion': 'Servicio de prueba', 'precio': '15.00', 'duracion_min': 30})[0] == 200
status, html, _ = admin.get('admin', entity='horarios')
service = re.search(r'<option value="(\d+)"\s*>Prueba de dispositivo</option>', html).group(1)
tomorrow = (datetime.datetime.now() + datetime.timedelta(days=3)).strftime('%Y-%m-%d')
assert admin.post('admin-save', {'csrf': admin.csrf(), 'entity': 'horarios', 'servicio_id': service,
    'fecha': '2020-01-01', 'hora_inicio': '09:00', 'hora_fin': '10:00', 'cupos_total': 2})[0] == 422
assert admin.post('admin-save', {'csrf': admin.csrf(), 'entity': 'horarios', 'servicio_id': service,
    'fecha': tomorrow, 'hora_inicio': '09:00', 'hora_fin': '10:00', 'cupos_total': 2})[0] == 200

throttle = Browser()
for attempt in range(5):
    assert throttle.post('login', {'csrf': throttle.csrf(), 'correo': 'bloqueo@netlinks.pa', 'password': 'incorrecta'})[0] == 422
status, html, _ = throttle.post('login', {'csrf': throttle.csrf(), 'correo': 'bloqueo@netlinks.pa', 'password': 'incorrecta'})
assert status == 422 and 'Demasiados intentos' in html
print('OK: catálogo, CRUD, horarios, login/bloqueo, roles, cabeceras, SQLi, CSRF, propiedad, transiciones, reseña/XSS y cupos.')
