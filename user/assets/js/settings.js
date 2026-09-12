document.addEventListener('DOMContentLoaded', function () {
    const divisionSelect = document.getElementById('division_id');
    const districtSelect = document.getElementById('district_id');
    const thanaSelect = document.getElementById('thana_id');

    const areaInput = document.getElementById('area');
    const roadInput = document.getElementById('road_no');
    const houseInput = document.getElementById('house_no');
    const postCodeInput = document.getElementById('post_code');
    const locationPreview = document.getElementById('locationPreview');

    const profileForm = document.getElementById('profileUpdateForm');

    const savedDivisionId = profileForm ? parseInt(profileForm.dataset.savedDivisionId || '0', 10) : 0;
    const savedDistrictId = profileForm ? parseInt(profileForm.dataset.savedDistrictId || '0', 10) : 0;
    const savedThanaId = profileForm ? parseInt(profileForm.dataset.savedThanaId || '0', 10) : 0;
    const lang = profileForm ? profileForm.dataset.lang : '';

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '<option value="">' + placeholder + '</option>';
    }

    function addOptions(select, items, selectedId) {
        if (!select) return;

        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;

            if (parseInt(item.id, 10) === parseInt(selectedId || 0, 10)) {
                option.selected = true;
            }

            select.appendChild(option);
        });
    }

    function updatePreview() {
        if (!locationPreview) return;

        const divisionText = divisionSelect && divisionSelect.value
            ? divisionSelect.options[divisionSelect.selectedIndex].text
            : '';

        const districtText = districtSelect && districtSelect.value
            ? districtSelect.options[districtSelect.selectedIndex].text
            : '';

        const thanaText = thanaSelect && thanaSelect.value
            ? thanaSelect.options[thanaSelect.selectedIndex].text
            : '';

        const area = areaInput ? areaInput.value.trim() : '';
        const road = roadInput ? roadInput.value.trim() : '';
        const house = houseInput ? houseInput.value.trim() : '';
        const postCode = postCodeInput ? postCodeInput.value.trim() : '';

        let thanaWithPostCode = thanaText;

        if (postCode) {
            thanaWithPostCode = thanaText ? thanaText + '-' + postCode : postCode;
        }

        const parts = [];

        if (house) parts.push(house);
        if (road) parts.push(road);
        if (area) parts.push(area);
        if (thanaWithPostCode) parts.push(thanaWithPostCode);
        if (districtText) parts.push(districtText);
        if (divisionText) parts.push(divisionText);

        locationPreview.textContent = parts.length ? parts.join(', ') : 'Selected address will appear here.';
    }

    async function loadDistricts(divisionId, selectedDistrictId) {
        resetSelect(districtSelect, 'Select District');
        resetSelect(thanaSelect, 'Select Thana');
        updatePreview();

        if (!divisionId) return;

        const response = await fetch('settings.php?ajax=districts&division_id=' + encodeURIComponent(divisionId) + '&lang=' + encodeURIComponent(lang));
        const districts = await response.json();

        addOptions(districtSelect, districts, selectedDistrictId);
        updatePreview();

        if (selectedDistrictId) {
            await loadThanas(selectedDistrictId, savedThanaId);
        }
    }

    async function loadThanas(districtId, selectedThanaId) {
        resetSelect(thanaSelect, 'Select Thana');
        updatePreview();

        if (!districtId) return;

        const response = await fetch('settings.php?ajax=thanas&district_id=' + encodeURIComponent(districtId) + '&lang=' + encodeURIComponent(lang));
        const thanas = await response.json();

        addOptions(thanaSelect, thanas, selectedThanaId);
        updatePreview();
    }

    if (divisionSelect) {
        divisionSelect.addEventListener('change', function () {
            loadDistricts(this.value, 0);
        });
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', function () {
            loadThanas(this.value, 0);
        });
    }

    [thanaSelect, areaInput, roadInput, houseInput, postCodeInput].forEach(function (input) {
        if (input) {
            input.addEventListener('change', updatePreview);
            input.addEventListener('input', updatePreview);
        }
    });

    if (savedDivisionId) {
        loadDistricts(savedDivisionId, savedDistrictId);
    } else {
        updatePreview();
    }

    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        });
    });
});
