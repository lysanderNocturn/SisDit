<?php
session_start();

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include('header.php'); ?>

<!-- SweetAlert2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<!-- Font Awesome 6 (íconos gratuitos) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
body{
    background:#f3f3f3;
}

/* ================= TITULO ================= */
.hero{
    background:#7b0f2e;
    color:white;
    text-align:center;
    padding:50px 20px 90px;
}

.hero h1{
    font-size: clamp(20px, 5vw, 28px);
}

.hero p{
    font-size: clamp(14px, 3vw, 16px);
    max-width: 800px;
    margin: 10px auto 0;
}

/* ================= CARD ================= */
.card{
    max-width:450px;
    width: 90%;
    margin:-70px auto 60px;
    background:white;
    border-radius:15px;
    box-shadow:0 15px 35px rgba(0,0,0,0.15);
    padding:30px 20px;
}

.tabs{
    display:flex;
    border-bottom:2px solid #e5e5e5;
    margin-bottom:20px;
}

.tab{
    flex:1;
    padding:10px;
    background:none;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size: clamp(14px, 3vw, 16px);
    white-space: nowrap;
}

.tab.active{
    border-bottom:3px solid #7b0f2e;
    color:#7b0f2e;
}

#loginForm, #registroForm{
    flex-direction:column;
}

.logo-center{
    width:70px;
    margin:10px auto;
    display:block;
}

h2{
    text-align:center;
    margin-bottom:20px;
    font-size: clamp(18px, 4vw, 24px);
}

input, select{
    width:100%;
    padding:12px 14px;
    margin-bottom:15px;
    border-radius:10px;
    border:1px solid #ccc;
    font-size: 16px;
}

input:focus, select:focus{
    border-color:#2f7d6d;
    outline:none;
}

.btn{
    width:100%;
    padding:14px;
    border:none;
    border-radius:10px;
    background:#2f7d6d;
    color:white;
    font-weight:600;
    cursor:pointer;
    font-size: 16px;
}

.btn:hover{
    background:#246356;
}

.alert{
    padding:12px;
    margin-bottom:15px;
    border-radius:8px;
    text-align:center;
    font-size: 14px;
}

.alert-error{
    background:#fee;
    color:#c33;
    border:1px solid #fcc;
}

.alert-success{
    background:#efe;
    color:#3c3;
    border:1px solid #cfc;
}

.note{
    font-size:12px;
    color:#666;
    margin-bottom:15px;
    text-align:center;
}

/* ================= CONTENEDOR DE CONTRASEÑA CON OJITO ================= */
.password-container {
    position: relative;
    width: 100%;
    margin-bottom: 15px;
}

.password-container input {
    width: 100%;
    padding: 12px 45px 12px 14px;
    margin-bottom: 0;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 16px;
    box-sizing: border-box;
}

.password-container input:focus {
    border-color: #2f7d6d;
    outline: none;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    background: transparent;
    border: none;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #7b0f2e;
    border-radius: 50%;
}

.toggle-password:hover {
    color: #2f7d6d;
    background: rgba(0, 0, 0, 0.05);
}

.toggle-password i {
    pointer-events: none;
}
</style>

<section class="hero">
    <img src="logos/SisDiT LOGO.png" style="width:450px; height:180px; border-radius:15px; margin-bottom:5px;">
</section>

