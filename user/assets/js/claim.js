document.addEventListener('DOMContentLoaded', function () {
    const showMoreBtn = document.getElementById('claimShowMoreBtn');
    const counter = document.getElementById('claimResultCounter');

    if (showMoreBtn) {
        const step = parseInt(showMoreBtn.dataset.step || '10', 10);

        function hiddenItems() {
            return Array.from(document.querySelectorAll('.claim-result-item.is-hidden-result'));
        }

        const allItems = Array.from(document.querySelectorAll('.claim-result-item'));

        function updateCounter() {
            if (!counter) {
                return;
            }

            const visibleCount = allItems.filter(function (item) {
                return !item.classList.contains('is-hidden-result');
            }).length;

            counter.textContent = 'Showing ' + visibleCount + ' of ' + allItems.length + ' results.';
        }

        showMoreBtn.addEventListener('click', function () {
            const itemsToShow = hiddenItems().slice(0, step);

            itemsToShow.forEach(function (item) {
                item.classList.remove('is-hidden-result');
            });

            updateCounter();

            if (hiddenItems().length === 0) {
                showMoreBtn.style.display = 'none';
            }
        });

        updateCounter();
    }
});
