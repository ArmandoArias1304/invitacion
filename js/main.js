// ==========================================================================
// INVITACIÓN DIGITAL DE BODA - JAVASCRIPT PRINCIPAL CON MÚSICA
// ==========================================================================

// Inicializar cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Inicializar cuenta regresiva
  initCountdown();

  // Inicializar smooth scroll
  initSmoothScroll();

  // Inicializar trivia
  initTrivia();

  // Inicializar control de música
  musicController = new MusicController();

  // Inicializar efecto de timeline en móvil
  initTimelineScrollEffect();

  // Inicializar efecto de regalos en móvil
  initRegalosScrollEffect();

  // Inicializar efecto de itinerario en móvil
  initItinerarioScrollEffect();

  // También inicializar cuando se muestre el contenido principal
  setTimeout(() => {
    initRegalosScrollEffect();
    initItinerarioScrollEffect();
  }, 1000);
});

// ==========================================================================
// FUNCIÓN PARA MOSTRAR LA INVITACIÓN CON MÚSICA
// ==========================================================================

function mostrarInvitacion() {
  const bienvenida = document.getElementById("bienvenida");
  const contenidoPrincipal = document.getElementById("contenido-principal");

  // Animación de salida para la bienvenida
  bienvenida.style.transition =
    "opacity 0.8s ease-out, transform 0.8s ease-out";
  bienvenida.style.opacity = "0";
  bienvenida.style.transform = "translateY(-50px)";

  setTimeout(() => {
    bienvenida.style.display = "none";

    // Mostrar contenido principal
    contenidoPrincipal.style.display = "block";
    contenidoPrincipal.classList.add("show");

    // IMPORTANTE: Inicializar AOS después de mostrar el contenido
    setTimeout(() => {
      if (typeof AOS !== "undefined") {
        AOS.init({
          duration: 1000,
          easing: "ease-in-out",
          once: true,
          offset: 100,
        });

        // Refresh AOS para detectar los nuevos elementos
        AOS.refresh();
      }

      // Scroll suave al inicio del contenido
      document.getElementById("presentacion").scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }, 300);
  }, 800);
}

// ==========================================================================
// EFECTO DE TIMELINE CARDS ACTIVADO POR SCROLL EN MÓVIL
// ==========================================================================

function initTimelineScrollEffect() {
  // Solo en móvil
  if (window.innerWidth <= 768) {
    const timelineCards = document.querySelectorAll(".timeline-card");

    if (timelineCards.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Quitar efecto de todas las cards
            timelineCards.forEach((card) => {
              card.classList.remove("mobile-active");
            });

            // Agregar efecto solo a la card visible
            entry.target.classList.add("mobile-active");
          }
        });
      },
      {
        threshold: 0.6, // Se activa cuando 60% de la card es visible
        rootMargin: "-20% 0px -20% 0px", // Solo el centro de la pantalla
      }
    );

    timelineCards.forEach((card) => {
      observer.observe(card);
    });

    console.log("Timeline scroll effect inicializado para móvil");
  }
}

// ==========================================================================
// EFECTO DE SCROLL PARA CARDS DE REGALOS EN MÓVIL
// ==========================================================================

function initRegalosScrollEffect() {
  // Solo en móvil
  if (window.innerWidth <= 768) {
    const regalosCards = document.querySelectorAll(".regalo-card-simple");

    if (regalosCards.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Quitar efecto de todas las cards
            regalosCards.forEach((card) => {
              card.classList.remove("mobile-active");
            });

            // Agregar efecto solo a la card visible
            entry.target.classList.add("mobile-active");
          }
        });
      },
      {
        threshold: 0.6, // Se activa cuando 60% de la card es visible
        rootMargin: "-20% 0px -20% 0px", // Solo el centro de la pantalla
      }
    );

    regalosCards.forEach((card) => {
      observer.observe(card);
    });

    console.log("Efecto de scroll para regalos inicializado en móvil");
  }
}

// ==========================================================================
// EFECTO DE SCROLL PARA ITINERARIO EN MÓVIL - SOLO ELEVACIÓN
// ==========================================================================

function initItinerarioScrollEffect() {
  // Solo en móvil
  if (window.innerWidth <= 768) {
    const itinerarioRows = document.querySelectorAll(".itinerario-row");

    if (itinerarioRows.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Quitar efecto de todas las filas
            itinerarioRows.forEach((row) => {
              row.classList.remove("mobile-active");
            });

            // Agregar efecto solo a la fila visible
            entry.target.classList.add("mobile-active");
          }
        });
      },
      {
        threshold: 0.7, // Se activa cuando 70% de la fila es visible
        rootMargin: "-15% 0px -15% 0px", // Area un poco más amplia
      }
    );

    itinerarioRows.forEach((row) => {
      observer.observe(row);
    });

    console.log("Efecto de scroll para itinerario inicializado en móvil");
  }
}

// ==========================================================================
// SMOOTH SCROLL
// ==========================================================================

function scrollToSection(sectionId) {
  const element = document.getElementById(sectionId);
  if (element) {
    element.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }
}

function initSmoothScroll() {
  // Agregar smooth scroll a todos los enlaces internos
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const targetId = this.getAttribute("href").substring(1);
      scrollToSection(targetId);
    });
  });
}

// ==========================================================================
// CUENTA REGRESIVA - VERSIÓN SIMPLE
// ==========================================================================

