<?php

include('config/config.php');
require_once __DIR__ . '/config/seguridad.php';

// Verifico que el formulario de login venga de la pagina del sistema
verificarCsrf('index.php');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Busco al usuario en la base de datos por su email
$sql = "SELECT * FROM usuarios WHERE email = :email AND estado = '1' LIMIT 1";
$query = $pdo->prepare($sql);
$query->execute([':email' => $email]);

$user = $query->fetch(PDO::FETCH_ASSOC);

// Si encuentro el usuario, verifico la contraseña
if ($user && password_verify($password, $user['password'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['mensaje'] = "Bienvenido al sistema";
    $_SESSION['icono'] = "success";
    $_SESSION['sesion email'] = $email;
    $_SESSION['name'] = $user['nombres'];
    $_SESSION['id_usuario'] = $user['id_usuario'];

    // Busco el rol del usuario para guardarlo en sesion
    $sql_role = "SELECT nombre_rol FROM roles WHERE id_rol = :id_rol LIMIT 1";
    $query = $pdo->prepare($sql_role);
    $query->bindValue(':id_rol', $user['rol_id'], PDO::PARAM_INT);
    $query->execute();
    $role = $query->fetchColumn();
    $_SESSION['role'] = $role ?: '';

    // Redirijo segun el rol
    if ($_SESSION['role'] === 'ADMINISTRADOR') {
        // Uso ruta relativa en vez de APP_URL
        header('Location: admin/index.php');
    } else {
        // Uso ruta relativa en vez de APP_URL
        header('Location: admin/home.php');
    }
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['mensaje'] = "Los datos son incorrectos, porfavor verifiquelos y vuelva a intentarlo";
$_SESSION['icono'] = 'error';
header('Location: index.php');
exit();
