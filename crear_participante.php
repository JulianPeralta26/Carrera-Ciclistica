<?php
require_once 'db.php';

// Obtener las modalidades disponibles del evento
$stmt_mods = $pdo->query("
    SELECT m.id_modalidad, c.nombre AS categoria, r.distancia_km, m.desc_modalidad
    FROM modalidades m
    JOIN categorias c ON m.id_categoria = c.id_categoria
    JOIN recorridos r ON m.id_recorrido = r.id_recorrido
    ORDER BY c.id_categoria, r.distancia_km
");
$modalidades_disponibles = $stmt_mods->fetchAll();

// Obtener fecha del evento para calcular grupo de edad
$stmt_evento = $pdo->query("SELECT id_evento, fecha_evento FROM eventos WHERE id_evento = 1 LIMIT 1");
$evento = $stmt_evento->fetch();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula             = trim($_POST['cedula'] ?? '');
    $primer_nombre      = trim($_POST['primer_nombre'] ?? '');
    $segundo_nombre     = trim($_POST['segundo_nombre'] ?? '');
    $primer_apellido    = trim($_POST['primer_apellido'] ?? '');
    $segundo_apellido   = trim($_POST['segundo_apellido'] ?? '');
    $direccion          = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento   = trim($_POST['fecha_nacimiento'] ?? '');
    $telefono           = trim($_POST['telefono'] ?? '');
    $telefono_emergencia= trim($_POST['telefono_emergencia'] ?? '');
    $nombre_contacto    = trim($_POST['nombre_contacto_emergencia'] ?? '');
    $modalidades_sel    = $_POST['modalidades'] ?? [];

    // Validaciones
    if (empty($cedula)) $errores[] = "La cédula es obligatoria.";
    if (empty($primer_nombre)) $errores[] = "El primer nombre es obligatorio.";
    if (empty($primer_apellido)) $errores[] = "El primer apellido es obligatorio.";
    if (empty($direccion)) $errores[] = "La dirección es obligatoria.";
    if (empty($fecha_nacimiento)) $errores[] = "La fecha de nacimiento es obligatoria.";
    if (empty($telefono)) $errores[] = "El teléfono principal es obligatorio.";
    if (empty($telefono_emergencia)) $errores[] = "El teléfono de emergencia es obligatorio.";
    if (empty($modalidades_sel)) $errores[] = "Debe inscribir al participante al menos en una modalidad.";

    // Verificar si la cédula ya existe
    $stmt_check = $pdo->prepare("SELECT id_participante FROM participantes WHERE cedula = ?");
    $stmt_check->execute([$cedula]);
    if ($stmt_check->fetch()) {
        $errores[] = "Ya existe un participante registrado con la cédula {$cedula}.";
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // 1. Insertar Participante
            $stmt_part = $pdo->prepare("
                INSERT INTO participantes (cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, direccion, fecha_nacimiento)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_part->execute([
                $cedula,
                $primer_nombre,
                !empty($segundo_nombre) ? $segundo_nombre : null,
                $primer_apellido,
                !empty($segundo_apellido) ? $segundo_apellido : null,
                $direccion,
                $fecha_nacimiento
            ]);
            $id_participante = $pdo->lastInsertId();

            // 2. Insertar Contacto
            $stmt_cont = $pdo->prepare("
                INSERT INTO contactos (id_participante, telefono, telefono_emergencia, nombre_contacto_emergencia)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_cont->execute([
                $id_participante,
                $telefono,
                $telefono_emergencia,
                !empty($nombre_contacto) ? $nombre_contacto : null
            ]);

            // 3. Determinar Grupo de Salida según la modalidad y la edad del participante
            $fecha_ev = new DateTime($evento['fecha_evento']);
            $fecha_nac = new DateTime($fecha_nacimiento);
            $edad = $fecha_ev->diff($fecha_nac)->y;

            $stmt_grupo = $pdo->prepare("
                SELECT id_grupo 
                FROM grupos_salida 
                WHERE id_modalidad = ? AND ? BETWEEN edad_minima AND edad_maxima 
                LIMIT 1
            ");

            $stmt_def = $pdo->prepare("
                SELECT id_grupo FROM grupos_salida WHERE id_modalidad = ? ORDER BY id_grupo ASC LIMIT 1
            ");

            // 4. Inscribir en cada modalidad seleccionada con su grupo correspondiente
            $stmt_insc = $pdo->prepare("
                INSERT INTO inscripciones (id_participante, id_modalidad, id_grupo_salida, id_estado)
                VALUES (?, ?, ?, 1)
            ");

            foreach ($modalidades_sel as $id_mod) {
                $stmt_grupo->execute([$id_mod, $edad]);
                $id_grupo_salida = $stmt_grupo->fetchColumn();

                if (!$id_grupo_salida) {
                    $stmt_def->execute([$id_mod]);
                    $id_grupo_salida = $stmt_def->fetchColumn();
                }

                if ($id_grupo_salida) {
                    $stmt_insc->execute([$id_participante, $id_mod, $id_grupo_salida]);
                }
            }

            $pdo->commit();

            $_SESSION['mensaje'] = "¡Participante {$primer_nombre} {$primer_apellido} inscrito exitosamente en " . count($modalidades_sel) . " modalidad(es)!";
            $_SESSION['tipo_mensaje'] = "success";
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar el participante: " . $e->getMessage();
        }
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Inscripción de Nuevo Participante</h5>
                <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="crear_participante.php" class="needs-validation">
                    
                    <!-- Sección 1: Datos Personales -->
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="bi bi-card-heading me-1"></i> 1. Información Personal
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cédula / Documento <span class="text-danger">*</span></label>
                            <input type="text" name="cedula" class="form-control" required placeholder="Ej: 1070584930" value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primer Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="primer_nombre" class="form-control" required placeholder="Ej: Carlos" value="<?= htmlspecialchars($_POST['primer_nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Segundo Nombre</label>
                            <input type="text" name="segundo_nombre" class="form-control" placeholder="Ej: Eduardo" value="<?= htmlspecialchars($_POST['segundo_nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primer Apellido <span class="text-danger">*</span></label>
                            <input type="text" name="primer_apellido" class="form-control" required placeholder="Ej: Rodriguez" value="<?= htmlspecialchars($_POST['primer_apellido'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Segundo Apellido</label>
                            <input type="text" name="segundo_apellido" class="form-control" placeholder="Ej: Perez" value="<?= htmlspecialchars($_POST['segundo_apellido'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                            <small class="text-muted">Define el grupo de salida y color automáticamente.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección de Residencia <span class="text-danger">*</span></label>
                            <input type="text" name="direccion" class="form-control" required placeholder="Ej: Carrera 5 # 10-25 Barrio Centro, El Espinal" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Sección 2: Información de Contacto -->
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="bi bi-telephone-fill me-1"></i> 2. Teléfonos y Contacto de Emergencia
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono del Participante <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono" class="form-control" required placeholder="Ej: 3105551234" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono de Emergencia <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono_emergencia" class="form-control" required placeholder="Ej: 3159998877" value="<?= htmlspecialchars($_POST['telefono_emergencia'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nombre Contacto de Emergencia</label>
                            <input type="text" name="nombre_contacto_emergencia" class="form-control" placeholder="Ej: Maria Perez (Esposa)" value="<?= htmlspecialchars($_POST['nombre_contacto_emergencia'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Sección 3: Modalidades de Carrera -->
                    <h6 class="text-primary fw-bold text-uppercase border-bottom pb-2 mb-3">
                        <i class="bi bi-bicycle me-1"></i> 3. Modalidades a Participar (Selecciona una o varias)
                    </h6>
                    <p class="text-muted small">El participante puede competir en múltiples categorías y recorridos:</p>
                    
                    <div class="row g-3 mb-4">
                        <?php foreach ($modalidades_disponibles as $mod): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check p-3 border rounded h-100 bg-light">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="modalidades[]" value="<?= $mod['id_modalidad'] ?>" id="mod_<?= $mod['id_modalidad'] ?>" <?= in_array($mod['id_modalidad'], $_POST['modalidades'] ?? []) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold d-block" for="mod_<?= $mod['id_modalidad'] ?>">
                                        <?= htmlspecialchars($mod['categoria']) ?> - <?= $mod['distancia_km'] ?> Km
                                    </label>
                                    <small class="text-muted d-block"><?= htmlspecialchars($mod['desc_modalidad']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Guardar Inscripción</button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