<div class="card">

    <?php
    // Mostrar mensajes de recuperación de contraseña
    if (isset($_GET['ok']) && in_array($_GET['ok'], ['correo_enviado', 'correo_procesado', 'correo_dev'])) {
        $msg = $_SESSION['recuperar_msg'] ?? 'Revisa tu correo electrónico.';
        echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>';
        
        // En modo desarrollo, mostrar el enlace
        if (isset($_SESSION['recuperar_enlace_mostrar'])) {
            echo '<div class="alert alert-success" style="word-break: break-all; font-size: 12px;">';
            echo '<strong>Enlace de recuperación:</strong><br>';
            echo '<a href="' . htmlspecialchars($_SESSION['recuperar_enlace_mostrar']) . '" style="color: #155724;">';
            echo htmlspecialchars($_SESSION['recuperar_enlace_mostrar']);
            echo '</a></div>';
            unset($_SESSION['recuperar_enlace_mostrar']);
        }
        unset($_SESSION['recuperar_msg']);
    }
    
    // Mostrar errores
    if (isset($_GET['error'])) {
        $error_msg = urldecode($_GET['error']);
        
        // Verificar si es un mensaje personalizado (como el de solicitud pendiente)
        if (strpos($error_msg, 'pendiente') !== false || 
            strpos($error_msg, 'rechazada') !== false ||
            strpos($error_msg, 'registrado') !== false) {
            echo '<div class="alert alert-warning" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba;">
                    <strong>⚠️ Atención</strong><br>' . htmlspecialchars($error_msg) . '
                  </div>';
        } else {
            $errores = [
                'campos_obligatorios' => 'Todos los campos son obligatorios.',
                'email_invalido' => 'El correo electrónico no es válido.',
                'usuario_no_encontrado' => 'No se encontró una cuenta con ese correo.',
                'error_sistema' => 'Error del sistema. Intenta más tarde.',
                'error_envio' => 'Error al enviar el correo. Intenta más tarde.',
                'Token de seguridad invalido' => 'Error de seguridad. Por favor recarga la página.',
                'password_invalida' => 'La contraseña debe tener al menos 8 caracteres y una mayúscula.',
                'password_incorrecto' => 'La contraseña es incorrecta. Verifica tus datos.',
                'telefono_invalido' => 'El teléfono debe tener 10 dígitos.'
            ];
            $error_msg_display = $errores[$error_msg] ?? htmlspecialchars($error_msg);
            echo '<div class="alert alert-error">' . $error_msg_display . '</div>';
        }
    }
    ?>

    <div class="tabs">
        <button type="button" class="tab active" id="btnLogin">Iniciar Sesión</button>
        <button type="button" class="tab" id="btnRegistro">Registro</button>
    </div>

    <!-- LOGIN -->
    <form id="loginForm" action="php/login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <img src="logos/SisDiT LOGO BOTON.png" class="logo-center">
        <h2>Bienvenido</h2>

        <input type="email" name="correo" placeholder="Correo electrónico" required
           oninput="this.value = this.value.toLowerCase()">
        <div class="password-container">
            <input type="password" name="password" id="login_password" placeholder="Contraseña" required>
            <button type="button" class="toggle-password" onclick="togglePassword('login_password')">
                <i class="fas fa-eye"></i>
            </button>
        </div>

        <button type="submit" style="background:#7b0f2e; border:none; border-radius:15px; color:white; padding:10px 20px; font-size:16px;" class="btn btn-primary">Ingresar al sistema</button>

        <p style="text-align:center; margin-top:15px;">
            <a href="#" id="forgotPassword" style="color:#7b0f2e; font-size:14px;">
                ¿Olvidaste tu contraseña?
            </a>
        </p>
    </form>

    <!-- REGISTRO -->
    <form id="registroForm" action="php/registro.php" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <h2>Crear cuenta</h2>

        <input type="text" name="nombre" id="reg_nombre" placeholder="Nombre(s)" required 
       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
       oninput="this.value = this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÑ\s]/g, '')"
       style="text-transform:uppercase;"
       title="Solo letras, no se permiten números ni símbolos">
       <input type="text" name="apellidos" id="reg_apellidos" placeholder="Apellidos" required 
       pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
       oninput="this.value = this.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÑ\s]/g, '')"
       style="text-transform:uppercase;"
       title="Solo letras, no se permiten números ni símbolos">
        <input type="email" name="correo" placeholder="Correo electrónico" required
       oninput="this.value = this.value.toLowerCase()"
       title="Ingresa un correo electrónico válido">
        <input type="text" name="telefono" placeholder="Teléfono (10 dígitos, para notificación)" maxlength="10"
               pattern="[0-9]{10}" inputmode="numeric"
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
        <div class="password-container">
            <input type="password" name="password" id="reg_password" placeholder="Contraseña (8 caracteres: Aa 123 !@#)" required minlength="8" maxlength="8">
            <button type="button" class="toggle-password" onclick="togglePassword('reg_password')">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        
        <div id="pass-hint" style="font-size:12px;margin-top:-8px;margin-bottom:6px;padding:6px 10px;border-radius:6px;display:none;"></div>

        <select name="rol" id="selectRol" required onchange="mostrarNotaRol(this.value)">
            <option value="">Selecciona tu rol...</option>
            <option value="Usuario">Usuario</option>
            <option value="Ventanilla">Ventanilla</option>
            <option value="Verificador">Verificador</option>
        </select>

        <div id="nota-usuario" class="note" style="display:none;background:#fff3cd;border-left:4px solid #ffc107;padding:8px 12px;border-radius:6px;font-size:13px;">
            ⏳ <strong>Tu solicitud requiere aprobación del administrador.</strong><br>
            Recibirás una notificación cuando tu cuenta sea activada.
        </div>
        <div id="nota-aprobacion" class="note" style="display:none;background:#fff3cd;border-left:4px solid #ffc107;padding:8px 12px;border-radius:6px;font-size:13px;">
            ⏳ <strong>Este rol requiere aprobación del administrador.</strong><br>
            Recibirás una notificación por WhatsApp o correo cuando tu cuenta sea activada.
        </div>

        <button type="submit" style="background:#7b0f2e; border:none; border-radius:15px; color:white; padding:10px 20px; font-size:16px;" class="btn btn-primary">Registrarse</button>
    </form>

