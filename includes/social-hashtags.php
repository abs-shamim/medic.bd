<?php
/*
|--------------------------------------------------------------------------
| Specialty Hashtag Mapping
|--------------------------------------------------------------------------
| All comments are in English.
*/

function medic_specialty_hashtags(string $specialty_slug): array
{
    $map = [
        'alternative-medicine' => [
            'AlternativeMedicine',
            'IntegrativeMedicine',
            'HolisticHealth',
            'NaturalHealthCare',
            'WellnessCare',
        ],

        'anesthesiology-pain-medicine' => [
            'Anesthesiologist',
            'PainManagement',
            'PainSpecialist',
            'ChronicPainCare',
            'AnesthesiaCare',
        ],

        'cardiac-thoracic-surgery' => [
            'CardiacSurgeon',
            'HeartSurgery',
            'ThoracicSurgery',
            'CardiacCare',
            'ChestSurgery',
        ],

        'cardiology' => [
            'Cardiologist',
            'HeartSpecialist',
            'HeartHealth',
            'CardiacCare',
            'CardiologyCare',
        ],

        'dentistry' => [
            'Dentist',
            'DentalCare',
            'OralHealth',
            'TeethCare',
            'DentalClinic',
        ],

        'dermatology-venereology' => [
            'Dermatologist',
            'SkinSpecialist',
            'SkinCare',
            'SkinHealth',
            'DermatologyCare',
        ],

        'endocrinology-diabetes' => [
            'Endocrinologist',
            'DiabetesSpecialist',
            'DiabetesCare',
            'ThyroidCare',
            'HormoneHealth',
        ],

        'ent' => [
            'ENTDoctor',
            'ENTSpecialist',
            'EarNoseThroat',
            'SinusCare',
            'ENTCare',
        ],

        'gastroenterology-hepatology' => [
            'Gastroenterologist',
            'LiverSpecialist',
            'DigestiveHealth',
            'Hepatology',
            'GastroCare',
        ],

        'general-surgery' => [
            'GeneralSurgeon',
            'GeneralSurgery',
            'SurgerySpecialist',
            'SurgicalCare',
            'HospitalCare',
        ],

        'gynecology-obstetrics' => [
            'Gynecologist',
            'Obstetrician',
            'WomensHealth',
            'PregnancyCare',
            'GynaeCare',
        ],

        'hematology' => [
            'Hematologist',
            'BloodDisorders',
            'BloodHealth',
            'HematologyCare',
            'BloodSpecialist',
        ],

        'medicine-general-physician' => [
            'GeneralPhysician',
            'MedicineSpecialist',
            'InternalMedicine',
            'PrimaryCare',
            'DoctorConsultation',
        ],

        'nephrology' => [
            'Nephrologist',
            'KidneySpecialist',
            'KidneyCare',
            'KidneyHealth',
            'DialysisCare',
        ],

        'neurology' => [
            'Neurologist',
            'BrainSpecialist',
            'BrainHealth',
            'NeuroCare',
            'NervousSystemCare',
        ],

        'neurosurgery' => [
            'Neurosurgeon',
            'BrainSurgery',
            'SpineSurgery',
            'NeuroSurgery',
            'NeurosurgeryCare',
        ],

        'nuclear-medicine' => [
            'NuclearMedicine',
            'PETCTScan',
            'MedicalImaging',
            'DiagnosticCare',
            'AdvancedDiagnostics',
        ],

        'nutrition-dietetics' => [
            'Nutritionist',
            'Dietitian',
            'ClinicalNutrition',
            'HealthyEating',
            'NutritionCare',
        ],

        'oncology' => [
            'Oncologist',
            'CancerSpecialist',
            'CancerCare',
            'OncologyCare',
            'CancerAwareness',
        ],

        'ophthalmology' => [
            'Ophthalmologist',
            'EyeSpecialist',
            'EyeCare',
            'VisionCare',
            'EyeHealth',
        ],

        'orthopedics' => [
            'OrthopedicSurgeon',
            'BoneSpecialist',
            'JointCare',
            'BoneHealth',
            'OrthopedicCare',
        ],

        'pathology-laboratory-medicine' => [
            'Pathologist',
            'DiagnosticLab',
            'LaboratoryMedicine',
            'MedicalTesting',
            'DiagnosticCare',
        ],

        'pediatric-surgery' => [
            'PediatricSurgeon',
            'ChildSurgery',
            'ChildHealth',
            'PediatricCare',
            'KidsHealth',
        ],

        'pediatrics' => [
            'Pediatrician',
            'ChildSpecialist',
            'ChildHealth',
            'KidsHealth',
            'PediatricCare',
        ],

        'physical-medicine-rehabilitation' => [
            'PhysicalMedicine',
            'RehabilitationMedicine',
            'PainRehab',
            'RecoveryCare',
            'RehabSpecialist',
        ],

        'psychiatry-mental-health' => [
            'Psychiatrist',
            'MentalHealthCare',
            'MentalWellness',
            'PsychologicalHealth',
            'MindHealth',
        ],

        'pulmonology-respiratory-medicine' => [
            'Pulmonologist',
            'LungSpecialist',
            'RespiratoryCare',
            'LungHealth',
            'ChestMedicine',
        ],

        'radiology-imaging' => [
            'Radiologist',
            'MedicalImaging',
            'DiagnosticImaging',
            'CTScan',
            'MRI',
        ],

        'reproductive-medicine-infertility' => [
            'FertilitySpecialist',
            'IVF',
            'InfertilityCare',
            'ReproductiveHealth',
            'FertilityCare',
        ],

        'rheumatology' => [
            'Rheumatologist',
            'ArthritisCare',
            'JointPain',
            'AutoimmuneCare',
            'JointHealth',
        ],

        'urology' => [
            'Urologist',
            'KidneyAndBladder',
            'UrinaryHealth',
            'ProstateCare',
            'UrologyCare',
        ],
    ];

    return $map[$specialty_slug] ?? [
        'SpecialistDoctor',
        'DoctorConsultation',
        'HealthcareService',
        'MedicalCare',
        'DoctorProfile',
    ];
}