function initCountdown() {
  const weddingDate = new Date(2025, 11, 26, 17, 0, 0).getTime(); // 26 septiembre 2025, 5:00 PM

  function updateCountdown() {
    const now = new Date().getTime();
    const distance = weddingDate - now;

    if (distance < 0) {
      // Si la fecha ya pasó, mostrar ceros
      document.getElementById("months").textContent = "00";
      document.getElementById("days").textContent = "00";
      document.getElementById("hours").textContent = "00";
      document.getElementById("minutes").textContent = "00";
      document.getElementById("seconds").textContent = "00";
      return;
    }

    // Calcular tiempo
    const totalSeconds = Math.floor(distance / 1000);
    const totalMinutes = Math.floor(totalSeconds / 60);
    const totalHours = Math.floor(totalMinutes / 60);
    const totalDays = Math.floor(totalHours / 24);

    const months = Math.floor(totalDays / 30.44);

    // Lógica inteligente para horas (nunca 00)
    let days = totalDays % Math.floor(30.44);
    let hours = totalHours % 24;

    if (hours === 0 && totalHours > 0) {
      hours = 24;
      days = days > 0 ? days - 1 : Math.floor(30.44) - 1;

      if (days < 0) {
        days = Math.floor(30.44) - 1;
      }
    }

    const minutes = totalMinutes % 60;
    const seconds = totalSeconds % 60;

    // Actualizar display
    document.getElementById("months").textContent = months
      .toString()
      .padStart(2, "0");
    document.getElementById("days").textContent = days
      .toString()
      .padStart(2, "0");
    document.getElementById("hours").textContent = hours
      .toString()
      .padStart(2, "0");
    document.getElementById("minutes").textContent = minutes
      .toString()
      .padStart(2, "0");
    document.getElementById("seconds").textContent = seconds
      .toString()
      .padStart(2, "0");
  }

  updateCountdown();
  setInterval(updateCountdown, 1000);
}

// ==========================================================================
// TRIVIA MEJORADA CON DISEÑO INTUITIVO
// ==========================================================================

let currentQuestion = 0;
let score = 0;
let triviaQuestions = [
  {
    question: "¿En qué año se conocieron los novios?",
    options: ["2018", "2019", "2020", "2021"],
    correct: 1, // índice de la respuesta correcta (2019)
  },
  {
    question: "¿Cuál fue su primera cita?",
    options: ["Cine", "Restaurante", "Parque", "Café"],
    correct: 3, // Café
  },
  {
    question: "¿En qué mes se comprometieron?",
    options: ["Enero", "Febrero", "Marzo", "Abril"],
    correct: 1, // Febrero
  },
  {
    question: "¿Cuál es su película favorita juntos?",
    options: [
      "Titanic",
      "El Diario de Noa",
      "La La Land",
      "Orgullo y Prejuicio",
    ],
    correct: 2, // La La Land
  },
  {
    question: "¿Dónde fue su primer viaje juntos?",
    options: ["Cancún", "Puerto Vallarta", "Playa del Carmen", "Mazatlán"],
    correct: 0, // Cancún
  },
];

// ==========================================================================
// INICIALIZACIÓN DE LA TRIVIA
// ==========================================================================

function initTrivia() {
  currentQuestion = 0;
  score = 0;
  updateProgressDisplay();
  showQuestion();
}

// ==========================================================================
// ACTUALIZAR DISPLAYS DE PROGRESO
// ==========================================================================

function updateProgressDisplay() {
  // Actualizar números en el header
  document.getElementById("current-question").textContent = currentQuestion + 1;
  document.getElementById("total-questions").textContent =
    triviaQuestions.length;
  document.getElementById("current-score").textContent = score;
  document.getElementById("max-score").textContent = triviaQuestions.length;

  // Actualizar barra de progreso
  const progressPercentage = (currentQuestion / triviaQuestions.length) * 100;
  document.getElementById("progress-fill").style.width =
    progressPercentage + "%";

  // Actualizar corazones de progreso
  const hearts = document.querySelectorAll(".progress-heart");
  hearts.forEach((heart, index) => {
    heart.classList.remove("active", "correct", "incorrect");
    if (index < currentQuestion) {
      heart.classList.add("active");
    }
  });
}

// ==========================================================================
// MOSTRAR PREGUNTA ACTUAL
// ==========================================================================

function showQuestion() {
  if (currentQuestion >= triviaQuestions.length) {
    showResults();
    return;
  }

  const question = triviaQuestions[currentQuestion];
  const container = document.getElementById("question-container");

  if (!container) return;

  // Actualizar el texto de la pregunta
  document.getElementById("question-text").textContent = question.question;

  // Generar opciones
  const optionsContainer = document.getElementById("options-container");
  optionsContainer.innerHTML = question.options
    .map(
      (option, index) =>
        `<button class="trivia-option" onclick="selectOption(${index})" data-option="${index}">
            ${option}
        </button>`
    )
    .join("");

  // Actualizar progreso
  updateProgressDisplay();

  // Animación de entrada para las opciones
  setTimeout(() => {
    const options = document.querySelectorAll(".trivia-option");
    options.forEach((option, index) => {
      setTimeout(() => {
        option.style.opacity = "0";
        option.style.transform = "translateY(20px)";
        option.style.transition = "all 0.4s ease";
        setTimeout(() => {
          option.style.opacity = "1";
          option.style.transform = "translateY(0)";
        }, 50);
      }, index * 100);
    });
  }, 100);
}

// ==========================================================================
// SELECCIONAR OPCIÓN
// ==========================================================================

