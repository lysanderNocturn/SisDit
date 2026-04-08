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
            <span>Presidencia Municipal<br>Rincón de Romos</span>
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