<?php
declare(strict_types=1);

function register_user(): void {
    $name = input('nombre', 80); $surname = input('apellido', 80);
    $email = valid_email(input('correo', 190)); $password = input('password', 255);
    if (!$name || !$surname || mb_strlen($password) < 8) throw new DomainException('Complete nombre, apellido y contraseña de mínimo 8 caracteres.');
    if (q('SELECT id FROM usuarios WHERE correo=?', [$email])->fetch()) throw new DomainException('No se pudo completar el registro con ese correo.');
    q('INSERT INTO usuarios(rol_id,nombre,apellido,correo,password_hash) VALUES(2,?,?,?,?)',
      [$name,$surname,$email,password_hash($password, PASSWORD_DEFAULT)]);
    notice('Cuenta creada. Ya puede iniciar sesión.'); go('login');
}
function login_user(): void {
    $email = valid_email(input('correo', 190)); $password = input('password', 255);
    $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempt = q('SELECT fallos,bloqueado_hasta FROM intentos_login WHERE clave=?', [$key])->fetch();
    if ($attempt && $attempt['bloqueado_hasta'] && strtotime($attempt['bloqueado_hasta']) > time())
        throw new DomainException('Demasiados intentos. Espere 15 minutos y pruebe de nuevo.');
    $user = q('SELECT id,password_hash FROM usuarios WHERE correo=? AND estado=\'ACTIVO\'', [$email])->fetch();
    // Evita revelar mediante el mensaje si existe el correo.
    $dummy = '$2y$10$05YwBGV4qNzGNdYS51mc6eHTPnb6p7v7dW.MCLZRBlqFkFYOmMuBq';
    if (!password_verify($password, $user['password_hash'] ?? $dummy)) {
        q("INSERT INTO intentos_login(clave,fallos,bloqueado_hasta) VALUES(?,1,NULL)
           ON DUPLICATE KEY UPDATE fallos=IF(bloqueado_hasta IS NOT NULL AND bloqueado_hasta<=NOW(),1,LEAST(fallos+1,255)),
           bloqueado_hasta=IF(fallos>=5,DATE_ADD(NOW(), INTERVAL 15 MINUTE),NULL)", [$key]);
        throw new DomainException('Credenciales inválidas. Tras cinco intentos fallidos se bloquea el acceso durante 15 minutos.');
    }
    q('DELETE FROM intentos_login WHERE clave=?', [$key]);
    session_regenerate_id(true); $_SESSION['uid'] = $user['id'];
    notice('Bienvenido a Netlinks.'); go('home');
}
function save_entity(string $entity): void {
    must_login(1);
    if (!in_array($entity, ['categorias','servicios','horarios'], true)) throw new DomainException('Módulo inválido.');
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? positive($_POST['id']) : null;
    if ($entity === 'categorias') {
        $name = input('nombre', 100); $description = input('descripcion', 500);
        if (!$name || !$description) throw new DomainException('Complete los campos obligatorios.');
        if ($id) { $s = q('UPDATE categorias SET nombre=?,descripcion=? WHERE id=?', [$name,$description,$id]); }
        else { $s = q('INSERT INTO categorias(nombre,descripcion) VALUES(?,?)', [$name,$description]); }
    } elseif ($entity === 'servicios') {
        $category = positive($_POST['categoria_id'] ?? null);
        if (!q('SELECT id FROM categorias WHERE id=? AND estado=\'ACTIVO\'', [$category])->fetch()) throw new DomainException('Seleccione una categoría activa.');
        $name = input('nombre', 120); $description = input('descripcion', 2000);
        $price = input('precio', 12); $duration = positive($_POST['duracion_min'] ?? null);
        if (!$name || !$description || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) || $duration > 1440)
            throw new DomainException('Revise nombre, descripción, precio no negativo y duración positiva.');
        if ($id) { $s = q('UPDATE servicios SET categoria_id=?,nombre=?,descripcion=?,precio=?,duracion_min=? WHERE id=?', [$category,$name,$description,$price,$duration,$id]); }
        else { $s = q('INSERT INTO servicios(categoria_id,nombre,descripcion,precio,duracion_min) VALUES(?,?,?,?,?)', [$category,$name,$description,$price,$duration]); }
    } else {
        save_slot($id); notice('Horario guardado.'); go('admin', ['entity' => $entity]);
    }
    if ($id && !$s->rowCount() && !q('SELECT id FROM ' . $entity . ' WHERE id=?', [$id])->fetch()) throw new DomainException('Registro no encontrado.');
    notice('Registro guardado.'); go('admin', ['entity' => $entity]);
}
function save_slot(?int $id): void {
    $service = positive($_POST['servicio_id'] ?? null);
    if (!q("SELECT s.id FROM servicios s JOIN categorias c ON c.id=s.categoria_id WHERE s.id=? AND s.estado='ACTIVO' AND c.estado='ACTIVO'", [$service])->fetch()) throw new DomainException('Seleccione un servicio activo.');
    $date = input('fecha', 10); $start = input('hora_inicio', 5); $end = input('hora_fin', 5);
    valid_slot($date,$start,$end);
    $total = positive($_POST['cupos_total'] ?? null);
    if ($total > 1000) throw new DomainException('Máximo 1000 cupos.');
    if (!$id) {
        q('INSERT INTO horarios(servicio_id,fecha,hora_inicio,hora_fin,cupos_total,cupos_disponibles) VALUES(?,?,?,?,?,?)', [$service,$date,$start,$end,$total,$total]);
        return;
    }
    db()->beginTransaction();
    try {
        $old = q('SELECT * FROM horarios WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$old) throw new DomainException('Horario no encontrado.');
        $occupied = (int)$old['cupos_total'] - (int)$old['cupos_disponibles'];
        if ($total < $occupied) throw new DomainException('El total no puede ser inferior a las reservas no canceladas.');
        $dependencies = q('SELECT COUNT(*) FROM reservas WHERE horario_id=?', [$id])->fetchColumn();
        if ($dependencies && ((int)$old['servicio_id'] !== $service || $old['fecha'] !== $date || substr($old['hora_inicio'],0,5) !== $start || substr($old['hora_fin'],0,5) !== $end))
            throw new DomainException('Un horario con reservas solo permite ajustar los cupos.');
        q('UPDATE horarios SET servicio_id=?,fecha=?,hora_inicio=?,hora_fin=?,cupos_total=?,cupos_disponibles=? WHERE id=?',
          [$service,$date,$start,$end,$total,$total-$occupied,$id]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
function toggle_entity(string $entity): void {
    must_login(1);
    if (!in_array($entity, ['categorias','servicios','horarios'], true)) throw new DomainException('Módulo inválido.');
    $id = positive($_POST['id'] ?? null);
    // Solo desactivación lógica: referencias históricas y reseñas se conservan.
    $s = q('UPDATE ' . $entity . " SET estado=IF(estado='ACTIVO','INACTIVO','ACTIVO') WHERE id=?", [$id]);
    if (!$s->rowCount()) throw new DomainException('Registro no encontrado.');
    notice('Estado actualizado.'); go('admin', ['entity'=>$entity]);
}
function book_slot(array $user): void {
    $slotId = positive($_POST['horario_id'] ?? null);
    db()->beginTransaction();
    try {
        $slot = q("SELECT h.*,s.estado AS servicio_estado,c.estado AS categoria_estado
            FROM horarios h JOIN servicios s ON s.id=h.servicio_id JOIN categorias c ON c.id=s.categoria_id
            WHERE h.id=? FOR UPDATE", [$slotId])->fetch();
        if (!$slot || $slot['estado'] !== 'ACTIVO' || $slot['servicio_estado'] !== 'ACTIVO' || $slot['categoria_estado'] !== 'ACTIVO'
            || strtotime($slot['fecha'].' '.$slot['hora_inicio']) <= time()) throw new DomainException('Este horario no está disponible.');
        if ((int)$slot['servicio_id'] !== positive($_POST['servicio_id'] ?? null)) throw new DomainException('El horario no pertenece al servicio.');
        if (q("SELECT id FROM reservas WHERE cliente_id=? AND horario_id=? AND estado<>'CANCELADA'", [$user['id'],$slotId])->fetch())
            throw new DomainException('Ya tiene una reserva activa para este horario.');
        $updated = q("UPDATE horarios SET cupos_disponibles=cupos_disponibles-1 WHERE id=? AND cupos_disponibles>0", [$slotId]);
        if (!$updated->rowCount()) throw new DomainException('Se agotaron los cupos.');
        q('INSERT INTO reservas(cliente_id,servicio_id,horario_id) VALUES(?,?,?)', [$user['id'],$slot['servicio_id'],$slotId]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    notice('Reserva registrada como PENDIENTE.'); go('reservas');
}
function change_booking(array $user, bool $admin): void {
    $id = positive($_POST['id'] ?? null);
    $target = $admin ? input('estado', 10) : 'CANCELADA';
    db()->beginTransaction();
    try {
        $ref = q('SELECT horario_id FROM reservas WHERE id=?', [$id])->fetch();
        if (!$ref) throw new DomainException('Reserva no encontrada.');
        $slot = q('SELECT * FROM horarios WHERE id=? FOR UPDATE', [$ref['horario_id']])->fetch();
        $booking = q('SELECT * FROM reservas WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$admin && (int)$booking['cliente_id'] !== (int)$user['id']) throw new DomainException('Reserva no encontrada.');
        $state = $booking['estado'];
        if ($target === 'CANCELADA') {
            if (!in_array($state,['PENDIENTE','CONFIRMADA'],true)) throw new DomainException('La reserva no se puede cancelar.');
            if (!$admin && strtotime($slot['fecha'].' '.$slot['hora_inicio']) <= time()) throw new DomainException('La cita ya comenzó.');
            q("UPDATE reservas SET estado='CANCELADA' WHERE id=? AND estado=?", [$id,$state]);
            $updated = q('UPDATE horarios SET cupos_disponibles=cupos_disponibles+1 WHERE id=? AND cupos_disponibles<cupos_total', [$slot['id']]);
            if (!$updated->rowCount()) throw new DomainException('No se pudo devolver el cupo.');
        } elseif ($admin && (($state === 'PENDIENTE' && $target === 'CONFIRMADA') || ($state === 'CONFIRMADA' && $target === 'REALIZADA'))) {
            q('UPDATE reservas SET estado=? WHERE id=? AND estado=?', [$target,$id,$state]);
        } else throw new DomainException('Transición de estado no permitida.');
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    notice('Reserva actualizada.'); go($admin ? 'admin-reservas' : 'reservas');
}
function add_review(array $user): void {
    $id = positive($_POST['reserva_id'] ?? null);
    $score = positive($_POST['calificacion'] ?? null); $comment = input('comentario', 1500);
    if ($score > 5 || !$comment) throw new DomainException('Califique del 1 al 5 y escriba un comentario.');
    $booking = q("SELECT id FROM reservas WHERE id=? AND cliente_id=? AND estado='REALIZADA'", [$id,$user['id']])->fetch();
    if (!$booking) throw new DomainException('Solo puede reseñar una reserva propia y realizada.');
    if (q('SELECT id FROM resenas WHERE reserva_id=?', [$id])->fetch()) throw new DomainException('Esta reserva ya tiene una reseña.');
    q('INSERT INTO resenas(reserva_id,cliente_id,calificacion,comentario) VALUES(?,?,?,?)', [$id,$user['id'],$score,$comment]);
    notice('Gracias por su reseña.'); go('reservas');
}
