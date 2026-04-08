<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sis Dit</title>

<style>
/* ======= RESET ======= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', sans-serif;
}

/* ===== HEADER ===== */
.header{
    background:#7b0f2b;
    color:white;
    padding:15px 40px;
}

.nav-container{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}

.logo-area{
    display:flex;
    align-items:center;
    gap:10px;
}

.logo-area img{
    height:50px;
}

nav ul{
    display:flex;
    gap:25px;
    list-style:none;
}

nav a{
    color:white;
    text-decoration:none;
    font-weight:500;
}

.btn-nav{
    background:#2e7d6f;
    padding:8px 15px;
    border-radius:6px;
}

/* ===== FOOTER ===== */
.footer{
    background:#7b0f2b;
    color:white;
    text-align:center;
    padding:40px 20px;
}

.footer img{
    height:70px;
    margin-bottom:15px;
}

/* ===== RESPONSIVO ===== */
@media(max-width:768px){
    nav ul{
        flex-direction:column;
        align-items:center;
        margin-top:10px;
    }

    .nav-container{
        flex-direction:column;
        gap:15px;
    }
}
</style>
</head>

<body>

<header class="header">
    <div class="nav-container">
        <div class="logo-area">
            <img src="logos/logo_presi.jpeg">
            <span>Presidencia <br>Rincón de Romos</span>
        </div>

        <nav>
            <ul>
                <li><a href="index.php">Inicio</a></li>
                <li><a href="requisitos.php">Requisitos</a></li>
                <li><a href="acceso.php" class="btn-nav">Acceso al sistema</a></li>
            </ul>
        </nav>
    </div>
</header>

<!-- CONTENIDO PRINCIPAL AQUÍ -->

<footer class="footer">
    <div style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap: wrap;">
        <img src="logos/logoDPDU.png" alt="Dirección de Planeación y Desarrollo Urbano" style="height:100px; vertical-align:middle; margin-right:5px;">
        <img src="logos/logo_presi.jpeg" alt="Presidencia Municipal de Rincón de Romos" style="height:100px; vertical-align:middle; margin-left:5px;">

        <div>
            <h3>Dirección de Planeación y Desarrollo Urbano</h3>
            <p>Rincón de Romos, Aguascalientes</p>
        </div>
        <br>
    </div>

    <p>2026 Presidencia Municipal de Rincón de Romos - Sistema único de Simplificación y Digitalización de Trámites "SisDit"</p>
</footer>

</body>
</html>