<?php
/*
|--------------------------------------------------------------------------
| Contact Form Field Schema (single source of truth)
|--------------------------------------------------------------------------
| Shared by contact.php (renders the public form + validates submissions)
| and admin/contact-message-view.php (renders human-readable field labels
| for the stored form_data/attachments of each submission). Keeping this in
| one file means the admin panel always shows the same labels the visitor
| actually saw, without duplicating the schema.
|
| Each field: label, type (text|email|tel|url|textarea|select|division|
| district|checkbox_group|file|heading), required, options (for select /
| checkbox_group), accept (image|doc, for file fields).
|--------------------------------------------------------------------------
*/

function cf_subject_labels(): array
{
    return [
        'add_doctor'        => 'Add Doctor Profile',
        'update_doctor'     => 'Update Doctor Profile',
        'add_hospital'      => 'Add Hospital Profile',
        'update_hospital'   => 'Update Hospital Profile',
        'claim_doctor'      => 'Claim Doctor Profile',
        'claim_hospital'    => 'Claim Hospital Profile',
        'report_incorrect'  => 'Report Incorrect Information',
        'technical_support' => 'Technical Support',
        'other'             => 'Other',
    ];
}

function cf_update_doctor_options(): array
{
    return ['Doctor Name', 'Degree / Qualification', 'Specialty / Department', 'Designation', 'Workplace / Affiliation', 'Chamber Information', 'Visiting Hours', 'Appointment Number', 'Mobile Number', 'Email', 'Photo', 'Social Links', 'Other'];
}

function cf_update_hospital_options(): array
{
    return ['Hospital Name', 'Hospital Type', 'Address', 'Phone Number', 'Emergency Number', 'Email', 'Website', 'Google Map', 'Available Services', 'Departments', 'Logo / Image', 'Other'];
}