function selectOption(selectedIndex) {
  const question = triviaQuestions[currentQuestion];
  const options = document.querySelectorAll(".trivia-option");
  const hearts = document.querySelectorAll(".progress-heart");
  const currentHeart = hearts[currentQuestion];

  // Deshabilitar todas las opciones
  options.forEach((option) => {
    option.style.pointerEvents = "none";
  });

  // Mostrar la respuesta correcta
  options[question.correct].classList.add("correct");

  // Si la respuesta es incorrecta, marcarla
  if (selectedIndex !== question.correct) {
    options[selectedIndex].classList.add("incorrect");
    // Animar corazón como incorrecto
    if (currentHeart) {
      currentHeart.classList.add("incorrect");
    }
    // Efecto de vibración en móvil
    if ("vibrate" in navigator) {
      navigator.vibrate(200);
    }
  } else {
    score++;
    // Animar corazón como correcto
    if (currentHeart) {
      currentHeart.classList.add("correct");
    }
    // Efecto de confeti virtual
    createConfettiEffect();
  }

  // Actualizar puntuación en tiempo real
  document.getElementById("current-score").textContent = score;

  // Avanzar a la siguiente pregunta después de mostrar el resultado
  setTimeout(() => {
    currentQuestion++;
    showQuestion();
  }, 2000);
}

// ==========================================================================
// EFECTO DE CONFETTI PARA RESPUESTAS CORRECTAS
// ==========================================================================

function createConfettiEffect() {
  const colors = ["#d4af37", "#e6c653", "#28a745", "#20c997", "#ffd700"];
  const confettiCount = 15;

  for (let i = 0; i < confettiCount; i++) {
    setTimeout(() => {
      const confetti = document.createElement("div");
      confetti.style.position = "fixed";
      confetti.style.left = Math.random() * window.innerWidth + "px";
      confetti.style.top = "-10px";
      confetti.style.width = "8px";
      confetti.style.height = "8px";
      confetti.style.backgroundColor =
        colors[Math.floor(Math.random() * colors.length)];
      confetti.style.borderRadius = "50%";
      confetti.style.pointerEvents = "none";
      confetti.style.zIndex = "10000";
      confetti.style.animation = "confettiFall 3s ease-out forwards";

      document.body.appendChild(confetti);

      setTimeout(() => {
        if (document.body.contains(confetti)) {
          document.body.removeChild(confetti);
        }
      }, 3000);
    }, i * 50);
  }
}

// Agregar los keyframes para la animación de confetti
if (!document.getElementById("confetti-styles")) {
  const style = document.createElement("style");
  style.id = "confetti-styles";
  style.textContent = `
        @keyframes confettiFall {
            0% {
                transform: translateY(-10px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
    `;
  document.head.appendChild(style);
}

// ==========================================================================
// MOSTRAR RESULTADOS FINALES
// ==========================================================================

function showResults() {
  const resultContainer = document.getElementById("result-container");
  const questionContainer = document.getElementById("question-container");

  if (!resultContainer || !questionContainer) return;

  // Ocultar pregunta y mostrar resultado
  questionContainer.style.display = "none";
  resultContainer.style.display = "block";

  // Calcular porcentaje y determinar nivel
  const percentage = (score / triviaQuestions.length) * 100;
  let level, message, iconClass, titleText;

  if (percentage >= 80) {
    level = "excellent";
    titleText = "¡Excelente! 🎉";
    message =
      "¡Increíble! Eres un verdadero experto en nuestra relación. Conoces todos nuestros secretos y momentos especiales. ¡Definitivamente mereces estar en nuestra boda! 💕";
    iconClass = "fas fa-trophy";
  } else if (percentage >= 60) {
    level = "good";
    titleText = "¡Muy Bien! 👏";
    message =
      "¡Genial! Tienes un buen conocimiento sobre nosotros. Sabes bastante de nuestra historia y eso nos hace muy felices. ¡Sigue así! 😊";
    iconClass = "fas fa-medal";
  } else if (percentage >= 40) {
    level = "average";
    titleText = "¡No Está Mal! 😉";
    message =
      "¡Bien! Conoces algunas cosas sobre nosotros, pero hay espacio para aprender más. ¡Te invitamos a que nos conozcas mejor! 🤗";
    iconClass = "fas fa-star";
  } else {
    level = "poor";
    titleText = "¡Hay que Ponerse al Día! 😅";
    message =
      "¡Ups! Parece que necesitas conocernos un poco más. ¡No te preocupes! Tendrás muchas oportunidades de aprender sobre nosotros. 💫";
    iconClass = "fas fa-heart";
  }

  // Actualizar elementos del resultado
  document.getElementById("result-title").textContent = titleText;
  document.getElementById("final-score").textContent = score;
  document.getElementById("score-text").textContent = message;

  // Actualizar icono y clase
  const resultIcon = document.getElementById("result-icon");
  const iconElement = resultIcon.querySelector("i");
  resultIcon.className = `result-icon ${level}`;
  iconElement.className = iconClass;

  // Animar estrellas basadas en la puntuación
  animateStars(Math.ceil((percentage / 100) * 5));

  // Actualizar progreso final
  setTimeout(() => {
    updateFinalProgress();
  }, 500);
}

// ==========================================================================
// ANIMAR ESTRELLAS DEL RESULTADO
// ==========================================================================

function animateStars(activeStars) {
  const stars = document.querySelectorAll(".result-stars i");
  stars.forEach((star, index) => {
    star.classList.remove("active");
    if (index < activeStars) {
      setTimeout(() => {
        star.classList.add("active");
      }, index * 200);
    }
  });
}

// ==========================================================================
// ACTUALIZAR PROGRESO FINAL
// ==========================================================================

