(function () {
  'use strict';

  const config = window.PRESCRIPTION_CONFIG || {};
  const librarySearchUrl = config.librarySearchUrl || config.medicineSearchUrl || '';


  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');

  function setSidebarOpen(isOpen) {
    document.body.classList.toggle('rx-sidebar-open', Boolean(isOpen));
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      setSidebarOpen(!document.body.classList.contains('rx-sidebar-open'));
    });
  }

  sidebarCloseButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      setSidebarOpen(false);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setSidebarOpen(false);
    }
  });


  const banglaPattern = /[\u0980-\u09FF]/;
  const banglaTextSelector = [
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'p', 'span', 'strong', 'small', 'label', 'a', 'button',
    'td', 'th', 'li', 'option', 'legend', 'figcaption'
  ].join(',');

  function containsBangla(value) {
    return banglaPattern.test(String(value == null ? '' : value));
  }

  function directTextContent(element) {
    if (!element || !element.childNodes) return '';

    return Array.from(element.childNodes).map(function (node) {
      return node.nodeType === Node.TEXT_NODE ? node.nodeValue || '' : '';
    }).join(' ').trim();
  }

  function updateBanglaFont(element) {
    if (!(element instanceof Element)) return;

    let value = '';

    if (element.matches('input, textarea')) {
      value = String(element.value || '') + ' ' + String(element.getAttribute('placeholder') || '');
    } else if (element.matches('select')) {
      const option = element.options && element.selectedIndex >= 0
        ? element.options[element.selectedIndex]
        : null;
      value = option ? option.textContent || '' : '';
    } else {
      value = directTextContent(element);
    }

    const isBangla = containsBangla(value);
    element.classList.toggle('rx-bangla-font', isBangla);

    if (isBangla) {
      element.setAttribute('lang', 'bn');
    } else if (element.getAttribute('lang') === 'bn') {
      element.removeAttribute('lang');
    }
  }

  function refreshBanglaFonts(root) {
    const scope = root instanceof Element || root instanceof Document ? root : document;

    if (scope instanceof Element) {
      updateBanglaFont(scope);
    }

    scope.querySelectorAll(banglaTextSelector + ', input, textarea, select').forEach(updateBanglaFont);
  }

  function initialiseBanglaFontDetection() {
    refreshBanglaFonts(document);

    document.addEventListener('input', function (event) {
      if (event.target instanceof Element) updateBanglaFont(event.target);
    }, true);

    document.addEventListener('change', function (event) {
      if (event.target instanceof Element) updateBanglaFont(event.target);
    }, true);

    document.addEventListener('click', function () {
      window.setTimeout(function () {
        refreshBanglaFonts(document);
      }, 0);
    }, true);

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.type === 'characterData') {
          const parent = mutation.target.parentElement;
          if (parent) updateBanglaFont(parent);
          return;
        }

        mutation.addedNodes.forEach(function (node) {
          if (node instanceof Element) refreshBanglaFonts(node);
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function debounce(callback, delay) {
    let timer = null;

    return function () {
      const context = this;
      const args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        callback.apply(context, args);
      }, delay);
    };
  }

  function libraryFieldHtml(label, name, type, value, placeholder, extraClass, role) {
    const roleAttr = role ? ` data-library-role="${escapeHtml(role)}"` : '';
    const labelHtml = label ? `<label>${label}</label>` : '';

    return `
      <div class="rx-library-field ${extraClass || ''}">
        ${labelHtml}
        <div class="rx-library-input-control">
          <input
            type="text"
            name="${name}[]"
            class="js-library-input${type === 'medicine' && role === 'generic' ? ' js-medicine-generic' : ''}${type === 'medicine' && role === 'brand' ? ' js-medicine-brand' : ''}"
            data-library-type="${escapeHtml(type)}"${roleAttr}
            value="${escapeHtml(value || '')}"
            placeholder="${escapeHtml(placeholder || '')}"
            autocomplete="off"
          >
          <button type="button" class="rx-library-open js-open-library" aria-label="Open ${escapeHtml(type)} library">⌄</button>
        </div>
        <div class="rx-field-library-dropdown"></div>
      </div>
    `;
  }

  function medicineRow(data) {
    data = data || {};

    const genericName = data.generic_name || data.medicine_generic_name || data.medicine_name || '';
    const brandName = data.brand_name || data.medicine_brand_name || '';
    const medicineForm = data.medicine_form || '';

    return `
      <div class="rx-editor-medicine-row js-medicine-row">
        <span class="rx-editor-medicine-number">0.</span>
        <div class="rx-editor-medicine-fields">
          <input type="hidden" name="medicine_library_id[]" value="${escapeHtml(data.library_id || '')}" class="js-medicine-library-id">

          <div class="rx-editor-medicine-search-head">
            <span>Each field has its own library, and manual entry remains available.</span>
            <a class="rx-library-browse-btn" href="${escapeHtml(config.libraryUrl || '#')}" target="_blank" rel="noopener">Manage Libraries</a>
          </div>

          <div class="rx-editor-medicine-primary rx-field">
            ${libraryFieldHtml('Medicine Form', 'medicine_form', 'medicine_form', medicineForm, 'Tab., Cap., Syr., Inj...', 'rx-medicine-input-wrap')}
            ${libraryFieldHtml('Generic Name', 'medicine_generic_name', 'medicine', genericName, 'Type or select generic name', 'rx-medicine-input-wrap', 'generic')}
            ${libraryFieldHtml('Brand Name <small>(optional)</small>', 'medicine_brand_name', 'medicine', brandName, 'Type or select optional brand', 'rx-medicine-input-wrap', 'brand')}
            ${libraryFieldHtml('Strength', 'medicine_strength', 'strength', data.strength || '', 'Type or select strength', 'rx-medicine-input-wrap')}
          </div>

          <div class="rx-editor-medicine-secondary">
            ${libraryFieldHtml('Dosage', 'medicine_dosage', 'dosage', data.dosage || '', 'Type or select dosage')}
            ${libraryFieldHtml('Frequency', 'medicine_frequency', 'frequency', data.frequency || '', 'Type or select frequency')}
            ${libraryFieldHtml('Duration', 'medicine_duration', 'duration', data.duration || '', 'Type or select duration')}
            ${libraryFieldHtml('Instruction', 'medicine_instruction', 'instruction', data.instruction || '', 'Type or select instruction')}
          </div>

          <div class="rx-medicine-manual-note">
            Select from each independent dropdown or continue typing manually in any field.
          </div>
        </div>
        <button type="button" class="rx-paper-line-remove js-remove-row" aria-label="Remove medicine">×</button>
      </div>
    `;
  }

  function testRow(data) {
    data = data || {};

    return `
      <div class="rx-editor-test-row js-test-row">
        <span class="rx-editor-test-bullet">•</span>

        ${libraryFieldHtml('', 'test_name', 'test', data.test_name || '', 'Type or select investigation')}

        ${libraryFieldHtml('', 'test_instruction', 'test_instruction', data.instruction || '', 'Type or select instruction')}

        <button type="button" class="rx-paper-line-remove js-remove-row" aria-label="Remove test">×</button>
      </div>
    `;
  }

  function renumberMedicines() {
    document.querySelectorAll('#medicineRows .js-medicine-row').forEach(function (row, index) {
      const number = row.querySelector('.rx-editor-medicine-number');
      if (number) number.textContent = String(index + 1) + '.';
    });
  }

  function flashTarget(element) {
    if (!element) return;

    element.classList.remove('rx-editor-flash-target');
    void element.offsetWidth;
    element.classList.add('rx-editor-flash-target');

    window.setTimeout(function () {
      element.classList.remove('rx-editor-flash-target');
    }, 1200);
  }

  function focusAndReveal(element) {
    if (!element) return;

    const section = element.closest('.rx-paper-section, .rx-editor-medicine-column, .rx-editor-advice');
    const target = section || element;
    flashTarget(target);
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    window.setTimeout(function () {
      element.focus();
    }, 260);
  }

  const medicineList = document.getElementById('medicineRows');
  const testList = document.getElementById('testRows');
  const addMedicineButton = document.getElementById('addMedicineRow');
  const addTestButton = document.getElementById('addTestRow');

  function addMedicine(data) {
    if (!medicineList) return null;

    medicineList.insertAdjacentHTML('beforeend', medicineRow(data));
    renumberMedicines();

    const row = medicineList.lastElementChild;
    const input = row ? row.querySelector('.js-medicine-generic') : null;
    focusAndReveal(input);

    return row;
  }

  function addTest(data) {
    if (!testList) return null;

    testList.insertAdjacentHTML('beforeend', testRow(data));
    const row = testList.lastElementChild;
    const input = row ? row.querySelector('input[name="test_name[]"]') : null;
    focusAndReveal(input);

    return row;
  }

  if (addMedicineButton) {
    addMedicineButton.addEventListener('click', function () {
      addMedicine();
    });
  }

  if (addTestButton) {
    addTestButton.addEventListener('click', function () {
      addTest();
    });
  }

  renumberMedicines();

  function autoGrowTextarea(textarea) {
    if (!textarea) return;

    textarea.style.height = 'auto';
    textarea.style.height = Math.min(Math.max(textarea.scrollHeight, 28), 170) + 'px';
  }

  function lineLibraryType(target) {
    const map = {
      chief_complaints: 'complaint',
      diagnosis: 'diagnosis',
      medical_history: 'history',
      examination: 'examination',
      advice: 'advice'
    };

    return map[target] || '';
  }

  function lineRow(value, placeholder, libraryType) {
    return `
      <div class="rx-paper-line js-paper-line rx-library-field">
        <div class="rx-library-input-control rx-line-library-control">
          <textarea
            rows="1"
            class="js-line-input js-library-input"
            data-library-type="${escapeHtml(libraryType || '')}"
            placeholder="${escapeHtml(placeholder || 'Write here')}"
            autocomplete="off"
          >${escapeHtml(value || '')}</textarea>
          <button type="button" class="rx-library-open js-open-library" aria-label="Open library">⌄</button>
        </div>
        <div class="rx-field-library-dropdown"></div>
        <button type="button" class="rx-paper-line-remove js-remove-line" aria-label="Remove line">×</button>
      </div>
    `;
  }

  function syncLineGroup(target) {
    const list = document.querySelector('[data-line-list="' + target + '"]');
    const hidden = document.querySelector('[data-line-hidden="' + target + '"]');

    if (!list || !hidden) return;

    hidden.value = Array.from(list.querySelectorAll('.js-line-input'))
      .map(function (input) { return input.value.trim(); })
      .filter(Boolean)
      .join('\n');
  }

  function syncAllLineGroups() {
    document.querySelectorAll('[data-line-list]').forEach(function (list) {
      syncLineGroup(list.dataset.lineList || '');
    });
  }

  function addLine(target, value) {
    const list = document.querySelector('[data-line-list="' + target + '"]');

    if (!list) return null;

    const placeholder = list.dataset.placeholder || 'Write here';
    list.insertAdjacentHTML('beforeend', lineRow(value || '', placeholder, lineLibraryType(target)));

    const row = list.lastElementChild;
    const input = row ? row.querySelector('.js-line-input') : null;
    autoGrowTextarea(input);
    syncLineGroup(target);
    focusAndReveal(input);

    return row;
  }

  document.querySelectorAll('.js-line-input').forEach(autoGrowTextarea);

  document.addEventListener('input', function (event) {
    if (!event.target.matches('.js-line-input')) return;

    autoGrowTextarea(event.target);
    const list = event.target.closest('[data-line-list]');
    if (list) syncLineGroup(list.dataset.lineList || '');
  });

  document.addEventListener('click', function (event) {
    const addLineButton = event.target.closest('.js-add-line');

    if (addLineButton) {
      addLine(addLineButton.dataset.target || '');
      return;
    }

    const removeLineButton = event.target.closest('.js-remove-line');

    if (removeLineButton) {
      const row = removeLineButton.closest('.js-paper-line');
      const list = row ? row.closest('[data-line-list]') : null;

      if (row && list) {
        const rows = list.querySelectorAll('.js-paper-line');

        if (rows.length === 1) {
          const input = row.querySelector('.js-line-input');
          if (input) {
            input.value = '';
            autoGrowTextarea(input);
            input.focus();
          }
        } else {
          row.remove();
        }

        syncLineGroup(list.dataset.lineList || '');
      }
      return;
    }

    const removeButton = event.target.closest('.js-remove-row');

    if (removeButton) {
      const medicineRowElement = removeButton.closest('.js-medicine-row');
      const testRowElement = removeButton.closest('.js-test-row');
      const row = medicineRowElement || testRowElement;

      if (!row) return;

      const parent = row.parentElement;
      const selector = medicineRowElement ? '.js-medicine-row' : '.js-test-row';
      const siblings = parent ? parent.querySelectorAll(selector) : [];

      if (siblings.length === 1) {
        row.querySelectorAll('input, textarea').forEach(function (field) {
          field.value = '';
        });
        const first = row.querySelector('input, textarea');
        if (first) first.focus();
      } else {
        row.remove();
      }

      renumberMedicines();
    }
  });

  document.querySelectorAll('.js-quick-add').forEach(function (button) {
    button.addEventListener('click', function () {
      const type = button.dataset.addType || '';

      if (type === 'medicine') {
        addMedicine();
      } else if (type === 'test') {
        addTest();
      } else if (type === 'line') {
        addLine(button.dataset.target || '');
      }
    });
  });

  const patientSearch = document.getElementById('patientSearch');
  const patientResults = document.getElementById('patientSearchResults');
  const patientIdField = document.querySelector('[name="patient_id"]');
  const patientNameField = document.getElementById('patientName') || document.querySelector('[name="patient_name"]');
  const patientPhoneField = document.getElementById('patientPhone') || document.querySelector('[name="patient_phone"]');
  const selectedPatientCard = document.getElementById('selectedPatientCard');
  const selectedPatientName = document.getElementById('selectedPatientName');
  const selectedPatientMeta = document.getElementById('selectedPatientMeta');
  const selectedPatientHistoryLink = document.getElementById('selectedPatientHistoryLink');
  const clearSelectedPatientButton = document.getElementById('clearSelectedPatient');
  let selectedPatient = config.selectedPatient && typeof config.selectedPatient === 'object'
    ? config.selectedPatient
    : null;

  function normalisePhone(value) {
    let digits = String(value || '').replace(/\D+/g, '');

    if (digits.indexOf('00880') === 0) digits = digits.slice(2);
    if (digits.indexOf('0') === 0 && digits.length === 11) digits = '88' + digits;

    return digits;
  }

  function formatDate(value) {
    if (!value) return '';
    const date = new Date(String(value) + 'T00:00:00');
    if (Number.isNaN(date.getTime())) return String(value);

    return date.toLocaleDateString(undefined, {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
  }

  function hidePatientResults() {
    if (!patientResults) return;
    patientResults.classList.remove('show');
    patientResults.innerHTML = '';
  }

  function setPatientField(name, value, force) {
    const cleanValue = value == null ? '' : String(value);

    if (name === 'medical_history') {
      const list = document.querySelector('[data-line-list="medical_history"]');
      const hidden = document.querySelector('[data-line-hidden="medical_history"]');
      const current = hidden ? String(hidden.value || '').trim() : '';

      if (!list || !hidden || (!force && current !== '')) return;

      const lines = cleanValue.replace(/\r\n?/g, '\n').split('\n')
        .map(function (line) { return line.trim(); })
        .filter(Boolean);
      const values = lines.length ? lines : [''];
      const placeholder = list.dataset.placeholder || 'Write here';

      list.innerHTML = values.map(function (line) {
        return lineRow(line, placeholder, lineLibraryType('medical_history'));
      }).join('');
      hidden.value = cleanValue;
      list.querySelectorAll('.js-line-input').forEach(autoGrowTextarea);
      refreshBanglaFonts(list);
      return;
    }

    const field = document.querySelector('[name="' + name + '"]');
    if (!field) return;

    const current = String(field.value || '').trim();
    if (force || current === '') {
      field.value = cleanValue;
      updateBanglaFont(field);
    }
  }

  function renderSelectedPatient(patient) {
    if (!selectedPatientCard || !patient) return;

    const count = Number(patient.prescription_count || 0);
    const meta = [
      patient.patient_code || '',
      patient.phone || '',
      count + (count === 1 ? ' prescription' : ' prescriptions'),
      patient.last_visit ? 'Last visit ' + formatDate(patient.last_visit) : ''
    ].filter(Boolean).join(' · ');

    if (selectedPatientName) selectedPatientName.textContent = patient.name || 'Selected Patient';
    if (selectedPatientMeta) selectedPatientMeta.textContent = meta;

    if (selectedPatientHistoryLink) {
      selectedPatientHistoryLink.href = config.patientViewUrl
        ? config.patientViewUrl + '?id=' + encodeURIComponent(patient.id || '')
        : '#';
    }

    selectedPatientCard.classList.add('show');
  }

  function clearPatientSelection(keepEnteredData) {
    selectedPatient = null;
    if (patientIdField) patientIdField.value = '';
    if (selectedPatientCard) selectedPatientCard.classList.remove('show');
    if (patientSearch) patientSearch.value = '';

    if (!keepEnteredData) {
      [
        'patient_name', 'patient_phone', 'patient_age', 'patient_gender',
        'patient_blood_group', 'patient_address', 'weight', 'height',
        'blood_pressure', 'temperature', 'pulse', 'spo2', 'medical_history'
      ].forEach(function (name) {
        setPatientField(name, '', true);
      });
      if (patientSearch) patientSearch.value = '';
    }
  }

  function applyPatient(patient, force) {
    if (!patient) return;

    const mapping = {
      patient_id: patient.id,
      patient_name: patient.name,
      patient_phone: patient.phone,
      patient_age: patient.age,
      patient_gender: patient.gender,
      patient_blood_group: patient.blood_group,
      patient_address: patient.address,
      weight: patient.last_weight,
      height: patient.last_height,
      blood_pressure: patient.last_blood_pressure,
      temperature: patient.last_temperature,
      pulse: patient.last_pulse,
      spo2: patient.last_spo2,
      medical_history: patient.medical_history
    };

    Object.keys(mapping).forEach(function (name) {
      setPatientField(name, mapping[name], force !== false);
    });

    selectedPatient = patient;
    if (patientSearch) patientSearch.value = patient.name || patient.phone || '';
    renderSelectedPatient(patient);
    hidePatientResults();
    refreshBanglaFonts(document);
  }

  function patientIsExactMatch(patient, query, source) {
    const raw = String(query || '').trim();
    if (!raw) return false;

    if (source === 'phone') {
      const queryPhone = normalisePhone(raw);
      const patientPhone = normalisePhone(patient.phone_normalized || patient.phone || '');
      return queryPhone.length >= 7 && queryPhone === patientPhone;
    }

    if (source === 'name') {
      return raw.length >= 3
        && raw.toLocaleLowerCase() === String(patient.name || '').trim().toLocaleLowerCase();
    }

    return false;
  }

  function showPatientRows(rows) {
    if (!patientResults) return;

    if (!rows.length) {
      patientResults.innerHTML = '<div class="rx-search-option rx-search-empty"><small>No matching patient found. This will be saved as a new patient.</small></div>';
      patientResults.classList.add('show');
      return;
    }

    patientResults.innerHTML = rows.map(function (patient, index) {
      const count = Number(patient.prescription_count || 0);
      const details = [
        patient.patient_code || '',
        patient.phone || '',
        patient.age || '',
        count + (count === 1 ? ' Rx' : ' Rx'),
        patient.last_visit ? 'Last ' + formatDate(patient.last_visit) : ''
      ].filter(Boolean).join(' · ');

      return `
        <button type="button" class="rx-search-option js-patient-option" data-index="${index}">
          <span class="rx-search-patient-avatar">${escapeHtml(String(patient.name || 'P').slice(0, 1).toUpperCase())}</span>
          <span class="rx-search-patient-copy">
            <strong>${escapeHtml(patient.name || '')}</strong>
            <small>${escapeHtml(details)}</small>
            ${patient.last_diagnosis ? `<em>${escapeHtml(patient.last_diagnosis)}</em>` : ''}
          </span>
          <span class="rx-search-select-label">Select</span>
        </button>
      `;
    }).join('');
    patientResults.classList.add('show');

    patientResults.querySelectorAll('.js-patient-option').forEach(function (button) {
      button.addEventListener('click', function () {
        const patient = rows[Number(button.dataset.index || 0)];
        if (patient) applyPatient(patient, true);
      });
    });
  }

  async function searchPatients(query, source, allowAutoSelect) {
    const cleanQuery = String(query || '').trim();

    if (!patientResults || !config.patientSearchUrl || cleanQuery.length < 2) {
      hidePatientResults();
      return;
    }

    try {
      const response = await fetch(config.patientSearchUrl + '?q=' + encodeURIComponent(cleanQuery), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const data = await response.json();
      const rows = Array.isArray(data.items) ? data.items : [];

      if (allowAutoSelect) {
        const exactRows = rows.filter(function (patient) {
          return patientIsExactMatch(patient, cleanQuery, source);
        });

        if (exactRows.length === 1) {
          applyPatient(exactRows[0], true);
          return;
        }
      }

      showPatientRows(rows);
    } catch (error) {
      hidePatientResults();
    }
  }

  const debouncedPatientSearch = debounce(function (query, source, allowAutoSelect) {
    searchPatients(query, source, allowAutoSelect);
  }, 280);

  if (patientSearch) {
    patientSearch.addEventListener('input', function () {
      debouncedPatientSearch(patientSearch.value, 'search', false);
    });
  }

  if (patientNameField) {
    patientNameField.addEventListener('input', function () {
      if (selectedPatient && String(patientNameField.value || '').trim() !== String(selectedPatient.name || '').trim()) {
        clearPatientSelection(true);
      }
      debouncedPatientSearch(patientNameField.value, 'name', true);
    });
  }

  if (patientPhoneField) {
    patientPhoneField.addEventListener('input', function () {
      if (selectedPatient && normalisePhone(patientPhoneField.value) !== normalisePhone(selectedPatient.phone || '')) {
        clearPatientSelection(true);
      }
      debouncedPatientSearch(patientPhoneField.value, 'phone', true);
    });
  }

  if (clearSelectedPatientButton) {
    clearSelectedPatientButton.addEventListener('click', function () {
      clearPatientSelection(true);
      if (patientNameField) patientNameField.focus();
    });
  }

  document.addEventListener('click', function (event) {
    if (!patientResults || !patientResults.classList.contains('show')) return;
    const wrapper = patientResults.closest('.rx-patient-search-wrap');
    const clickedInsideSearch = wrapper && wrapper.contains(event.target);
    const clickedPatientField = event.target === patientNameField || event.target === patientPhoneField;

    if (!clickedInsideSearch && !clickedPatientField) hidePatientResults();
  });

  if (selectedPatient && patientIdField && patientIdField.value) {
    renderSelectedPatient(selectedPatient);
  }

  function closeLibraryDropdown(field) {
    const wrapper = field ? field.closest('.rx-library-field') : null;
    const dropdown = wrapper ? wrapper.querySelector('.rx-field-library-dropdown') : null;

    if (dropdown) {
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
    }
  }

  function closeAllLibraryDropdowns(except) {
    document.querySelectorAll('.rx-field-library-dropdown.show').forEach(function (dropdown) {
      if (except && dropdown === except) return;
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
    });
  }

  function applyLibraryItem(input, item) {
    if (!input || !item) return;

    const type = input.dataset.libraryType || '';
    const row = input.closest('.js-medicine-row');

    if (type === 'medicine') {
      if (!row) return;

      const generic = row.querySelector('.js-medicine-generic');
      const brand = row.querySelector('.js-medicine-brand');
      const libraryId = row.querySelector('.js-medicine-library-id');

      if (generic) generic.value = item.generic_name || item.label || '';
      if (brand) brand.value = item.brand_name || '';
      if (libraryId) libraryId.value = item.library_id || item.id || '';
    } else {
      input.value = item.value || item.label || '';
    }

    closeLibraryDropdown(input);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  async function searchLibrary(input, browseAll) {
    if (!input || !librarySearchUrl) return;

    const wrapper = input.closest('.rx-library-field');
    const dropdown = wrapper ? wrapper.querySelector('.rx-field-library-dropdown') : null;
    const type = input.dataset.libraryType || '';
    const query = input.value.trim();

    if (!dropdown || !type) return;

    if (!browseAll && query.length < 1) {
      closeLibraryDropdown(input);
      return;
    }

    closeAllLibraryDropdowns(dropdown);
    dropdown.innerHTML = '<div class="rx-library-dropdown-state">Loading library...</div>';
    dropdown.classList.add('show');

    try {
      const url = librarySearchUrl
        + '?type=' + encodeURIComponent(type)
        + '&q=' + encodeURIComponent(browseAll ? '' : query);
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      const items = Array.isArray(data.items) ? data.items : [];

      const options = items.map(function (item, index) {
        if (type === 'medicine') {
          const brand = item.brand_name ? `Brand: ${item.brand_name}` : 'No brand specified';
          const source = item.is_demo ? ' · Shared demo' : ' · My library';
          return `
            <button type="button" class="rx-field-library-option js-field-library-option" data-index="${index}">
              <strong>${escapeHtml(item.generic_name || item.label || '')}</strong>
              <small>${escapeHtml(brand + source)}</small>
            </button>
          `;
        }

        return `
          <button type="button" class="rx-field-library-option js-field-library-option" data-index="${index}">
            <strong>${escapeHtml(item.value || item.label || '')}</strong>
            <small>${item.is_demo ? 'Shared demo' : 'My library'}</small>
          </button>
        `;
      }).join('');

      const manualLabel = query !== '' ? `Keep manual value: ${query}` : 'Continue with manual entry';
      const emptyState = items.length
        ? ''
        : '<div class="rx-library-dropdown-state">No saved library value found.</div>';

      dropdown.innerHTML = options + emptyState + `
        <button type="button" class="rx-field-library-option rx-field-library-manual js-field-library-manual">
          <strong>${escapeHtml(manualLabel)}</strong>
          <small>Use the text currently written in this field.</small>
        </button>
        <a class="rx-field-library-manage" href="${escapeHtml((config.libraryFormUrls && config.libraryFormUrls[type]) || config.libraryUrl || '#')}" target="_blank" rel="noopener">
          Open this library form
        </a>
      `;
      dropdown.classList.add('show');

      dropdown.querySelectorAll('.js-field-library-option').forEach(function (button) {
        button.addEventListener('click', function () {
          const item = items[Number(button.dataset.index || 0)];
          if (item) applyLibraryItem(input, item);
        });
      });

      const manual = dropdown.querySelector('.js-field-library-manual');
      if (manual) {
        manual.addEventListener('click', function () {
          if (type === 'medicine') {
            const row = input.closest('.js-medicine-row');
            const libraryId = row ? row.querySelector('.js-medicine-library-id') : null;
            if (libraryId) libraryId.value = '';
          }
          closeLibraryDropdown(input);
          input.focus();
        });
      }
    } catch (error) {
      dropdown.innerHTML = `
        <div class="rx-library-dropdown-state">Library could not be loaded.</div>
        <button type="button" class="rx-field-library-option rx-field-library-manual js-field-library-manual">
          <strong>Continue with manual entry</strong>
        </button>
      `;
      dropdown.classList.add('show');

      const manual = dropdown.querySelector('.js-field-library-manual');
      if (manual) manual.addEventListener('click', function () { closeLibraryDropdown(input); });
    }
  }

  const delayedLibrarySearch = debounce(function (input) {
    searchLibrary(input, false);
  }, 220);

  document.addEventListener('input', function (event) {
    if (!event.target.matches('.js-library-input')) return;

    if (event.target.dataset.libraryType === 'medicine') {
      const row = event.target.closest('.js-medicine-row');
      const libraryId = row ? row.querySelector('.js-medicine-library-id') : null;
      if (libraryId) libraryId.value = '';
    }

    delayedLibrarySearch(event.target);
  });

  document.addEventListener('focusin', function (event) {
    if (!event.target.matches('.js-library-input')) return;
    searchLibrary(event.target, event.target.value.trim() === '');
  });

  document.addEventListener('click', function (event) {
    const openButton = event.target.closest('.js-open-library');

    if (openButton) {
      const wrapper = openButton.closest('.rx-library-field');
      const input = wrapper ? wrapper.querySelector('.js-library-input') : null;
      if (input) searchLibrary(input, true);
      return;
    }

    if (!event.target.closest('.rx-library-field')) {
      closeAllLibraryDropdowns();
    }

    if (!event.target.closest('.rx-patient-search-wrap')) {
      hidePatientResults();
    }
  });

  const prescriptionForm = document.getElementById('prescriptionForm');
  const draftButton = document.getElementById('saveDraftButton');
  const draftMessage = document.getElementById('draftMessage');

  if (prescriptionForm) {
    prescriptionForm.addEventListener('submit', syncAllLineGroups);
  }

  if (prescriptionForm && draftButton && config.draftUrl) {
    draftButton.addEventListener('click', async function () {
      const patientName = prescriptionForm.querySelector('[name="patient_name"]');

      if (!patientName || !patientName.value.trim()) {
        window.alert('Patient name is required before saving a draft.');
        if (patientName) patientName.focus();
        return;
      }

      syncAllLineGroups();

      const originalText = draftButton.textContent;
      draftButton.disabled = true;
      draftButton.textContent = 'Saving...';

      try {
        const formData = new FormData(prescriptionForm);
        formData.set('save_status', 'draft');

        const response = await fetch(config.draftUrl, {
          method: 'POST',
          body: formData,
          headers: { Accept: 'application/json' }
        });
        const data = await response.json();

        if (!response.ok || !data.ok) {
          throw new Error(data.message || 'Draft could not be saved.');
        }

        const idField = prescriptionForm.querySelector('[name="prescription_id"]');
        if (idField) idField.value = data.id || '';

        if (draftMessage) {
          draftMessage.textContent = data.message || 'Draft saved.';
          draftMessage.classList.add('show');
          window.setTimeout(function () {
            draftMessage.classList.remove('show');
          }, 3500);
        }

        if (data.edit_url && window.history && window.history.replaceState) {
          window.history.replaceState({}, '', data.edit_url);
        }
      } catch (error) {
        window.alert(error.message || 'Draft could not be saved.');
      } finally {
        draftButton.disabled = false;
        draftButton.textContent = originalText;
      }
    });
  }

  document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm('Are you sure you want to permanently delete this prescription?')) {
        event.preventDefault();
      }
    });
  });

  initialiseBanglaFontDetection();

  document.querySelectorAll('.js-confirm-library-delete').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      const isDemo = form.dataset.demo === '1';
      const message = isDemo
        ? 'Remove this shared demo from your account? Other doctors will still see it, and you can restore it later.'
        : 'Delete this item from your personal library? Existing prescriptions will not be changed.';

      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
