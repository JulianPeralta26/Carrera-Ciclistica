<?php
require_once 'db.php';

$id_participante = intval($_GET['id'] ?? 0);
if ($id_participante <= 0) {
    header("Location: index.php");
    exit;
}

// Obtener datos del participante
$stmt = $pdo->prepare("
    SELECT p.*, 
           TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, (SELECT fecha_evento FROM eventos WHERE id_evento = 1)) AS edad
    FROM participantes p 
    WHERE p.id_participante = ?
");
$stmt->execute([$id_participante]);
$participante = $stmt->fetch();

if (!$participante) {
    header("Location: index.php");
    exit;
}

// Modalidades en las que ya está inscrito
$stmt_ya = $pdo->prepare("SELECT id_modalidad FROM inscripciones WHERE id_participante = ?");
$stmt_ya->execute([$id_participante]);
$ya_inscritas = $stmt_ya->fetchAll(PDO::FETCH_COLUMN);

// Modalidades disponibles
$stmt_mods = $pdo->query("
    SELECT m.id_modalidad, c.nombre AS categoria, r.distancia_km, m.desc_modalidad
    FROM modalidades m
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    ORDER BY c.id_categoria, r.distancia_km
");
$todas_modalidades = $stmt_mods->fetchAll();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modalidades_sel = $_POST['modalidades'] ?? [];

    if (empty($modalidades_sel)) {
        $errores[] = "Debes seleccionar al menos una nueva modalidad.";
    } else {
        try {
            $stmt_grupo = $pdo->prepare("
                SELECT id_grupo FROM grupos_salida 
                WHERE id_modalidad = ? AND ? BETWEEN edad_minima AND edad_maxima LIMIT 1
            ");

            $stmt_def = $pdo->prepare("
                SELECT id_grupo FROM grupos_salida WHERE id_modalidad = ? ORDER BY id_grupo ASC LIMIT 1
            ");

            $stmt_ins = $pdo->prepare("
                INSERT INTO inscripciones (id_participante, id_modalidad, id_grupo_salida, id_estado)
                VALUES (?, ?, ?, 1)
            ");

            $agregadas = 0;
            foreach ($modalidades_sel as $id_mod) {
                if (!in_array($id_mod, $ya_inscritas)) {
                    $stmt_grupo->execute([$id_mod, $participante['edad']]);
                    $id_grupo_salida = $stmt_grupo->fetchColumn();

                    if (!$id_grupo_salida) {
                        $stmt_def->execute([$id_mod]);
                        $id_grupo_salida = $stmt_def->fetchColumn();
                    }

                    if ($id_grupo_salida) {
                        $stmt_ins->execute([$id_participante, $id_mod, $id_grupo_salida]);
                        $agregadas++;
                    }
                }
            }

            $_SESSION['mensaje'] = "Se inscribió a {$participante['primer_nombre']} en {$agregadas} nueva(s) modalidad(es).";
            $_SESSION['tipo_mensaje'] = "success";
            header("Location: editar_participante.php?id={$id_participante}");
            exit;

        } catch (Exception $e) {
            $errores[] = "Error al inscribir: " . $e->getMessage();
        }
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle text-success me-2"></i>Inscribir a Nuevas Modalidades</h5>
                <a href="editar_participante.php?id=<?= $id_participante ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border mb-4">
                    <strong>Participante:</strong> <?= htmlspecialchars($participante['primer_nombre'] . ' ' . $participante['primer_apellido']) ?><br>
                    <strong>Cédula:</strong> <?= htmlspecialchars($participante['cedula']) ?> | <strong>Edad:</strong> <?= $participante['edad'] ?> años
                </div>

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errores as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <p class="fw-semibold mb-2">Selecciona las modalidades adicionales:</p>
                    <div class="row g-3 mb-4">
                        <?php foreach ($todas_modalidades as $mod): ?>
                            <?php $ya_esta = in_array($mod['id_modalidad'], $ya_inscritas); ?>
                            <div class="col-12">
                                <div class="form-check p-3 border rounded <?= $ya_esta ? 'bg-light opacity-75' : '' ?>">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="modalidades[]" value="<?= $mod['id_modalidad'] ?>" id="m_<?= $mod['id_modalidad'] ?>" <?= $ya_esta ? 'disabled checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="m_<?= $mod['id_modalidad'] ?>">
                                        <?= htmlspecialchars($mod['categoria']) ?> - <?= $mod['distancia_km'] ?> Km
                                        <?php if ($ya_esta): ?>
                                            <span class="badge bg-secondary ms-2">Ya inscrito</span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="small text-muted"><?= htmlspecialchars($mod['desc_modalidad']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="editar_participante.php?id=<?= $id_participante ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i> Confirmar Inscripción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
