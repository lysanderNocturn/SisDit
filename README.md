# SisDit - Sistema Único de Simplificación y Digitalización de Trámites

## Descripción

SisDit es una plataforma web desarrollada para la Dirección de Planeación y Desarrollo Urbano del Municipio de Rincón de Romos, Aguascalientes, México. El sistema facilita la gestión digital de trámites urbanos, permitiendo el registro, consulta y seguimiento de procedimientos de planeación territorial de manera eficiente y transparente.

## Características Principales

### Gestión de Trámites
- **Trámite de Número Oficial**: Asignación de numeración oficial para predios
- **Constancia de Compatibilidad Urbanística**: Certificación de compatibilidad del uso de suelo
- **Informe de Compatibilidad Urbanística**: Análisis técnico de predios
- **Registro Georreferenciado**: Captura de coordenadas UTM automáticamente
- **Seguimiento en Tiempo Real**: Estados de trámites (En revisión, Aprobado, Rechazado, etc.)

### Roles de Usuario
- **Usuario**: Ciudadanos que pueden registrar y consultar sus trámites
- **Ventanilla**: Personal de recepción que registra nuevos trámites y atiende correcciones
- **Verificador**: Profesionales que revisan, aprueban y generan constancias
- **Administrador**: Gestión completa del sistema, usuarios y reportes

### Funcionalidades Avanzadas
- **Dashboard Interactivos**: Paneles personalizados por rol con estadísticas en tiempo real
- **Sistema de Documentos**: Carga y gestión de archivos (INE, escrituras, prediales, fotos)
- **Impresión de Formatos Oficiales**: Fichas, constancias y reportes con diseño municipal
- **Sistema de Reportes**: Estadísticas por mes, tipo de trámite y año
- **Autenticación Segura**: Sesiones con timeout, validación de IP y CSRF
- **Recuperación de Contraseña**: Sistema de restablecimiento por correo electrónico
- **API REST**: Endpoints para integración con otros sistemas
- **Interfaz Responsiva**: Compatible con dispositivos móviles y desktop

## Tecnologías Utilizadas

### Backend
- **PHP 7.4+**: Lenguaje principal del servidor
- **MySQL**: Base de datos relacional
- **PDO**: Interfaz de acceso a datos preparada

### Frontend
- **HTML5/CSS3**: Estructura y estilos
- **Bootstrap 5**: Framework CSS responsivo
- **JavaScript/jQuery**: Interactividad y AJAX
- **DataTables**: Tablas interactivas y paginadas
- **Leaflet**: Mapas georreferenciados
- **Chart.js**: Gráficas y estadísticas
- **SweetAlert2**: Notificaciones modales

### Herramientas de Desarrollo
- **Composer**: Gestión de dependencias PHP
- **Git**: Control de versiones
- **XAMPP**: Entorno de desarrollo local
- **phpMyAdmin**: Gestión de base de datos

## Instalación

### Prerrequisitos
- Servidor web (Apache/Nginx)
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Composer (para dependencias PHP)
- Node.js (opcional, para assets)

### Pasos de Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/lysanderNocturn/SisDit.git
   cd sisDit
   ```

2. **Instalar dependencias PHP**
   ```bash
   composer install
   ```

3. **Configurar la base de datos**
   - Crear base de datos MySQL
   - Importar el archivo `database/schema.sql`
   - Configurar credenciales en `php/db.php`

4. **Configurar el entorno**
   - Copiar `.env.example` a `.env`
   - Configurar variables de entorno:
     ```env
     DB_HOST=localhost
     DB_NAME=sisDit
     DB_USER=tu_usuario
     DB_PASS=tu_contraseña
     APP_URL=http://localhost/sisDit
     ```

5. **Configurar permisos**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   ```


## Configuración de Red Local

Para acceso en red local, ejecutar el script `Agregar-SisDit.bat` como administrador:

```batch
# Configura el archivo hosts para apuntar a la IP del servidor
Agregar-SisDit.bat
```

## Estructura del Proyecto

```
sisDit/
├── index.php              # Página principal
├── acceso.php             # Login y registro
├── logout.php             # Cierre de sesión
├── css/
│   └── style.css          # Estilos globales
├── js/
│   └── scripts.js         # JavaScript común
├── php/
│   ├── db.php             # Conexión a base de datos
│   ├── funciones_seguridad.php  # Utilidades de seguridad
│   ├── login.php          # Procesamiento de login
│   ├── registro.php       # Procesamiento de registro
│   ├── tramite.php        # Gestión de trámites
│   └── reset_password.php # Recuperación de contraseña
├── uploads/               # Archivos subidos
├── logs/                  # Registros del sistema
├── DashAdmin.php          # Dashboard administrador
├── DashVer.php            # Dashboard verificador
├── DashVentanilla.php     # Dashboard ventanilla
├── DashUser.php           # Dashboard usuario
├── ficha.php              # Ficha de ingreso imprimible
├── ficha_fotografias.php  # Ficha de fotografías
├── constancia_numero.php  # Constancia oficial
├── imprimir_documentos.php # Impresión de documentos
├── requisitos.php         # Página de requisitos
├── footer.php             # Pie de página común
├── header.php             # Cabecera común
├── seguridad.php          # Seguridad de sesiones
├── Nav_footer.php         # Navegación alternativa
└──  Agregar-SisDit.bat    # Script configuración red

```

## Uso del Sistema

### Para Ciudadanos
1. Registrarse en el sistema
2. Iniciar sesión
3. Seleccionar tipo de trámite
4. Completar formulario con datos del predio
5. Subir documentos requeridos
6. Hacer seguimiento del estado de tramite

### Para Personal Municipal
1. Iniciar sesión con credenciales asignadas
2. Gestionar trámites según rol asignado
3. Revisar y aprobar procedimientos
4. Generar constancias y reportes

## API Endpoints

### Autenticación
- `POST /php/login.php` - Inicio de sesión
- `POST /php/registro.php` - Registro de usuarios
- `POST /php/reset_password.php` - Recuperación de contraseña

### Gestión de Trámites
- `POST /php/tramite.php` - Crear/modificar trámite
- `GET /ficha.php?folio=XXX/YYYY` - Obtener ficha de trámite
- `GET /constancia_numero.php?folio=XXX/YYYY` - Constancia oficial

### Administración
- `GET /DashAdmin.php` - Panel de administración
- `GET /DashVer.php` - Panel de verificación
- `GET /DashVentanilla.php` - Panel de ventanilla

## Seguridad

- **Sesiones seguras**: Timeout automático, regeneración de IDs
- **Validación de IP**: Prevención de robo de sesiones
- **CSRF Protection**: Tokens en formularios críticos
- **Validación de entrada**: Sanitización y filtrado de datos
- **Logs de actividad**: Registro de todas las operaciones
- **Cifrado de contraseñas**: bcrypt para almacenamiento seguro

## Contribución

1. Fork el proyecto
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Agrega nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT. Ver archivo `LICENSE` para más detalles.

## Contacto

**Dirección de Planeación y Desarrollo Urbano**  
Municipio de Rincón de Romos, Aguascalientes  
📧 dir.planeacionydu@gmail.com  
📞 WhatsApp: 449 807 78 99  

**Desarrollador**: 
Este proyecto fue desarrollado por alumn@s de estadias de la Universidad Tecnologica del Norte UTNA
-
-
-
-
---

*Sistema desarrollado para fortalecer la planeación urbana, la transparencia y la toma de decisiones municipales.*