</div>

<?php include('footer.php'); ?>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const login = document.getElementById("loginForm");
    const registro = document.getElementById("registroForm");
    const btnLogin = document.getElementById("btnLogin");
    const btnRegistro = document.getElementById("btnRegistro");

    function mostrarLogin(){
        login.style.display = "block";
        registro.style.display = "none";
        btnLogin.classList.add("active");
        btnRegistro.classList.remove("active");
    }

    function mostrarRegistro(){
        login.style.display = "none";
        registro.style.display = "block";
        btnRegistro.classList.add("active");
        btnLogin.classList.remove("active");
    }

    btnLogin.addEventListener("click", mostrarLogin);
    btnRegistro.addEventListener("click", mostrarRegistro);

    // SCRIPT RECUPERACIÓN
    document.getElementById("forgotPassword").addEventListener("click", function(e){
        e.preventDefault();

        let email = document.querySelector("#loginForm input[name='correo']").value;

        if(email === ""){
            Swal.fire({
                icon: 'warning',
                title: 'Correo requerido',
                text: 'Ingresa tu correo electrónico primero.',
                confirmButtonColor: '#2f7d6d'
            });
            return;
        }

        Swal.fire({
            title: '¿Recuperar contraseña?',
            text: "Se enviará un enlace de recuperación a tu correo.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2f7d6d',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, enviar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "php/recuperar.php?correo=" + encodeURIComponent(email);
            }
        });
    });

    mostrarLogin();

    // VALIDACIÓN CONTRASEÑA: 8 caracteres exactos, 1 mayúscula, 1 minúscula, 1 número, 1 símbolo
var passInput = document.getElementById('reg_password');
var passHint  = document.getElementById('pass-hint');
if (passInput) {
    passInput.addEventListener('input', function() {
        var val = this.value;
        var tieneMayus = /[A-Z]/.test(val);
        var tieneMinus = /[a-z]/.test(val);
        var tieneNum   = /[0-9]/.test(val);
        var tieneSim   = /[!@#$%^&*(),.?":{}|<>]/.test(val);
        var longitudValida = val.length === 8;
        
        passHint.style.display = val.length > 0 ? 'block' : 'none';
        
        if (tieneMayus && tieneMinus && tieneNum && tieneSim && longitudValida) {
            passHint.style.background = '#d1f0e0';
            passHint.style.color = '#155724';
            passHint.innerHTML = '✅ Contraseña válida';
        } else {
            passHint.style.background = '#fff3cd';
            passHint.style.color = '#856404';
            var msg = [];
            if (!longitudValida) msg.push('exactamente 8 caracteres');
            if (!tieneMayus) msg.push('1 mayúscula');
            if (!tieneMinus) msg.push('1 minúscula');
            if (!tieneNum) msg.push('1 número');
            if (!tieneSim) msg.push('1 símbolo (!@#$%^&*)');
            passHint.innerHTML = '⚠️ Requiere: ' + msg.join(', ');
        }
    });
}

    // VALIDAR ANTES DE ENVIAR REGISTRO
var regForm = document.getElementById('registroForm');
if (regForm) {
    regForm.addEventListener('submit', function(e) {
        var pass = document.getElementById('reg_password').value;
        var tieneMayus = /[A-Z]/.test(pass);
        var tieneMinus = /[a-z]/.test(pass);
        var tieneNum   = /[0-9]/.test(pass);
        var tieneSim   = /[!@#$%^&*(),.?":{}|<>]/.test(pass);
        var longitudValida = pass.length === 8;
        
        if (!tieneMayus || !tieneMinus || !tieneNum || !tieneSim || !longitudValida) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Contraseña inválida',
                html: 'La contraseña debe tener:<br>' +
                      '• 8 caracteres exactos<br>' +
                      '• 1 mayúscula (A-Z)<br>' +
                      '• 1 minúscula (a-z)<br>' +
                      '• 1 número (0-9)<br>' +
                      '• 1 símbolo (!@#$%^&*)',
                confirmButtonColor: '#7b0f2b'
            });
            return false;
        }
    });
}
}); 
function mostrarNotaRol(rol) {
    document.getElementById('nota-usuario').style.display    = (rol === 'Usuario')    ? 'block' : 'none';
    document.getElementById('nota-aprobacion').style.display = (rol === 'Ventanilla' || rol === 'Verificador') ? 'block' : 'none';
}
// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    var icon = document.querySelector('.toggle-password i');
    
    if (input.type === "password") {
        input.type = "text";
        icon.className = "fas fa-eye-slash"; // Ojo tachado (ocultar)
    } else {
        input.type = "password";
        icon.className = "fas fa-eye"; // Ojo abierto (mostrar)
    }
}
</script>

