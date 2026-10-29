<?php 
include "conexion.php";

$email = $_POST['email'];
$password = $_POST['password'];

// 1. Buscamos al usuario sin restringir el rol en el SQL
$sql = "SELECT * FROM usuarios WHERE email = '$email' AND password = '$password' AND estado = 'Activo'";
$res = mysqli_query($cnx, $sql) or die(mysqli_error($cnx));

if (mysqli_num_rows($res) > 0) {
    session_start();
    
    // 2. Obtenemos los datos del usuario en un arreglo
    $row = mysqli_fetch_assoc($res);

    // Guardamos variables de sesión útiles
    $_SESSION['id_usuario'] = $row['id_usuario'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['nombre'] = $row['nombre'];
    $_SESSION['apellido'] = $row['apellido'];

    // Concatenamos nombre y apellido
    $_SESSION['nombre_completo'] = 
        $row['nombre'] . ' ' . $row['apellido'];

    $_SESSION['rol'] = $row['rol'];

    // 3. Redireccionamos según el valor del rol registrado
    switch ($row['rol']) {
        case 'Administrador':
            header("Location: admin/administrador_panel.php");
            break;
            
        case 'Recepcionista':
            header("Location: recep/recepcion_panel.php");
            break;

        case 'Entrenador':
            header("Location: entre/entrenador_panel.php");
            break;
            
        case 'Cliente':
            header("Location: clien/cliente_panel.php");
            break;
            
        default:
            // Por si existe otro rol no contemplado
            header("Location: logout.php?error=rol_invalido");
            break;
    }
    exit(); // Detiene la ejecución del script tras redireccionar
} else {
    // Si las credenciales no son correctas
    header("Location: logout.php?error=datos_incorrectos");
    exit();
}
?>