function updateFinalProgress() {
  // Llenar barra de progreso al 100%
  document.getElementById("progress-fill").style.width = "100%";

  // Marcar todos los corazones según los resultados
  const hearts = document.querySelectorAll(".progress-heart");
  hearts.forEach((heart, index) => {
    heart.classList.remove("active");

    // Determinar si esta pregunta fue respondida correctamente
    // (esto requeriría tracking de respuestas individuales,
    // por simplicidad, marcamos según el score total)
    if (index < score) {
      heart.classList.add("correct");
    } else if (index < currentQuestion) {
      heart.classList.add("incorrect");
    }
  });
}

// ==========================================================================
// REINICIAR TRIVIA
// ==========================================================================

function restartTrivia() {
  currentQuestion = 0;
  score = 0;

  const resultContainer = document.getElementById("result-container");
  const questionContainer = document.getElementById("question-container");

  if (resultContainer && questionContainer) {
    resultContainer.style.display = "none";
    questionContainer.style.display = "block";
  }

  // Limpiar corazones
  const hearts = document.querySelectorAll(".progress-heart");
  hearts.forEach((heart) => {
    heart.classList.remove("active", "correct", "incorrect");
  });

  // Reiniciar barra de progreso
  document.getElementById("progress-fill").style.width = "0%";

  // Mostrar primera pregunta
  showQuestion();
}

// ==========================================================================
// COMPARTIR RESULTADO
// ==========================================================================

function shareResult() {
  const percentage = (score / triviaQuestions.length) * 100;
  let shareMessage = `¡Acabo de completar la trivia de Guillermo & Wendy! 💕\n\n`;
  shareMessage += `Mi puntuación: ${score}/${
    triviaQuestions.length
  } (${Math.round(percentage)}%)\n\n`;

  if (percentage >= 80) {
    shareMessage += `¡Soy un experto en su relación! 🎉 #GuillermoYWendy2025`;
  } else if (percentage >= 60) {
    shareMessage += `¡Conozco bastante sobre ellos! 👏 #GuillermoYWendy2025`;
  } else {
    shareMessage += `¡Necesito conocerlos mejor! 😊 #GuillermoYWendy2025`;
  }

  // Intentar usar Web Share API si está disponible
  if (navigator.share) {
    navigator
      .share({
        title: "Trivia Guillermo & Wendy",
        text: shareMessage,
        url: window.location.href,
      })
      .catch((err) => {
        console.log("Error sharing:", err);
        fallbackShare(shareMessage);
      });
  } else {
    fallbackShare(shareMessage);
  }
}

// ==========================================================================
// COMPARTIR ALTERNATIVO
// ==========================================================================

function fallbackShare(message) {
  // Copiar al portapapeles
  if (navigator.clipboard) {
    navigator.clipboard
      .writeText(message)
      .then(() => {
        showShareNotification("¡Resultado copiado al portapapeles!");
      })
      .catch(() => {
        showShareNotification("¡Prepara tu mensaje para compartir!");
      });
  } else {
    // Fallback para navegadores más antiguos
    const textArea = document.createElement("textarea");
    textArea.value = message;
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand("copy");
      showShareNotification("¡Resultado copiado al portapapeles!");
    } catch (err) {
      showShareNotification("¡Prepara tu mensaje para compartir!");
    }
    document.body.removeChild(textArea);
  }
}

// ==========================================================================
// MOSTRAR NOTIFICACIÓN DE COMPARTIR
// ==========================================================================

function showShareNotification(message) {
  const notification = document.createElement("div");
  notification.style.position = "fixed";
  notification.style.bottom = "20px";
  notification.style.left = "50%";
  notification.style.transform = "translateX(-50%)";
  notification.style.background =
    "linear-gradient(135deg, #28a745 0%, #20c997 100%)";
  notification.style.color = "white";
  notification.style.padding = "15px 25px";
  notification.style.borderRadius = "25px";
  notification.style.fontFamily = '"Patua One", serif';
  notification.style.fontSize = "14px";
  notification.style.fontWeight = "600";
  notification.style.zIndex = "10000";
  notification.style.boxShadow = "0 8px 25px rgba(40, 167, 69, 0.3)";
  notification.style.animation = "slideUpNotification 0.4s ease-out";
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideDownNotification 0.4s ease-in";
    setTimeout(() => {
      if (document.body.contains(notification)) {
        document.body.removeChild(notification);
      }
    }, 400);
  }, 3000);
}

// Agregar estilos para las animaciones de notificación
if (!document.getElementById("notification-styles")) {
  const style = document.createElement("style");
  style.id = "notification-styles";
  style.textContent = `
        @keyframes slideUpNotification {
            0% {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        @keyframes slideDownNotification {
            0% {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            100% {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
        }
    `;
  document.head.appendChild(style);
}

// ==========================================================================
// FUNCIONES DE UTILIDAD ADICIONALES
// ==========================================================================

// Función para mezclar preguntas (opcional)
function shuffleQuestions() {
  for (let i = triviaQuestions.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [triviaQuestions[i], triviaQuestions[j]] = [
      triviaQuestions[j],
      triviaQuestions[i],
    ];
  }
}

// Función para agregar más preguntas dinámicamente
function addQuestion(questionData) {
  triviaQuestions.push(questionData);
  // Actualizar el total en el display
  document.getElementById("total-questions").textContent =
    triviaQuestions.length;
  document.getElementById("max-score").textContent = triviaQuestions.length;
}

// Función para obtener estadísticas
function getTriviaStats() {
  return {
    totalQuestions: triviaQuestions.length,
    currentScore: score,
    currentQuestion: currentQuestion + 1,
    percentage: Math.round((score / triviaQuestions.length) * 100),
    isCompleted: currentQuestion >= triviaQuestions.length,
  };
}