<?php
// Mensajes de éxito/error al regresar del registro
if (!empty($_GET['ok'])) {
    $msg = '';
    $title = 'Éxito';
    if ($_GET['ok'] === 'usuario_creado' || $_GET['ok'] === 'solicitud_enviada') {
        $msg = 'Solicitud de registro enviada correctamente. El administrador revisará tu información y te notificará cuando sea aprobada.';
        $title = 'Solicitud recibida';
    }
    if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success', 
            title: '<?= $title ?>', 
            text: '<?= addslashes($msg) ?>', 
            confirmButtonColor: '#7b0f2b',
            confirmButtonText: 'Entendido'
        });
    });
    </script>
    <?php endif;
}

if (!empty($_GET['error'])): 
    $error_text = urldecode($_GET['error']);
    $icon = 'error';
    $title = 'Error';
    
    // Personalizar según el tipo de error
    if (strpos($error_text, 'pendiente') !== false) {
        $icon = 'info';
        $title = 'Solicitud pendiente';
        $error_text = 'Ya existe una solicitud pendiente con este correo. En breve recibirás una respuesta.';
    } elseif (strpos($error_text, 'rechazada') !== false) {
        $icon = 'error';
        $title = 'Solicitud rechazada';
        $error_text = 'Tu solicitud fue rechazada. Contacta al administrador para más información.';
    } elseif (strpos($error_text, 'registrado') !== false) {
        $icon = 'warning';
        $title = 'Correo registrado';
        $error_text = 'Este correo ya está registrado en el sistema.';
    } elseif ($error_text == 'usuario_no_encontrado') {
        $icon = 'warning';
        $title = 'Cuenta no encontrada';
        $error_text = 'No existe una cuenta con este correo electrónico. Verifica tus datos o regístrate.';
    } elseif ($error_text == 'password_invalida' || $error_text == 'password_incorrecto') {
        $icon = 'warning';
        $title = 'Contraseña incorrecta';
        $error_text = 'La contraseña ingresada no es correcta. Verifica tus datos.';
    } elseif ($error_text == 'cuenta_inactiva') {
        $icon = 'info';
        $title = 'Cuenta pendiente';
        $error_text = 'Tu cuenta está pendiente de activación. Espera la revisión del administrador.';
    } elseif ($error_text == 'campos_obligatorios') {
        $icon = 'warning';
        $title = 'Campos incompletos';
        $error_text = 'Por favor, completa todos los campos obligatorios.';
    } elseif ($error_text == 'email_invalido') {
        $icon = 'warning';
        $title = 'Correo inválido';
        $error_text = 'El correo electrónico ingresado no es válido.';
    } elseif ($error_text == 'error_sistema') {
        $icon = 'error';
        $title = 'Error del sistema';
        $error_text = 'Ocurrió un error en el sistema. Por favor, intenta más tarde.';
    } else {
        // Para otros errores que vienen como texto plano, mostrarlos directamente
        $title = 'Aviso';
        $icon = 'info';
        // No modificamos $error_text para mostrar el mensaje original
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?= $icon ?>', 
            title: '<?= $title ?>', 
            text: '<?= addslashes($error_text) ?>', 
            confirmButtonColor: '#7b0f2b',
            confirmButtonText: 'Entendido'
        });
        
        // Mostrar pestaña de registro si el error está relacionado con registro o usuario no encontrado
        <?php if (strpos($error_text, 'pendiente') !== false || 
                  strpos($error_text, 'registrado') !== false ||
                  $error_text == 'No existe una cuenta con este correo electrónico. Verifica tus datos o regístrate.'): ?>
        const btnRegistro = document.getElementById('btnRegistro');
        if (btnRegistro) btnRegistro.click();
        <?php endif; ?>
    });
    </script>
<?php endif; ?>

</body>
</html>