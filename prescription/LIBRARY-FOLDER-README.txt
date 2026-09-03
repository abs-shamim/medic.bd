PRESCRIPTION LIBRARY FOLDER

All library list and form pages are inside /prescription/Library/.

Each library has two files:
- LibraryName.php: list, search, filter, demo hide/restore, edit and delete actions.
- LibraryName_form.php: add and edit personal values.

Shared files used by every library:
- /prescription/includes/auth.php
- /prescription/includes/functions.php
- /prescription/includes/header.php
- /prescription/includes/footer.php
- /prescription/includes/library-list-page.php
- /prescription/includes/library-form-page.php

Shared demo values are visible to every approved doctor. Hiding a demo affects only the current doctor. Personal values are visible only to the doctor who created them.

Library dashboard:
/prescription/librarys.php

The main Libraries navigation now opens librarys.php. Library/index.php also redirects to this dashboard.


Current architecture: each Library list and form PHP file is standalone and contains its own inline CSS.
