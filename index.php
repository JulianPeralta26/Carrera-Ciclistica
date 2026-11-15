<?php
require_once 'db.php';
require_once 'header.php';

// Filtro de búsqueda
$busqueda = trim($_GET['q'] ?? '');

// Consulta para listar participantes con sus contactos e inscripciones agrupadas
$sql = "
    SELECT 
        p.id_participante,
        p.cedula,
        CONCAT(
            p.primer_nombre, ' ', 
            IFNULL(CONCAT(p.segundo_nombre, ' '), ''), 
            p.primer_apellido, ' ', 
            IFNULL(p.segundo_apellido, '')
        ) AS nombre_completo,
        p.direccion,
        p.fecha_nacimiento,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, (SELECT fecha_evento FROM eventos WHERE id_evento = 1)) AS edad,
        c.telefono,
        c.telefono_emergencia,
        c.nombre_contacto_emergencia,
        GROUP_CONCAT(
            CONCAT(
                i.id_inscripcion, '::',
                cat.nombre, ' ', r.distancia_km, 'K::',
                gs.color, '::',
                gs.hora_salida, '::',
                est.desc_estado, '::',
                est.id_estado
            ) SEPARATOR '||'
        ) AS inscripciones_info
    FROM participantes p
    LEFT JOIN contactos c ON p.id_participante = c.id_participante
    LEFT JOIN inscripciones i ON p.id_participante = i.id_participante
    LEFT JOIN modalidades m ON i.id_modalidad = m.id_modalidad
    LEFT JOIN categorias cat ON m.id_categoria = cat.id_categoria
    LEFT JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    LEFT JOIN grupos_salida gs ON i.id_grupo_salida = gs.id_grupo
    LEFT JOIN estados est ON i.id_estado = est.id_estado
";

if (!empty($busqueda)) {
    $sql .= " WHERE p.cedula LIKE ? 
                 OR p.primer_nombre LIKE ? 
                 OR p.segundo_nombre LIKE ? 
                 OR p.primer_apellido LIKE ? 
                 OR p.segundo_apellido LIKE ?
                 OR CONCAT(p.primer_nombre, ' ', p.primer_apellido) LIKE ? ";
}

$sql .= " GROUP BY p.id_participante ORDER BY p.id_participante DESC ";

$stmt = $pdo->prepare($sql);
if (!empty($busqueda)) {
    $param = "%{$busqueda}%";
    $stmt->execute([$param, $param, $param, $param, $param, $param]);
} else {
    $stmt->execute();
}
$participantes = $stmt->fetchAll();

// Estadísticas rápidas
$total_participantes = count($participantes);
$stmt_inscripciones = $pdo->query("SELECT COUNT(*) FROM inscripciones WHERE id_estado = 1");
$total_inscritos_activos = $stmt_inscripciones->fetchColumn();
$stmt_cancelados = $pdo->query("SELECT COUNT(*) FROM inscripciones WHERE id_estado = 2");
$total_cancelados = $stmt_cancelados->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card p-3 bg-white border-start border-primary border-4 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Total Participantes</span>
                    <h2 class="mb-0 fw-bold text-primary"><?= $total_participantes ?></h2>
                </div>
                <div class="fs-1 text-primary opacity-50"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 bg-white border-start border-success border-4 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Inscripciones Activas</span>
                    <h2 class="mb-0 fw-bold text-success"><?= $total_inscritos_activos ?></h2>
                </div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 bg-white border-start border-danger border-4 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Inscripciones Canceladas</span>
                    <h2 class="mb-0 fw-bold text-danger"><?= $total_cancelados ?></h2>
                </div>
                <div class="fs-1 text-danger opacity-50"><i class="bi bi-x-circle"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Listado de Participantes e Inscripciones</h5>
            <small class="text-muted">Gestiona corredores, contactos, asignación de oleadas por edad y estado de inscripción.</small>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar cédula o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                <?php if (!empty($busqueda)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x"></i></a>
                <?php endif; ?>
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
            </form>
            <a href="crear_participante.php" class="btn btn-primary btn-sm px-3 d-flex align-items-center gap-1">
                <i class="bi bi-plus-lg"></i> Inscribir Participante
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Cédula</th>
                        <th>Participante</th>
                        <th>Edad</th>
                        <th>Contacto Principal</th>
                        <th>Tel. Emergencia</th>
                        <th>Modalidades & Turno de Salida</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participantes)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No se encontraron participantes registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participantes as $p): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-secondary">#<?= $p['id_participante'] ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-6">
                                        <?= htmlspecialchars($p['cedula']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($p['nombre_completo']) ?></div>
                                    <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['direccion']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $p['edad'] !== null ? $p['edad'] . ' años' : 'N/D' ?></span>
                                </td>
                                <td>
                                    <i class="bi bi-telephone text-primary me-1"></i><?= htmlspecialchars($p['telefono'] ?? 'Sin tel') ?>
                                </td>
                                <td>
                                    <i class="bi bi-shield-exclamation text-danger me-1"></i><?= htmlspecialchars($p['telefono_emergencia'] ?? 'Sin contacto') ?>
                                    <?php if (!empty($p['nombre_contacto_emergencia'])): ?>
                                        <div class="small text-muted">(<?= htmlspecialchars($p['nombre_contacto_emergencia']) ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($p['inscripciones_info'])): ?>
                                        <span class="text-muted small fst-italic">Sin modalidades activas</span>
                                    <?php else: ?>
                                        <?php 
                                        $modalidades = explode('||', $p['inscripciones_info']);
                                        foreach ($modalidades as $mod_item):
                                            $partes = explode('::', $mod_item);
                                            if (count($partes) < 6) continue;
                                            list($id_insc, $mod_nombre, $color, $hora, $desc_estado, $id_estado) = $partes;
                                            
                                            $badge_class = 'badge-color-' . strtolower($color);
                                            $is_cancelado = ($id_estado == 2);
                                        ?>
                                            <div class="d-inline-flex align-items-center gap-1 mb-1 p-1 px-2 border rounded <?= $is_cancelado ? 'bg-light text-muted text-decoration-line-through' : 'bg-white' ?>" style="font-size: 0.82rem;">
                                                <strong><?= htmlspecialchars($mod_nombre) ?></strong>
                                                <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($color) ?></span>
                                                <span class="text-muted"><i class="bi bi-clock"></i> <?= substr($hora, 0, 5) ?></span>
                                                <?php if ($is_cancelado): ?>
                                                    <span class="badge bg-danger">Cancelado</span>
                                                    <a href="cambiar_estado.php?id_inscripcion=<?= $id_insc ?>&accion=reactivar" class="btn btn-link btn-sm p-0 text-success text-decoration-none" title="Reactivar inscripción">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?= htmlspecialchars($desc_estado) ?></span>
                                                    <a href="cambiar_estado.php?id_inscripcion=<?= $id_insc ?>&accion=cancelar" class="btn btn-link btn-sm p-0 text-danger text-decoration-none" title="Cancelar inscripción" onclick="return confirm('¿Deseas marcar como cancelada esta inscripción?');">
                                                        <i class="bi bi-x-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div><br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="editar_participante.php?id=<?= $p['id_participante'] ?>" class="btn btn-outline-primary" title="Editar Participante">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="inscribir_modalidad.php?id=<?= $p['id_participante'] ?>" class="btn btn-outline-success" title="Inscribir en otra modalidad">
                                            <i class="bi bi-plus-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