/*
|--------------------------------------------------------------------------
| Hashtag Helpers
|--------------------------------------------------------------------------
*/

function medic_hashtag_token(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    /*
     * Supports English and Unicode district names.
     */
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);

    return is_string($value) ? $value : '';
}

function medic_make_hashtag(string $value): string
{
    $value = medic_hashtag_token($value);

    return $value !== '' ? '#' . $value : '';
}

function medic_unique_hashtags(array $hashtags): array
{
    $unique = [];
    $seen = [];

    foreach ($hashtags as $tag) {
        $tag = medic_hashtag_token((string) $tag);

        if ($tag === '') {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($tag, 'UTF-8')
            : strtolower($tag);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $unique[] = $tag;
    }

    return $unique;
}

function medic_district_hashtags(string $district_name): array
{
    $district = medic_hashtag_token($district_name);

    if ($district === '') {
        return [];
    }

    return [
        'DoctorIn' . $district,
        $district . 'Doctor',
        $district . 'Healthcare',
    ];
}

/*
|--------------------------------------------------------------------------
| Create Platform-Specific Hashtags
|--------------------------------------------------------------------------
*/

function medic_generate_social_hashtags(
    string $specialty_slug,
    string $district_name = '',
    string $platform = 'facebook'
): string {
    $platform = strtolower(trim($platform));

    $specialty_tags = medic_specialty_hashtags($specialty_slug);
    $district_tags = medic_district_hashtags($district_name);

    /*
     * These tags are added after specialty and district tags
     * so that the most relevant hashtags always get priority.
     */
    $brand_tags = [
        'MedicBD',
        'BangladeshDoctors',
        'SpecialistDoctorBD',
        'HealthcareBangladesh',
        'FindADoctorBD',
    ];

    /*
     * Priority:
     * 1. Main specialty hashtags
     * 2. District-based doctor hashtags
     * 3. Brand and Bangladesh discovery hashtags
     * 4. Remaining specialty and district hashtags
     */
    $hashtags = array_merge(
        array_slice($specialty_tags, 0, 3),
        array_slice($district_tags, 0, 2),
        array_slice($brand_tags, 0, 2),
        array_slice($specialty_tags, 3),
        array_slice($district_tags, 2),
        array_slice($brand_tags, 2)
    );

    $hashtags = medic_unique_hashtags($hashtags);

    /*
     * Practical platform-specific hashtag limits.
     * Facebook: 3, Instagram: 5, LinkedIn: 3, X/Twitter: 0.
     */
    $limits = [
        'facebook'  => 3,
        'instagram' => 5,
        'linkedin'  => 3,
        'twitter'   => 0,
        'x'         => 0,
        'tiktok'    => 8,
        'youtube'   => 8,
    ];

    $limit = $limits[$platform] ?? 3;

    $hashtags = array_slice($hashtags, 0, $limit);

    return implode(' ', array_map('medic_make_hashtag', $hashtags));
}