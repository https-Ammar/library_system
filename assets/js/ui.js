document.addEventListener("DOMContentLoaded", () => {
  // قص النص داخل عناصر .text
  document.querySelectorAll(".text").forEach((el) => {
    el.textContent = el.textContent.split(".")[0];
  });

  const message = document.getElementById("message");
  if (!localStorage.getItem("messageDisplayed")) {
    localStorage.setItem("messageDisplayed", "true");
    setTimeout(() => {
      message.style.opacity = "0";
      setTimeout(() => {
        message.style.display = "none";
      }, 500);
    }, 10000);
  } else {
    if (message) message.style.display = "none";
  }

  document.querySelectorAll(".playSound").forEach((button) => {
    button.addEventListener("click", () => {
      const audio = document.getElementById("audio");
      if (audio) {
        audio.currentTime = 0;
        audio.play();
        if (navigator.vibrate) navigator.vibrate(200);
      }
    });
  });

  $(".js-home-products").owlCarousel({
    loop: false,
    margin: 10,
    nav: true,
    autoplay: true,
    autoplayTimeout: 3000,
    responsive: {
      0: { items: 2 },
      600: { items: 2 },
      1000: { items: 5 },
    },
  });
});
