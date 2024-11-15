document.addEventListener("DOMContentLoaded", function () {
  let currentQuestion = 0;
  let score = 0;
  let lives = 3;
  let timer = 20;
  let timerInterval;
  let isAnswered = false;
  let firstName = "";
  let lastName = "";
  let bonusScore = 0;
  let bonusTimer = 30;
  let bonusTimerInterval;
  let moleInterval;
  let activeMole = null;

  const startScreen = document.getElementById("quiz-start-screen");
  const gameScreen = document.getElementById("quiz-game-screen");
  const endScreen = document.getElementById("quiz-end-screen");
  const bonusScreen = document.getElementById("bonus-game-screen");

  // Start Quiz Button Handler
  document.getElementById("start-quiz").addEventListener("click", function () {
    firstName = document.getElementById("first-name").value.trim();
    lastName = document.getElementById("last-name").value.trim();

    if (!firstName || !lastName) {
      alert("Please enter both your first and last name");
      return;
    }

    startGame();
  });

  // Restart Quiz Button Handler
  document.getElementById("restart-quiz").addEventListener("click", resetGame);

  function startGame() {
    startScreen.classList.remove("active");
    gameScreen.classList.add("active");
    loadQuestion();
    startTimer();
  }

  function loadQuestion() {
    const question = wpQuizGame.questions[currentQuestion];

    const markup = `
          <div class="quiz-header">
              <div class="lives">❤️ x ${lives}</div>
              <div class="timer">Time: ${timer}s</div>
              <div class="score">Score: ${score}</div>
          </div>
          <div class="progress-bar">
              <div class="progress" style="width: ${
                ((currentQuestion + 1) / wpQuizGame.questions.length) * 100
              }%"></div>
          </div>
          <div class="question">
              <h3>${question.question}</h3>
              <div class="options">
                  ${question.options
                    .map(
                      (option, index) => `
                      <button class="option" data-index="${index}">${option}</button>
                  `
                    )
                    .join("")}
              </div>
          </div>
      `;

    gameScreen.innerHTML = markup;

    // Add click handlers to options
    document.querySelectorAll(".option").forEach((button) => {
      button.addEventListener("click", function () {
        if (!isAnswered) {
          handleAnswer(parseInt(this.dataset.index));
        }
      });
    });
  }

  function startTimer() {
    clearInterval(timerInterval);
    timer = 20;
    timerInterval = setInterval(() => {
      timer--;
      document.querySelector(".timer").textContent = `Time: ${timer}s`;

      if (timer <= 0) {
        handleTimeUp();
      }
    }, 1000);
  }

  function handleTimeUp() {
    clearInterval(timerInterval);
    lives--;
    isAnswered = true;

    if (lives <= 0) {
      endGame(false); // End game without bonus round if lives are depleted
    } else {
      setTimeout(moveToNextQuestion, 1500);
    }
  }

  function handleAnswer(selectedIndex) {
    isAnswered = true;
    clearInterval(timerInterval);

    const question = wpQuizGame.questions[currentQuestion];
    const options = document.querySelectorAll(".option");

    options[question.correct].classList.add("correct");

    if (selectedIndex === question.correct) {
      const pointsEarned = 100 + timer * 5;
      score += pointsEarned;
      document.querySelector(".score").textContent = `Score: ${score}`;
    } else {
      options[selectedIndex].classList.add("wrong");
      lives--;

      if (lives <= 0) {
        endGame(false); // End game without bonus round if lives are depleted
        return;
      }
    }

    setTimeout(moveToNextQuestion, 1500);
  }

  function moveToNextQuestion() {
    if (currentQuestion < wpQuizGame.questions.length - 1) {
      currentQuestion++;
      isAnswered = false;
      loadQuestion();
      startTimer();
    } else {
      endGame(true); // Successfully completed all questions, include bonus round
    }
  }
  // Bonus Game Functions
  function startBonusGame() {
    clearInterval(timerInterval);
    gameScreen.classList.remove("active");
    bonusScreen.classList.add("active");

    // Initialize bonus game with shorter timer
    bonusScore = 0;
    bonusTimer = 15; // Reduced from 30 to 15 seconds
    updateBonusUI();
    startBonusTimer();
    startMoleAnimation();

    // Add click handlers to moles
    document.querySelectorAll(".mole").forEach((mole) => {
      mole.addEventListener("click", handleMoleClick);
    });
  }

  function startBonusTimer() {
    clearInterval(bonusTimerInterval);
    bonusTimerInterval = setInterval(() => {
      bonusTimer--;
      updateBonusUI();

      if (bonusTimer <= 0) {
        endBonusGame();
      }
    }, 1000);
  }

  function updateBonusUI() {
    document.getElementById("bonus-timer").textContent = `Time: ${bonusTimer}s`;
    document.getElementById(
      "bonus-score"
    ).textContent = `Bonus Score: ${bonusScore}`;
  }

  function startMoleAnimation() {
    if (moleInterval) clearInterval(moleInterval);

    const NUM_ACTIVE_MOLES = 2; // Show 2 moles at a time
    const MOLE_DURATION = 800; // Moles stay up for 800ms (reduced from 1000ms)
    const MOLE_INTERVAL = 900; // New moles appear every 900ms (reduced from 1200ms)

    let activeMoles = new Set();

    moleInterval = setInterval(() => {
      // Hide any expired moles
      activeMoles.forEach((mole) => {
        if (mole.timestamp + MOLE_DURATION < Date.now()) {
          mole.element.classList.remove("active");
          activeMoles.delete(mole);
        }
      });

      // Add new moles until we have NUM_ACTIVE_MOLES
      const moles = Array.from(document.querySelectorAll(".mole"));
      while (activeMoles.size < NUM_ACTIVE_MOLES) {
        // Find an inactive mole
        const availableMoles = moles.filter(
          (mole) =>
            !Array.from(activeMoles).some((active) => active.element === mole)
        );

        if (availableMoles.length === 0) break;

        const randomIndex = Math.floor(Math.random() * availableMoles.length);
        const newMole = availableMoles[randomIndex];
        newMole.classList.add("active");

        activeMoles.add({
          element: newMole,
          timestamp: Date.now(),
        });

        // Set auto-hide timeout for this mole
        setTimeout(() => {
          newMole.classList.remove("active");
          activeMoles.delete(
            Array.from(activeMoles).find((m) => m.element === newMole)
          );
        }, MOLE_DURATION);
      }
    }, MOLE_INTERVAL);
  }

  function handleMoleClick(event) {
    const mole = event.target;
    if (mole.classList.contains("active")) {
      bonusScore += 10;
      updateBonusUI();
      mole.classList.remove("active");

      // Enhanced visual feedback
      const splash = document.createElement("div");
      splash.className = "splash";
      mole.appendChild(splash);
      setTimeout(() => splash.remove(), 300);

      // Add quick shake animation to the container
      const container = document.querySelector(".mole-container");
      container.classList.add("shake");
      setTimeout(() => container.classList.remove("shake"), 200);
    }
  }

  function endBonusGame() {
    clearInterval(bonusTimerInterval);
    clearInterval(moleInterval);

    // Update final score with bonus points
    score += bonusScore;

    // Show final screen
    bonusScreen.classList.remove("active");
    endScreen.classList.add("active");

    document.getElementById(
      "final-score"
    ).textContent = `Final Score: ${score} (Quiz: ${
      score - bonusScore
    }, Bonus: ${bonusScore})`;

    // Save final score to database
    const formData = new FormData();
    formData.append("action", "save_quiz_stats");
    formData.append("nonce", wpQuizGame.nonce);
    formData.append(
      "player_id",
      `${firstName.toLowerCase()}_${lastName.toLowerCase()}`
    );
    formData.append("first_name", firstName);
    formData.append("last_name", lastName);
    formData.append("score", score);

    fetch(wpQuizGame.ajaxurl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => console.log("Score saved:", data))
      .catch((error) => console.error("Error saving score:", error));
  }

  function resetGame() {
    currentQuestion = 0;
    score = 0;
    bonusScore = 0;
    lives = 3;
    timer = 20;
    isAnswered = false;

    clearInterval(timerInterval);
    clearInterval(bonusTimerInterval);
    clearInterval(moleInterval);

    endScreen.classList.remove("active");
    startScreen.classList.add("active");

    document.getElementById("first-name").value = "";
    document.getElementById("last-name").value = "";
  }

  function endGame(includeBonus) {
    if (includeBonus) {
      startBonusGame();
    } else {
      // Skip bonus game and go directly to end screen
      clearInterval(timerInterval);
      gameScreen.classList.remove("active");
      endScreen.classList.add("active");

      document.getElementById(
        "final-score"
      ).textContent = `Final Score: ${score}`;

      // Save final score to database
      const formData = new FormData();
      formData.append("action", "save_quiz_stats");
      formData.append("nonce", wpQuizGame.nonce);
      formData.append(
        "player_id",
        `${firstName.toLowerCase()}_${lastName.toLowerCase()}`
      );
      formData.append("first_name", firstName);
      formData.append("last_name", lastName);
      formData.append("score", score);

      fetch(wpQuizGame.ajaxurl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => console.log("Score saved:", data))
        .catch((error) => console.error("Error saving score:", error));
    }
  }
});