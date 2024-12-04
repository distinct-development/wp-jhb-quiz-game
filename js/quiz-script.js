document.addEventListener("DOMContentLoaded", function () {
  let currentQuestion = 0;
  let score = 0;
  let lives = wpQuizGame.settings.lives;
  let timer = wpQuizGame.settings.questionTimer;
  let timerInterval;
  let isAnswered = false;
  let firstName = "";
  let lastName = "";
  let bonusScore = 0;
  let bonusTimer = wpQuizGame.settings.bonusTimer;
  let bonusTimerInterval;
  let moleInterval;
  let activeMole = null;
  let randomizedQuestions = [];

  const startScreen = document.getElementById("quiz-start-screen");
  const gameScreen = document.getElementById("quiz-game-screen");
  const endScreen = document.getElementById("quiz-end-screen");
  const bonusScreen = document.getElementById("bonus-game-screen");

  // Function to shuffle array (Fisher-Yates algorithm)
  function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
  }

  // Function to randomize questions and their options
  function randomizeQuestions() {
    randomizedQuestions = wpQuizGame.questions.map((q) => {
      // Create a copy of the question object
      const randomQ = { ...q };

      // Get all options with their correct index
      const optionsWithIndex = q.options.map((opt, idx) => ({
        text: opt,
        isCorrect: idx === q.correct,
      }));

      // Shuffle options
      const shuffledOptions = shuffleArray([...optionsWithIndex]);

      // Update the question object with shuffled options
      randomQ.options = shuffledOptions.map((opt) => opt.text);
      // Update correct answer index based on new option positions
      randomQ.correct = shuffledOptions.findIndex((opt) => opt.isCorrect);

      return randomQ;
    });

    // Shuffle the questions themselves
    return shuffleArray(randomizedQuestions);
  }

  // Start Quiz Button Handler
  document.getElementById("start-quiz").addEventListener("click", function () {
    firstName = document.getElementById("first-name").value.trim();
    lastName = document.getElementById("last-name").value.trim();

    if (!firstName || !lastName) {
      alert("Please enter both your first and last name");
      return;
    }

    randomizeQuestions();
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
    const question = randomizedQuestions[currentQuestion];

    const markup = `
          <div class="quiz-header">
              <div class="lives">❤️ x ${lives}</div>
              <div class="timer">Time: ${timer}s</div>
              <div class="score">Score: ${score}</div>
          </div>
          <div class="progress-bar">
              <div class="progress" style="width: ${
                ((currentQuestion + 1) / randomizedQuestions.length) * 100
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
    timer = wpQuizGame.settings.questionTimer;
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

    const question = randomizedQuestions[currentQuestion];
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
        endGame(false);
        return;
      }
    }

    setTimeout(moveToNextQuestion, 1500);
  }

  function moveToNextQuestion() {
    if (currentQuestion < randomizedQuestions.length - 1) {
      currentQuestion++;
      isAnswered = false;
      loadQuestion();
      startTimer();
    } else {
      endGame(true);
    }
  }
  // Bonus Game Functions
  function startBonusGame() {
    clearInterval(timerInterval);
    gameScreen.classList.remove("active");
    bonusScreen.classList.add("active");

    bonusScore = 0;
    bonusTimer = wpQuizGame.settings.bonusTimer;
    updateBonusUI();
    startBonusTimer();
    startMoleAnimation();

    document.querySelectorAll(".mole").forEach((mole) => {
      mole.addEventListener("click", handleMoleClick);
    });
  }

  // Update initial game display
  function updateGameDisplay() {
    const gameInfo = document.querySelector(".quiz-header");
    if (gameInfo) {
      gameInfo.innerHTML = `
            <div class="lives">❤️ x ${lives}</div>
            <div class="timer">Time: ${timer}s</div>
            <div class="score">Score: ${score}</div>
        `;
    }
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

    const MOLE_DURATION = 800;
    const MOLE_INTERVAL = 900;
    let activeMoles = new Set();

    moleInterval = setInterval(() => {
      // Hide any expired moles
      activeMoles.forEach((mole) => {
        if (mole.timestamp + MOLE_DURATION < Date.now()) {
          mole.element.classList.remove("active");
          activeMoles.delete(mole);
        }
      });

      // Randomly determine number of active moles (1-3)
      const NUM_ACTIVE_MOLES = Math.floor(Math.random() * 3) + 1;

      // Add new moles until we have NUM_ACTIVE_MOLES
      const moles = Array.from(document.querySelectorAll(".mole"));
      while (activeMoles.size < NUM_ACTIVE_MOLES) {
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
      // Ensure bonus score is handled as a number
      bonusScore =
        parseInt(bonusScore || 0) +
        parseInt(wpQuizGame.settings.bonusScore || 10);
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

    // Ensure all numbers are properly parsed as integers
    const quizScore = parseInt(score) || 0;
    const bonusPoints = parseInt(bonusScore) || 0;

    // Calculate final score using mathematical addition
    score = quizScore + bonusPoints;

    // Show final screen
    bonusScreen.classList.remove("active");
    endScreen.classList.add("active");

    // Format the scores with thousands separators for display
    document.getElementById(
      "final-score"
    ).textContent = `Final Score: ${score.toLocaleString()} (Quiz: ${quizScore.toLocaleString()}, Bonus: ${bonusPoints.toLocaleString()})`;

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
    lives = wpQuizGame.settings.lives;
    timer = wpQuizGame.settings.questionTimer;
    isAnswered = false;
    randomizedQuestions = [];

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
