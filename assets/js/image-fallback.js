document.addEventListener('error', function (event) {
  const img = event.target;

  if (!(img instanceof HTMLImageElement)) {
    return;
  }

  if (img.dataset.fallback === 'webp' && img.dataset.fallbackPng) {
    img.dataset.fallback = 'png';
    img.src = img.dataset.fallbackPng;
    return;
  }

  if (img.dataset.fallbackSrc && img.src !== img.dataset.fallbackSrc) {
    const fallbackSrc = img.dataset.fallbackSrc;
    delete img.dataset.fallbackSrc;
    img.src = fallbackSrc;
    return;
  }

  if (img.dataset.fallbackTarget) {
    img.style.display = 'none';
    const target = img.closest('[data-fallback-scope]') || img.parentNode;
    const fallback = target ? target.querySelector(img.dataset.fallbackTarget) : null;

    if (fallback) {
      fallback.style.display = 'flex';
    }

    return;
  }

  img.style.display = 'none';
}, true);
