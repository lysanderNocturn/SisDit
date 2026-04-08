<?php include('header.php'); ?>

<style>
body{
    background:#f4f6f8;
}

/* ===== TITULO ===== */
.titulo{
    text-align:center;
    padding:40px 20px;
    background:#8c1231;
    color:white;
}

.titulo h1{
    font-size:32px;
}

.titulo p{
    margin-top:10px;
    font-size:14px;
}

/* ===== CONTENEDOR ===== */
.container{
    width:90%;
    max-width:1100px;
    margin:40px auto;
}

/* ===== TARJETAS ===== */
.card{
    background:white;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.08);
    margin-bottom:40px;
    overflow:hidden;
}

.card-header{
    padding:20px;
    color:white;
}

.card-body{
    padding:25px;
}

/* COLORES */
.rojo{ background:#a51d3d; }
.verde{ background:#2e7d6f; }
.azul{ background:#3569a5; }

/* ITEMS */
.item{
    background:#f0e3e7;
    padding:15px 20px;
    border-radius:8px;
    margin-bottom:15px;
    font-size:14px;
}

.verde-items .item{ background:#d9ebe8; }
.azul-items .item{ background:#dbe4f0; }

/* ALERTA */
.alerta{
    background:#fff3cd;
    border:1px solid #f1c40f;
    padding:15px;
    border-radius:8px;
    margin-top:15px;
    font-size:13px;
}

/* INFO */
.info{
    background:#e2e5e9;
    padding:15px;
    border-radius:8px;
    margin-top:15px;
    font-size:13px;
}

/* CONTACTO */
.contacto{
    text-align:center;
    margin:60px 0;
}

.contact-box{
    display:flex;
    justify-content:center;
    gap:20px;
    flex-wrap:wrap;
    margin-top:20px;
}

.contact-item{
    padding:15px 25px;
    border-radius:8px;
    min-width:250px;
    font-weight:500;
}

.whatsapp{ background:#d4e8d4; }
.correo{ background:#dbe3f0; }

/* ===== RESPONSIVO ===== */
@media(max-width:768px){
    .titulo h1{
        font-size:24px;
    }
}
</style>

<section class="titulo">
    <h1>Requisitos para Trámites</h1>
    <p>Dirección de Planeación y Desarrollo Urbano del Municipio de Rincón de Romos</p>
</section>

<div class="container">

    <!-- TRAMITE 1 -->
    <div class="card">
        <div class="card-header rojo">
            <h2>Trámite de Número Oficial</h2>
            <small>Requisitos para realizar el trámite</small>
        </div>

        <div class="card-body">
            <div class="item">Acreditación del inmueble con título de propiedad o escritura pública</div>
            <div class="item">Boleta predial vigente</div>
            <div class="item">Identificación oficial (INE o Pasaporte)</div>
            <div class="item">Ubicación del predio</div>

            <div class="alerta">
                Los requisitos se obtuvieron del Cap. III Art. 30 del Reglamento de Ordenamiento Territorial.
            </div>

            <div class="info">
                • Entregar requisitos en copia<br>
                • Tiempo de respuesta: 10 días hábiles<br>
                • Si lo realiza un tercero, presentar carta poder
            </div>
        </div>
    </div>

    <!-- TRAMITE 2 -->
    <div class="card verde-items">
        <div class="card-header verde">
            <h2>Constancia de Compatibilidad Urbanística</h2>
        </div>

        <div class="card-body">
            <div class="item">Título de propiedad</div>
            <div class="item">Boleta predial vigente</div>
            <div class="item">Identificación oficial</div>
            <div class="item">Formato de constancia</div>

            <div class="alerta">
                Según Art. 576 COD. Urbano. Para predios mayores a 10,000m² agregar certificado catastral.
            </div>

            <div class="info">
                • Entregar requisitos en copia<br>
                • Tiempo de respuesta: 10 días hábiles
            </div>
        </div>
    </div>

    <!-- TRAMITE 3 -->
    <div class="card azul-items">
        <div class="card-header azul">
            <h2>Informe de Compatibilidad Urbanística</h2>
        </div>

        <div class="card-body">
            <div class="item">Identificación oficial</div>
            <div class="item">Ubicación del predio (Cuenta Catastral)</div>

            <div class="alerta">
                Los requisitos se obtuvieron del Cap. III Art. 30 del Reglamento de Ordenamiento Territorial.
            </div>

            <div class="info">
                • Entregar requisitos en copia<br>
                • Tiempo de respuesta: 10 días hábiles
            </div>
        </div>
    </div>

</div>

<section class="contacto">
    <h2>Contacto</h2>

    <div class="contact-box">
        <div class="contact-item whatsapp">
            WhatsApp<br>
            <strong>449 807 78 99</strong>
        </div>

        <div class="contact-item correo">
            Correo<br>
            <strong>dir.planeacionydu@gmail.com</strong>
        </div>
    </div>
</section>

<?php include('footer.php'); ?>
