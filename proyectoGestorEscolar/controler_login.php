<?php

use Dom\Entity;

include('config/config.php');
$email = $_POST['email'];
$password = $_POST['password'];

// Busco al usuario en la base de datos por su email
$sql = "SELECT * FROM usuarios WHERE email = '$email' AND estado = '1'";
$query = $pdo->prepare($sql);
$query->execute();

$usuarios = $query->fetchAll(PDO::FETCH_ASSOC);

// Si encuentro el usuario, verifico la contraseña
if (count($usuarios) > 0) {
    $user = $usuarios[0];
    if (!empty($user) && (password_verify($password, $user['password']))) {
        session_start();
        $_SESSION['mensaje'] = "Bienvenido al sistema";
        $_SESSION['icono'] = "success";
        $_SESSION['sesion email'] = $email;
        $_SESSION['name'] = $user['nombres'];

        // Busco el rol del usuario para guardarlo en sesion
        $sql_role = "SELECT * FROM roles WHERE id_rol = " . $user['rol_id'];
        $query = $pdo->prepare($sql_role);
        $query->execute();
        $role = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($role) > 0) {
            $_SESSION['role'] = $role[0]['nombre_rol'];
        } else {
            $_SESSION['role'] = '';
        }

        // Redirijo segun el rol
        if ($_SESSION['role'] === 'ADMINISTRADOR') {
            // Uso ruta relativa en vez de APP_URL
            header('Location: admin/index.php');
        } else {
            // Uso ruta relativa en vez de APP_URL
            header('Location: admin/home.php');
        }
        return;
    }
}

session_start();
$_SESSION['mensaje'] = "Los datos son incorrectos, porfavor verifiquelos y vuelva a intentarlo";
header('Location: index.php');
exit();
