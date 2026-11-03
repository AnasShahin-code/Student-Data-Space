/*
 * main.js
 * -------
 * All the client-side behaviour of the site:
 *
 *   1. AJAX validation of the student form
 *   2. Live preview of the chosen profile picture
 *   3. Confirmation prompt before deleting a student
 *   4. Live "passwords match" note on the register page
 *   5. Live search on the student list (also AJAX-ish, via the URL)
 *
 * Everything is wrapped in DOMContentLoaded so the elements exist
 * before we try to find them.
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ==============================================================
     * 1. AJAX VALIDATION OF THE STUDENT FORM
     *
     * Both add_student.php and update_student.php give their form the
     * id "studentForm", so this one block covers both pages.
     *
     * How it works:
     *   - the user leaves a field (the "blur" event) or stops typing
     *   - we send the whole form to validate_student.php with fetch()
     *   - PHP answers with JSON saying which fields are wrong
     *   - we add Bootstrap's is-valid / is-invalid class to each field
     *
     * The page never reloads, which is the whole point of AJAX.
     * ============================================================== */

    const studentForm = document.getElementById('studentForm');

    if (studentForm) {

        const fieldNames = ['name', 'email', 'phone', 'address'];

        // Used to cancel a request that is still in flight when a newer
        // one starts. Without this, a slow old answer could arrive after
        // a fast new one and overwrite the correct messages.
        let currentRequest = null;

        /**
         * Ask the server to check the form.
         * @param {string[]} fields - which fields we want messages about
         */
        function validateFields(fields) {

            // FormData collects every input in the form for us.
            const data = new FormData(studentForm);

            // Drop the picture before sending. FormData would include the
            // whole file, and re-uploading a 2 MB image every time the
            // user pauses typing would be very slow for no reason -
            // validate_student.php only looks at the text fields.
            data.delete('profile_picture');

            // Tell PHP to only report on the fields we ask about, so
            // empty fields the user has not reached yet stay quiet.
            data.set('fields', fields.join(','));

            // Cancel the previous request if it is still running
            if (currentRequest) {
                currentRequest.abort();
            }
            const controller = new AbortController();
            currentRequest = controller;

            fetch('validate_student.php', {
                method: 'POST',
                body: data,
                signal: controller.signal
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    applyResult(result, fields);
                })
                .catch(function (error) {
                    // AbortError just means we cancelled it on purpose,
                    // so it is not a real problem.
                    if (error.name !== 'AbortError') {
                        console.error('Validation request failed:', error);
                    }
                });
        }

        /**
         * Paint the result of one validation round onto the form.
         */
        function applyResult(result, fields) {

            fields.forEach(function (field) {

                const input = studentForm.querySelector('[name="' + field + '"]');
                if (!input) {
                    return;
                }

                // Bootstrap looks for .invalid-feedback immediately after
                // the input to decide where to print the red message.
                const feedback = input.parentElement.querySelector('.invalid-feedback');

                if (result.errors && result.errors[field]) {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                    if (feedback) {
                        feedback.textContent = result.errors[field];
                    }
                } else {
                    input.classList.remove('is-invalid');
                    // Only paint it green if the user actually typed something
                    if (input.value.trim() !== '') {
                        input.classList.add('is-valid');
                    } else {
                        input.classList.remove('is-valid');
                    }
                    if (feedback) {
                        feedback.textContent = '';
                    }
                }
            });
        }

        // --- Hook the events onto every field ---
        fieldNames.forEach(function (field) {

            const input = studentForm.querySelector('[name="' + field + '"]');
            if (!input) {
                return;
            }

            // Check as soon as the user leaves the field
            input.addEventListener('blur', function () {
                if (input.value.trim() !== '') {
                    validateFields([field]);
                }
            });

            // Also check while they type, but wait until they have paused
            // for 500ms. This is called "debouncing" - without it we
            // would fire a request on every single key press.
            let timer = null;
            input.addEventListener('input', function () {
                clearTimeout(timer);

                // If the field is already marked red, re-check quickly so
                // the error disappears as soon as they fix it.
                const delay = input.classList.contains('is-invalid') ? 300 : 600;

                timer = setTimeout(function () {
                    if (input.value.trim() !== '') {
                        validateFields([field]);
                    } else {
                        input.classList.remove('is-valid', 'is-invalid');
                    }
                }, delay);
            });
        });

        // --- Final check when the form is submitted ---
        // We stop the submit, ask the server about ALL the fields at
        // once, and only let it through if everything is fine.
        studentForm.addEventListener('submit', function (event) {

            // A flag we set ourselves once the server says OK, so the
            // second (real) submit is not intercepted again.
            if (studentForm.dataset.validated === 'true') {
                return;
            }

            event.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : '';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>Checking...';
            }

            const data = new FormData(studentForm);
            data.delete('profile_picture');          // same reason as above
            data.set('fields', fieldNames.join(','));

            fetch('validate_student.php', { method: 'POST', body: data })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {

                    applyResult(result, fieldNames);

                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }

                    if (result.valid) {
                        // Everything is fine - submit for real this time
                        studentForm.dataset.validated = 'true';
                        studentForm.submit();
                    } else {
                        // Scroll to the first red field so the user sees it
                        const firstBad = studentForm.querySelector('.is-invalid');
                        if (firstBad) {
                            firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstBad.focus();
                        }
                    }
                })
                .catch(function (error) {
                    // If the AJAX call itself breaks (no network, server
                    // down) we let the form submit normally. PHP will
                    // validate it again anyway, so nothing bad can slip in.
                    console.error('Could not reach the validation script:', error);
                    studentForm.dataset.validated = 'true';
                    studentForm.submit();
                });
        });
    }


    /* ==============================================================
     * 2. PROFILE PICTURE PREVIEW
     *
     * FileReader lets us read the chosen file in the browser and show
     * it straight away, before anything is uploaded.
     * ============================================================== */

    const pictureInput   = document.getElementById('profile_picture');
    const previewImg     = document.getElementById('picturePreview');
    const previewWrapper = document.getElementById('previewWrapper');

    if (pictureInput && previewImg && previewWrapper) {

        pictureInput.addEventListener('change', function () {

            const file = pictureInput.files[0];

            // Nothing picked (or they cancelled the dialog)
            if (!file) {
                previewWrapper.classList.add('d-none');
                return;
            }

            // Quick client-side size check so the user finds out now
            // instead of after a slow upload. PHP checks again anyway.
            const maxBytes = 2 * 1024 * 1024;
            const feedback = pictureInput.parentElement.querySelector('.invalid-feedback');

            if (file.size > maxBytes) {
                pictureInput.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'That picture is bigger than 2 MB.';
                }
                previewWrapper.classList.add('d-none');
                return;
            }

            if (!file.type.startsWith('image/')) {
                pictureInput.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = 'Please choose an image file.';
                }
                previewWrapper.classList.add('d-none');
                return;
            }

            pictureInput.classList.remove('is-invalid');

            const reader = new FileReader();
            reader.onload = function (event) {
                previewImg.src = event.target.result;
                previewWrapper.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
    }


    /* ==============================================================
     * 3. DELETE CONFIRMATION
     *
     * Every delete button sits inside a form with the class
     * js-delete-form. We catch the submit and ask first.
     * ============================================================== */

    document.querySelectorAll('.js-delete-form').forEach(function (form) {

        form.addEventListener('submit', function (event) {

            const nameField = form.querySelector('[name="student_name"]');
            const name = nameField ? nameField.value : 'this student';

            const message = 'Delete "' + name + '"?\n\n'
                          + 'This cannot be undone. Their profile picture '
                          + 'will be removed from the server too.';

            // confirm() returns false if the user clicks Cancel, and then
            // preventDefault() stops the form from being sent.
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });


    /* ==============================================================
     * 4. LIVE "PASSWORDS MATCH" NOTE (register page)
     * ============================================================== */

    const passwordField = document.getElementById('password');
    const confirmField  = document.getElementById('confirm_password');
    const matchNote     = document.getElementById('passwordMatchNote');

    if (passwordField && confirmField && matchNote) {

        function checkMatch() {

            if (confirmField.value === '') {
                matchNote.textContent = '';
                confirmField.classList.remove('is-valid', 'is-invalid');
                return;
            }

            if (passwordField.value === confirmField.value) {
                matchNote.textContent = 'Passwords match.';
                matchNote.className = 'form-text text-success';
                confirmField.classList.remove('is-invalid');
                confirmField.classList.add('is-valid');
            } else {
                matchNote.textContent = 'Passwords do not match yet.';
                matchNote.className = 'form-text text-danger';
                confirmField.classList.remove('is-valid');
                confirmField.classList.add('is-invalid');
            }
        }

        passwordField.addEventListener('input', checkMatch);
        confirmField.addEventListener('input', checkMatch);
    }


    /* ==============================================================
     * 5. SEARCH BOX - submit after the user stops typing
     *
     * The Search button still works normally (and works with
     * JavaScript off). This just saves the user from clicking it.
     * ============================================================== */

    const searchInput = document.getElementById('searchInput');

    if (searchInput) {

        let searchTimer = null;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);

            // 700ms so we are not reloading the page mid-word
            searchTimer = setTimeout(function () {
                searchInput.form.submit();
            }, 700);
        });
    }

});
