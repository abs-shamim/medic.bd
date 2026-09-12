document.addEventListener('DOMContentLoaded', function () {
    const slugInput = document.getElementById('slugInput');
    const slugStatus = document.getElementById('slugStatus');

    let slugTimer = null;

    if (slugInput && slugStatus) {
        slugInput.addEventListener('input', function () {
            clearTimeout(slugTimer);

            slugTimer = setTimeout(function () {
                const slug = slugInput.value.trim();

                if (!slug) {
                    slugStatus.className = '';
                    slugStatus.style.display = 'none';
                    slugStatus.textContent = '';
                    return;
                }

                fetch('hospital-profile-update.php?ajax=check_slug&slug=' + encodeURIComponent(slug))
                    .then(response => response.json())
                    .then(data => {
                        slugStatus.className = data.ok ? 'ok' : 'bad';
                        slugStatus.textContent = data.message || '';
                    })
                    .catch(() => {
                        slugStatus.className = 'bad';
                        slugStatus.textContent = 'Could not check slug.';
                    });
            }, 450);
        });
    }

    const divisionSelect = document.getElementById('division_id');
    const districtSelect = document.getElementById('district_id');
    const thanaSelect = document.getElementById('thana_id');

    const divisionName = document.getElementById('division_name');
    const districtName = document.getElementById('district_name');
    const thanaName = document.getElementById('thana_name');

    function selectedText(select) {
        if (!select || !select.selectedOptions || !select.selectedOptions[0]) {
            return '';
        }

        return select.selectedOptions[0].textContent.trim();
    }

    function updateHiddenNames() {
        if (divisionName) divisionName.value = selectedText(divisionSelect);
        if (districtName) districtName.value = selectedText(districtSelect);
        if (thanaName) thanaName.value = selectedText(thanaSelect);
    }

    function filterDistricts() {
        if (!divisionSelect || !districtSelect) {
            return;
        }

        const divisionId = divisionSelect.value;

        Array.from(districtSelect.options).forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            option.hidden = divisionId !== '' && option.dataset.division !== divisionId;
        });

        if (districtSelect.selectedOptions[0] && districtSelect.selectedOptions[0].hidden) {
            districtSelect.value = '';
        }

        filterThanas();
        updateHiddenNames();
    }

    function filterThanas() {
        if (!districtSelect || !thanaSelect) {
            return;
        }

        const districtId = districtSelect.value;

        Array.from(thanaSelect.options).forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            option.hidden = districtId !== '' && option.dataset.district !== districtId;
        });

        if (thanaSelect.selectedOptions[0] && thanaSelect.selectedOptions[0].hidden) {
            thanaSelect.value = '';
        }

        updateHiddenNames();
    }

    if (divisionSelect) {
        divisionSelect.addEventListener('change', filterDistricts);
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', filterThanas);
    }

    if (thanaSelect) {
        thanaSelect.addEventListener('change', updateHiddenNames);
    }

    filterDistricts();
    updateHiddenNames();
});
