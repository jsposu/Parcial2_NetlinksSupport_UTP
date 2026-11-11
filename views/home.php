<?php
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$category = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? positive($_GET['categoria']) : null;
$categories = q("SELECT id,nombre FROM categorias WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll();
$services = q("SELECT s.*,c.nombre AS categoria FROM servicios s JOIN categorias c ON c.id=s.categoria_id
 WHERE s.estado='ACTIVO' AND c.estado='ACTIVO' AND s.nombre LIKE ? AND (? IS NULL OR s.categoria_id=?) ORDER BY s.id",
 ['%' . $search . '%',$category,$category])->fetchAll();
?>
<main><section class="hero"><div class="container hero-grid"><div><span class="eyebrow">SOPORTE TÉCNICO SIN VUELTAS</span>
<h1>Tu tecnología vuelve a <em>funcionar.</em></h1><p>Reserva diagnósticos, mantenimiento y ayuda con tus dispositivos. Elige el horario que te convenga y sigue tu cita desde un solo lugar.</p>
<a class="button" href="#catalogo">Explorar servicios <span aria-hidden="true">↗</span></a></div>
<div class="hero-illustration" aria-hidden="true"><div class="screen"><div class="screen-top"><i></i><i></i><i></i></div><div class="screen-symbol">✳</div><div class="screen-lines"><b></b><b></b><b></b></div></div><div class="floating-tag">✓ Sistema en buenas manos</div></div></div></section>
<section class="container section" id="catalogo"><div class="section-title"><div><span class="eyebrow">LO QUE HACEMOS</span><h2>Soluciones a un clic</h2></div><p>Atención clara, precios visibles y horarios para ti.</p></div>
<form class="filters" method="get" action="index.php"><input type="hidden" name="route" value="home"><label>Buscar servicio<input type="search" name="q" placeholder="Ej. diagnóstico" value="<?= e($search) ?>" maxlength="120"></label>
<label>Categoría<select name="categoria"><option value="">Todas las categorías</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat['id']) ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['nombre']) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Buscar</button></form>
<div class="cards"><?php foreach ($services as $service): ?><article class="card"><div class="card-icon" aria-hidden="true">✳</div><span class="pill"><?= e($service['categoria']) ?></span><h3><?= e($service['nombre']) ?></h3><p><?= e($service['descripcion']) ?></p><div class="card-foot"><span><strong>$<?= e(number_format((float)$service['precio'],2)) ?></strong> <small>· <?= e($service['duracion_min']) ?> min</small></span><a href="<?= url('service',['id'=>$service['id']]) ?>" aria-label="Ver <?= e($service['nombre']) ?>">Ver detalles ↗</a></div></article><?php endforeach; ?></div>
<?php if (!$services): ?><p class="empty">No encontramos servicios con esos filtros. Prueba otra búsqueda.</p><?php endif; ?></section></main>