// ==========================================================================
// INICIALIZACIÓN AUTOMÁTICA
// ==========================================================================

// Inicializar trivia cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Solo inicializar si estamos en la página correcta
  if (document.getElementById("trivia-container")) {
    initTrivia();
  }
});

// Exportar funciones para uso global
window.triviaFunctions = {
  initTrivia,
  restartTrivia,
  shareResult,
  shuffleQuestions,
  addQuestion,
  getTriviaStats,
};

// ==========================================================================
// UTILIDADES
// ==========================================================================

// Función para manejar errores de imágenes
function handleImageError(img) {
  img.style.display = "none";
  console.log("Imagen no encontrada:", img.src);
}

// Agregar manejo de errores a todas las imágenes
document.addEventListener("DOMContentLoaded", function () {
  const images = document.querySelectorAll("img");
  images.forEach((img) => {
    img.addEventListener("error", function () {
      handleImageError(this);
    });
  });
});

// Función para mostrar alertas elegantes
function showAlert(message, type = "info") {
  // Crear elemento de alerta
  const alert = document.createElement("div");
  alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
  alert.style.cssText =
    "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";
  alert.innerHTML = `
       ${message}
       <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
   `;

  document.body.appendChild(alert);

  // Remover después de 5 segundos
  setTimeout(() => {
    if (alert.parentNode) {
      alert.parentNode.removeChild(alert);
    }
  }, 5000);
}

// ==========================================================================
//   FUNCIONALIDAD DEL SOBRE DE CARTA CON ANIMACIÓN DE VIBRACIÓN Y DETECTOR DE SECCIÓN
// ==========================================================================

document.addEventListener("DOMContentLoaded", function () {
  const envelope = document.getElementById("envelope");
  const envelopeFlap = document.getElementById("envelope-flap");
  const letterPaper = document.getElementById("letter-paper");
  const closeLetterBtn = document.getElementById("close-letter-btn");
  const letterSection = document.getElementById("mensaje-invitados");

  let isLetterOpen = false;
  let vibrationInterval;
  let isInLetterSection = false;
  let sectionObserver;

  // ==========================================================================
  // DETECTOR DE SECCIÓN - SOLO VIBRA CUANDO ESTÁ EN LA SECCIÓN DE LA CARTA
  // ==========================================================================

  function initSectionDetector() {
    if (!letterSection) return;

    sectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          isInLetterSection = entry.isIntersecting;

          if (isInLetterSection && !isLetterOpen) {
            console.log("Usuario en sección de carta - vibraciones activadas");
            // Si no hay vibración activa, iniciarla
            if (!vibrationInterval) {
              startVibrationEffect();
            }
          } else {
            console.log(
              "Usuario fuera de sección de carta - vibraciones desactivadas"
            );
            // Detener vibraciones si no está en la sección
            stopVibrationEffect();
          }
        });
      },
      {
        threshold: 0.3, // Se activa cuando 30% de la sección es visible
        rootMargin: "0px 0px -20% 0px",
      }
    );

    sectionObserver.observe(letterSection);
  }

  // Función para iniciar animación de vibración con líneas Y VIBRACIÓN FÍSICA
  function startVibrationEffect() {
    if (!envelope || isLetterOpen || !isInLetterSection) return;

    // Añadir clase de vibración al sobre
    envelope.classList.add("envelope-vibrating");

    // VIBRACIÓN FÍSICA DEL TELÉFONO (solo si está en la sección)
    if ("vibrate" in navigator && isInLetterSection) {
      navigator.vibrate([200, 100, 200, 100, 200]); // Patrón suave: vibra-pausa-vibra
    }

    // Crear líneas de vibración en las esquinas
    createVibrationLines();

    // Repetir cada 8 segundos (solo si sigue en la sección)
    vibrationInterval = setInterval(() => {
      if (!isLetterOpen && isInLetterSection) {
        envelope.classList.remove("envelope-vibrating");
        setTimeout(() => {
          if (!isLetterOpen && isInLetterSection) {
            envelope.classList.add("envelope-vibrating");

            // VIBRACIÓN FÍSICA DEL TELÉFONO en cada repetición
            if ("vibrate" in navigator) {
              navigator.vibrate([200, 100, 200, 100, 200]);
            }

            createVibrationLines();
          }
        }, 100);
      } else {
        // Si ya no está en la sección, detener vibraciones
        stopVibrationEffect();
      }
    }, 5000);
  }

  // Función para crear líneas de vibración
  function createVibrationLines() {
    if (!envelope || isLetterOpen || !isInLetterSection) return;

    const positions = [
      { top: "10px", left: "-25px", rotation: 0 }, // Izquierda
      { top: "10px", right: "-25px", rotation: 0 }, // Derecha
      { top: "-25px", left: "50%", rotation: 90 }, // Arriba
      { bottom: "-25px", left: "50%", rotation: 90 }, // Abajo
    ];

    positions.forEach((pos, index) => {
      setTimeout(() => {
        if (!isLetterOpen && isInLetterSection) {
          const vibrationContainer = document.createElement("div");
          vibrationContainer.className = "vibration-lines";

          // Aplicar posición
          Object.keys(pos).forEach((key) => {
            if (key === "rotation") {
              vibrationContainer.style.transform = `rotate(${pos[key]}deg)`;
              if (pos[key] === 90) {
                vibrationContainer.style.transform += " translateX(-50%)";
              }
            } else {
              vibrationContainer.style[key] = pos[key];
            }
          });

          // Crear las 3 líneas de vibración
          for (let i = 0; i < 3; i++) {
            const line = document.createElement("div");
            line.className = "vibration-line";
            line.style.animationDelay = `${i * 0.1}s`;
            vibrationContainer.appendChild(line);
          }

          envelope.appendChild(vibrationContainer);

          // Remover después de la animación
          setTimeout(() => {
            if (envelope.contains(vibrationContainer)) {
              envelope.removeChild(vibrationContainer);
            }
          }, 2000);
        }
      }, index * 100);
    });
  }

  // Función para detener vibración
  function stopVibrationEffect() {
    if (vibrationInterval) {
      clearInterval(vibrationInterval);
      vibrationInterval = null;
    }

    if (envelope) {
      envelope.classList.remove("envelope-vibrating");
    }

    // Remover líneas existentes
    const existingLines = envelope.querySelectorAll(".vibration-lines");
    existingLines.forEach((lines) => {
      if (envelope.contains(lines)) {
        envelope.removeChild(lines);
      }
    });
  }

  // Función para abrir la carta
  function openLetter() {
    if (isLetterOpen) return;

    isLetterOpen = true;

    // Detener vibración
    stopVibrationEffect();

    // Animar la solapa del sobre
    envelopeFlap.classList.add("opened");

    // Animar el sobre
    envelope.classList.add("opened");

    // Mostrar el papel después de un pequeño delay
    setTimeout(() => {
      letterPaper.classList.add("visible");
      letterPaper.classList.add("slide-in");
    }, 400);

    // Ocultar la pista para abrir
    const hint = envelope.querySelector(".open-letter-hint");
    if (hint) {
      hint.style.opacity = "0";
      hint.style.pointerEvents = "none";
    }
  }

  // Función para cerrar la carta
  function closeLetter() {
    if (!isLetterOpen) return;

    isLetterOpen = false;

    // Ocultar el papel
    letterPaper.classList.remove("visible");
    letterPaper.classList.remove("slide-in");

    // Restaurar el sobre después de un delay
    setTimeout(() => {
      envelope.classList.remove("opened");
      envelopeFlap.classList.remove("opened");

      // Mostrar la pista nuevamente
      const hint = envelope.querySelector(".open-letter-hint");
      if (hint) {
        hint.style.opacity = "0.7";
        hint.style.pointerEvents = "auto";
      }

      // Reiniciar vibración después de cerrar (solo si está en la sección)
      setTimeout(() => {
        if (isInLetterSection) {
          startVibrationEffect();
        }
      }, 1000);
    }, 300);
  }

  // Event listeners
  if (envelope) {
    envelope.addEventListener("click", openLetter);
  }

  if (closeLetterBtn) {
    closeLetterBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      closeLetter();
    });
  }

  // Cerrar con escape
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && isLetterOpen) {
      closeLetter();
    }
  });

  // Cerrar haciendo click fuera del papel
  if (letterPaper) {
    letterPaper.addEventListener("click", function (e) {
      e.stopPropagation();
    });

    document.addEventListener("click", function (e) {
      if (
        isLetterOpen &&
        !letterPaper.contains(e.target) &&
        !envelope.contains(e.target)
      ) {
        closeLetter();
      }
    });
  }

  // Inicialización
  setTimeout(() => {
    if (envelope) {
      envelope.style.transform = "translateY(0)";
      envelope.style.opacity = "1";

      // Inicializar detector de sección
      initSectionDetector();

      console.log("Sistema de vibración inteligente inicializado");
    }
  }, 500);

  // Cleanup al salir de la página
  window.addEventListener("beforeunload", () => {
    stopVibrationEffect();
    if (sectionObserver) {
      sectionObserver.disconnect();
    }
  });
});

