document.addEventListener('click', function(event) {
  const button = event.target.closest('[data-show-more]');

  if (!button) {
    return;
  }

  event.preventDefault();

  const wrapper = button.closest('.hospital-limited-tags');

  if (!wrapper) {
    return;
  }

  const hiddenTags = wrapper.querySelectorAll('.hospital-hidden-tag');

  hiddenTags.forEach(function(tag) {
    tag.classList.remove('hospital-hidden-tag');
  });

  button.remove();
});
