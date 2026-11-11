#!/usr/bin/env bash
set -euo pipefail
# Solo para una base local ya importada, con README.local.md generado.
# Ejemplo: DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASSWORD='' bash tests/run-local.sh
PHP_BIN="${PHP_BIN:-php}"
port="$(python -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()')"
"$PHP_BIN" -S "127.0.0.1:$port" -t public >/tmp/opencode/netlinks-smoke-http.log 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true' EXIT
python tests/smoke.py "http://127.0.0.1:$port/"
