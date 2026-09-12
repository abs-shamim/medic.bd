(function () {
  const slugInput = document.getElementById('slugInput');
  const checkSlugBtn = document.getElementById('checkSlugBtn');
  const slugStatus = document.getElementById('slugStatus');

  function normalizeSlug(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9-]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 50);
  }

  function setSlugStatus(type, message) {
    if (!slugStatus) return;
    slugStatus.className = type === 'ok' ? 'ok' : 'error';
    slugStatus.textContent = message;
    slugStatus.style.display = 'block';
  }

  async function checkSlugAvailability() {
    if (!slugInput) return false;

    const slug = normalizeSlug(slugInput.value);
    slugInput.value = slug;

    if (slug.length < 6 || slug.length > 50) {
      setSlugStatus('error', 'Slug must be minimum 6 and maximum 50 characters.');
      return false;
    }

    try {
      setSlugStatus('error', 'Checking slug...');
      const response = await fetch('profile-update.php?ajax=check_slug&slug=' + encodeURIComponent(slug), {
        headers: { 'Accept': 'application/json' }
      });
      const data = await response.json();
      setSlugStatus(data.ok ? 'ok' : 'error', data.message || 'Unable to check slug.');
      return !!data.ok;
    } catch (error) {
      setSlugStatus('error', 'Unable to check slug right now.');
      return false;
    }
  }

  if (slugInput) {
    slugInput.addEventListener('input', function () {
      slugInput.value = normalizeSlug(slugInput.value);
      if (slugStatus) {
        slugStatus.style.display = 'none';
        slugStatus.textContent = '';
        slugStatus.className = '';
      }
    });
  }

  if (checkSlugBtn) checkSlugBtn.addEventListener('click', checkSlugAvailability);

  const profileUpdateMainForm = document.getElementById('profileUpdateMainForm');
  const districts = profileUpdateMainForm ? JSON.parse(profileUpdateMainForm.dataset.districts || '[]') : [];
  const thanas = profileUpdateMainForm ? JSON.parse(profileUpdateMainForm.dataset.thanas || '[]') : [];
  const locationSource = document.getElementById('doctor_location_source');
  const divisionSelect = document.getElementById('doctor_division');
  const districtSelect = document.getElementById('doctor_district');
  const thanaSelect = document.getElementById('doctor_thana');

  function resetSelect(select, label) {
    if (!select) return;
    select.innerHTML = '<option value="">' + label + '</option>';
  }

  function fillDistricts() {
    if (!divisionSelect || !districtSelect) return;
    const divisionId = String(divisionSelect.value || '');
    const current = String(districtSelect.dataset.current || '');
    resetSelect(districtSelect, 'Select District');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    districts.filter(row => String(row.division_id || '') === divisionId).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      if (String(row.id) === current) option.selected = true;
      districtSelect.appendChild(option);
    });

    fillThanas();
  }

  function fillThanas() {
    if (!districtSelect || !thanaSelect) return;
    const districtId = String(districtSelect.value || '');
    const current = String(thanaSelect.dataset.current || '');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    thanas.filter(row => String(row.district_id || '') === districtId).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      if (String(row.id) === current) option.selected = true;
      thanaSelect.appendChild(option);
    });
  }

  function toggleLocationFields() {
    const manual = !locationSource || locationSource.value === 'manual';
    document.querySelectorAll('.pu-location-field select').forEach(select => {
      select.disabled = !manual;
    });
  }

  if (divisionSelect) {
    divisionSelect.addEventListener('change', function () {
      if (districtSelect) districtSelect.dataset.current = '';
      if (thanaSelect) thanaSelect.dataset.current = '';
      fillDistricts();
    });
    fillDistricts();
  }

  if (districtSelect) {
    districtSelect.addEventListener('change', function () {
      if (thanaSelect) thanaSelect.dataset.current = '';
      fillThanas();
    });
  }

  if (locationSource) {
    locationSource.addEventListener('change', toggleLocationFields);
    toggleLocationFields();
  }

  function updateCounter(field) {
    const counter = document.querySelector('[data-counter-for="' + field.id + '"]');
    if (counter) counter.textContent = String(field.value.length);
  }

  function applySeoMode(select) {
    const target = document.getElementById(select.dataset.target || '');
    if (!target) return;
    const auto = select.value !== 'manual';
    target.readOnly = auto;
    if (auto) target.value = target.dataset.autoValue || '';
    updateCounter(target);
  }

  document.querySelectorAll('.pu-seo-mode').forEach(select => {
    select.addEventListener('change', () => applySeoMode(select));
    applySeoMode(select);
  });

  document.querySelectorAll('[data-counter-for]').forEach(counter => {
    const target = document.getElementById(counter.dataset.counterFor || '');
    if (!target) return;
    target.addEventListener('input', () => updateCounter(target));
    updateCounter(target);
  });
})();
