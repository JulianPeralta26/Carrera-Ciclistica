<?php
require_once 'db.php';
require_once 'header.php';

// Filtro de modalidad
$filtro_modalidad = intval($_GET['modalidad'] ?? 0);

// Obtener catálogo de modalidades
$stmt_mods = $pdo->query("
    SELECT m.id_modalidad, c.nombre AS categoria, r.distancia_km, m.desc_modalidad
    FROM modalidades m
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    ORDER BY c.id_categoria, r.distancia_km
");
$modalidades = $stmt_mods->fetchAll();

// Consulta base de resultados utilizando la vista normalizada
$sql = "
    SELECT 
        m.id_modalidad,
        e.nombre AS evento,
        c.nombre AS categoria,
        CONCAT(r.distancia_km, ' Km') AS recorrido,
        p.cedula,
        CONCAT(
            p.primer_nombre, ' ', 
            IFNULL(CONCAT(p.segundo_nombre, ' '), ''), 
            p.primer_apellido, ' ', 
            IFNULL(p.segundo_apellido, '')
        ) AS participante,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, e.fecha_evento) AS edad,
        gs.color AS color_salida,
        gs.hora_salida,
        res.hora_llegada,
        COALESCE(
            res.tiempo_empleado, 
            TIMEDIFF(res.hora_llegada, gs.hora_salida)
        ) AS tiempo_calculado,
        res.puesto_modalidad,
        res.puesto_general,
        est.desc_estado AS estado
    FROM resultados res
    JOIN inscripciones i ON res.id_inscripcion = i.id_inscripcion
    JOIN participantes p ON i.id_participante = p.id_participante
    JOIN modalidades m ON i.id_modalidad = m.id_modalidad
    JOIN eventos e ON m.id_evento = e.id_evento
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    JOIN grupos_salida gs ON i.id_grupo_salida = gs.id_grupo
    JOIN estados est ON res.id_estado = est.id_estado
    WHERE i.id_estado = 1
";

if ($filtro_modalidad > 0) {
    $sql .= " AND m.id_modalidad = :mod ";
}

$sql .= " ORDER BY m.id_modalidad ASC, res.puesto_modalidad ASC ";

$stmt = $pdo->prepare($sql);
if ($filtro_modalidad > 0) {
    $stmt->execute([':mod' => $filtro_modalidad]);
} else {
    $stmt->execute();
}
$todos_resultados = $stmt->fetchAll();

