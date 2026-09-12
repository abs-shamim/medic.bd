document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('prescriptionForm');

    if (!editor) {
        return;
    }

    const normalizeText = function (value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    };

    const unwantedMedicineHelperText = function (value) {
        const text = normalizeText(value).toLowerCase();

        return text === 'manage libraries'
            || text === 'each field has its own library, and manual entry remains available.'
            || text === 'each field has its own library, and manual entry remains available'
            || text === 'select from each independent dropdown or continue typing manually in any field.'
            || text === 'select from each independent dropdown or continue typing manually in any field';
    };

    const cleanMedicineHelperMessages = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll(
            '.rx-editor-medicine-column a, '
            + '.rx-editor-medicine-column button, '
            + '.rx-editor-medicine-column p, '
            + '.rx-editor-medicine-column small, '
            + '.rx-editor-medicine-column span, '
            + '.rx-editor-medicine-column div'
        ).forEach(function (element) {
            if (element.children.length > 0) {
                return;
            }

            if (unwantedMedicineHelperText(element.textContent)) {
                element.remove();
            }
        });
    };

    const cleanDropdownMessages = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll('.rx-field-library-dropdown').forEach(function (dropdown) {
            dropdown.querySelectorAll('.js-field-library-manual').forEach(function (element) {
                element.remove();
            });

            dropdown.querySelectorAll('a, button, p, small, span, div, li').forEach(function (element) {
                const text = normalizeText(element.textContent);
                const lowerText = text.toLowerCase();

                if (
                    lowerText.startsWith('keep manual value')
                    || lowerText === 'continue with manual entry'
                    || lowerText === 'use the text currently written in this field.'
                    || lowerText === 'open this library form'
                    || unwantedMedicineHelperText(text)
                ) {
                    element.remove();
                    return;
                }

                if (lowerText === 'my library') {
                    element.remove();
                    return;
                }

                if (
                    lowerText.includes('my library')
                    && element.children.length === 0
                ) {
                    const cleanedText = text
                        .replace(/\s*[·•|-]?\s*my library/ig, '')
                        .replace(/\s{2,}/g, ' ')
                        .trim();

                    if (cleanedText === '') {
                        element.remove();
                    } else {
                        element.textContent = cleanedText;
                    }
                }
            });
        });

        cleanMedicineHelperMessages(scope);
    };

    const ensureMedicineNameSearch = function (root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            const generic = row.querySelector('input[name="medicine_generic_name[]"]');
            const brand = row.querySelector('input[name="medicine_brand_name[]"]');

            [generic, brand].forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                input.classList.add('js-library-input');
                input.dataset.libraryType = 'medicine';
                input.placeholder = 'Search generic or brand name';
                input.setAttribute('autocomplete', 'off');
            });

            if (generic) {
                generic.dataset.libraryRole = 'both';
                generic.dataset.searchFields = 'generic_name,brand_name';
            }

            if (brand) {
                brand.dataset.libraryRole = 'both';
                brand.dataset.searchFields = 'generic_name,brand_name';
            }
        });
    };

    const createMedicineFormField = function () {
        const field = document.createElement('div');
        field.className = 'rx-medicine-input-wrap rx-library-field';
        field.innerHTML = `
            <label>Medicine Form</label>
            <div class="rx-library-input-control">
                <input
                    type="text"
                    name="medicine_form[]"
                    class="js-library-input"
                    data-library-type="medicine_form"
                    value=""
                    placeholder="Medicine form — Tab., Cap., Syr..."
                    autocomplete="off"
                >
                <button
                    type="button"
                    class="rx-library-open js-open-library"
                    aria-label="Open medicine forms library"
                    tabindex="-1"
                    aria-hidden="true"
                ></button>
            </div>
            <div class="rx-field-library-dropdown"></div>
        `;
        return field;
    };

    const ensureMedicineFormOnEveryRow = function (root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            const primaryFields = row.querySelector('.rx-editor-medicine-primary');

            if (!primaryFields) {
                return;
            }

            if (!primaryFields.querySelector('input[name="medicine_form[]"]')) {
                primaryFields.insertBefore(
                    createMedicineFormField(),
                    primaryFields.firstElementChild
                );
            }
        });
    };

    const openLibraryForInput = function (input) {
        if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
            return;
        }

        if (!input.classList.contains('js-library-input')) {
            return;
        }

        const libraryField = input.closest('.rx-library-field');

        if (!libraryField) {
            return;
        }

        const dropdown = libraryField.querySelector('.rx-field-library-dropdown');
        const openButton = libraryField.querySelector('.js-open-library');

        if (
            openButton instanceof HTMLButtonElement
            && (!dropdown || dropdown.childElementCount === 0)
        ) {
            openButton.click();
        }
    };

    ensureMedicineFormOnEveryRow(editor);
    ensureMedicineNameSearch(editor);
    cleanDropdownMessages(editor);

    editor.addEventListener('focusin', function (event) {
        openLibraryForInput(event.target);

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 0);
    });

    editor.addEventListener('input', function (event) {
        if (
            event.target instanceof HTMLInputElement
            && (
                event.target.name === 'medicine_generic_name[]'
                || event.target.name === 'medicine_brand_name[]'
            )
        ) {
            event.target.dataset.libraryType = 'medicine';
            event.target.dataset.libraryRole = 'both';
            event.target.dataset.searchFields = 'generic_name,brand_name';
        }

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 260);
    });

    editor.addEventListener('click', function (event) {
        openLibraryForInput(event.target);

        window.setTimeout(function () {
            cleanDropdownMessages(editor);
        }, 0);
    });

    const medicineRowsContainer = document.getElementById('medicineRows');
    const addMedicineButton = document.getElementById('addMedicineRow');
    const medicineEditorMessage = document.getElementById('medicineEditorMessage');
    let medicineRowCountBeforeAdd = 0;
    let medicineAddWasApproved = false;
    let addMedicineButtonTopBeforeAdd = null;

    const medicineValueSelectors = [
        'input[name="medicine_form[]"]',
        'input[name="medicine_generic_name[]"]',
        'input[name="medicine_brand_name[]"]',
        'input[name="medicine_strength[]"]',
        'input[name="medicine_dosage[]"]',
        'input[name="medicine_frequency[]"]',
        'input[name="medicine_duration[]"]',
        'input[name="medicine_instruction[]"]'
    ];

    const getMedicineValue = function (row, fieldName) {
        const input = row.querySelector('input[name="' + fieldName + '[]"]');

        return input instanceof HTMLInputElement
            ? input.value.replace(/\s+/g, ' ').trim()
            : '';
    };

    const setTextIfChanged = function (element, value) {
        if (element && element.textContent !== value) {
            element.textContent = value;
        }
    };

    const ensureMedicineRowChrome = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        const fields = row.querySelector('.rx-editor-medicine-fields');

        if (!fields) {
            return;
        }

        let preview = fields.querySelector('.rx-medicine-prescription-preview');

        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'rx-medicine-prescription-preview';
            preview.setAttribute('aria-live', 'polite');
            preview.innerHTML = `
                <p class="rx-medicine-preview-name"></p>
                <p class="rx-medicine-preview-direction"></p>
            `;
            fields.insertBefore(preview, fields.firstElementChild);
        }

        let cardFooter = fields.querySelector('.rx-medicine-card-footer');

        if (!cardFooter) {
            cardFooter = document.createElement('div');
            cardFooter.className = 'rx-medicine-card-footer';
            cardFooter.innerHTML = `
                <button
                    type="button"
                    class="rx-medicine-save-button js-save-medicine"
                >Save Medicine</button>
            `;
            fields.appendChild(cardFooter);
        }

        let actions = row.querySelector('.rx-medicine-row-actions');
        const directRemoveButton = Array.from(row.children).find(function (child) {
            return child.classList && child.classList.contains('js-remove-row');
        });

        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'rx-medicine-row-actions';
            row.appendChild(actions);
        }

        let editButton = actions.querySelector('.js-edit-medicine');

        if (!editButton) {
            editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'rx-medicine-edit-button js-edit-medicine';
            editButton.setAttribute('aria-label', 'Edit medicine');
            editButton.setAttribute('title', 'Edit medicine');
            editButton.setAttribute('aria-expanded', 'false');
            editButton.innerHTML = `
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M4 16.5V20h3.5L18.1 9.4l-3.5-3.5L4 16.5Zm16.7-9.9a1 1 0 0 0 0-1.4l-1.9-1.9a1 1 0 0 0-1.4 0l-1.5 1.5 3.5 3.5 1.3-1.7Z"/>
                </svg>
            `;
            actions.appendChild(editButton);
        }

        if (directRemoveButton) {
            directRemoveButton.setAttribute('title', 'Remove medicine');
            actions.appendChild(directRemoveButton);
        }
    };

    const syncMedicinePreview = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        ensureMedicineRowChrome(row);

        const form = getMedicineValue(row, 'medicine_form');
        const generic = getMedicineValue(row, 'medicine_generic_name');
        const brand = getMedicineValue(row, 'medicine_brand_name');
        const strength = getMedicineValue(row, 'medicine_strength');
        const dosage = getMedicineValue(row, 'medicine_dosage');
        const frequency = getMedicineValue(row, 'medicine_frequency');
        const duration = getMedicineValue(row, 'medicine_duration');
        const instruction = getMedicineValue(row, 'medicine_instruction');

        const displayName = generic || brand;
        const nameParts = [];

        if (form !== '') {
            nameParts.push(form);
        }

        if (displayName !== '') {
            nameParts.push(displayName);
        }

        if (generic !== '' && brand !== '') {
            nameParts.push('(' + brand + ')');
        }

        if (strength !== '') {
            nameParts.push(strength);
        }

        const direction = [dosage, frequency, duration, instruction]
            .filter(function (value) {
                return value !== '';
            })
            .join('  •  ');

        const nameElement = row.querySelector('.rx-medicine-preview-name');
        const directionElement = row.querySelector('.rx-medicine-preview-direction');

        setTextIfChanged(
            nameElement,
            nameParts.join(' ') || 'Medicine details not entered'
        );
        setTextIfChanged(directionElement, direction);
    };

    const collapseMedicineRow = function (row) {
        if (!(row instanceof Element)) {
            return;
        }

        syncMedicinePreview(row);
        row.classList.remove('is-editing');
        row.classList.add('is-preview');

        const editButton = row.querySelector('.js-edit-medicine');

        if (editButton) {
            editButton.setAttribute('aria-expanded', 'false');
        }

        row.querySelectorAll('.rx-field-library-dropdown').forEach(function (dropdown) {
            if (dropdown.childElementCount > 0) {
                dropdown.replaceChildren();
            }
        });
    };

    const collapseAllMedicineRows = function (exceptRow) {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row) {
            if (row !== exceptRow) {
                collapseMedicineRow(row);
            }
        });
    };

    const openMedicineRow = function (row, shouldFocus) {
        if (!(row instanceof Element)) {
            return;
        }

        collapseAllMedicineRows(row);
        ensureMedicineRowChrome(row);
        syncMedicinePreview(row);
        row.classList.remove('is-preview');
        row.classList.add('is-editing');

        const editButton = row.querySelector('.js-edit-medicine');

        if (editButton) {
            editButton.setAttribute('aria-expanded', 'true');
        }

        if (shouldFocus) {
            const firstInput = row.querySelector(
                'input[name="medicine_form[]"], '
                + 'input[name="medicine_generic_name[]"], '
                + 'input[name="medicine_brand_name[]"]'
            );

            if (firstInput instanceof HTMLInputElement) {
                window.setTimeout(function () {
                    firstInput.focus({preventScroll: true});
                    row.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                }, 40);
            }
        }
    };

    const renumberMedicineRows = function () {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row, index) {
            const number = row.querySelector('.rx-editor-medicine-number');
            setTextIfChanged(number, String(index + 1) + '.');
        });
    };

    const prepareMedicineCardUI = function (root) {
        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            ensureMedicineRowChrome(row);
            syncMedicinePreview(row);

            if (
                !row.classList.contains('is-preview')
                && !row.classList.contains('is-editing')
            ) {
                collapseMedicineRow(row);
            }
        });

        renumberMedicineRows();
    };

    const initializeMedicineCardUI = function () {
        const rows = Array.from(editor.querySelectorAll('.js-medicine-row'));

        prepareMedicineCardUI(editor);

        if (rows.length === 0) {
            return;
        }

        const lastRow = rows[rows.length - 1];

        if (rows.length === 1 || !rowHasAnyMedicineValue(lastRow)) {
            openMedicineRow(lastRow, false);
            return;
        }

        collapseAllMedicineRows();
    };


    const normalizeMedicineName = function (value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLocaleLowerCase();
    };

    const setMedicineMessage = function (message) {
        if (medicineEditorMessage) {
            medicineEditorMessage.textContent = message || '';
        }
    };

    const clearMedicineRowStates = function () {
        editor.querySelectorAll('.js-medicine-row').forEach(function (row) {
            row.classList.remove(
                'rx-medicine-row-invalid',
                'rx-medicine-row-duplicate'
            );
        });
    };

    const rowHasAnyMedicineValue = function (row) {
        if (!(row instanceof Element)) {
            return false;
        }

        return medicineValueSelectors.some(function (selector) {
            const input = row.querySelector(selector);

            return input instanceof HTMLInputElement
                && input.value.trim() !== '';
        });
    };

    const findDuplicateMedicineRows = function () {
        const rows = Array.from(editor.querySelectorAll('.js-medicine-row'));
        const seen = new Map();
        const duplicateIndexes = new Set();

        rows.forEach(function (row, rowIndex) {
            const generic = row.querySelector(
                'input[name="medicine_generic_name[]"]'
            );
            const brand = row.querySelector(
                'input[name="medicine_brand_name[]"]'
            );

            [generic, brand].forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const normalized = normalizeMedicineName(input.value);

                if (normalized === '') {
                    return;
                }

                if (
                    seen.has(normalized)
                    && seen.get(normalized) !== rowIndex
                ) {
                    duplicateIndexes.add(rowIndex);
                    duplicateIndexes.add(seen.get(normalized));
                    return;
                }

                seen.set(normalized, rowIndex);
            });
        });

        return {
            rows: rows,
            indexes: Array.from(duplicateIndexes)
        };
    };

    const validateMedicineDuplicates = function () {
        clearMedicineRowStates();

        const duplicateResult = findDuplicateMedicineRows();

        if (duplicateResult.indexes.length === 0) {
            return true;
        }

        duplicateResult.indexes.forEach(function (index) {
            const row = duplicateResult.rows[index];

            if (row) {
                row.classList.add('rx-medicine-row-duplicate');
            }
        });

        setMedicineMessage(
            'The same Generic Name or Brand Name cannot be added twice.'
        );

        const firstDuplicate = duplicateResult.rows[
            duplicateResult.indexes[0]
        ];

        if (firstDuplicate) {
            firstDuplicate.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        return false;
    };

    const keepAddMedicineButtonInView = function (previousTop) {
        if (!addMedicineButton || typeof previousTop !== 'number') {
            return;
        }

        const currentTop = addMedicineButton.getBoundingClientRect().top;
        const movement = currentTop - previousTop;

        if (Math.abs(movement) > 1) {
            window.scrollBy({
                top: movement,
                left: 0,
                behavior: 'smooth'
            });
        }
    };

    const prepareMedicineRows = function (root) {
        ensureMedicineFormOnEveryRow(root);
        ensureMedicineNameSearch(root);

        const scope = root instanceof Element || root instanceof Document
            ? root
            : document;

        const fieldSettings = [
            ['medicine_form[]', 'Medicine form — Tab., Cap., Syr...'],
            ['medicine_generic_name[]', 'Generic name — search generic or brand'],
            ['medicine_brand_name[]', 'Brand name (optional) — search generic or brand'],
            ['medicine_strength[]', 'Strength — e.g. 500 mg'],
            ['medicine_dosage[]', 'Dosage — e.g. 1 tablet'],
            ['medicine_frequency[]', 'Frequency — e.g. 1+0+1'],
            ['medicine_duration[]', 'Duration — e.g. 5 days'],
            ['medicine_instruction[]', 'Instruction — e.g. after meal']
        ];

        scope.querySelectorAll('.js-medicine-row').forEach(function (row) {
            fieldSettings.forEach(function (setting) {
                const input = row.querySelector(
                    'input[name="' + setting[0] + '"]'
                );

                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                input.placeholder = setting[1];
                input.setAttribute(
                    'aria-label',
                    setting[1].split('—')[0].trim()
                );
            });
        });
    };

    prepareMedicineRows(editor);
    initializeMedicineCardUI();
    cleanMedicineHelperMessages(editor);

    if (addMedicineButton) {
        addMedicineButton.addEventListener('click', function (event) {
            clearMedicineRowStates();
            setMedicineMessage('');

            const rows = Array.from(
                editor.querySelectorAll('.js-medicine-row')
            );
            const editingRow = editor.querySelector(
                '.js-medicine-row.is-editing'
            );
            const validationRow = editingRow || rows[rows.length - 1];

            if (validationRow && !rowHasAnyMedicineValue(validationRow)) {
                event.preventDefault();
                event.stopImmediatePropagation();

                validationRow.classList.add('rx-medicine-row-invalid');
                setMedicineMessage(
                    'Enter a value in at least one medicine field before adding another medicine.'
                );

                const firstInput = validationRow.querySelector(
                    'input[name="medicine_form[]"], '
                    + 'input[name="medicine_generic_name[]"], '
                    + 'input[name="medicine_brand_name[]"]'
                );

                if (firstInput instanceof HTMLInputElement) {
                    firstInput.focus({preventScroll: true});
                }

                return;
            }

            if (!validateMedicineDuplicates()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            collapseAllMedicineRows();
            medicineRowCountBeforeAdd = rows.length;
            addMedicineButtonTopBeforeAdd = addMedicineButton.getBoundingClientRect().top;
            medicineAddWasApproved = true;
        }, true);
    }

    editor.addEventListener('input', function (event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const row = input.closest('.js-medicine-row');

        if (row) {
            row.classList.remove(
                'rx-medicine-row-invalid',
                'rx-medicine-row-duplicate'
            );
            syncMedicinePreview(row);
        }

        setMedicineMessage('');
    });


    editor.addEventListener('click', function (event) {
        const saveButton = event.target.closest('.js-save-medicine');

        if (!saveButton) {
            return;
        }

        const row = saveButton.closest('.js-medicine-row');

        if (!row) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        clearMedicineRowStates();
        setMedicineMessage('');

        if (!rowHasAnyMedicineValue(row)) {
            row.classList.add('rx-medicine-row-invalid');
            setMedicineMessage(
                'Enter a value in at least one medicine field before saving.'
            );

            const firstInput = row.querySelector(
                'input[name="medicine_form[]"], '
                + 'input[name="medicine_generic_name[]"], '
                + 'input[name="medicine_brand_name[]"]'
            );

            if (firstInput instanceof HTMLInputElement) {
                firstInput.focus({preventScroll: true});
            }

            return;
        }

        if (!validateMedicineDuplicates()) {
            return;
        }

        syncMedicinePreview(row);
        collapseMedicineRow(row);
        setMedicineMessage('Medicine saved.');

        if (addMedicineButton instanceof HTMLButtonElement) {
            addMedicineButton.focus({preventScroll: true});
        }

        window.setTimeout(function () {
            if (
                medicineEditorMessage
                && medicineEditorMessage.textContent === 'Medicine saved.'
            ) {
                setMedicineMessage('');
            }
        }, 1800);
    });

    editor.addEventListener('click', function (event) {
        const editButton = event.target.closest('.js-edit-medicine');

        if (!editButton) {
            return;
        }

        const row = editButton.closest('.js-medicine-row');

        if (!row) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        clearMedicineRowStates();
        setMedicineMessage('');
        openMedicineRow(row, true);
    });

    editor.addEventListener('submit', function (event) {
        if (!validateMedicineDuplicates()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    const observer = new MutationObserver(function () {
        prepareMedicineRows(editor);
        prepareMedicineCardUI(editor);
        cleanDropdownMessages(editor);
        cleanMedicineHelperMessages(editor);

        const rows = Array.from(
            editor.querySelectorAll('.js-medicine-row')
        );

        if (
            medicineAddWasApproved
            && rows.length > medicineRowCountBeforeAdd
        ) {
            const newestRow = rows[rows.length - 1];

            if (newestRow) {
                openMedicineRow(newestRow, false);
                newestRow.classList.add('rx-medicine-row-enter');

                window.requestAnimationFrame(function () {
                    newestRow.classList.remove('rx-medicine-row-enter');
                });
            }

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    keepAddMedicineButtonInView(
                        addMedicineButtonTopBeforeAdd
                    );
                });
            });

            medicineAddWasApproved = false;
            medicineRowCountBeforeAdd = rows.length;
            addMedicineButtonTopBeforeAdd = null;

            if (newestRow) {
                const firstInput = newestRow.querySelector(
                    'input[name="medicine_form[]"], '
                    + 'input[name="medicine_generic_name[]"]'
                );

                if (firstInput instanceof HTMLInputElement) {
                    window.setTimeout(function () {
                        firstInput.focus({preventScroll: true});
                    }, 260);
                }
            }
        }
    });

    observer.observe(editor, {
        childList: true,
        subtree: true
    });
});
