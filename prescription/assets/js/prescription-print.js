document.addEventListener('DOMContentLoaded', function () {
  const printButton = document.querySelector('[data-print-trigger]');

  if (!printButton) {
    return;
  }

  printButton.addEventListener('click', function () {
    window.print();
  });
});
