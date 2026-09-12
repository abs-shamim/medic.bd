(function () {
    var script = document.currentScript;
    var url = script ? script.getAttribute('data-redirect-url') : '';

    if (url) {
        window.location.href = url;
    }
})();
