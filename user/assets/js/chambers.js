(function() {
  const addForm = document.getElementById('addChamberAjaxForm');

  const isHospitalOwner = addForm ? addForm.dataset.isHospitalOwner === 'true' : false;
  const fixedDoctorId = addForm ? parseInt(addForm.dataset.fixedDoctorId || '0', 10) : 0;
  const fixedHospital = addForm ? JSON.parse(addForm.dataset.fixedHospital || '{}') : {};
  const csrfToken = addForm ? (addForm.dataset.csrfToken || '') : '';

  const division = document.getElementById('doctor_chamber_division');
  const district = document.getElementById('doctor_chamber_district');
  const thana = document.getElementById('doctor_chamber_thana');
  const dropdown = document.getElementById('doctor_chamber_hospital_dropdown');
  const hospitalSearch = document.getElementById('doctor_chamber_hospital_search');
  const hospitalId = document.getElementById('doctor_chamber_hospital');
  const hospitalViewUrl = document.getElementById('doctor_chamber_hospital_view_url');
  const hospitalAddress = document.getElementById('doctor_chamber_hospital_address');
  const hospitalLocation = document.getElementById('doctor_chamber_hospital_location');
  const hospitalList = document.getElementById('doctor_chamber_hospital_list');
  const doctorSelect = document.getElementById('hospital_owner_doctor_id');
  const preview = document.getElementById('doctor_chamber_preview');
  const chamberList = document.getElementById('doctorChamberList');
  let searchTimer = null;
  let lastSearchKey = '';

  function escapeHtml(value) {
    return String(value || '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }

  function ajaxUrl(action, params = {}) {
    const url = new URL(window.location.href);
    url.searchParams.set('chamber_ajax', action);
    Object.keys(params).forEach(key => url.searchParams.set(key, params[key]));
    return url.toString();
  }

  function resetSelect(select, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
  }

  function fillSelect(select, rows, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (rows || []).forEach(row => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.name;
      select.appendChild(option);
    });
    select.disabled = false;
  }

  function clearHospital(keepText = false) {
    if (hospitalId) hospitalId.value = '';
    if (hospitalViewUrl) hospitalViewUrl.value = '';
    if (hospitalAddress) hospitalAddress.value = '';
    if (hospitalLocation) hospitalLocation.value = '';
    if (hospitalSearch && !keepText) hospitalSearch.value = '';
    updatePreview();
  }

  function openDropdown() { if (dropdown) dropdown.classList.add('is-open'); }
  function closeDropdown() { if (dropdown) dropdown.classList.remove('is-open'); }

  function renderHospitals(items, message = '') {
    if (!hospitalList) return;
    hospitalList.innerHTML = '';
    if (message) { hospitalList.innerHTML = `<div class="wp-search-dropdown-empty">${escapeHtml(message)}</div>`; return; }
    if (!Array.isArray(items) || !items.length) { hospitalList.innerHTML = '<div class="wp-search-dropdown-empty">No hospital found</div>'; return; }
    items.forEach(item => {
      const row = document.createElement('div');
      row.className = 'wp-search-dropdown-item';
      row.innerHTML = `<strong>${escapeHtml(item.name || 'Hospital')}</strong><span class="wp-search-dropdown-meta">${escapeHtml(item.location || '')}</span>`;
      row.addEventListener('click', function() {
        hospitalId.value = String(item.id || '');
        hospitalViewUrl.value = String(item.view_url || '');
        hospitalAddress.value = String(item.address || '');
        hospitalLocation.value = String(item.location || '');
        hospitalSearch.value = String(item.name || '');
        closeDropdown();
        updatePreview();
      });
      hospitalList.appendChild(row);
    });
  }

  async function searchHospitals(force = false) {
    if (isHospitalOwner || !hospitalSearch) return;
    const key = [hospitalSearch.value.trim(), division?.value || '', district?.value || '', thana?.value || ''].join('|');
    if (!force && key === lastSearchKey) return;
    lastSearchKey = key;
    openDropdown();
    renderHospitals([], 'Searching hospitals...');
    try {
      const response = await fetch(ajaxUrl('hospitals', {q:hospitalSearch.value.trim(),division_id:division?.value || '',district_id:district?.value || '',thana_id:thana?.value || '',limit:'50'}));
      const data = await response.json();
      renderHospitals(data && data.success ? (data.items || []) : [], data && data.success ? '' : (data.message || 'Hospital search failed'));
    } catch (error) {
      renderHospitals([], 'Hospital search failed');
    }
  }

  function scheduleSearch(force = false) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchHospitals(force), 180);
  }

  async function loadDistricts(id) {
    resetSelect(district, '— Select District —');
    resetSelect(thana, '— All Thanas —');
    clearHospital();
    if (!id) { scheduleSearch(true); return; }
    try { const response = await fetch(ajaxUrl('districts', {division_id:id})); fillSelect(district, await response.json(), '— Select District —'); } catch (e) {}
    scheduleSearch(true); updatePreview();
  }

  async function loadThanas(id) {
    resetSelect(thana, '— All Thanas —');
    clearHospital();
    if (!id) { scheduleSearch(true); return; }
    try { const response = await fetch(ajaxUrl('thanas', {district_id:id})); fillSelect(thana, await response.json(), '— All Thanas —'); } catch (e) {}
    scheduleSearch(true); updatePreview();
  }

  function selectedText(select) {
    return select && select.selectedIndex > 0 ? select.options[select.selectedIndex].textContent.trim() : '';
  }

  function updatePreview() {
    if (!preview) return;
    if (isHospitalOwner) {
      const doctorName = selectedText(doctorSelect);
      preview.textContent = [doctorName, fixedHospital.name || ''].filter(Boolean).join(' → ') || 'Select a doctor and click Add.';
      return;
    }
    const parts = [selectedText(division), selectedText(district), selectedText(thana), hospitalId?.value ? hospitalSearch?.value.trim() : ''].filter(Boolean);
    preview.textContent = parts.length ? parts.join(' → ') : 'Select Division → District → Thana → Hospital Name.';
  }

  function currentSelection() {
    if (isHospitalOwner) {
      return {
        doctorId: doctorSelect?.value || '',
        doctorName: selectedText(doctorSelect),
        hospitalId: fixedHospital.id || '',
        hospitalName: fixedHospital.name || 'Hospital',
        viewUrl: fixedHospital.view_url || '#',
        address: fixedHospital.address || '',
        location: fixedHospital.location || ''
      };
    }
    return {
      doctorId: fixedDoctorId,
      doctorName: '',
      hospitalId: hospitalId?.value || '',
      hospitalName: hospitalSearch?.value.trim() || '',
      viewUrl: hospitalViewUrl?.value || '#',
      address: hospitalAddress?.value || '',
      location: hospitalLocation?.value || ''
    };
  }

  function duplicateExists(doctorIdValue, hospitalIdValue) {
    return Array.from(chamberList?.querySelectorAll('.lite-chamber-entry') || []).some(row =>
      String(row.dataset.doctorId || '') === String(doctorIdValue) && String(row.dataset.hospitalId || '') === String(hospitalIdValue)
    );
  }

  function nextSortOrder() {
    return (chamberList?.querySelectorAll('.lite-chamber-entry').length || 0) + 1;
  }

  function dayPills() {
    const days = [['sat','Saturday / শনিবার'],['sun','Sunday / রবিবার'],['mon','Monday / সোমবার'],['tue','Tuesday / মঙ্গলবার'],['wed','Wednesday / বুধবার'],['thu','Thursday / বৃহস্পতিবার'],['fri','Friday / শুক্রবার']];
    return days.map(day => `<label class="wp-day-pill"><input type="checkbox" name="available_days[]" value="${day[0]}"><span class="wp-day-check"></span><span class="wp-day-text">${day[1]}</span></label>`).join('');
  }

  function renumberRows() {
    Array.from(chamberList?.querySelectorAll('.lite-chamber-entry') || []).forEach((row,index) => {
      const number = row.querySelector('.lite-serial-number'); if (number) number.textContent = String(index + 1);
      if (row.classList.contains('unsaved-chamber')) {
        const editRow = row.nextElementSibling;
        const sortInput = editRow?.querySelector('input[name="chamber_sort_order"]');
        if (sortInput) sortInput.value = String(index + 1);
      }
    });
  }

  function buildNewRows(data) {
    const sort = nextSortOrder();
    const editId = `newChamberEditRow${data.hospitalId}_${data.doctorId}_${Date.now()}`;
    const subtitle = isHospitalOwner ? `Doctor: ${data.doctorName || ''}` : (data.location || 'Not saved yet. Fill options and click Save Chamber.');
    return `
      <tr class="lite-chamber-entry unsaved-chamber is-editing" data-doctor-id="${escapeHtml(data.doctorId)}" data-hospital-id="${escapeHtml(data.hospitalId)}" data-sort-order="${sort}">
        <td class="lite-serial-cell"><span class="lite-serial-number">${sort}</span></td>
        <td><div class="lite-chamber-name-cell"><div class="lite-chamber-name-main"><strong>${escapeHtml(data.hospitalName)}</strong></div><div class="lite-chamber-sub">${escapeHtml(subtitle)}</div></div></td>
        <td>—</td><td>—</td><td><span class="lite-status-badge lite-status-inactive">Unsaved</span></td>
        <td><div class="lite-table-action-group"><button type="button" class="lite-table-btn" data-chamber-edit-toggle="${editId}">Edit</button><div class="lite-chamber-menu-wrap" data-chamber-menu-wrap><button type="button" class="lite-kebab" data-chamber-menu-toggle aria-label="More actions"><i class="fa fa-ellipsis-v fa-solid fa-ellipsis-vertical"></i></button><div class="lite-chamber-menu"><a href="${escapeHtml(data.viewUrl)}" target="_blank" rel="noopener" class="lite-menu-link"><span class="lite-menu-icon">👁</span><span>View</span></a></div></div></div></td>
      </tr>
      <tr class="lite-chamber-edit-row is-open" id="${editId}"><td colspan="6">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="form_action" value="save_chamber"><input type="hidden" name="doctor_id" value="${escapeHtml(data.doctorId)}"><input type="hidden" name="chamber_hospital_id" value="${escapeHtml(data.hospitalId)}">
          <div class="wp-schedule-grid">
            <div><label>Appointment Phone</label><input type="text" name="chamber_appointment_phone" placeholder="+880..."></div>
            <div><label>Consultation Fee</label><input type="number" min="0" step="0.01" name="chamber_consultation_fee" placeholder="1000"></div>
            <div><label>From</label><input type="time" name="available_from"></div>
            <div><label>To</label><input type="time" name="available_to"></div>
            <div><label>Sort Order</label><input type="number" min="0" name="chamber_sort_order" value="${sort}"></div>
            <div><label>Status</label><select name="chamber_status"><option value="active" selected>Active</option><option value="inactive">Inactive</option></select></div>
            <div class="wp-field-pair"><div><label>Schedule</label><input type="text" name="schedule" placeholder="Saturday to Thursday 3pm to 9pm (Closed: Friday)"></div><div><label>Schedule Bangla</label><input type="text" name="schedule_bn" placeholder="শনিবার থেকে বৃহস্পতিবার বিকাল ৩টা থেকে রাত ৯টা (শুক্রবার বন্ধ)"></div></div>
            <div class="wp-field-pair"><div><label>Chamber Address</label><input type="text" name="chamber_address" value="${escapeHtml(data.address)}" placeholder="Chamber address or room/floor details"></div><div><label>Chamber Address Bangla</label><input type="text" name="chamber_address_bn" placeholder="চেম্বারের ঠিকানা বা রুম/ফ্লোরের তথ্য"></div></div>
          </div>
          <div class="wp-days-row">${dayPills()}<label class="wp-day-pill wp-day-pill-closed"><input type="checkbox" name="is_closed" value="1"><span class="wp-day-check"></span><span class="wp-day-text">Closed / বন্ধ</span></label></div>
          <div class="lite-edit-form-actions"><button type="submit" class="lite-btn lite-btn-primary">Save Chamber</button><button type="button" class="lite-btn lite-btn-light" data-unsaved-remove>Remove</button></div>
        </form>
      </td></tr>`;
  }

  function closeAllExcept(target) {
    document.querySelectorAll('.lite-chamber-edit-row.is-open').forEach(row => {
      if (row !== target) { row.classList.remove('is-open'); row.previousElementSibling?.classList.remove('is-editing'); }
    });
  }

  function bindInteractions(root = document) {
    root.querySelectorAll('[data-chamber-edit-toggle]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() {
        const row = document.getElementById(this.dataset.chamberEditToggle);
        if (!row) return;
        const opening = !row.classList.contains('is-open');
        closeAllExcept(row);
        row.classList.toggle('is-open', opening);
        row.previousElementSibling?.classList.toggle('is-editing', opening);
      });
    });
    root.querySelectorAll('[data-chamber-edit-cancel]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() { const row = this.closest('.lite-chamber-edit-row'); row?.classList.remove('is-open'); row?.previousElementSibling?.classList.remove('is-editing'); });
    });
    root.querySelectorAll('[data-unsaved-remove]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function() { const editRow = this.closest('.lite-chamber-edit-row'); const mainRow = editRow?.previousElementSibling; editRow?.remove(); mainRow?.remove(); renumberRows(); if (!(chamberList?.querySelector('.lite-chamber-entry'))) chamberList.innerHTML = '<tr id="noChamberMessage"><td colspan="6" class="lite-chamber-empty-cell">No saved chamber yet. Select the required doctor/hospital and click Add to open the option form.</td></tr>'; });
    });
    root.querySelectorAll('[data-chamber-menu-toggle]').forEach(button => {
      if (button.dataset.bound) return; button.dataset.bound = '1';
      button.addEventListener('click', function(event) { event.stopPropagation(); const wrap = this.closest('[data-chamber-menu-wrap]'); document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(item => { if (item !== wrap) item.classList.remove('is-open'); }); wrap?.classList.toggle('is-open'); });
    });
  }

  if (division) division.addEventListener('change', () => loadDistricts(division.value));
  if (district) district.addEventListener('change', () => loadThanas(district.value));
  if (thana) thana.addEventListener('change', () => { clearHospital(); scheduleSearch(true); updatePreview(); });
  if (hospitalSearch) {
    hospitalSearch.addEventListener('focus', () => scheduleSearch(true));
    hospitalSearch.addEventListener('input', function() { clearHospital(true); scheduleSearch(false); });
  }
  if (doctorSelect) doctorSelect.addEventListener('change', updatePreview);

  document.addEventListener('click', function(event) {
    if (dropdown && !dropdown.contains(event.target)) closeDropdown();
    if (!event.target.closest('[data-chamber-menu-wrap]')) document.querySelectorAll('[data-chamber-menu-wrap].is-open').forEach(item => item.classList.remove('is-open'));
  });

  if (addForm) addForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const data = currentSelection();
    if (!data.hospitalId) { alert('Please select a hospital first.'); return; }
    if (!data.doctorId) { alert('Please select a doctor first.'); return; }
    if (duplicateExists(data.doctorId, data.hospitalId)) { alert('This chamber already exists for the selected doctor and hospital.'); return; }
    document.getElementById('noChamberMessage')?.remove();
    chamberList.insertAdjacentHTML('afterbegin', buildNewRows(data));
    bindInteractions(chamberList);
    renumberRows();
    if (!isHospitalOwner) { clearHospital(); closeDropdown(); }
  });

  document.addEventListener('submit', function(event) {
    const form = event.target;
    const message = form instanceof HTMLFormElement ? form.dataset.confirmMessage : null;
    if (message && !window.confirm(message)) event.preventDefault();
  });

  bindInteractions(document);
  updatePreview();
})();
