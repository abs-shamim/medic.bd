(function () {
  var form = document.getElementById('cfForm');
  var subjectSelect = document.getElementById('cfSubjectType');
  var submitBtn = document.getElementById('cfSubmitBtn');
  var districtsUrl = form ? form.dataset.districtsUrl : '';

  function setSectionRequired(section, isActive) {
    // Several field keys (common_name, common_email, doctor_name, division_id,
    // district_id, etc.) repeat across multiple sections' schemas. All 9
    // sections stay in the DOM at once (only CSS-hidden), so an inactive
    // section's inputs must be disabled -- otherwise the browser still submits
    // them, and since they share the same "name" as the active section's
    // fields, PHP's $_POST keeps only the last one in document order and
    // silently wipes out the real answers.
    var fields = section.querySelectorAll('.cf-field');

    fields.forEach(function (field) {
      var isRequired = field.getAttribute('data-required') === '1';

      field.disabled = !isActive;

      if (isActive && isRequired) {
        field.setAttribute('required', 'required');
      } else {
        field.removeAttribute('required');
      }

      field.classList.remove('is-invalid');
    });

    var groups = section.querySelectorAll('.cf-checkbox-group');
    groups.forEach(function (group) {
      group.classList.remove('is-invalid');
      group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
        checkbox.disabled = !isActive;
      });
    });
  }

  function showSection(type) {
    document.querySelectorAll('.cf-section').forEach(function (section) {
      var isActive = section.getAttribute('data-section') === type;
      section.classList.toggle('active', isActive);
      setSectionRequired(section, isActive);
    });
  }

  if (subjectSelect) {
    subjectSelect.addEventListener('change', function () {
      showSection(this.value);
    });

    showSection(subjectSelect.value);
  }

  // Division -> District cascading selects (reuses the existing
  // ajax/get-districts.php endpoint already used elsewhere on the site).
  document.querySelectorAll('.cf-division-select').forEach(function (select) {
    select.addEventListener('change', function () {
      var targetId = this.getAttribute('data-district-target');
      var target = document.getElementById(targetId);

      if (!target) {
        return;
      }

      target.innerHTML = '<option value="">Loading...</option>';

      if (!this.value) {
        target.innerHTML = '<option value="">-- Select Division First --</option>';
        return;
      }

      fetch(districtsUrl + '?division_id=' + encodeURIComponent(this.value))
        .then(function (res) { return res.json(); })
        .then(function (districts) {
          var html = '<option value="">-- Select District --</option>';
          (districts || []).forEach(function (district) {
            html += '<option value="' + district.id + '">' + district.name + '</option>';
          });
          target.innerHTML = html;
        })
        .catch(function () {
          target.innerHTML = '<option value="">-- Could not load districts --</option>';
        });
    });
  });

  // Claim Doctor: highlight the BMDC field when "I am the Doctor" is selected.
  var relationshipField = document.getElementById('cf_claim_doctor_relationship');
  var bmdcWrap = document.getElementById('bmdcNumberField_wrap');

  if (relationshipField && bmdcWrap) {
    var toggleBmdcHighlight = function () {
      bmdcWrap.classList.toggle('cf-highlight', relationshipField.value === 'I am the Doctor');
    };

    relationshipField.addEventListener('change', toggleBmdcHighlight);
    toggleBmdcHighlight();
  }

  // Client-side validation + prevent duplicate submissions.
  if (form) {
    form.addEventListener('submit', function (event) {
      var activeSection = document.querySelector('.cf-section.active');
      var valid = true;

      if (!subjectSelect.value) {
        subjectSelect.classList.add('is-invalid');
        valid = false;
      } else {
        subjectSelect.classList.remove('is-invalid');
      }

      if (activeSection) {
        activeSection.querySelectorAll('.cf-field[required]').forEach(function (field) {
          if (!field.value || (field.type === 'file' && field.files.length === 0)) {
            field.classList.add('is-invalid');
            valid = false;
          } else if (field.type === 'email' && field.validity && !field.validity.valid) {
            field.classList.add('is-invalid');
            valid = false;
          } else if (field.type === 'url' && field.validity && !field.validity.valid) {
            field.classList.add('is-invalid');
            valid = false;
          } else {
            field.classList.remove('is-invalid');
          }
        });

        activeSection.querySelectorAll('.cf-checkbox-group[data-required="1"]').forEach(function (group) {
          var checked = group.querySelectorAll('input[type="checkbox"]:checked').length;

          if (checked === 0) {
            group.classList.add('is-invalid');
            valid = false;
          } else {
            group.classList.remove('is-invalid');
          }
        });
      }

      if (!valid) {
        event.preventDefault();
        return;
      }

      if (submitBtn.classList.contains('cf-loading')) {
        event.preventDefault();
        return;
      }

      submitBtn.classList.add('cf-loading');
      submitBtn.disabled = true;
      submitBtn.querySelector('.cf-btn-label').textContent = 'Sending...';
    });
  }
})();
