document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.blog-category-select').forEach(function (select) {
    select.addEventListener('change', function () {
      if (this.value) {
        window.location.href = this.value;
      }
    });
  });
});