// Función para hacer que las funciones sean accesibles globalmente si es necesario
window.letterEnvelopeFunctions = {
  open: function () {
    const event = new Event("click");
    document.getElementById("envelope").dispatchEvent(event);
  },
  close: function () {
    const event = new Event("click");
    document.getElementById("close-letter-btn").dispatchEvent(event);
  },
};

// ==========================================================================
//   ESTILOS CSS DINÁMICOS ADICIONALES
// ==========================================================================

const additionalStyles = `
  /* Animación de vibración sutil para el sobre */
  .envelope-vibrating {
      animation: subtleVibration 0.6s ease-in-out 3;
  }
  
  @keyframes subtleVibration {
      0%, 100% { transform: translateX(0px); }
      25% { transform: translateX(-1px); }
      75% { transform: translateX(1px); }
  }
  
  /* Líneas de vibración */
  .vibration-lines {
      position: absolute;
      display: flex;
      flex-direction: column;
      gap: 3px;
      pointer-events: none;
      z-index: 5;
  }
  
  .vibration-line {
      width: 15px;
      height: 2px;
      background: #d4af37;
      border-radius: 1px;
      opacity: 0;
      animation: vibrationLinePulse 1.5s ease-in-out infinite;
      box-shadow: 0 0 4px rgba(212, 175, 55, 0.5);
  }
  
  .vibration-line:nth-child(1) { width: 12px; }
  .vibration-line:nth-child(2) { width: 15px; }
  .vibration-line:nth-child(3) { width: 10px; }
  
  @keyframes vibrationLinePulse {
      0%, 100% { 
          opacity: 0; 
          transform: scaleX(0.8);
      }
      50% { 
          opacity: 0.8; 
          transform: scaleX(1.1);
      }
  }
  
  /* Efecto dorado activado por scroll en móvil para timeline */
  @media (max-width: 768px) {
      .timeline-card.mobile-active {
          transform: translateY(-10px) scale(1.02);
          box-shadow: 0 25px 50px rgba(212, 175, 55, 0.2);
          border-color: rgba(212, 175, 55, 0.3);
          background: linear-gradient(145deg, #ffffff 0%, #f8f5f0 50%, #ffffff 100%);
          outline: 2px solid rgba(212, 175, 55, 0.6);
          outline-offset: -8px;
      }
      
      .timeline-card.mobile-active .timeline-img {
          transform: scale(1.05);
          border-radius: 20px;
      }
      
      .timeline-card.mobile-active h4 {
          color: #b8941f !important;
          text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
      }
      
      /* Efecto activado por scroll en móvil para cards de regalos */
      .regalo-card-simple.mobile-active {
          transform: translateY(-8px);
          border-color: rgba(212, 175, 55, 0.4);
          box-shadow: 
              0 20px 40px rgba(0,0,0,0.12),
              0 8px 25px rgba(212, 175, 55, 0.15),
              0 0 0 1px rgba(212, 175, 55, 0.1);
      }
      
      .regalo-card-simple.mobile-active::before {
          left: 100%;
      }
      
      .regalo-card-simple.mobile-active .regalo-icon-circle {
          transform: scale(1.1);
          box-shadow: 
              0 12px 30px rgba(212, 175, 55, 0.4),
              inset 0 2px 0 rgba(255,255,255,0.4);
      }
      
      .regalo-card-simple.mobile-active .regalo-name {
          color: #d4af37;
      }
      
      .regalo-card-simple.mobile-active .regalo-logo {
          transform: scale(1.1);
          filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
      }
      
      /* Efecto de elevación para itinerario en móvil */
      .itinerario-row.mobile-active {
          transform: translateY(-8px);
          transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      }
      
      .itinerario-row.mobile-active .evento-card {
          box-shadow: 
              0 20px 40px rgba(0,0,0,0.15),
              0 8px 25px rgba(0,0,0,0.1);
          transform: scale(1.02);
      }
      
      .itinerario-row.mobile-active .icon-circle {
          transform: translateY(-50%) scale(1.1);
          box-shadow: 0 8px 25px rgba(0,0,0,0.25);
      }
      
      /* Líneas de vibración más pequeñas en móvil */
      .vibration-line {
          width: 12px;
          height: 1.5px;
      }
      
      .vibration-line:nth-child(1) { width: 10px; }
      .vibration-line:nth-child(2) { width: 12px; }
      .vibration-line:nth-child(3) { width: 8px; }
  }
`;

