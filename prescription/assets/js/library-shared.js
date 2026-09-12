document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('.rx-library-shared[data-accent]');

    if (el) {
        el.style.setProperty('--lib-accent', el.dataset.accent);
    }
});

document.addEventListener('submit', function (event) {
    var form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    var message = form.dataset.confirmMessage;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