// Agrupar resultados por modalidad
$resultados_por_mod = [];
foreach ($todos_resultados as $row) {
    $resultados_por_mod[$row['id_modalidad']][] = $row;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-trophy-fill text-warning me-2"></i>Clasificación y Ganadores por Modalidad</h3>
        <p class="text-muted mb-0">Podio oficial, tiempos individuales y puestos clasificados en la carrera Uniespinal 2026.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 fw-semibold text-nowrap d-none d-sm-block">Filtrar:</label>
            <select name="modalidad" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">-- Todas las Modalidades --</option>
                <?php foreach ($modalidades as $mod): ?>
                    <option value="<?= $mod['id_modalidad'] ?>" <?= $filtro_modalidad === $mod['id_modalidad'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mod['categoria']) ?> (<?= $mod['distancia_km'] ?> Km)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="registrar_resultado.php" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-stopwatch me-1"></i> Cargar Resultados
        </a>
    </div>
</div>

<?php 
// Filtrar las modalidades a mostrar
$modalidades_a_mostrar = array_filter($modalidades, function($m) use ($filtro_modalidad) {
    return ($filtro_modalidad === 0 || $filtro_modalidad === $m['id_modalidad']);
});
?>

<?php foreach ($modalidades_a_mostrar as $mod): ?>
    <?php 
    $id_m = $mod['id_modalidad'];
    $participantes_mod = $resultados_por_mod[$id_m] ?? [];
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark fs-6 px-3 py-2"><?= htmlspecialchars($mod['categoria']) ?></span>
                <span class="badge bg-primary fs-6 px-3 py-2"><i class="bi bi-geo-alt"></i> <?= $mod['distancia_km'] ?> Km</span>
                <span class="text-muted small ms-2 d-none d-md-inline"><?= htmlspecialchars($mod['desc_modalidad']) ?></span>
            </div>
            <div>
                <span class="badge bg-light text-dark border">
                    <?= count($participantes_mod) ?> finalistas registrados
                </span>
            </div>
        </div>

        <div class="card-body p-4">

            <?php if (empty($participantes_mod)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-flag fs-2 d-block mb-2 text-secondary"></i>
                    Aún no se han registrado tiempos ni resultados oficiales para esta modalidad.
                    <div class="mt-2">
                        <a href="registrar_resultado.php?modalidad=<?= $id_m ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Registrar tiempos para esta modalidad
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Podio de Ganadores (Top 3) -->
                <div class="row g-3 mb-4">
                    <?php 
                    $podio = array_slice($participantes_mod, 0, 3);
                    $medallas = [
                        1 => ['icono' => '🥇', 'titulo' => 'Campeón (1° Lugar)', 'clase' => 'border-warning bg-warning bg-opacity-10 text-dark'],
                        2 => ['icono' => '🥈', 'titulo' => 'Subcampeón (2° Lugar)', 'clase' => 'border-secondary bg-secondary bg-opacity-10 text-dark'],
                        3 => ['icono' => '🥉', 'titulo' => 'Tercer Puesto (3° Lugar)', 'clase' => 'border-danger bg-danger bg-opacity-10 text-dark'],
                    ];
                    foreach ($podio as $idx => $ganador):
                        $puesto = $ganador['puesto_modalidad'] ?? ($idx + 1);
                        $info_medalla = $medallas[$puesto] ?? ['icono' => '🎖️', 'titulo' => "Puesto #{$puesto}", 'clase' => 'border-info'];
                    ?>
                        <div class="col-12 col-md-4">
                            <div class="card p-3 border-2 h-100 <?= $info_medalla['clase'] ?> shadow-sm">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2"><?= $info_medalla['icono'] ?></span>
                                    <span class="badge bg-dark"><?= $info_medalla['titulo'] ?></span>
                                </div>
                                <h5 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($ganador['participante']) ?></h5>
                                <div class="small text-muted mb-2">Cédula: <?= htmlspecialchars($ganador['cedula']) ?> | <?= $ganador['edad'] ?> años</div>
                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top border-secondary border-opacity-25">
                                    <span class="small text-muted">Tiempo Oficial:</span>
                                    <strong class="fs-5 font-monospace text-primary"><?= htmlspecialchars($ganador['tiempo_calculado'] ?? 'N/D') ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tabla de Clasificación Completa -->
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ol me-1"></i> Tabla Completa de Posiciones</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 text-center" style="width: 80px;">Puesto</th>
                                <th>Cédula</th>
                                <th>Participante</th>
                                <th>Color de Salida</th>
                                <th>Hora Salida</th>
                                <th>Hora Llegada</th>
                                <th>Tiempo Empleado</th>
                                <th class="text-end pe-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantes_mod as $r): ?>
                                <?php 
                                $es_oro = ($r['puesto_modalidad'] == 1);
                                $badge_color = 'badge-color-' . strtolower($r['color_salida']);
                                ?>
                                <tr class="<?= $es_oro ? 'table-warning fw-semibold' : '' ?>">
                                    <td class="ps-3 text-center">
                                        <?php if ($r['puesto_modalidad'] == 1): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1 fs-6">🥇 1°</span>
                                        <?php elseif ($r['puesto_modalidad'] == 2): ?>
                                            <span class="badge bg-secondary text-white px-2 py-1 fs-6">🥈 2°</span>
                                        <?php elseif ($r['puesto_modalidad'] == 3): ?>
                                            <span class="badge bg-danger text-white px-2 py-1 fs-6">🥉 3°</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border px-2 py-1">#<?= $r['puesto_modalidad'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-monospace"><?= htmlspecialchars($r['cedula']) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($r['participante']) ?></div>
                                        <small class="text-muted"><?= $r['edad'] ?> años</small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_color ?>"><?= htmlspecialchars($r['color_salida']) ?></span>
                                    </td>
                                    <td class="font-monospace text-muted"><?= substr($r['hora_salida'], 0, 8) ?></td>
                                    <td class="font-monospace text-muted"><?= htmlspecialchars($r['hora_llegada'] ?? 'N/D') ?></td>
                                    <td>
                                        <span class="badge bg-primary fs-6 font-monospace px-2 py-1">
                                            <?= htmlspecialchars($r['tiempo_calculado'] ?? 'N/D') ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="badge bg-success"><?= htmlspecialchars($r['estado']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>
<?php endforeach; ?>

<?php require_once 'footer.php'; ?>
