document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('appointmentPatientLookup');
    const results = document.getElementById('appointmentPatientResults');
    const wrapper = document.getElementById('appointmentPatientSearchWrap');
    const patientId = document.getElementById('appointmentPatientId');
    const nameField = document.getElementById('appointmentPatientName');
    const phoneField = document.getElementById('appointmentPatientPhone');
    const ageField = document.getElementById('appointmentPatientAge');
    const genderField = document.getElementById('appointmentPatientGender');
    const bloodGroupField = document.getElementById('appointmentPatientBloodGroup');
    const addressField = document.getElementById('appointmentPatientAddress');
    const selectedCard = document.getElementById('appointmentSelectedPatient');
    const selectedName = document.getElementById('appointmentSelectedName');
    const selectedMeta = document.getElementById('appointmentSelectedMeta');
    const clearButton = document.getElementById('appointmentClearPatient');

    if (!searchInput || !results || !wrapper || !clearButton) return;

    const searchUrl = wrapper.dataset.searchUrl;
    let timer = null;
    let controller = null;

    const closeResults = function () {
        results.classList.remove('is-open');
        results.innerHTML = '';
    };

    const fillPatient = function (patient) {
        patientId.value = String(patient.id || '');
        nameField.value = patient.name || '';
        phoneField.value = patient.phone || '';
        ageField.value = patient.age || '';
        genderField.value = patient.gender || '';
        bloodGroupField.value = patient.blood_group || '';
        addressField.value = patient.address || '';
        selectedName.textContent = patient.name || 'Selected Patient';
        selectedMeta.textContent = [patient.patient_code || '', patient.phone || ''].filter(Boolean).join(' · ');
        selectedCard.classList.add('is-visible');
        searchInput.value = '';
        closeResults();
    };

    const clearPatient = function () {
        patientId.value = '';
        nameField.value = '';
        phoneField.value = '';
        ageField.value = '';
        genderField.value = '';
        bloodGroupField.value = '';
        addressField.value = '';
        selectedName.textContent = '';
        selectedMeta.textContent = '';
        selectedCard.classList.remove('is-visible');
        searchInput.focus();
    };

    const renderResults = function (items) {
        results.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'rx-appointment-result-empty';
            empty.textContent = 'No matching patient found. Enter the details below to add a new patient.';
            results.appendChild(empty);
            results.classList.add('is-open');
            return;
        }

        items.forEach(function (patient) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rx-appointment-result';

            const title = document.createElement('strong');
            title.textContent = patient.name || 'Patient';

            const meta = document.createElement('span');
            meta.textContent = [patient.phone || 'No mobile', patient.patient_code || '', patient.age || '', patient.gender || ''].filter(Boolean).join(' · ');

            button.appendChild(title);
            button.appendChild(meta);
            button.addEventListener('click', function () { fillPatient(patient); });
            results.appendChild(button);
        });

        results.classList.add('is-open');
    };

    const runSearch = function () {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            closeResults();
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then(function (payload) { renderResults(payload && payload.items ? payload.items : []); })
            .catch(function (error) {
                if (error.name !== 'AbortError') closeResults();
            });
    };

    searchInput.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(runSearch, 220);
    });
    searchInput.addEventListener('focus', function () {
        if (searchInput.value.trim().length >= 2) runSearch();
    });
    clearButton.addEventListener('click', clearPatient);
    document.addEventListener('click', function (event) {
        if (!wrapper.contains(event.target)) closeResults();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeResults();
    });
});
