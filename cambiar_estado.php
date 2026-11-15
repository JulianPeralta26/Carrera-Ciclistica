<?php
require_once 'db.php';
session_start();

$id_inscripcion = intval($_GET['id_inscripcion'] ?? 0);
$accion         = trim($_GET['accion'] ?? '');
$ref            = trim($_GET['ref'] ?? 'index');
$id_part        = intval($_GET['id_participante'] ?? 0);

if ($id_inscripcion > 0 && in_array($accion, ['cancelar', 'reactivar'])) {
    $nuevo_estado = ($accion === 'cancelar') ? 2 : 1; // 1 = Inscrito, 2 = Cancelado
    $texto_accion = ($accion === 'cancelar') ? 'cancelada' : 'reactivada';

    try {
        $stmt = $pdo->prepare("UPDATE inscripciones SET id_estado = ? WHERE id_inscripcion = ?");
        $stmt->execute([$nuevo_estado, $id_inscripcion]);

        $_SESSION['mensaje'] = "La inscripción #{$id_inscripcion} ha sido {$texto_accion} exitosamente.";
        $_SESSION['tipo_mensaje'] = ($accion === 'cancelar') ? 'warning' : 'success';
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "Error al cambiar el estado: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'danger';
    }
}

if ($ref === 'editar' && $id_part > 0) {
    header("Location: editar_participante.php?id={$id_part}");
} else {
    header("Location: index.php");
}
exit;
