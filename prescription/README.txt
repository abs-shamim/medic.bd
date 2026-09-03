PRESCRIPTION MODULE WITH COMPLETE SHARED LIBRARIES
===================================================

INSTALL LOCATION
----------------
Upload the complete prescription folder to the website root:

/root/prescription/

Main URL:
/prescription/

REQUIRED EXISTING FILE
----------------------
/user/includes/auth.php

The existing auth system must provide:
- require_user_login()
- user_profile_type($user)
- user_has_claim($user)
- $user['claimed_doctor_id']
- PDO connection in $pdo

ACCESS CONTROL
--------------
Only a logged-in approved doctor with a valid claimed_doctor_id can access the module.
Every prescription, patient and personal library query is restricted by doctor_id.

DATABASE
--------
Fresh installation:
Import prescription/database.sql once using phpMyAdmin.

Existing Prescription Module installation:
Import prescription/complete-libraries-upgrade.sql once.

The module can also create missing tables automatically when the database user has
CREATE and ALTER permissions.

COMPLETE LIBRARY LIST
---------------------
Open:
/prescription/medicine-library.php

Medicine libraries:
1. Medicine Names
   - Generic Name required
   - Brand Name optional
2. Strength
3. Dosage
4. Frequency
5. Duration
6. Medicine Instructions

Clinical libraries:
7. Tests / Investigations
8. Test Instructions
9. Chief Complaints
10. Diagnosis
11. Medical History
12. Examination Findings
13. Advice

Each library has:
- Separate Add form
- Separate dropdown in the prescription editor
- Manual Entry support
- Edit and Delete for the doctor's own items
- Search and filters
- Active / Inactive status for personal items
- Usage count
- Shared demo items

SHARED DEMO ITEMS
-----------------
Global demo rows use doctor_id = 0.

- Every approved doctor can see the shared demos.
- A doctor can click Remove Demo.
- Removing a demo hides it only for that doctor.
- Other doctors continue to see the demo.
- Restore Demo Items makes hidden demos visible again.
- A doctor's personal items are visible only to that doctor.

PRESCRIPTION EDITOR DROPDOWNS
-----------------------------
While writing or editing a prescription:
- Generic and Brand use Medicine Names Library.
- Strength, Dosage, Frequency, Duration and Medicine Instruction use their own libraries.
- Investigation Name and Test Instruction use separate libraries.
- C/C, Dx, D/H and O/E each use their own clinical library.
- Advice uses the Advice Library.
- Every field still accepts manual typing.

PAGES
-----
/prescription/index.php              Prescription list
/prescription/new.php                New prescription
/prescription/edit.php               Edit prescription
/prescription/view.php               Detailed view
/prescription/print.php              A4 print view
/prescription/patients.php           Patient directory
/prescription/patient-view.php       Patient history
/prescription/medicine-library.php   Complete library management

AJAX
----
/ajax/patient-search.php             Search current doctor's patients
/ajax/library-search.php             Search any selected library by type
/ajax/medicine-search.php            Backward-compatible search endpoint
/ajax/save-draft.php                 Save prescription draft

SECURITY
--------
- Approved doctor-only access
- Doctor ownership verification
- Shared demo isolation using a per-doctor hidden table
- CSRF protection
- Prepared PDO statements
- Database transactions
- Private doctor notes are excluded from print

IMPORTANT
---------
The demo values are interface examples and are not clinical recommendations.
A qualified doctor must verify every medicine, dose, test and advice before completing
or printing a prescription.

Back up the website and database before replacement.
PHP and JavaScript syntax checks are included, but a live browser and MySQL test is recommended.
