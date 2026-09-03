<?php
declare(strict_types=1);

$library_form_urls = [];

foreach (prescription_managed_library_types() as $type) {
    $library_form_urls[$type] = prescription_library_form_url($type);
}

$prescription_config = [
    'baseUrl'            => prescription_url(),
    'patientSearchUrl'   => prescription_url('ajax/patient-search.php'),
    'patientViewUrl'     => prescription_url('patient-view.php'),
    'librarySearchUrl'   => prescription_url('ajax/library-search.php'),
    'medicineSearchUrl'  => prescription_url('ajax/medicine-search.php'),
    'libraryUrl'         => prescription_library_dashboard_url(),
    'libraryFormUrls'    => $library_form_urls,
    'draftUrl'           => prescription_url('ajax/save-draft.php'),
    'isEditing'          => (bool) ($editing ?? false),
    'selectedPatient'    => isset($selected_patient) && is_array($selected_patient)
        ? $selected_patient
        : null,
];

$prescription_config_json = json_encode(
    $prescription_config,
    JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_THROW_ON_ERROR
);
?>

        </main>

        <footer class="rx-footer">
            <div>
                <strong>Prescription Workspace</strong>
                <span>Secure doctor-only patient and prescription management.</span>
            </div>

            <a href="<?= prescription_e(prescription_user_url('dashboard.php')) ?>">
                Back to Main Dashboard
            </a>
        </footer>
    </div>
</div>

<script>
window.PRESCRIPTION_CONFIG = <?= $prescription_config_json ?>;
</script>

<script src="<?= prescription_e(
    prescription_url('assets/js/prescription.js')
) ?>"></script>

</body>
</html>