// Inyectar estilos dinámicos
const styleSheet = document.createElement("style");
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

/* ==========================================================================
CONTROL DE MÚSICA DE FONDO - CORREGIDO PARA MÓVIL
========================================================================== */

class MusicController {
  constructor() {
    this.audio = document.getElementById("backgroundMusic");
    this.musicControl = document.getElementById("musicControl");
    this.musicBtn = document.getElementById("musicBtn");
    this.musicIcon = document.getElementById("musicIcon");
    this.isPlaying = false;
    this.isMuted = false;
    this.volume = 0.3; // Volumen inicial (30%)

    this.init();
  }

  init() {
    if (!this.audio || !this.musicBtn) {
      console.warn("Elementos de audio no encontrados");
      return;
    }

    // Configurar audio
    this.audio.volume = this.volume;

    // Event listeners
    this.musicBtn.addEventListener("click", (event) => this.toggleMusic(event));
    this.audio.addEventListener("ended", () => this.onMusicEnded());
    this.audio.addEventListener("error", () => this.onMusicError());

    // Mostrar control cuando la música esté lista
    this.audio.addEventListener("canplaythrough", () => {
      console.log("Música lista para reproducir");
    });

    console.log("Control de música inicializado");
  }

  // Iniciar música (llamar desde el botón "Ver Invitación")
  startMusic() {
    if (!this.audio) return;

    console.log("Iniciando música de fondo...");

    this.audio
      .play()
      .then(() => {
        this.isPlaying = true;
        this.updateUI();
        this.showControl();
        console.log("Música iniciada correctamente");
      })
      .catch((error) => {
        console.warn("No se pudo reproducir la música automáticamente:", error);
        this.showControl(); // Mostrar control para que el usuario pueda activarla manualmente
      });
  }

  // Toggle play/pause y mute/unmute - CORREGIDO
  toggleMusic(event) {
    // CORREGIDO: Prevenir interferencias de otros elementos
    if (event) {
      event.stopPropagation();
      event.preventDefault();
    }

    if (!this.audio) return;

    // CORREGIDO: Debounce para evitar clicks múltiples en móvil
    if (this.isToggling) return;
    this.isToggling = true;

    setTimeout(() => {
      this.isToggling = false;
    }, 300);

    if (this.isPlaying) {
      if (this.isMuted) {
        this.unmuteMusic();
      } else {
        this.muteMusic();
      }
    } else {
      this.playMusic();
    }
  }

  playMusic() {
    this.audio
      .play()
      .then(() => {
        this.isPlaying = true;
        this.isMuted = false;
        this.audio.volume = this.volume;
        this.updateUI();
        console.log("Música reanudada");
      })
      .catch((error) => {
        console.warn("Error al reproducir música:", error);
      });
  }

  muteMusic() {
    this.audio.volume = 0;
    this.isMuted = true;
    this.updateUI();
    console.log("Música silenciada");
  }

  unmuteMusic() {
    this.audio.volume = this.volume;
    this.isMuted = false;
    this.updateUI();
    console.log("Música activada");
  }

