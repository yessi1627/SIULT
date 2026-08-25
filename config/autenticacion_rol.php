<?php
// Me aseguro de que la sesion este iniciada antes de continuar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifico si el usuario esta autenticado
if (!isset($_SESSION['sesion email'])) {
    header('Location: ../../index.php');
    exit();
}

// Obtengo el rol del usuario
$role = $_SESSION['role'] ?? '';

// Si estoy en roles solo puede entrar el administrador
if (strpos($_SERVER['REQUEST_URI'], '/roles/') !== false && $role !== 'ADMINISTRADOR') {
    header('Location: ../home.php');
    exit();
}

// En materias los estudiantes solo pueden consultar sus materias matriculadas
if (strpos($_SERVER['REQUEST_URI'], '/materias/') !== false) {
    $pagina_materia = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $puede_consultar_materias = in_array($pagina_materia, ['index.php', 'show.php'], true);
    if (!in_array($role, ['ADMINISTRADOR', 'PROFESOR']) && !($role === 'ESTUDIANTE' && $puede_consultar_materias)) {
        header('Location: ../home.php');
        exit();
    }
}

// Si estoy en usuarios solo puede entrar el administrador
if (strpos($_SERVER['REQUEST_URI'], '/usuarios/') !== false && $role !== 'ADMINISTRADOR') {
    header('Location: ../home.php');
    exit();
}

// Si estoy en tareas pueden entrar administrador profesor y estudiante
if (strpos($_SERVER['REQUEST_URI'], '/tareas/') !== false && !in_array($role, ['ADMINISTRADOR', 'PROFESOR', 'ESTUDIANTE'])) {
    header('Location: ../home.php');
    exit();
}

// El modulo de matriculas es exclusivo del administrador
if (strpos($_SERVER['REQUEST_URI'], '/matriculas/') !== false && $role !== 'ADMINISTRADOR') {
    header('Location: ../home.php');
    exit();
}
