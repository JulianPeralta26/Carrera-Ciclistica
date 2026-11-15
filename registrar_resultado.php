<?php
require_once 'db.php';

$filtro_modalidad = intval($_GET['modalidad'] ?? 1);

// Obtener catálogo de modalidades
$stmt_mods = $pdo->query("
    SELECT m.id_modalidad, c.nombre AS categoria, r.distancia_km
    FROM modalidades m
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    ORDER BY c.id_categoria, r.distancia_km
");
$modalidades = $stmt_mods->fetchAll();

// Procesar guardado de resultado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_resultado'])) {
    $id_inscripcion   = intval($_POST['id_inscripcion'] ?? 0);
    $hora_llegada     = trim($_POST['hora_llegada'] ?? '');
    $puesto_modalidad = intval($_POST['puesto_modalidad'] ?? 0);
    $id_estado        = intval($_POST['id_estado'] ?? 3); // 3 = Finalizado

    if ($id_inscripcion > 0) {
        try {
            // Verificar si ya existe resultado para esta inscripción
            $stmt_check = $pdo->prepare("SELECT id_resultado FROM resultados WHERE id_inscripcion = ?");
            $stmt_check->execute([$id_inscripcion]);
            $existe = $stmt_check->fetch();

            if ($existe) {
                $stmt_upd = $pdo->prepare("
                    UPDATE resultados 
                    SET hora_llegada = ?, puesto_modalidad = ?, id_estado = ?
                    WHERE id_inscripcion = ?
                ");
                $stmt_upd->execute([
                    !empty($hora_llegada) ? $hora_llegada : null,
                    $puesto_modalidad > 0 ? $puesto_modalidad : null,
                    $id_estado,
                    $id_inscripcion
                ]);
            } else {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO resultados (id_inscripcion, hora_llegada, puesto_modalidad, id_estado)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_ins->execute([
                    $id_inscripcion,
                    !empty($hora_llegada) ? $hora_llegada : null,
                    $puesto_modalidad > 0 ? $puesto_modalidad : null,
                    $id_estado
                ]);
            }

            $_SESSION['mensaje'] = "Resultado guardado correctamente.";
            $_SESSION['tipo_mensaje'] = "success";
            header("Location: registrar_resultado.php?modalidad={$filtro_modalidad}");
            exit;

        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al guardar el resultado: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "danger";
        }
    }
}

// Obtener inscritos en la modalidad seleccionada
$stmt_inscritos = $pdo->prepare("
    SELECT 
        i.id_inscripcion,
        p.id_participante,
        p.cedula,
        CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS participante,
        gs.color AS color_salida,
        gs.hora_salida,
        res.hora_llegada,
        res.puesto_modalidad,
        res.id_estado AS estado_resultado,
        COALESCE(res.tiempo_empleado, TIMEDIFF(res.hora_llegada, gs.hora_salida)) AS tiempo_calc,
        est_insc.desc_estado AS estado_inscripcion
    FROM inscripciones i
    JOIN participantes p ON i.id_participante = p.id_participante
    JOIN grupos_salida gs ON i.id_grupo_salida = gs.id_grupo
    JOIN estados est_insc ON i.id_estado = est_insc.id_estado
    LEFT JOIN resultados res ON i.id_inscripcion = res.id_inscripcion
    WHERE i.id_modalidad = ? AND i.id_estado = 1
    ORDER BY res.puesto_modalidad ASC, i.id_inscripcion ASC
");
$stmt_inscritos->execute([$filtro_modalidad]);
$inscritos = $stmt_inscritos->fetchAll();

// Obtener estados para el resultado
$stmt_estados = $pdo->query("SELECT id_estado, desc_estado FROM estados WHERE id_estado >= 3");
$estados_resultado = $stmt_estados->fetchAll();

require_once 'header.php';
?>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-stopwatch text-primary me-2"></i>Cargar Tiempos y Resultados de Carrera</h5>
            <small class="text-muted">Ingresa la hora de llegada de cada competidor para computar automáticamente los tiempos y puestos.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 fw-semibold text-nowrap">Modalidad:</label>
            <select class="form-select form-select-sm" onchange="location.href='registrar_resultado.php?modalidad=' + this.value">
                <?php foreach ($modalidades as $mod): ?>
                    <option value="<?= $mod['id_modalidad'] ?>" <?= $filtro_modalidad === $mod['id_modalidad'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mod['categoria']) ?> - <?= $mod['distancia_km'] ?> Km
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID Insc.</th>
                        <th>Cédula</th>
                        <th>Participante</th>
                        <th>Bloque Salida</th>
                        <th>Hora Salida</th>
                        <th>Hora Llegada</th>
                        <th>Tiempo Empleado</th>
                        <th>Puesto Oficial</th>
                        <th>Estado Carrera</th>
                        <th class="text-end pe-3">Guardar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inscritos)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                No hay corredores activos inscritos en esta modalidad.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inscritos as $row): ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="id_inscripcion" value="<?= $row['id_inscripcion'] ?>">
                                    <input type="hidden" name="guardar_resultado" value="1">

                                    <td class="ps-3 fw-bold">#<?= $row['id_inscripcion'] ?></td>
                                    <td class="font-monospace"><?= htmlspecialchars($row['cedula']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['participante']) ?></td>
                                    <td>
                                        <span class="badge badge-color-<?= strtolower($row['color_salida']) ?>">
                                            <?= htmlspecialchars($row['color_salida']) ?>
                                        </span>
                                    </td>
                                    <td class="font-monospace text-muted"><?= substr($row['hora_salida'], 0, 8) ?></td>
                                    <td style="max-width: 130px;">
                                        <input type="time" step="1" name="hora_llegada" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($row['hora_llegada'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace fs-6">
                                            <?= htmlspecialchars($row['tiempo_calc'] ?? '--:--:--') ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 90px;">
                                        <input type="number" min="1" max="999" name="puesto_modalidad" class="form-control form-control-sm text-center fw-bold" placeholder="Puesto" value="<?= htmlspecialchars($row['puesto_modalidad'] ?? '') ?>">
                                    </td>
                                    <td style="max-width: 140px;">
                                        <select name="id_estado" class="form-select form-select-sm">
                                            <?php foreach ($estados_resultado as $est): ?>
                                                <option value="<?= $est['id_estado'] ?>" <?= ($row['estado_resultado'] == $est['id_estado']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($est['desc_estado']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="submit" class="btn btn-primary btn-sm px-3">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
