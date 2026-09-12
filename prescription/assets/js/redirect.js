(function () {
  var script = document.currentScript;
  if (!script) {
    return;
  }

  var url = script.getAttribute('data-url');
  if (url) {
    window.location.href = url;
  }
})();
