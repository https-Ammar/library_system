window.addEventListener("DOMContentLoaded", () => {
  let elements = document.querySelectorAll(".text");
  elements.forEach((element) => {
    let text = element.textContent;
    let updatedText = text.split(".")[0];
    element.textContent = updatedText;
  });
});

document.querySelectorAll(".playSound").forEach((button) => {
  button.addEventListener("click", () => {
    var audio = document.getElementById("audio");
    audio.currentTime = 0;
    audio.play();
    if (navigator.vibrate) navigator.vibrate(200);
  });
});

if (!localStorage.getItem("messageDisplayed")) {
  localStorage.setItem("messageDisplayed", "true");
  setTimeout(() => {
    const canvas = document.getElementById("message");
    canvas.style.opacity = "0";
    setTimeout(() => (canvas.style.display = "none"), 500);
  }, 10000);
} else {
  document.getElementById("message").style.display = "none";
}
