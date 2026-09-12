function togglePassword(inputId, button) {
    const passwordInput = document.getElementById(inputId);

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        button.textContent = 'Hide';
    } else {
        passwordInput.type = 'password';
        button.textContent = 'Show';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-password[data-toggle-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            togglePassword(button.dataset.toggleTarget, button);
        });
    });
});

function setRequiredState(containerId, isRequired) {
    const fields = document.querySelectorAll('#' + containerId + ' input, #' + containerId + ' select, #' + containerId + ' textarea');

    fields.forEach(function(field) {
        if (isRequired) {
            field.setAttribute('required', 'required');
        } else {
            field.removeAttribute('required');
        }
    });
}

function handleAccountType() {
    const accountType = document.getElementById('user_type').value;
    const doctorFields = document.getElementById('doctorFields');
    const hospitalFields = document.getElementById('hospitalFields');
    const nameLabel = document.getElementById('nameLabel');
    const nameInput = document.getElementById('name');

    doctorFields.classList.remove('active');
    hospitalFields.classList.remove('active');

    setRequiredState('doctorFields', false);
    setRequiredState('hospitalFields', false);

    if (accountType === 'doctor') {
        doctorFields.classList.add('active');
        setRequiredState('doctorFields', true);
        nameLabel.innerHTML = 'Doctor Full Name <span>*</span>';
        nameInput.placeholder = 'Enter doctor full name';
    } else if (accountType === 'hospital_owner') {
        hospitalFields.classList.add('active');
        setRequiredState('hospitalFields', true);
        nameLabel.innerHTML = 'Hospital / Clinic Name <span>*</span>';
        nameInput.placeholder = 'Enter hospital or clinic name';
    } else {
        nameLabel.innerHTML = 'Full Name <span>*</span>';
        nameInput.placeholder = 'Enter your full name or hospital name';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    handleAccountType();

    const accountTypeSelect = document.getElementById('user_type');

    if (accountTypeSelect) {
        accountTypeSelect.addEventListener('change', handleAccountType);
    }
});
