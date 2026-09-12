document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('homeDynamicSearchForm');
  const typeSelect = document.getElementById('homeSearchType');
  const specialtySelect = document.getElementById('homeSpecialtySelect');

  if (!form || !typeSelect) {
    return;
  }

  const doctorsUrl = form.dataset.doctorsUrl || '';
  const hospitalsUrl = form.dataset.hospitalsUrl || '';

  function slugify(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s-]+/gu, '')
      .replace(/[\s_]+/gu, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  function updateSearchAction() {
    if (typeSelect.value === 'hospitals') {
      form.action = hospitalsUrl;

      if (specialtySelect) {
        specialtySelect.disabled = true;
        specialtySelect.hidden = true;
      }
    } else {
      form.action = doctorsUrl;

      if (specialtySelect) {
        specialtySelect.disabled = false;
        specialtySelect.hidden = false;
      }
    }
  }

  typeSelect.addEventListener('change', updateSearchAction);
  updateSearchAction();

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    const searchInput = form.querySelector('input[name="search"]');
    const citySelect = form.querySelector('select[name="city"]');

    const type = typeSelect.value || 'doctors';
    const search = searchInput ? searchInput.value.trim() : '';
    const city = citySelect ? citySelect.value.trim() : '';
    const specialty = specialtySelect && !specialtySelect.disabled ? specialtySelect.value.trim() : '';

    let path = type === 'hospitals' ? hospitalsUrl : doctorsUrl;

    if (city !== '') {
      path += '/' + slugify(city);
    }

    if (type !== 'hospitals' && specialty !== '') {
      path += '/' + slugify(specialty);
    }

    const params = new URLSearchParams();

    if (search !== '') {
      params.set('search', search);
    }

    const queryString = params.toString();

    window.location.href = path + (queryString ? '?' + queryString : '');
  });
});
