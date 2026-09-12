document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-bar-percent]').forEach(function (bar) {
    bar.style.width = bar.dataset.barPercent + '%';
  });

  const loadMoreButton = document.getElementById('doctorReviewLoadMore');

  if (!loadMoreButton) {
    return;
  }

  const reviewItems = Array.from(document.querySelectorAll('[data-review-item]'));
  const step = parseInt(loadMoreButton.getAttribute('data-step') || '5', 10);

  loadMoreButton.addEventListener('click', function () {
    const hiddenItems = reviewItems.filter(function (item) {
      return item.classList.contains('is-hidden');
    });

    hiddenItems.slice(0, step).forEach(function (item) {
      item.classList.remove('is-hidden');
    });

    const remainingHiddenItems = reviewItems.filter(function (item) {
      return item.classList.contains('is-hidden');
    });

    if (remainingHiddenItems.length === 0) {
      loadMoreButton.style.display = 'none';
    }
  });
});
