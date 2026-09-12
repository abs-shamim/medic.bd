(function () {
  const backLink = document.querySelector('.wr-success-btn');
  const redirectUrl = backLink ? backLink.getAttribute('href') : '';
  const countdown = document.getElementById('redirectCountdown');
  let secondsLeft = 60;

  const timer = window.setInterval(function () {
    secondsLeft -= 1;

    if (countdown) {
      countdown.textContent = String(Math.max(secondsLeft, 0));
    }

    if (secondsLeft <= 0) {
      window.clearInterval(timer);
      window.location.href = redirectUrl;
    }
  }, 1000);
})();
