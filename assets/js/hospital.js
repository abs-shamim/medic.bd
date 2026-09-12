document.addEventListener('click', function(event) {
  const button = event.target.closest('[data-show-more]');

  if (!button) {
    return;
  }

  event.preventDefault();

  const wrapper = button.closest('.hospital-limited-tags');

  if (!wrapper) {
    return;
  }

  const hiddenTags = wrapper.querySelectorAll('.hospital-hidden-tag');

  hiddenTags.forEach(function(tag) {
    tag.classList.remove('hospital-hidden-tag');
  });

  button.remove();
});

function initHospitalDoctorFilter() {
  const filter = document.querySelector('[data-hospital-doctor-filter]');

  if (!filter || filter.dataset.filterReady === 'true') {
    return;
  }

  filter.dataset.filterReady = 'true';

  const doctorItems = Array.from(document.querySelectorAll('[data-doctor-specialty]'));
  const emptyState = document.querySelector('[data-hospital-filter-empty]');
  const resultCount = document.querySelector('[data-hospital-doctor-count]');

  function applyHospitalDoctorFilter() {
    const selectedSpecialty = String(filter.value || 'all').trim().toLowerCase();
    let visibleCount = 0;

    doctorItems.forEach(function(item) {
      const doctorSpecialty = String(item.dataset.doctorSpecialty || '').trim().toLowerCase();
      const isVisible = selectedSpecialty === 'all'
        || doctorSpecialty === selectedSpecialty;

      item.hidden = !isVisible;
      item.setAttribute('aria-hidden', isVisible ? 'false' : 'true');

      if (isVisible) {
        visibleCount += 1;
      }
    });

    if (emptyState) {
      emptyState.hidden = visibleCount !== 0;
    }

    if (resultCount) {
      const doctorLabel = visibleCount === 1
        ? (resultCount.dataset.labelOne || 'Doctor')
        : (resultCount.dataset.labelMany || 'Doctors');
      resultCount.textContent = visibleCount + ' ' + doctorLabel;
    }

  }

  filter.addEventListener('change', applyHospitalDoctorFilter);
  filter.addEventListener('input', applyHospitalDoctorFilter);
  applyHospitalDoctorFilter();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHospitalDoctorFilter);
} else {
  initHospitalDoctorFilter();
}