function cf_subject_sections(): array
{
    return [
        'add_doctor' => [
            'label' => 'Add Doctor Profile',
            'fields' => [
                'doctor_name'        => ['label' => 'Doctor Name', 'type' => 'text', 'required' => true],
                'primary_specialty'  => ['label' => 'Primary Specialty / Department', 'type' => 'text', 'required' => true],
                'degree'             => ['label' => 'Degree / Qualification', 'type' => 'text'],
                'designation'        => ['label' => 'Designation', 'type' => 'text'],
                'workplace'          => ['label' => 'Current Workplace / Affiliation', 'type' => 'text'],
                'hospital_chamber'   => ['label' => 'Hospital / Chamber Name', 'type' => 'text'],
                'division'           => ['label' => 'Division', 'type' => 'division'],
                'district'           => ['label' => 'District', 'type' => 'district', 'required' => true],
                'area_city'          => ['label' => 'Area / City', 'type' => 'text'],
                'doctor_mobile'      => ['label' => 'Mobile Number', 'type' => 'tel'],
                'doctor_email'       => ['label' => 'Email Address', 'type' => 'email'],
                'attach_heading'     => ['type' => 'heading', 'label' => 'Attachments'],
                'doctor_photo'       => ['label' => 'Doctor Photo', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'supporting_document'=> ['label' => 'Supporting Document', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'    => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'        => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'       => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'       => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
                'additional_info'    => ['label' => 'Additional Information', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'update_doctor' => [
            'label' => 'Update Doctor Profile',
            'fields' => [
                'doctor_name'           => ['label' => 'Doctor Name', 'type' => 'text', 'required' => true],
                'existing_profile_url'  => ['label' => 'Existing Doctor Profile URL', 'type' => 'url', 'required' => true],
                'update_fields'         => ['label' => 'What Do You Want to Update?', 'type' => 'checkbox_group', 'required' => true, 'options' => cf_update_doctor_options()],
                'correct_info'          => ['label' => 'Correct / Updated Information', 'type' => 'textarea', 'required' => true],
                'supporting_document'   => ['label' => 'Supporting Document / Screenshot', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'       => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'           => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'          => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'          => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
                'additional_message'    => ['label' => 'Additional Message', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone', 'name_fallback' => 'doctor_name'],
        ],

        'add_hospital' => [
            'label' => 'Add Hospital Profile',
            'fields' => [
                'hospital_name'      => ['label' => 'Hospital Name', 'type' => 'text', 'required' => true],
                'hospital_type'      => ['label' => 'Hospital Type', 'type' => 'select', 'required' => true, 'options' => ['Hospital', 'Clinic', 'Diagnostic Center', 'Medical College Hospital', 'Specialized Hospital', 'Eye Hospital', 'Dental Hospital', 'Other']],
                'division'           => ['label' => 'Division', 'type' => 'division', 'required' => true],
                'district'           => ['label' => 'District', 'type' => 'district', 'required' => true],
                'area_city'          => ['label' => 'Area / City', 'type' => 'text'],
                'full_address'       => ['label' => 'Full Address', 'type' => 'textarea', 'required' => true],
                'hospital_phone'     => ['label' => 'Hospital Phone', 'type' => 'tel', 'required' => true],
                'emergency_phone'    => ['label' => 'Emergency Phone', 'type' => 'tel'],
                'hospital_email'     => ['label' => 'Email', 'type' => 'email'],
                'official_website'   => ['label' => 'Official Website', 'type' => 'url'],
                'google_map_link'    => ['label' => 'Google Map Link', 'type' => 'url'],
                'available_services' => ['label' => 'Available Services', 'type' => 'textarea'],
                'departments'        => ['label' => 'Departments', 'type' => 'textarea'],
                'attach_heading'     => ['type' => 'heading', 'label' => 'Attachments'],
                'hospital_logo'      => ['label' => 'Hospital Logo', 'type' => 'file', 'accept' => 'image', 'help' => 'JPG, PNG or WEBP, up to 5 MB.'],
                'hospital_image'     => ['label' => 'Hospital Image', 'type' => 'file', 'accept' => 'image', 'help' => 'JPG, PNG or WEBP, up to 5 MB.'],
                'supporting_document'=> ['label' => 'Supporting Document', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'    => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'        => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'       => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'       => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
                'additional_info'    => ['label' => 'Additional Information', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'update_hospital' => [
            'label' => 'Update Hospital Profile',
            'fields' => [
                'hospital_name'         => ['label' => 'Hospital Name', 'type' => 'text', 'required' => true],
                'existing_profile_url'  => ['label' => 'Existing Hospital Profile URL', 'type' => 'url', 'required' => true],
                'update_fields'         => ['label' => 'What Do You Want to Update?', 'type' => 'checkbox_group', 'required' => true, 'options' => cf_update_hospital_options()],
                'correct_info'          => ['label' => 'Correct / Updated Information', 'type' => 'textarea', 'required' => true],
                'supporting_document'   => ['label' => 'Supporting Document / Screenshot', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'       => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'           => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'          => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'          => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
                'additional_message'    => ['label' => 'Additional Message', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone', 'name_fallback' => 'hospital_name'],
        ],

        'claim_doctor' => [
            'label' => 'Claim Doctor Profile',
            'fields' => [
                'doctor_name'          => ['label' => 'Doctor Name', 'type' => 'text', 'required' => true],
                'doctor_profile_url'   => ['label' => 'Doctor Profile URL', 'type' => 'url', 'required' => true],
                'common_name'          => ['label' => 'Your Full Name', 'type' => 'text', 'required' => true],
                'relationship'         => ['label' => 'Relationship to Doctor', 'type' => 'select', 'required' => true, 'options' => ["I am the Doctor", "Doctor's Assistant", 'Hospital Representative', 'Clinic Representative', 'Authorized Representative', 'Other']],
                'common_phone'         => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
                'common_email'         => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'bmdc_number'          => ['label' => 'BMDC Registration Number', 'type' => 'text', 'id' => 'bmdcNumberField'],
                'verification_document'=> ['label' => 'Verification Document', 'type' => 'file', 'accept' => 'doc', 'required' => true, 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB. e.g. BMDC certificate, ID, or authorization letter.'],
                'additional_message'   => ['label' => 'Additional Message', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'claim_hospital' => [
            'label' => 'Claim Hospital Profile',
            'fields' => [
                'hospital_name'         => ['label' => 'Hospital Name', 'type' => 'text', 'required' => true],
                'hospital_profile_url'  => ['label' => 'Hospital Profile URL', 'type' => 'url', 'required' => true],
                'common_name'           => ['label' => 'Your Full Name', 'type' => 'text', 'required' => true],
                'position'              => ['label' => 'Your Position / Designation', 'type' => 'select', 'required' => true, 'options' => ['Owner', 'Director', 'Manager', 'Administrator', 'Marketing Officer', 'Authorized Representative', 'Other']],
                'common_phone'          => ['label' => 'Official Mobile Number', 'type' => 'tel', 'required' => true],
                'common_email'          => ['label' => 'Official Email', 'type' => 'email', 'required' => true],
                'hospital_website'      => ['label' => 'Hospital Website', 'type' => 'url'],
                'verification_document' => ['label' => 'Verification Document', 'type' => 'file', 'accept' => 'doc', 'required' => true, 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB. e.g. trade license, ID, or authorization letter.'],
                'additional_message'    => ['label' => 'Additional Message', 'type' => 'textarea'],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'report_incorrect' => [
            'label' => 'Report Incorrect Information',
            'fields' => [
                'profile_type'      => ['label' => 'Profile Type', 'type' => 'select', 'required' => true, 'options' => ['Doctor', 'Hospital', 'Other']],
                'profile_url'       => ['label' => 'Profile URL', 'type' => 'url', 'required' => true],
                'incorrect_type'    => ['label' => 'Incorrect Information Type', 'type' => 'select', 'required' => true, 'options' => ['Wrong Name', 'Wrong Phone Number', 'Wrong Address', 'Wrong Specialty', 'Wrong Degree', 'Wrong Hospital / Chamber', 'Wrong Visiting Hours', 'Wrong Website', 'Wrong Google Map Location', 'Profile Duplicate', 'Person No Longer Works Here', 'Hospital Closed', 'Doctor Deceased', 'Other']],
                'describe_incorrect'=> ['label' => 'Describe the Incorrect Information', 'type' => 'textarea', 'required' => true],
                'correct_info'      => ['label' => 'Correct Information', 'type' => 'textarea'],
                'supporting_document' => ['label' => 'Screenshot / Supporting Document', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'   => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'       => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'      => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'      => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'technical_support' => [
            'label' => 'Technical Support',
            'fields' => [
                'issue_type'        => ['label' => 'Issue Type', 'type' => 'select', 'required' => true, 'options' => ['Website Not Working', 'Appointment Problem', 'Login Problem', 'Claim Profile Problem', 'Form Submission Problem', 'Incorrect Page Display', 'Mobile Website Problem', 'Other Technical Issue']],
                'page_url'          => ['label' => 'Page URL', 'type' => 'url'],
                'device_type'       => ['label' => 'Device Type', 'type' => 'select', 'options' => ['Mobile', 'Desktop', 'Tablet']],
                'browser'           => ['label' => 'Browser', 'type' => 'select', 'options' => ['Chrome', 'Safari', 'Firefox', 'Edge', 'Other']],
                'describe_problem'  => ['label' => 'Describe the Problem', 'type' => 'textarea', 'required' => true],
                'screenshot'        => ['label' => 'Screenshot', 'type' => 'file', 'accept' => 'doc', 'help' => 'JPG, PNG, WEBP or PDF, up to 5 MB.'],
                'contact_heading'   => ['type' => 'heading', 'label' => 'Your Contact Information'],
                'common_name'       => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'common_email'      => ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                'common_phone'      => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'common_email', 'phone' => 'common_phone'],
        ],

        'other' => [
            'label' => 'Other',
            'fields' => [
                'common_name'         => ['label' => 'Your Name', 'type' => 'text', 'required' => true],
                'email_or_mobile'     => ['label' => 'Email Address or Mobile Number', 'type' => 'text', 'required' => true],
                'short_subject'       => ['label' => 'Short Subject', 'type' => 'text', 'required' => true],
                'other_message'       => ['label' => 'Message', 'type' => 'textarea', 'required' => true],
            ],
            'identity' => ['name' => 'common_name', 'email' => 'email_or_mobile', 'phone' => ''],
        ],
    ];
}
