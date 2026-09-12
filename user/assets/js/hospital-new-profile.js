document.addEventListener('DOMContentLoaded', function () {
    const divisionSelect = document.getElementById('division_id');
    const districtSelect = document.getElementById('district_id');
    const thanaSelect = document.getElementById('thana_id');

    function filterDistricts() {
        if (!divisionSelect || !districtSelect) return;

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
    }

    function filterThanas() {
        if (!districtSelect || !thanaSelect) return;

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
    }

    if (divisionSelect) {
        divisionSelect.addEventListener('change', filterDistricts);
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', filterThanas);
    }

    filterDistricts();
});
