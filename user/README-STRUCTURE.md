# User Panel Structure

This user panel is built only for doctor and hospital owner users. Admin approval pages are not included in this step.

## Folder Structure

```
user/
├── index.php
├── register.php
├── login.php
├── logout.php
├── dashboard.php
├── claim.php
├── profile-update.php
├── chambers.php
├── hospital-doctors.php
├── includes/
│   ├── auth.php
│   ├── header.php
│   └── footer.php
├── auth/
│   ├── register.php
│   ├── login.php
│   └── logout.php
├── pages/
│   ├── dashboard.php
│   ├── claim.php
│   ├── profile-update.php
│   ├── chambers.php
│   └── hospital-doctors.php
├── actions/
├── assets/
│   ├── css/user-panel.css
│   └── js/
└── README-STRUCTURE.md
```

## User Flow

1. Doctor or hospital owner creates an account.
2. User submits profile claim.
3. Admin later approves claim and connects user with doctor_id or hospital_id.
4. User can submit profile update, chamber add/update, and hospital doctor add requests.
5. All submitted data is stored in `profile_update_requests` as pending JSON payload.

## Required SQL

Import `user-panel.sql` after importing your main `medicare` database.
