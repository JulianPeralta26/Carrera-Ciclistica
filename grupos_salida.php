<?php
require_once 'db.php';

// ---------------------------------------------------------------------
// 1. PROCESAR ACCIONES: ELIMINAR GRUPO
// ---------------------------------------------------------------------
if (isset($_GET['eliminar'])) {
    $id_del = intval($_GET['eliminar']);
    if ($id_del > 0) {
        try {
            // Verificar si hay corredores inscritos en este grupo
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_grupo_salida = ?");
            $stmt_chk->execute([$id_del]);
            if ($stmt_chk->fetchColumn() > 0) {
                $_SESSION['mensaje'] = "No se puede eliminar el grupo #{$id_del} porque ya tiene corredores inscritos.";
                $_SESSION['tipo_mensaje'] = "danger";
            } else {
                $stmt_del = $pdo->prepare("DELETE FROM grupos_salida WHERE id_grupo = ?");
                $stmt_del->execute([$id_del]);
                $_SESSION['mensaje'] = "Grupo de salida #{$id_del} eliminado correctamente.";
                $_SESSION['tipo_mensaje'] = "success";
            }
        } catch (Exception $e) {
            $_SESSION['mensaje'] = "Error al eliminar el grupo: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "danger";
        }
    }
    // Conservar orden actual en la redirección
    $params = $_GET;
    unset($params['eliminar']);
    header("Location: grupos_salida.php?" . http_build_query($params));
    exit;
}

// ---------------------------------------------------------------------
// 2. PROCESAR ACCIONES: CREAR O EDITAR GRUPO
// ---------------------------------------------------------------------
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. CREAR NUEVO GRUPO
    if (isset($_POST['crear_grupo'])) {
        $id_modalidad = intval($_POST['id_modalidad'] ?? 0);
        $color        = trim($_POST['color'] ?? '');
        $edad_min     = intval($_POST['edad_minima'] ?? 0);
        $edad_max     = intval($_POST['edad_maxima'] ?? 0);
        $hora_salida  = trim($_POST['hora_salida'] ?? '');

        if ($id_modalidad <= 0) $errores[] = "Debes seleccionar una modalidad.";
        if (empty($color)) $errores[] = "Debes asignar un color al grupo.";
        if ($edad_min < 0 || $edad_max < $edad_min) $errores[] = "El rango de edad es inválido (la edad mínima no puede ser mayor que la máxima).";
        if (empty($hora_salida)) $errores[] = "Debes especificar la hora de salida.";

        if (empty($errores)) {
            try {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO grupos_salida (id_evento, id_modalidad, color, edad_minima, edad_maxima, hora_salida)
                    VALUES (1, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$id_modalidad, $color, $edad_min, $edad_max, $hora_salida]);

                $_SESSION['mensaje'] = "¡Grupo de salida color '{$color}' creado exitosamente!";
                $_SESSION['tipo_mensaje'] = "success";
                header("Location: grupos_salida.php?" . http_build_query($_GET));
                exit;
            } catch (Exception $e) {
                $errores[] = "Error al guardar el grupo: " . $e->getMessage();
            }
        }
    }

    // B. EDITAR GRUPO EXISTENTE
    if (isset($_POST['editar_grupo'])) {
        $id_grupo     = intval($_POST['id_grupo'] ?? 0);
        $id_modalidad = intval($_POST['id_modalidad'] ?? 0);
        $color        = trim($_POST['color'] ?? '');
        $edad_min     = intval($_POST['edad_minima'] ?? 0);
        $edad_max     = intval($_POST['edad_maxima'] ?? 0);
        $hora_salida  = trim($_POST['hora_salida'] ?? '');

        if ($id_grupo <= 0) $errores[] = "ID de grupo no válido.";
        if ($id_modalidad <= 0) $errores[] = "Debes seleccionar una modalidad.";
        if (empty($color)) $errores[] = "Debes asignar un color al grupo.";
        if ($edad_min < 0 || $edad_max < $edad_min) $errores[] = "El rango de edad es inválido.";
        if (empty($hora_salida)) $errores[] = "Debes especificar la hora de salida.";

        if (empty($errores)) {
            try {
                $stmt_upd = $pdo->prepare("
                    UPDATE grupos_salida 
                    SET id_modalidad = ?, color = ?, edad_minima = ?, edad_maxima = ?, hora_salida = ?
                    WHERE id_grupo = ?
                ");
                $stmt_upd->execute([$id_modalidad, $color, $edad_min, $edad_max, $hora_salida, $id_grupo]);

                $_SESSION['mensaje'] = "¡Grupo de salida #{$id_grupo} ({$color}) actualizado correctamente!";
                $_SESSION['tipo_mensaje'] = "success";
                header("Location: grupos_salida.php?" . http_build_query($_GET));
                exit;
            } catch (Exception $e) {
                $_SESSION['mensaje'] = "Error al actualizar el grupo: " . $e->getMessage();
                $_SESSION['tipo_mensaje'] = "danger";
            }
        }
    }
}

