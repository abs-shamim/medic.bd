(function () {
  var el = document.querySelector('[data-prescription-config]');
  if (!el) {
    window.PRESCRIPTION_CONFIG = {};
    return;
  }

  try {
    window.PRESCRIPTION_CONFIG = JSON.parse(el.getAttribute('data-prescription-config'));
  } catch (e) {
    window.PRESCRIPTION_CONFIG = {};
  }
})();
