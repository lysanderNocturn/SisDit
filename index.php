<?php include('header.php'); ?>

<style>

body{
    background:#f4f6f8;
}

/* ===== HERO ===== */
.hero{
    background:linear-gradient(rgba(123,15,43,0.85), rgba(123,15,43,0.85)),
    url("logos/fondos.jpg");
    background-size:cover;
    background-position:center;
    color:white;
    text-align:center;
    padding:100px 20px;
}

.hero h1{
    font-size:42px;
    margin-bottom:20px;
}

.hero p{
    max-width:800px;
    margin:auto;
    font-size:16px;
    line-height:1.6;
}

.hero-buttons{
    margin-top:30px;
}

.hero-buttons a{
    display:inline-block;
    padding:12px 25px;
    margin:10px;
    border-radius:6px;
    text-decoration:none;
    font-weight:500;
}

.btn-primary{
    background:#2e7d6f;
    color:white;
}

.btn-secondary{
    border:1px solid white;
    color:white;
}

/* ===== OBJETIVO + UBICACION ===== */
.section{
    width:90%;
    max-width:1100px;
    margin:50px auto;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:30px;
}

.card{
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.08);
}

.card h2{
    margin-bottom:15px;
}

.map{
    width:100%;
    height:250px;
    border-radius:8px;
}

/* ===== TRAMITES ===== */
.tramites{
    text-align:center;
    margin:70px 0 40px;
}

.tramites h2{
    font-size:28px;
}

.cards-tramites{
    width:90%;
    max-width:1100px;
    margin:30px auto;
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:25px;
}

.tramite-card{
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.08);
    text-align:left;
}

.tramite-card h3{
    margin-bottom:10px;
}

.tramite-card ul{
    margin-top:10px;
    padding-left:18px;
}

/* ===== BOTÓN CENTRAL CON MÁS ESPACIO ===== */
.btn-center{
    text-align:center;
    margin: 50px 0 80px;  /* Aumentado el margen inferior para separar del footer */
}

.btn-center a{
    background:#a51d3d;
    color:white;
    padding:14px 30px;  /* Botón más grande */
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
    font-size:16px;
    display:inline-block;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-center a:hover{
    background:#7b0f2b;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

/* ===== RESPONSIVO ===== */
@media(max-width:900px){

    .section{
        grid-template-columns:1fr;
    }

    .cards-tramites{
        grid-template-columns:1fr;
    }

    nav ul{
        flex-direction:column;
        margin-top:10px;
        align-items:center;
    }

    .nav-container{
        flex-direction:column;
        gap:15px;
    }

    .hero h1{
        font-size:28px;
    }
    
    .btn-center {
        margin: 40px 0 60px;
    }
}

@media(max-width:480px){
    .btn-center {
        margin: 30px 0 40px;
    }
    
    .btn-center a {
        padding: 12px 20px;
        font-size: 14px;
    }
}

</style>

<section class="hero">
    <h1>Sistema Único de Simplificación y Digitalización de Trámites "SisDit"</h1>
    <p>
        Plataforma oficial para el registro, consulta y gestión de información digital
        del municipio de Rincón de Romos, orientada a fortalecer la planeación urbana,
        la transparencia y la toma de decisiones.
    </p>

    <div class="hero-buttons">
        <a href="acceso.php" class="btn-primary">Acceder al sistema</a>
        <a href="requisitos.php" class="btn-secondary">Ver requisitos</a>
    </div>
</section>

<section class="section">
    <div class="card">
        <h2>Objetivo del Sistema</h2>
        <p>
            Facilitar a las áreas municipales el acceso a información territorial precisa,
            permitiendo identificar, analizar y dar seguimiento a infraestructura,
            reportes ciudadanos y servicios públicos mediante herramientas digitales.
        </p>
    </div>

    <div class="card">
        <h2>Ubicación del Municipio</h2>
        <iframe 
        class="map"
        src="https://maps.google.com/maps?q=Rincon%20de%20Romos&t=&z=13&ie=UTF8&iwloc=&output=embed">
        </iframe>
    </div>
</section>

<section class="tramites">
    <h2>Trámites disponibles</h2>
    <p>Dirección de Planeación y Desarrollo Urbano</p>
</section>

<div class="cards-tramites">
    <div class="tramite-card">
        <h3>Trámite de Número Oficial</h3>
        <p>Asignación de número oficial para predios dentro del municipio.</p>
        <ul>
            <li>Título de propiedad</li>
            <li>Boleta predial vigente</li>
            <li>INE o Pasaporte</li>
        </ul>
    </div>

    <div class="tramite-card">
        <h3>Constancia de Compatibilidad Urbanística</h3>
        <p>Constancia municipal que acredita compatibilidad del uso de suelo.</p>
        <ul>
            <li>Título de propiedad</li>
            <li>Boleta predial</li>
            <li>Formato de constancia</li>
        </ul>
    </div>

    <div class="tramite-card">
        <h3>Informe de Compatibilidad Urbanística</h3>
        <p>Informe técnico sobre compatibilidad del predio.</p>
        <ul>
            <li>INE o Pasaporte</li>
            <li>Ubicación del predio</li>
        </ul>
    </div>
</div>

<!-- BOTÓN CENTRAL CON ESPACIO MEJORADO -->
<div class="btn-center">
    <a href="requisitos.php">Ver todos los requisitos</a>
</div>

<?php include('footer.php'); ?>