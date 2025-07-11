# Invitación de Boda

Este proyecto es una invitación de boda en línea que permite a los invitados confirmar su asistencia, ver información sobre el evento y explorar una galería de fotos.

## Estructura del Proyecto

- **index.html**: Página principal de la invitación.
- **css/**: Contiene los estilos CSS para la invitación.
  - **style.css**: Estilos principales.
  - **responsive.css**: Estilos responsivos.
  - **animations.css**: Animaciones y transiciones.
- **js/**: Archivos JavaScript para la funcionalidad.
  - **main.js**: JavaScript principal.
  - **countdown.js**: Cuenta regresiva para la fecha de la boda.
  - **carousel.js**: Carrusel de imágenes.
  - **trivia.js**: Funcionalidad de trivia.  # Invitación de Boda
  
  Este proyecto es una invitación de boda en línea que permite a los invitados confirmar su asistencia, ver información sobre el evento, participar en una trivia, consultar el itinerario y explorar una galería de fotos.
  
  ## Estructura del Proyecto
  
  - **index.html**: Página principal de la invitación.
  - **css/**: Estilos CSS del sitio.
    - **style.css**: Estilos principales.
    - **responsive.css**: Estilos responsivos.
    - **animations.css**: Animaciones y transiciones.
  - **js/**: Archivos JavaScript para la funcionalidad.
    - **main.js**: Lógica principal y scripts generales.
    - **countdown.js**: Cuenta regresiva para la fecha de la boda.
    - **carousel.js**: Carrusel de imágenes.
    - **trivia.js**: Funcionalidad de trivia interactiva.
    - **smooth-scroll.js**: Navegación suave entre secciones.
  - **php/**: Scripts PHP para la lógica del servidor.
    - **config/**: Configuración de la base de datos.
    - **rsvp/**: Confirmación de asistencia (RSVP).
    - **admin/**: Panel administrativo para gestionar la invitación y asistentes.
    - **scanner/**: Escaneo y validación de códigos QR.
    - **utils/**: Funciones auxiliares y generador de QR.
  - **images/**: Imágenes utilizadas en la invitación (en formato `.webp` para optimización).
  - **libs/**: Bibliotecas externas utilizadas en el proyecto.
  - **uploads/**: Carpeta para archivos subidos, incluyendo códigos QR generados.
  - **README.md**: Documentación del proyecto.
  
  ## Instalación
  
  1. Clona este repositorio en tu máquina local.
  2. Asegúrate de tener un servidor web que soporte PHP (por ejemplo, XAMPP).
  3. Configura la base de datos en `php/config/database.php`.
  4. Abre `http://localhost/invitacion-boda/index.html` en tu navegador para ver la invitación.
  5. Para acceder al panel administrativo, abre `http://localhost/invitacion-boda/php/admin/dashboard.php`.
  
  ## Recomendaciones
  
  - Todas las imágenes deben estar en formato `.webp` para mejor rendimiento.
  - Si modificas los estilos, puedes limpiar el CSS usando herramientas como PurgeCSS o la cobertura de Chrome DevTools.
  - Para ver los cambios de estilos en el navegador, realiza una recarga forzada (`Ctrl + F5`).
  
  ## Contribuciones
  
  Las contribuciones son bienvenidas. Si deseas mejorar este proyecto, por favor abre un issue o envía un pull request.
  
  ## Licencia
  
  Este proyecto está bajo la Licencia
  - **smooth-scroll.js**: Navegación suave entre secciones.
- **php/**: Scripts PHP para manejar la lógica del servidor.
  - **config/**: Configuración de la base de datos.
  - **rsvp/**: Manejo de confirmaciones de asistencia.
  - **admin/**: Panel administrativo para gestionar la invitación.
  - **scanner/**: Funcionalidad de escaneo de códigos QR.
  - **utils/**: Funciones útiles y generador de QR.
- **images/**: Imágenes utilizadas en la invitación.
- **libs/**: Bibliotecas externas utilizadas en el proyecto.
- **uploads/**: Carpeta para archivos subidos, incluyendo códigos QR generados.
- **README.md**: Documentación del proyecto.

## Instalación

1. Clona este repositorio en tu máquina local.
2. Asegúrate de tener un servidor web que soporte PHP.
3. Configura la base de datos en `php/config/database.php`.
4. Abre `index.html` en tu navegador para ver la invitación.

## Contribuciones

Las contribuciones son bienvenidas. Si deseas mejorar este proyecto, por favor abre un issue o envía un pull request.

## Licencia

Este proyecto está bajo la Licencia MIT.