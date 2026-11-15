<?php
require_once 'db.php';

$id_participante = intval($_GET['id'] ?? 0);
if ($id_participante <= 0) {
    header("Location: index.php");
    exit;
}

// Obtener datos del participante y sus contactos
$stmt = $pdo->prepare("
    SELECT p.*, c.id_contacto, c.telefono, c.telefono_emergencia, c.nombre_contacto_emergencia
    FROM participantes p
    LEFT JOIN contactos c ON p.id_participante = c.id_participante
    WHERE p.id_participante = ?
");
$stmt->execute([$id_participante]);
$participante = $stmt->fetch();

if (!$participante) {
    $_SESSION['mensaje'] = "El participante no existe.";
    $_SESSION['tipo_mensaje'] = "danger";
    header("Location: index.php");
    exit;
}

// Obtener inscripciones del participante
$stmt_insc = $pdo->prepare("
    SELECT 
        i.id_inscripcion,
        cat.nombre AS categoria,
        r.distancia_km,
        gs.color,
        gs.hora_salida,
        est.id_estado,
        est.desc_estado
    FROM inscripciones i
    JOIN modalidades m ON i.id_modalidad = m.id_modalidad
    JOIN categorias cat ON m.id_categoria = cat.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    JOIN grupos_salida gs ON i.id_grupo_salida = gs.id_grupo
    JOIN estados est ON i.id_estado = est.id_estado
    WHERE i.id_participante = ?
");
$stmt_insc->execute([$id_participante]);
$inscripciones = $stmt_insc->fetchAll();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $primer_nombre      = trim($_POST['primer_nombre'] ?? '');
    $segundo_nombre     = trim($_POST['segundo_nombre'] ?? '');
    $primer_apellido    = trim($_POST['primer_apellido'] ?? '');
    $segundo_apellido   = trim($_POST['segundo_apellido'] ?? '');
    $direccion          = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento   = trim($_POST['fecha_nacimiento'] ?? '');
    $telefono           = trim($_POST['telefono'] ?? '');
    $telefono_emergencia= trim($_POST['telefono_emergencia'] ?? '');
    $nombre_contacto    = trim($_POST['nombre_contacto_emergencia'] ?? '');

    if (empty($primer_nombre)) $errores[] = "El primer nombre es obligatorio.";
    if (empty($primer_apellido)) $errores[] = "El primer apellido es obligatorio.";
    if (empty($direccion)) $errores[] = "La dirección es obligatoria.";
    if (empty($fecha_nacimiento)) $errores[] = "La fecha de nacimiento es obligatoria.";
    if (empty($telefono)) $errores[] = "El teléfono es obligatorio.";
    if (empty($telefono_emergencia)) $errores[] = "El teléfono de emergencia es obligatorio.";

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // 1. Actualizar participante
            $stmt_upd = $pdo->prepare("
                UPDATE participantes 
                SET primer_nombre = ?, segundo_nombre = ?, primer_apellido = ?, segundo_apellido = ?, direccion = ?, fecha_nacimiento = ?
                WHERE id_participante = ?
            ");
            $stmt_upd->execute([
                $primer_nombre,
                !empty($segundo_nombre) ? $segundo_nombre : null,
                $primer_apellido,
                !empty($segundo_apellido) ? $segundo_apellido : null,
                $direccion,
                $fecha_nacimiento,
                $id_participante
            ]);

            // 2. Actualizar o insertar contacto
            if (!empty($participante['id_contacto'])) {
                $stmt_c = $pdo->prepare("
                    UPDATE contactos 
                    SET telefono = ?, telefono_emergencia = ?, nombre_contacto_emergencia = ?
                    WHERE id_participante = ?
                ");
                $stmt_c->execute([$telefono, $telefono_emergencia, $nombre_contacto, $id_participante]);
            } else {
                $stmt_c = $pdo->prepare("
                    INSERT INTO contactos (id_participante, telefono, telefono_emergencia, nombre_contacto_emergencia)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_c->execute([$id_participante, $telefono, $telefono_emergencia, $nombre_contacto]);
            }

            $pdo->commit();

            $_SESSION['mensaje'] = "Datos de {$primer_nombre} {$primer_apellido} actualizados correctamente.";
            $_SESSION['tipo_mensaje'] = "success";
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Participante: <?= htmlspecialchars($participante['primer_nombre'] . ' ' . $participante['primer_apellido']) ?></h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
            <div class="card-body p-4">

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
                    
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="bi bi-person-fill"></i> Datos Personales
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cédula</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($participante['cedula']) ?>" readonly>
                            <small class="text-muted">La cédula es única y no se modifica.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primer Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="primer_nombre" class="form-control" required value="<?= htmlspecialchars($_POST['primer_nombre'] ?? $participante['primer_nombre']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Segundo Nombre</label>
                            <input type="text" name="segundo_nombre" class="form-control" value="<?= htmlspecialchars($_POST['segundo_nombre'] ?? $participante['segundo_nombre']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primer Apellido <span class="text-danger">*</span></label>
                            <input type="text" name="primer_apellido" class="form-control" required value="<?= htmlspecialchars($_POST['primer_apellido'] ?? $participante['primer_apellido']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Segundo Apellido</label>
                            <input type="text" name="segundo_apellido" class="form-control" value="<?= htmlspecialchars($_POST['segundo_apellido'] ?? $participante['segundo_apellido']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? $participante['fecha_nacimiento']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección <span class="text-danger">*</span></label>
                            <input type="text" name="direccion" class="form-control" required value="<?= htmlspecialchars($_POST['direccion'] ?? $participante['direccion']) ?>">
                        </div>
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="bi bi-telephone"></i> Datos de Contacto
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono" class="form-control" required value="<?= htmlspecialchars($_POST['telefono'] ?? $participante['telefono']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono de Emergencia <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono_emergencia" class="form-control" required value="<?= htmlspecialchars($_POST['telefono_emergencia'] ?? $participante['telefono_emergencia']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contacto de Emergencia</label>
                            <input type="text" name="nombre_contacto_emergencia" class="form-control" value="<?= htmlspecialchars($_POST['nombre_contacto_emergencia'] ?? $participante['nombre_contacto_emergencia']) ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Actualizar Participante</button>
                    </div>

                </form>

            </div>
        </div>

        <!-- Estado de Inscripciones -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-bicycle text-success me-2"></i>Modalidades Inscritas de este Participante</h6>
                <a href="inscribir_modalidad.php?id=<?= $id_participante ?>" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Inscribir a otra modalidad</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID Inscripción</th>
                            <th>Modalidad</th>
                            <th>Distancia</th>
                            <th>Color & Hora Salida</th>
                            <th>Estado Actual</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscripciones)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">No tiene inscripciones activas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inscripciones as $i): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?= $i['id_inscripcion'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($i['categoria']) ?></td>
                                    <td><?= $i['distancia_km'] ?> Km</td>
                                    <td>
                                        <span class="badge badge-color-<?= strtolower($i['color']) ?>"><?= htmlspecialchars($i['color']) ?></span>
                                        <small class="text-muted ms-1"><?= substr($i['hora_salida'], 0, 5) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($i['id_estado'] == 2): ?>
                                            <span class="badge bg-danger">Cancelado</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= htmlspecialchars($i['desc_estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($i['id_estado'] == 2): ?>
                                            <a href="cambiar_estado.php?id_inscripcion=<?= $i['id_inscripcion'] ?>&accion=reactivar&ref=editar&id_participante=<?= $id_participante ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-arrow-repeat"></i> Reactivar
                                            </a>
                                        <?php else: ?>
                                            <a href="cambiar_estado.php?id_inscripcion=<?= $i['id_inscripcion'] ?>&accion=cancelar&ref=editar&id_participante=<?= $id_participante ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro de cancelar esta inscripción?');">
                                                <i class="bi bi-x-circle"></i> Cancelar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>
