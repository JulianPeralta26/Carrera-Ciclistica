<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | Carrera Ciclística Uniespinal 2026</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #198754;
            --dark-color: #212529;
        }
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .badge-color-amarillo  { background-color: #ffc107; color: #000; }
        .badge-color-azul      { background-color: #0d6efd; color: #fff; }
        .badge-color-rojo      { background-color: #dc3545; color: #fff; }
        .badge-color-verde     { background-color: #198754; color: #fff; }
        .badge-color-naranja   { background-color: #fd7e14; color: #fff; }
        .badge-color-morado    { background-color: #6f42c1; color: #fff; }
        .badge-color-negro     { background-color: #212529; color: #fff; }
        .badge-color-blanco    { background-color: #f8f9fa; color: #000; border: 1px solid #ced4da; }
        .badge-color-dorado    { background-color: #d4af37; color: #000; }
        .badge-color-plateado  { background-color: #9e9e9e; color: #fff; }
        /* Fallback genérico para cualquier otro color no listado */
        [class^="badge-color-"] { padding: 0.35em 0.65em; border-radius: 0.375rem; font-size: 0.85em; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; }
        .footer {
            margin-top: auto;
            background-color: #ffffff;
            border-top: 1px solid #e9ecef;
        }
        .medal-icon {
            font-size: 1.4rem;
        }
    </style>
</head>
<body>

<!-- Barra de Navegación -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-bicycle text-warning fs-3"></i>
            <span>Uniespinal 2026 <small class="text-secondary fw-normal fs-6">| Admin Ciclismo</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-people-fill me-1"></i> Participantes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'crear_participante.php' ? 'active' : '' ?>" href="crear_participante.php">
                        <i class="bi bi-person-plus-fill me-1"></i> Nueva Inscripción
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'grupos_salida.php' ? 'active' : '' ?>" href="grupos_salida.php">
                        <i class="bi bi-palette-fill me-1"></i> Grupos de Salida
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'registrar_resultado.php' ? 'active' : '' ?>" href="registrar_resultado.php">
                        <i class="bi bi-stopwatch me-1"></i> Tiempos y Resultados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'ganadores.php' ? 'active' : '' ?>" href="ganadores.php">
                        <i class="bi bi-trophy-fill text-warning me-1"></i> Ganadores y Podio
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center text-light">
                <span class="badge bg-primary px-3 py-2 rounded-pill">
                    <i class="bi bi-calendar-event me-1"></i> Evento: 15 Nov 2026 (8:00 AM - 4:00 PM)
                </span>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    <!-- Mensajes Flash de Sesión -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['tipo_mensaje'] ?? 'info') ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
    <?php endif; ?>
