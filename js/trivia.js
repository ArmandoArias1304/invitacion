// Trivia functionality for the wedding invitation
const triviaQuestions = [
    {
        question: "¿Dónde se conocieron los novios?",
        options: ["Playa", "Universidad", "Fiesta", "Trabajo"],
        answer: "Universidad"
    },
    {
        question: "¿Cuál es la fecha de la boda?",
        options: ["12 de diciembre", "25 de diciembre", "1 de enero", "14 de febrero"],
        answer: "12 de diciembre"
    },
    {
        question: "¿Cuántos años llevan juntos?",
        options: ["1 año", "2 años", "3 años", "4 años"],
        answer: "3 años"
    },
    {
        question: "¿Cuál es el destino de luna de miel?",
        options: ["Maldivas", "París", "Bali", "Nueva York"],
        answer: "Bali"
    }
];

let currentQuestionIndex = 0;
let score = 0;

function loadQuestion() {
    const questionContainer = document.getElementById("trivia-question");
    const optionsContainer = document.getElementById("trivia-options");

    if (currentQuestionIndex < triviaQuestions.length) {
        const currentQuestion = triviaQuestions[currentQuestionIndex];
        questionContainer.textContent = currentQuestion.question;
        optionsContainer.innerHTML = "";

        currentQuestion.options.forEach(option => {
            const button = document.createElement("button");
            button.textContent = option;
            button.onclick = () => checkAnswer(option);
            optionsContainer.appendChild(button);
        });
    } else {
        showResult();
    }
}

function checkAnswer(selectedOption) {
    const currentQuestion = triviaQuestions[currentQuestionIndex];
    if (selectedOption === currentQuestion.answer) {
        score++;
    }
    currentQuestionIndex++;
    loadQuestion();
}

function showResult() {
    const triviaContainer = document.getElementById("trivia-container");
    triviaContainer.innerHTML = `<h2>Tu puntuación es: ${score} de ${triviaQuestions.length}</h2>`;
}

// Inicializar el juego de trivia
document.addEventListener("DOMContentLoaded", loadQuestion);