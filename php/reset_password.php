<?php
// =====================================================
// RESTABLECER CONTRASEÑA
// Verifica el token de recuperación y permite cambiar la contraseña
// =====================================================
/**
 * RESTABLECER CONTRASEÑA
 * Archivo: php/reset_password.php
 * Descripción: Formulario para establecer nueva contraseña usando el token
 */

require 'db.php';

$mensaje = '';
$tipo_mensaje = '';
$token_valido = false;
$token = '';

// Verificar si hay token en la URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validar token
    $stmt = $conn->prepare("SELECT id, nombre, correo, token_expira FROM usuarios WHERE token_recuperacion = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // Verificar si el token no ha expirado
        if (strtotime($usuario['token_expira']) > time()) {
            $token_valido = true;
        } else {
            $mensaje = "El enlace de recuperación ha expirado. Por favor solicita uno nuevo.";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Token de recuperación inválido.";
        $tipo_mensaje = "error";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar cambio de contraseña
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validaciones
    if (empty($token) || empty($password) || empty($password_confirm)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } elseif (strlen($password) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
        $tipo_mensaje = "error";
    } elseif ($password !== $password_confirm) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "error";
    } else {
        // Verificar token
        $stmt = $conn->prepare("SELECT id, token_expira FROM usuarios WHERE token_recuperacion = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            
            if (strtotime($usuario['token_expira']) > time()) {
                // Actualizar contraseña y limpiar token
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $conn->prepare("UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expira = NULL WHERE id = ?");
                $stmt->bind_param("si", $password_hash, $usuario['id']);
                
                if ($stmt->execute()) {
                    $mensaje = "Tu contraseña ha sido actualizada correctamente.";
                    $tipo_mensaje = "success";
                    $token_valido = false; // Ocultar el formulario
                } else {
                    $mensaje = "Error al actualizar la contraseña. Intenta de nuevo.";
                    $tipo_mensaje = "error";
                    $token_valido = true;
                }
            } else {
                $mensaje = "El enlace de recuperación ha expirado.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Token inválido.";
            $tipo_mensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sis Dit</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

body {
    background: #f3f3f3;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.header {
    background: #7b0f2e;
    color: white;
    padding: 15px 20px;
}

.nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    max-width: 1200px;
    margin: 0 auto;
}

.logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-area img {
    width: 45px;
    height: auto;
}

.logo-area span {
    font-size: 14px;
    line-height: 1.3;
}

.main-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.card {
    max-width: 450px;
    width: 100%;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    padding: 40px 30px;
}

.card-icon {
    width: 70px;
    height: 70px;
    background: #7b0f2e;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.card-icon svg {
    width: 35px;
    height: 35px;
    fill: white;
}

h1 {
    text-align: center;
    color: #333;
    margin-bottom: 10px;
    font-size: 24px;
}

.subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 30px;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

input[type="password"] {
    width: 100%;
    padding: 14px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.3s;
}

input[type="password"]:focus {
    border-color: #2f7d6d;
    outline: none;
}

.btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: #2f7d6d;
    color: white;
    font-weight: 600;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.3s;
}

.btn:hover {
    background: #246356;
}

.btn-secondary {
    background: #7b0f2e;
    margin-top: 15px;
}

.btn-secondary:hover {
    background: #5a0b22;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
    text-align: center;
}

.alert-error {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.password-requirements {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.password-requirements h4 {
    font-size: 14px;
    color: #333;
    margin-bottom: 8px;
}

.password-requirements ul {
    list-style: none;
    font-size: 13px;
    color: #666;
}

.password-requirements li {
    padding: 3px 0;
}

.password-requirements li::before {
    content: "•";
    color: #2f7d6d;
    margin-right: 8px;
}

.footer {
    background: #7b0f2e;
    color: white;
    text-align: center;
    padding: 20px;
}

.footer p {
    font-size: 14px;
}

@media (max-width: 480px) {
    .card {
        padding: 30px 20px;
    }
    
    h1 {
        font-size: 20px;
    }
}
</style>
</head>
<body>

<header class="header">
    <div class="nav">
        <div class="logo-area">
            <img src="../logos/logo_presi.jpeg" alt="Logo">
            <span>Presidencia Municipal<br>Rincón de Romos</span>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="card">
        <div class="card-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1C8.676 1 6 3.676 6 7v2H4v14h16V9h-2V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v2H8V7c0-2.276 1.724-4 4-4zm-6 8h12v10H6V11zm6 3c-1.1 0-2 .9-2 2 0 .74.4 1.38 1 1.72V19h2v-1.28c.6-.34 1-.98 1-1.72 0-1.1-.9-2-2-2z"/>
            </svg>
        </div>
        
        <h1>Restablecer Contraseña</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($token_valido): ?>
            <p class="subtitle">Ingresa tu nueva contraseña</p>
            
            <div class="password-requirements">
                <h4>Requisitos de la contraseña:</h4>
                <ul>
                    <li>Minimo 8 caracteres</li>
                    <li>Se recomienda usar mayusculas, minusculas y numeros</li>
                </ul>
            </div>
            
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Ingresa tu nueva contraseña" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmar contraseña</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirma tu nueva contraseña" required minlength="8">
                </div>
                
                <button type="submit" class="btn">Cambiar Contraseña</button>
            </form>
        <?php else: ?>
            <?php if ($tipo_mensaje === 'success'): ?>
                <p class="subtitle">Ya puedes iniciar sesion con tu nueva contraseña.</p>
            <?php else: ?>
                <p class="subtitle">No se puede procesar tu solicitud.</p>
            <?php endif; ?>
            
            <a href="../acceso.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">
                Ir a Iniciar Sesión
            </a>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <div style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap: wrap;">
<img src="../logos/logoDPDU.png" alt="Dirección de Planeación y Desarrollo Urbano" style="height:100px; vertical-align:middle; margin-right:5px;">
        <img src="../logos/logo_presi.jpeg" alt="Presidencia Municipal de Rincón de Romos" style="height:100px; vertical-align:middle; margin-left:5px;">

<div>
        <h3>Dirección de Planeación y Desarrollo Urbano</h3>
        <p>Rincón de Romos, Aguascalientes</p>
    </div>
    <p>2026 Presidencia Municipal de Rincón de Romos - Sistema único de Simplificación y Digitalización de Trámites "SisDit"</p>
</div>

</footer>

</body>
</html>
