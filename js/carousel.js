// ==========================================================================
// CARRUSEL AUTOMÁTICO SIMPLIFICADO
// ==========================================================================

document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.getElementById('galeriaCarousel');
    
    // Crear instancia del carrusel de Bootstrap con configuración automática
    const bsCarousel = new bootstrap.Carousel(carousel, {
        interval: 4000, // Cambia cada 4 segundos
        wrap: true,     // Vuelve al inicio después de la última imagen
        pause: false    // No se pausa al hacer hover
    });
    
    // Precargar imágenes para mejor rendimiento
    const images = document.querySelectorAll('.carousel-img');
    function preloadImages() {
        images.forEach(img => {
            const imageUrl = img.src;
            const preloadImg = new Image();
            preloadImg.src = imageUrl;
        });
    }
    
    preloadImages();
    
    // Efecto de zoom suave en las imágenes activas
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
    });
    
    // Animación de entrada para los indicadores
    setTimeout(() => {
        const indicators = document.querySelectorAll('.enhanced-indicators button');
        indicators.forEach((indicator, index) => {
            indicator.style.opacity = '0';
            indicator.style.transform = 'scale(0.5)';
            setTimeout(() => {
                indicator.style.transition = 'all 0.3s ease';
                indicator.style.opacity = '1';
                indicator.style.transform = 'scale(1)';
            }, index * 100);
        });
    }, 500);
});