// ---------------------------------------------------------------------
// 3. OBTENER MODALIDADES PARA LOS FORMULARIOS
// ---------------------------------------------------------------------
$stmt_mods = $pdo->query("
    SELECT m.id_modalidad, c.nombre AS categoria, r.distancia_km
    FROM modalidades m
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    ORDER BY c.id_categoria, r.distancia_km
");
$modalidades = $stmt_mods->fetchAll();

// ---------------------------------------------------------------------
// 4. GESTIÓN DEL ORDENAMIENTO DE LA TABLA
// ---------------------------------------------------------------------
$ordenes_validos = [
    'id'         => 'gs.id_grupo',
    'modalidad'  => 'cat.nombre :dir, r.distancia_km :dir',
    'color'      => 'gs.color :dir',
    'edad'       => 'gs.edad_minima :dir, gs.edad_maxima :dir',
    'hora'       => 'gs.hora_salida :dir',
    'inscritos'  => 'total_inscritos :dir'
];

$col_orden = $_GET['orden'] ?? 'modalidad';
$dir_orden = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

if (!array_key_exists($col_orden, $ordenes_validos)) {
    $col_orden = 'modalidad';
}

$order_clause = str_replace(':dir', $dir_orden, $ordenes_validos[$col_orden]);
if ($col_orden === 'id') {
    $order_clause .= " {$dir_orden}";
}

// Función auxiliar para generar enlaces de cabecera con ordenamiento
function link_orden($columna, $col_actual, $dir_actual, $texto) {
    $nueva_dir = ($columna === $col_actual && $dir_actual === 'ASC') ? 'desc' : 'asc';
    $url = "grupos_salida.php?" . http_build_query(array_merge($_GET, ['orden' => $columna, 'dir' => $nueva_dir]));
    
    $icono = '<i class="bi bi-arrow-down-up opacity-25 ms-1"></i>';
    if ($columna === $col_actual) {
        $icono = ($dir_actual === 'ASC') 
            ? '<i class="bi bi-sort-down text-primary ms-1"></i>' 
            : '<i class="bi bi-sort-up text-primary ms-1"></i>';
    }
    return '<a href="' . htmlspecialchars($url) . '" class="text-decoration-none text-dark d-inline-flex align-items-center">' . htmlspecialchars($texto) . ' ' . $icono . '</a>';
}

// ---------------------------------------------------------------------
// 5. CONSULTA DE GRUPOS DE SALIDA CON ORDEN DINÁMICO
// ---------------------------------------------------------------------
$sql_grupos = "
    SELECT 
        gs.id_grupo,
        gs.id_modalidad,
        gs.color,
        gs.edad_minima,
        gs.edad_maxima,
        gs.hora_salida,
        cat.nombre AS categoria,
        r.distancia_km,
        COUNT(i.id_inscripcion) AS total_inscritos
    FROM grupos_salida gs
    JOIN modalidades m ON gs.id_modalidad = m.id_modalidad
    JOIN categorias cat ON m.id_categoria = cat.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    LEFT JOIN inscripciones i ON gs.id_grupo = i.id_grupo_salida
    GROUP BY gs.id_grupo
    ORDER BY {$order_clause}
";
$stmt_grupos = $pdo->query($sql_grupos);
$grupos = $stmt_grupos->fetchAll();

require_once 'header.php';
?>

<div class="row g-4 mb-4">
    <!-- Formulario para Crear Nuevo Grupo -->
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-warning me-2"></i>Crear Grupo de Salida</h5>
                <small class="text-secondary">Define modalidad, color libre, edades y hora de salida.</small>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">
                            <?php foreach ($errores as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="crear_grupo" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Modalidad de Carrera <span class="text-danger">*</span></label>
                        <select name="id_modalidad" class="form-select" required>
                            <option value="">-- Selecciona una Modalidad --</option>
                            <?php foreach ($modalidades as $mod): ?>
                                <option value="<?= $mod['id_modalidad'] ?>" <?= (isset($_POST['id_modalidad']) && $_POST['id_modalidad'] == $mod['id_modalidad']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mod['categoria']) ?> - <?= $mod['distancia_km'] ?> Km
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Color del Grupo <span class="text-danger">*</span></label>
                        <input type="text" name="color" class="form-control" list="colores_sugeridos" placeholder="Ej: Rojo, Azul, Naranja, Dorado, Morado..." required value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                        <datalist id="colores_sugeridos">
                            <option value="Amarillo">
                            <option value="Azul">
                            <option value="Rojo">
                            <option value="Verde">
                            <option value="Naranja">
                            <option value="Blanco">
                            <option value="Negro">
                            <option value="Morado">
                            <option value="Dorado">
                            <option value="Plateado">
                        </datalist>
                       
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Edad Mínima <span class="text-danger">*</span></label>
                            <input type="number" min="0" max="120" name="edad_minima" class="form-control" required placeholder="Ej: 18" value="<?= htmlspecialchars($_POST['edad_minima'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Edad Máxima <span class="text-danger">*</span></label>
                            <input type="number" min="0" max="120" name="edad_maxima" class="form-control" required placeholder="Ej: 35" value="<?= htmlspecialchars($_POST['edad_maxima'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Hora de Salida <span class="text-danger">*</span></label>
                        <input type="time" step="1" name="hora_salida" class="form-control font-monospace" required value="<?= htmlspecialchars($_POST['hora_salida'] ?? '08:00:00') ?>">
                        <small class="text-muted">Hora de partida exacta del grupo.</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-save me-1"></i> Guardar Grupo de Salida
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado de Grupos Configurados con Ordenamiento y Edición -->
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-palette-fill text-primary me-2"></i>Grupos de Salida Configurados</h5>
                    <small class="text-muted">Haz clic en las columnas para ordenar por modalidad, ID, edad, hora o inscritos.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 fs-6"><?= count($grupos) ?> Grupos</span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3"><?= link_orden('id', $col_orden, $dir_orden, 'ID') ?></th>
                                <th><?= link_orden('modalidad', $col_orden, $dir_orden, 'Modalidad') ?></th>
                                <th><?= link_orden('color', $col_orden, $dir_orden, 'Color') ?></th>
                                <th><?= link_orden('edad', $col_orden, $dir_orden, 'Rango de Edad') ?></th>
                                <th><?= link_orden('hora', $col_orden, $dir_orden, 'Hora de Salida') ?></th>
                                <th><?= link_orden('inscritos', $col_orden, $dir_orden, 'Personas Inscritas') ?></th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grupos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-palette fs-2 d-block mb-2"></i>
                                        No hay grupos de salida registrados. Agrega uno usando el formulario.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($grupos as $g): ?>
                                    <?php 
                                    $col = strtolower(trim($g['color']));
                                    $style = "background-color: #6c757d; color: #fff;";
                                    if ($col === 'amarillo') $style = "background-color: #ffc107; color: #000;";
                                    elseif ($col === 'azul') $style = "background-color: #0d6efd; color: #fff;";
                                    elseif ($col === 'rojo') $style = "background-color: #dc3545; color: #fff;";
                                    elseif ($col === 'verde') $style = "background-color: #198754; color: #fff;";
                                    elseif ($col === 'naranja') $style = "background-color: #fd7e14; color: #fff;";
                                    elseif ($col === 'morado') $style = "background-color: #6f42c1; color: #fff;";
                                    elseif ($col === 'negro') $style = "background-color: #212529; color: #fff;";
                                    elseif ($col === 'blanco') $style = "background-color: #f8f9fa; color: #000; border: 1px solid #ced4da;";
                                    elseif ($col === 'dorado') $style = "background-color: #d4af37; color: #000;";
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-muted">#<?= $g['id_grupo'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($g['categoria']) ?></strong>
                                            <span class="badge bg-light text-dark border ms-1"><?= $g['distancia_km'] ?> Km</span>
                                        </td>
                                        <td>
                                            <span class="badge px-3 py-2 fw-semibold" style="<?= $style ?>">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.65rem;"></i>
                                                <?= htmlspecialchars($g['color']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-secondary">
                                                <?= $g['edad_minima'] ?> a <?= $g['edad_maxima'] ?> años
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark font-monospace fs-6">
                                                <i class="bi bi-clock me-1"></i><?= substr($g['hora_salida'], 0, 8) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($g['total_inscritos'] > 0): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-people-fill me-1"></i><?= $g['total_inscritos'] ?> inscritos
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1">0 inscritos</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Botón para Abrir Modal de Edición -->
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $g['id_grupo'] ?>" title="Editar Grupo">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>

                                                <!-- Botón de Eliminar -->
                                                <?php if ($g['total_inscritos'] == 0): ?>
                                                    <a href="grupos_salida.php?eliminar=<?= $g['id_grupo'] ?>" class="btn btn-outline-danger" onclick="return confirm('¿Seguro de eliminar este grupo de salida?');" title="Eliminar Grupo">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary" disabled title="No se puede eliminar porque tiene corredores inscritos">
                                                        <i class="bi bi-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL DE EDICIÓN PARA EL GRUPO <?= $g['id_grupo'] ?> -->
                                    <div class="modal fade" id="modalEditar<?= $g['id_grupo'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $g['id_grupo'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="editar_grupo" value="1">
                                                    <input type="hidden" name="id_grupo" value="<?= $g['id_grupo'] ?>">

                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title" id="modalLabel<?= $g['id_grupo'] ?>">
                                                            <i class="bi bi-pencil-square me-2"></i>Editar Grupo de Salida #<?= $g['id_grupo'] ?>
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body p-4 text-start">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Modalidad de Carrera</label>
                                                            <select name="id_modalidad" class="form-select" required>
                                                                <?php foreach ($modalidades as $mod): ?>
                                                                    <option value="<?= $mod['id_modalidad'] ?>" <?= ($mod['id_modalidad'] == $g['id_modalidad']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($mod['categoria']) ?> - <?= $mod['distancia_km'] ?> Km
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Color del Grupo</label>
                                                            <input type="text" name="color" class="form-control" list="colores_sugeridos" required value="<?= htmlspecialchars($g['color']) ?>">
                                                        </div>

                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Edad Mínima</label>
                                                                <input type="number" min="0" max="120" name="edad_minima" class="form-control" required value="<?= $g['edad_minima'] ?>">
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Edad Máxima</label>
                                                                <input type="number" min="0" max="120" name="edad_maxima" class="form-control" required value="<?= $g['edad_maxima'] ?>">
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Hora de Salida</label>
                                                            <input type="time" step="1" name="hora_salida" class="form-control font-monospace" required value="<?= htmlspecialchars($g['hora_salida']) ?>">
                                                        </div>

                                                        <?php if ($g['total_inscritos'] > 0): ?>
                                                            <div class="alert alert-warning py-2 mb-0 small">
                                                                <i class="bi bi-info-circle me-1"></i> Este grupo tiene <strong><?= $g['total_inscritos'] ?></strong> corredor(es) inscritos. Los cambios de color u hora se reflejarán en sus registros.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- FIN MODAL -->

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