  stopMusic() {
    if (this.audio) {
      this.audio.pause();
      this.audio.currentTime = 0;
      this.isPlaying = false;
      this.updateUI();
    }
  }

  // Actualizar interfaz
  updateUI() {
    if (!this.musicIcon || !this.musicBtn) return;

    // Remover clases anteriores
    this.musicBtn.classList.remove("muted", "playing");

    if (this.isPlaying) {
      if (this.isMuted) {
        this.musicIcon.className = "fas fa-volume-mute music-icon";
        this.musicBtn.classList.add("muted");
        this.musicBtn.title = "Activar música";
      } else {
        this.musicIcon.className = "fas fa-volume-up music-icon";
        this.musicBtn.classList.add("playing");
        this.musicBtn.title = "Silenciar música";
      }
    } else {
      this.musicIcon.className = "fas fa-play music-icon";
      this.musicBtn.title = "Reproducir música";
    }
  }

  showControl() {
    if (this.musicControl) {
      this.musicControl.classList.add("show");
    }
  }

  hideControl() {
    if (this.musicControl) {
      this.musicControl.classList.remove("show");
    }
  }

  // Event handlers
  onMusicEnded() {
    console.log("Música terminada");
    // La música se repetirá automáticamente por el atributo "loop"
  }

  onMusicError() {
    console.error("Error al cargar la música");
    this.hideControl();
  }
}

// Inicializar control de música
let musicController;

document.addEventListener("DOMContentLoaded", function () {
  // CORREGIDO: Asegurar una sola instancia
  if (!musicController) {
    musicController = new MusicController();
  }
});

// Función global para iniciar música desde el botón "Ver Invitación"
window.startWeddingMusic = function () {
  if (musicController) {
    musicController.startMusic();
  }
};

// Morphing Text Animation - Frases más elegantes
document.addEventListener("DOMContentLoaded", function () {
  const couplePresentation = document.querySelector(".couple-presentation");

  if (couplePresentation) {
    // Frases románticas más elegantes y formales
    const romanticPhrases = [
      "Para toda la eternidad",
      "Juntos para siempre",
      "Nuestro amor eterno",
      "Contigo hasta el infinito",
      "Un amor verdadero",
      "Nuestro destino juntos",
    ];

    let currentPhraseIndex = 0;
    let morphingInterval;
    let isActive = false;
    let isMorphing = false;

    // Crear elementos necesarios
    const morphContainer = document.createElement("div");
    morphContainer.className = "morphing-text-container";

    const romanticText = document.createElement("div");
    romanticText.className = "romantic-morph-text";

    const particlesContainer = document.createElement("div");
    particlesContainer.className = "floating-particles";

    // Reorganizar estructura
    const originalNames = couplePresentation.querySelector(
      ".couple-names-presentation"
    );
    morphContainer.appendChild(originalNames);
    morphContainer.appendChild(romanticText);
    morphContainer.appendChild(particlesContainer);
    couplePresentation.appendChild(morphContainer);

    // Crear partículas flotantes elegantes
    function createFloatingParticles() {
      particlesContainer.innerHTML = "";

      for (let i = 0; i < 6; i++) {
        // Menos partículas, más elegante
        const particle = document.createElement("div");
        particle.className = "floating-particle";
        particle.style.left = Math.random() * 100 + "%";
        particle.style.animationDelay = Math.random() * 3 + "s";
        particle.style.animationDuration = 3 + Math.random() * 2 + "s"; // Más lento
        particlesContainer.appendChild(particle);
      }
    }

    // Función de morphing
    function morphToRomantic() {
      if (!isActive || isMorphing) return;

      isMorphing = true;
      const phrase = romanticPhrases[currentPhraseIndex];

      romanticText.textContent = phrase;
      couplePresentation.classList.add("morphing");
      createFloatingParticles();

      // Vibración suave en móvil
      if (navigator.vibrate) {
        navigator.vibrate([30, 20, 30]);
      }

      // Volver a los nombres después de 4 segundos (más tiempo para leer)
      setTimeout(() => {
        if (isActive) {
          morphToNames();
        }
      }, 4000);

      currentPhraseIndex = (currentPhraseIndex + 1) % romanticPhrases.length;
    }

    function morphToNames() {
      couplePresentation.classList.remove("morphing");
      isMorphing = false;

      if (isActive) {
        setTimeout(() => {
          if (isActive) {
            morphToRomantic();
          }
        }, 2500); // Pausa más larga entre cambios
      }
    }

    function startMorphing() {
      if (isActive) return;

      isActive = true;
      currentPhraseIndex = 0;

      setTimeout(() => {
        if (isActive) {
          morphToRomantic();
        }
      }, 800);
    }

    function stopMorphing() {
      isActive = false;
      isMorphing = false;
      couplePresentation.classList.remove("morphing");
      particlesContainer.innerHTML = "";
      clearInterval(morphingInterval);
    }

    // Eventos para escritorio
    couplePresentation.addEventListener("mouseenter", startMorphing);
    couplePresentation.addEventListener("mouseleave", stopMorphing);

    // Eventos para móvil
    let touchActive = false;

    couplePresentation.addEventListener("touchstart", function (e) {
      e.preventDefault();

      if (touchActive) {
        touchActive = false;
        stopMorphing();
        return;
      }

      touchActive = true;
      startMorphing();

      setTimeout(() => {
        if (touchActive) {
          touchActive = false;
          stopMorphing();
        }
      }, 18000); // Más tiempo en móvil
    });

    couplePresentation.addEventListener("touchmove", function (e) {
      if (touchActive) {
        e.preventDefault();
      }
    });
  }
});
