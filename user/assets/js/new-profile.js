(function () {
  const newProfileForm = document.getElementById('newProfileForm');

  const specialties = newProfileForm ? JSON.parse(newProfileForm.dataset.specialties || '[]') : [];
  const districts = newProfileForm ? JSON.parse(newProfileForm.dataset.districts || '[]') : [];
  const thanas = newProfileForm ? JSON.parse(newProfileForm.dataset.thanas || '[]') : [];

  const doctorName = document.getElementById('doctorName');
  const doctorNameBn = document.getElementById('doctorNameBn');
  const slugInput = document.getElementById('slugInput');
  const checkSlugBtn = document.getElementById('checkSlugBtn');
  const slugStatus = document.getElementById('slugStatus');
  const specialtySelect = document.getElementById('specialtyId');

  const divisionSelect = document.getElementById('doctor_division_id');
  const districtSelect = document.getElementById('doctor_district_id');
  const thanaSelect = document.getElementById('doctor_thana_id');

  const divisionNameInput = document.getElementById('doctor_division_name');
  const districtNameInput = document.getElementById('doctor_district_name');
  const thanaNameInput = document.getElementById('doctor_thana_name');

  function normalizeSlug(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9-]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 70);
  }

  function setSlugStatus(type, message) {
    if (!slugStatus) return;
    slugStatus.className = type === 'ok' ? 'ok' : 'error';
    slugStatus.textContent = message;
  }

  async function checkSlugAvailability() {
    if (!slugInput) return false;

    const slug = normalizeSlug(slugInput.value);
    slugInput.value = slug;

    if (slug.length < 6) {
      setSlugStatus('error', 'Slug must contain at least 6 characters.');
      return false;
    }

    try {
      setSlugStatus('error', 'Checking slug...');
      const response = await fetch('new-profile.php?ajax=check_slug&slug=' + encodeURIComponent(slug), {
        headers: { Accept: 'application/json' }
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
        slugStatus.className = '';
        slugStatus.textContent = '';
      }
    });
  }

  if (doctorName && slugInput) {
    doctorName.addEventListener('input', function () {
      if (!slugInput.dataset.userEdited && slugInput.value.trim() === '') {
        slugInput.value = normalizeSlug(doctorName.value);
      }
      updateAutoSeo();
    });

    slugInput.addEventListener('input', function () {
      slugInput.dataset.userEdited = '1';
    });
  }

  if (doctorNameBn) {
    doctorNameBn.addEventListener('input', updateAutoSeo);
  }

  if (checkSlugBtn) {
    checkSlugBtn.addEventListener('click', checkSlugAvailability);
  }

  function selectedText(select) {
    if (!select || !select.value || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].textContent.trim();
  }

  function selectedBn(select) {
    if (!select || !select.value || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].dataset.bn || '';
  }

  function resetSelect(select, label) {
    if (!select) return;
    select.innerHTML = '<option value="">' + label + '</option>';
    select.disabled = true;
  }

  function fillSelect(select, rows, label, currentId) {
    if (!select) return;

    select.innerHTML = '<option value="">' + label + '</option>';

    rows.forEach(function (row) {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.label || row.name || row.id;
      option.dataset.bn = row.label_bn || '';

      if (String(row.id) === String(currentId || '')) {
        option.selected = true;
      }

      select.appendChild(option);
    });

    select.disabled = false;
  }

  function updateLocationNames() {
    if (divisionNameInput) divisionNameInput.value = selectedText(divisionSelect);
    if (districtNameInput) districtNameInput.value = selectedText(districtSelect);
    if (thanaNameInput) thanaNameInput.value = selectedText(thanaSelect);
    updateAutoSeo();
  }

  function loadDistricts(divisionId, currentDistrictId) {
    resetSelect(districtSelect, 'Select District');
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    if (!divisionId) {
      updateLocationNames();
      return;
    }

    const rows = districts.filter(function (row) {
      return String(row.division_id || '') === String(divisionId);
    });

    fillSelect(districtSelect, rows, 'Select District', currentDistrictId);
    updateLocationNames();
  }

  function loadThanas(districtId, currentThanaId) {
    resetSelect(thanaSelect, 'Select Thana / Upazila');

    if (!districtId) {
      updateLocationNames();
      return;
    }

    const rows = thanas.filter(function (row) {
      return String(row.district_id || '') === String(districtId);
    });

    fillSelect(thanaSelect, rows, 'Select Thana / Upazila', currentThanaId);
    updateLocationNames();
  }

  if (divisionSelect) {
    divisionSelect.addEventListener('change', function () {
      if (districtSelect) districtSelect.dataset.current = '';
      if (thanaSelect) thanaSelect.dataset.current = '';
      loadDistricts(this.value, '');
    });
  }

  if (districtSelect) {
    districtSelect.addEventListener('change', function () {
      if (thanaSelect) thanaSelect.dataset.current = '';
      loadThanas(this.value, '');
    });
  }

  if (thanaSelect) {
    thanaSelect.addEventListener('change', updateLocationNames);
  }

  if (divisionSelect && divisionSelect.value) {
    const savedDistrictId = districtSelect ? districtSelect.dataset.current : '';
    const savedThanaId = thanaSelect ? thanaSelect.dataset.current : '';

    loadDistricts(divisionSelect.value, savedDistrictId);

    if (savedDistrictId) {
      loadThanas(savedDistrictId, savedThanaId);
    }
  }

  function joinUnique(values, separator) {
    const seen = new Set();
    const clean = [];

    values.forEach(function (value) {
      value = String(value || '').trim().replace(/\s+/g, ' ');
      if (!value) return;

      const key = value.toLocaleLowerCase();
      if (!seen.has(key)) {
        seen.add(key);
        clean.push(value);
      }
    });

    return clean.join(separator || ', ');
  }

  function limitText(value, max) {
    value = String(value || '').trim().replace(/\s+/g, ' ');
    return value.length <= max ? value : value.slice(0, max - 1).trimEnd() + '…';
  }

  function autoSeoValues() {
    const name = doctorName ? doctorName.value.trim() : '';
    const nameBn = doctorNameBn && doctorNameBn.value.trim() ? doctorNameBn.value.trim() : name;
    const specialty = selectedText(specialtySelect);
    const specialtyBn = selectedBn(specialtySelect) || specialty;
    const district = selectedText(districtSelect);
    const districtBn = selectedBn(districtSelect) || district;

    const title = joinUnique([name, specialty, district], ' - ');
    const titleBn = joinUnique([nameBn, specialtyBn, districtBn], ' - ');

    return {
      seo_title: limitText(title, 160),
      seo_title_bn: limitText(titleBn, 160),
      seo_description: limitText(
        title ? joinUnique([name, specialty, district], ', ') + '. View doctor profile, specialty, location, appointment information, consultation fee and schedule.' : '',
        300
      ),
      seo_description_bn: limitText(
        titleBn ? joinUnique([nameBn, specialtyBn, districtBn], ', ') + ' এর ডাক্তার প্রোফাইল, বিশেষজ্ঞতা, অবস্থান, অ্যাপয়েন্টমেন্ট তথ্য, কনসালটেশন ফি ও সময়সূচি দেখুন।' : '',
        300
      ),
      meta_keywords: limitText(joinUnique([
        name,
        specialty,
        district,
        name && specialty ? name + ' ' + specialty : '',
        specialty && district ? specialty + ' doctor in ' + district : ''
      ], ', '), 255),
      meta_keywords_bn: limitText(joinUnique([
        nameBn,
        specialtyBn,
        districtBn,
        specialtyBn && districtBn ? districtBn + ' এর ' + specialtyBn + ' ডাক্তার' : ''
      ], ', '), 255)
    };
  }

  function updateCounter(field) {
    const counter = document.querySelector('[data-counter-for="' + field.id + '"]');
    if (counter) counter.textContent = String(field.value.length);
  }

  function updateAutoSeo() {
    const values = autoSeoValues();

    document.querySelectorAll('.np-seo-mode').forEach(function (modeSelect) {
      const target = document.getElementById(modeSelect.dataset.target || '');
      if (!target) return;

      target.dataset.autoValue = values[target.id] || '';

      if (modeSelect.value === 'auto') {
        target.value = target.dataset.autoValue;
        target.readOnly = true;
      } else {
        target.readOnly = false;
      }

      updateCounter(target);
    });
  }

  document.querySelectorAll('.np-seo-mode').forEach(function (select) {
    select.addEventListener('change', updateAutoSeo);
  });

  document.querySelectorAll('.np-seo-field input, .np-seo-field textarea').forEach(function (field) {
    field.addEventListener('input', function () {
      updateCounter(field);
    });
    updateCounter(field);
  });

  if (specialtySelect) {
    specialtySelect.addEventListener('change', updateAutoSeo);
  }

  updateAutoSeo();
})();
