<div class="table-responsive rounded mb-0">
    <table class="table table-lg m-0" id="prescription_table">
        <thead>
            <tr class="text-white">
                @if ($data['status'] == 1)
                    <th scope="col" class="text-center" style="width: 50px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            <label class="form-check-label text-white" for="selectAll"></label>
                        </div>
                    </th>
                @endif
                <th scope="col">{{ __('appointment.name') }}</th>
                <th scope="col">{{ __('appointment.quanitity') }}</th>
                <th scope="col">{{ __('appointment.frequency') }}</th>
                <th scope="col">{{ __('appointment.duration') }}</th>
                @if ($data['status'] == 1)
                    <th scope="col">{{ __('appointment.action') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($data['prescriptions'] as $index => $prescription)
                <tr>
                    @if ($data['status'] == 1)
                        <td class="text-center">
                            <div class="form-check">
                                <input class="form-check-input prescription-checkbox" type="checkbox"
                                    value="{{ $prescription['id'] }}" id="prescription_{{ $prescription['id'] }}"
                                    onchange="updateBulkDeleteButton()">
                                <label class="form-check-label" for="prescription_{{ $prescription['id'] }}"></label>
                            </div>
                        </td>
                    @endif
                    <td>
                        <p class="m-0">{{ $prescription['name'] }}</p>
                        <p class="m-0">{{ $prescription['instruction'] }}</p>
                    </td>
                    <td>{{ $prescription['quantity'] }}</td>
                    <td>{{ $prescription['frequency'] }}</td>
                    <td>{{ $prescription['duration'] }}</td>
                    @if ($data['status'] == 1)
                        <td class="action">
                            <div class="d-flex align-items-center gap-3">
                                {{-- <button type="button" class="btn text-primary p-0 fs-5 me-2"
                                    onclick="editPrescription({{ $prescription['id'] }})"
                                    aria-controls="form-offcanvas">
                                    <i class="ph ph-pencil-simple-line"></i>
                                </button> --}}
                                <button type="button" class="btn text-danger p-0 fs-5"
                                    onclick="destroyData({{ $prescription['id'] }}, 'Are you sure you want to delete it?')"
                                    data-bs-toggle="tooltip">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach

            @if (count($data['prescriptions']) <= 0)
                <tr>
                    <td colspan="6">
                        <div class="my-1 text-danger text-center no-prescription-message">
                            {{ __('appointment.no_prescription_found') }}
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
    <div class="my-3"></div> <!-- Added space between table and buttons -->
    @if (count($data['prescriptions']) > 0)
            <!-- Bulk Actions Bar -->
            @if ($data['status'] == 1)
                <div class="bulk-actions-bar mt-3 mb-2" id="bulkActionsBar" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border">
                        <div class="selected-count">
                            <span class="badge bg-primary" id="selectedCount">0</span>
                            <span class="ms-2 text-muted">prescriptions selected</span>
                        </div>
                        <div class="bulk-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger me-2"
                                onclick="bulkDeletePrescriptions()" id="bulkDeleteBtn">
                                <i class="ph ph-trash me-1"></i>
                                Delete Selected
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                                <i class="ph ph-x me-1"></i>
                                Clear Selection
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Existing buttons -->
            <div class="action-buttons-row p-3">
                <button id="printButton" class="btn btn-sm btn-primary" onclick="DownloadPDF({{ $data['id'] }})">
                    <i class="ph ph-file-text me-1"></i>
                    {{ __('appointment.lbl_download') }}
                </button>

                <button class="btn btn-sm btn-primary" onclick="sendPrescription(this, {{ $data['id'] }})">
                    <div class="d-inline-flex align-items-center gap-1">
                        <i class="ph ph-paper-plane-tilt" id="send_mail"></i>
                        {{ __('appointment.email') }}
                    </div>
                </button>
            </div>
    @endif
</div>

@push('after-scripts')
    <script>
        var baseUrl = '{{ url('/') }}';
        const checkPluginPharmaActive = "{{ checkPlugin('pharma') }}" === 'active';
        var rowCounter = 0;


        function getEncounterIdFromURL() {
            const pathSegments = window.location.pathname.split('/');
            return pathSegments[pathSegments.length - 1];
        }

        function switchToEditButton(encounterId) {
            const btn = $('#prescription_btn');


            btn.removeAttr('data-bs-toggle');
            btn.removeAttr('data-bs-target');


            btn.attr('data-encounter-id', encounterId);

            btn.off('click').on('click', function() {
                const id = $(this).data('encounter-id');
                editPrescription(id);
            });

            // Update icon
            btn.find('i').removeClass('ph-plus').addClass('ph-pencil-simple');

            // Update text
            btn.find('div').contents().filter(function() {
                return this.nodeType === 3;
            }).last().replaceWith(" {{ __('appointment.edit_prescription') }}");
        }

        function switchToAddButton(encounterId) {
            const btn = $('#prescription_btn');

            btn.off('click'); // remove any jQuery click listeners
            btn.removeAttr('onclick');

            btn.removeAttr('href');
            btn.removeAttr('data-encounter-id');

            btn.attr('data-bs-toggle', 'modal');
            btn.attr('data-bs-target', '#addprescription');

            btn.find('i').removeClass('ph-pencil-simple').addClass('ph-plus');

            btn.find('div').contents().filter(function() {
                return this.nodeType === 3;
            }).last().replaceWith(" {{ __('appointment.add_prescription') }}");
        }

        function destroyData(id, message) {

            confirmDeleteSwal({
                message
            }).then((result) => {

                if (!result.isConfirmed) return;

                $.ajax({
                    // url: baseUrl + '/app/encounter/delete-prescription/' + id,
                    url: "{{ route('backend.encounter.deletePrescription', ['id' => '__ID__']) }}".replace(
                        '__ID__', id),
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: (response) => {
                        if (response.html) {
                            $('#prescription_table').html(response.html);

                            setTimeout(() => {
                                const $rows = $('#prescription_table tbody tr');

                                // Exclude 'no data' row
                                const realRows = $rows.filter(function() {
                                    return !$(this).find('.no-prescription-message')
                                        .length;
                                });

                                const rowCount = realRows.length;
                                const encounterId = getEncounterIdFromURL();

                                if (rowCount > 0) {
                                    switchToEditButton(encounterId);
                                } else {
                                    switchToAddButton(encounterId);
                                }


                                console.log("Real prescription rows after delete:", rowCount);
                            }, 200);

                            Swal.fire({
                                title: 'Deleted',
                                text: response.message,
                                icon: 'success',
                                showClass: {
                                    popup: 'animate__animated animate__zoomIn'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__zoomOut'
                                }
                            });

                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.message || 'Failed to delete the prescription.',
                                icon: 'error',
                                showClass: {
                                    popup: 'animate__animated animate__shakeX'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__fadeOut'
                                }
                            });
                        }
                    }

                });
            });
        }

        function editPrescription(id) {
            const $editBtn = $(`button[onclick="editPrescription(${id})"]`);
            const $row = $editBtn.closest('tr');

            showRowLoaderSimple($row);
            $editBtn.prop('disabled', true);

            $.ajax({
                url: "{{ route('backend.encounter.edit-prescription', ['id' => '__ID__']) }}".replace('__ID__',
                    id),
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: (response) => {
                    if (response.status) {
                        const rawData = response.data;

                        const prescriptionArray = Object.values(rawData).filter(p => p && p.id);

                        $('#medicine-rows').empty();
                        rowCounter = 0;
                        $('#form-submit')[0].classList.remove('was-validated');

                        // Load each medicine row
                        addMedicineRowWithData(prescriptionArray);

                        if ($('#prescription-id-input').length === 0) {
                            $('#form-submit').append(
                                '<input type="hidden" name="prescription_id" id="prescription-id-input" value="">'
                            );
                        }

                        if (prescriptionArray.length > 0) {
                            $('#prescription-id-input').val(prescriptionArray[0].encounter_id || '');

                            const pharmaId = prescriptionArray[0].pharma_id;
                            const pharmaName = prescriptionArray[0].pharma_name || 'Selected Pharma';

                            if (pharmaId) {
                                if (!$('#pharma').hasClass("select2-hidden-accessible")) {
                                    $('#pharma').select2({
                                        placeholder: $('#pharma').data('placeholder'),
                                        ajax: {
                                            url: $('#pharma').data('ajax--url'),
                                            dataType: 'json',
                                            delay: 250,
                                            data: function(params) {
                                                return {
                                                    term: params.term
                                                };
                                            },
                                            processResults: function(data) {
                                                return {
                                                    results: data.results
                                                };
                                            },
                                            cache: true
                                        }
                                    });
                                }

                                $('#pharma').empty(); // clear old options
                                const option = new Option(pharmaName, pharmaId, true, true);
                                $('#pharma').append(option).trigger('change');
                            }

                        }

                        // Set mode to edit
                        $('#form-submit').attr('data-mode', 'edit');

                        // Show modal
                        const modalElement = document.getElementById(
                            'addprescription');
                        if (modalElement) {
                            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                            modal.show();
                        } else {
                            console.error("Modal with ID 'addprescription' not found.");
                        }
                    } else {
                        window.errorSnackbar(response.message || 'Failed to load prescription details.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error(error);
                    window.errorSnackbar('An unexpected error occurred.');
                },
                complete: function() {
                    hideRowLoaderSimple($row);
                    $editBtn.prop('disabled', false);
                }
            });
        }



        function showRowLoaderWithBlur($row) {
            $row.addClass('row-loading-blur');

            const $tableContainer = $row.closest('.table-responsive');
            const $table = $row.closest('table');

            const rowOffset = $row.offset();
            const containerOffset = $tableContainer.offset();
            const tableOffset = $table.offset();

            const rowHeight = $row.outerHeight();
            const rowWidth = $row.outerWidth();

            const top = rowOffset.top - containerOffset.top;
            const left = rowOffset.left - containerOffset.left;

            // Add loading overlay to table container instead of row
            if ($tableContainer.find('.enhanced-row-loader[data-row-id="' + $row.index() + '"]').length === 0) {
                $tableContainer.css('position', 'relative').append(`
                    <div class="enhanced-row-loader" data-row-id="${$row.index()}" style="
                        position: absolute;
                        top: ${top}px;
                        left: ${left}px;
                        width: ${rowWidth}px;
                        height: ${rowHeight}px;
                        z-index: 1000;
                    ">
                        <div class="enhanced-row-overlay">
                            <div class="loader-content">
                                <div class="spinner-border text-primary mb-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="loader-text">Loading prescription...</div>
                            </div>
                        </div>
                    </div>
                `);
            }
        }


        function hideRowLoaderWithBlur($row) {
            // Remove blur effect
            $row.removeClass('row-loading-blur');

            // Remove loading overlay with fade out animation
            const $tableContainer = $row.closest('.table-responsive');
            const $loader = $tableContainer.find('.enhanced-row-loader[data-row-id="' + $row.index() + '"]');

            if ($loader.length > 0) {
                $loader.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        }

        function showRowLoaderSimple($row) {
            // Add blur class to the row content
            $row.addClass('row-loading-blur');

            // Find the action cell (last cell with buttons)
            const $actionCell = $row.find('td:last');

            // Store original content and replace with loader
            if (!$actionCell.data('original-content')) {
                $actionCell.data('original-content', $actionCell.html());
                $actionCell.html(`
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2 text-muted small">Loading...</span>
                    </div>
                `);
            }
        }

        function hideRowLoaderSimple($row) {
            // Remove blur effect
            $row.removeClass('row-loading-blur');

            // Restore original content in action cell
            const $actionCell = $row.find('td:last');
            const originalContent = $actionCell.data('original-content');

            if (originalContent) {
                $actionCell.html(originalContent);
                $actionCell.removeData('original-content');
            }
        }

        // Optional: Add pulse effect while loading
        function addPulseEffect($row) {
            $row.addClass('row-pulse-loading');
        }

        function removePulseEffect($row) {
            $row.removeClass('row-pulse-loading');
        }



        function addMedicineRowWithData(prescriptionsArray = []) {
            prescriptionsArray.forEach(prescriptionData => {
                if (!prescriptionData || !prescriptionData.id) return;

                const row = editMedicineRowTemplate(prescriptionData.id);
                $('#medicine-rows').append(row);

                const $row = $(`[data-index="${prescriptionData.id}"]`);
                $row.find(`input[name="medicines[${prescriptionData.id}][quantity]"]`).val(prescriptionData
                    .quantity || '');
                $row.find(`input[name="medicines[${prescriptionData.id}][frequency]"]`).val(prescriptionData
                    .frequency || '');
                $row.find(`input[name="medicines[${prescriptionData.id}][duration]"]`).val(prescriptionData
                    .duration || '');
                $row.find(`textarea[name="medicines[${prescriptionData.id}][instruction]"]`).val(prescriptionData
                    .instruction || '');

                @if (checkPlugin('pharma') == 'active')
                    setTimeout(function() {
                        const medicineSelect = $row.find(
                            `[name="medicines[${prescriptionData.id}][medicine_id]"]`);

                        medicineSelect.select2({
                            dropdownParent: $('#addprescription .modal-content'),
                            width: '100%',
                            ajax: {
                                url: "{{ route('ajax-list', ['type' => 'clinic-medicine']) }}",
                                dataType: 'json',
                                delay: 250,
                                processResults: function(data) {
                                    return {
                                        results: data.results || []
                                    };
                                },
                                cache: true
                            }
                        });


                        setTimeout(() => {
                            if (prescriptionData.medicine_id && prescriptionData.name) {
                                const option = new Option(prescriptionData.name, prescriptionData
                                    .medicine_id, true, true);
                                medicineSelect.append(option).trigger('change');
                            }
                        }, 200);
                    }, 50);
                @else
                    $row.find(`input[name="medicines[${prescriptionData.id}][name]"]`).val(prescriptionData.name ||
                        '');
                @endif
            });

            updateRemoveButtons();
        }



        function editMedicineRowTemplate(index) {

            return `
                <tr class="medicine-row" data-index="${index}">
                    <td>
                        @if (checkPlugin('pharma') == 'active')
                            <select name="medicines[${index}][medicine_id]" class="form-control select2js medicine-select"
                                    data-placeholder="{{ __('clinic.select_medicines') }}" required>
                                <option value="">{{ __('clinic.select_medicines') }}</option>
                            </select>
                        @else
                            <input type="text" name="medicines[${index}][name]" class="form-control medicine-name"
                                placeholder="{{ __('clinic.medicines') }}" required>
                        @endif
                        <div class="medicine-stock mt-1 text-success" style="font-size: 0.85em; display:none;">
                            {{ __('clinic.stock') }}: <span class="stock-quantity"><b>0</b></span>
                        </div>
                        <input type="hidden" class="available-stock" value="0">
                        <span class="stock-warning text-danger" style="display: none; font-size: 0.85em;">
                            {{ __('clinic.stock_warning') ?? 'Quantity exceeds stock!' }}
                        </span>
                    </td>

                    <td>
                        <input type="text" name="medicines[${index}][frequency]" class="form-control text-center"
                            placeholder="e.g., 2x daily" required>
                    </td>
                    <td>
                        <input type="number" name="medicines[${index}][duration]" class="form-control text-center"
                            placeholder="Days" min="1" required>
                    </td>
                     <td>
                        <input type="number" name="medicines[${index}][quantity]" class="form-control prescription-quantity text-center"
                            placeholder="Qty" min="1" required>
                    </td>
                    <td>
                        <textarea name="medicines[${index}][instruction]" class="form-control custom-input-box overflow-hidden" rows="1"
                                placeholder="Instructions"></textarea>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-medicine-row"
                                ${index === 0 ? 'style="display:none;"' : ''}>
                            <i class="ph ph-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        function updateRemoveButtons() {
            const rows = $('.medicine-row');
            if (rows.length <= 1) {
                $('.remove-medicine-row').hide();
            } else {
                $('.remove-medicine-row').show();
                $('.medicine-row:first .remove-medicine-row').hide(); // Keep first row's remove button hidden
            }
        }

        function editMedicineRow() {
            addMedicineRowWithData(null);
        }

        function resetForm() {
            $('#medicine-rows').empty();
            rowCounter = 0;

            // Remove edit-specific elements and reset form state
            $('#prescription-id-input').remove();
            $('#form-submit').removeAttr('data-mode');

            // Reset modal title and button text
            $('#exampleModalLabel').text('{{ __('clinic.add_prescription') }}');
            $('.modal-footer .btn-primary').html(`
                <i class="ph ph-check me-1"></i>
                Save All Prescriptions
            `);

            // Add fresh row
            editMedicineRow();
            $('#form-submit')[0].classList.remove('was-validated');
        }




        function DownloadPDF(id) {
            const baseUrl = '{{ url('/') }}'; // Base URL of your application
            // const downloadUrl = `${baseUrl}/app/encounter/download-prescription?id=${id}`;
            const downloadUrl = "{{ route('backend.encounter.download-prescription', ['id' => '__ID__']) }}".replace(
                '__ID__', id);

            // Sending the request to fetch the download link
            $.ajax({
                url: downloadUrl,
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: (response) => {
                    if (response.status && response.link) {
                        // Create a temporary link element
                        const link = document.createElement('a');
                        link.href = response.link; // The URL from the response
                        link.target = '_blank'; // Open in a new tab
                        link.download = ''; // Optional: Set a filename (the server usually provides it)
                        document.body.appendChild(link); // Append link to the body
                        link.click(); // Trigger the download
                        document.body.removeChild(link); // Remove link after downloading
                    } else {}
                },
                error: (xhr, status, error) => {
                    console.error(error);
                }
            });
        }

        function sendPrescription(button, id) {

            $(button).prop('disabled', true).html(`
                <div class="d-inline-flex align-items-center gap-1">
                    <i class="ph ph-spinner ph-spin"></i>
                    Sending...
                </div>
            `);

            $.ajax({
                // url: baseUrl + '/app/encounter/send-prescription?id=' + id,
                url: "{{ route('backend.encounter.send-prescription', ['id' => '__ID__']) }}".replace('__ID__',
                    id),
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: (response) => {
                    if (response.status) {
                        window.successSnackbar(response.message);
                    } else {
                        window.errorSnackbar('Something went wrong! Please check.');
                    }
                    // Re-enable the button and reset its text
                    $(button).prop('disabled', false).html(`
                <div class="d-inline-flex align-items-center gap-1">
                    <i class="ph ph-paper-plane-tilt"></i>
                    {{ __('appointment.email') }}
                </div>
            `);
                },
                error: (xhr, status, error) => {
                    console.error(error);
                    window.errorSnackbar('Something went wrong! Please check.');
                    // Re-enable the button and reset its text
                    $(button).prop('disabled', false).html(`
                <div class="d-inline-flex align-items-center gap-1">
                    <i class="ph ph-paper-plane-tilt"></i>
                    {{ __('appointment.email') }}
                </div>
            `);
                }
            });
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const prescriptionCheckboxes = document.querySelectorAll('.prescription-checkbox');

            prescriptionCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });

            updateBulkDeleteButton();
        }

        function updateBulkDeleteButton() {
            const selectedCheckboxes = document.querySelectorAll('.prescription-checkbox:checked');
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCountElement = document.getElementById('selectedCount');
            const selectAllCheckbox = document.getElementById('selectAll');

            const selectedCount = selectedCheckboxes.length;
            const totalCheckboxes = document.querySelectorAll('.prescription-checkbox').length;

            // Update selected count
            selectedCountElement.textContent = selectedCount;

            // Show/hide bulk actions bar
            if (selectedCount > 0) {
                bulkActionsBar.style.display = 'block';
            } else {
                bulkActionsBar.style.display = 'none';
            }

            // Update select all checkbox state
            if (selectedCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (selectedCount === totalCheckboxes) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            }
        }

        function clearSelection() {
            const prescriptionCheckboxes = document.querySelectorAll('.prescription-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');

            prescriptionCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;

            updateBulkDeleteButton();
        }

        function bulkDeletePrescriptions() {
            const selectedCheckboxes = document.querySelectorAll('.prescription-checkbox:checked');
            const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

            if (selectedIds.length === 0) {
                window.errorSnackbar('Please select at least one prescription to delete.');
                return;
            }

            const message = `Are you sure you want to delete ${selectedIds.length} prescription(s)?`;

            confirmDeleteSwal({
                message
            }).then((result) => {
                if (!result.isConfirmed) return;

                // Show loading state
                const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
                const originalText = bulkDeleteBtn.innerHTML;
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML = `
                    <i class="ph ph-spinner ph-spin me-1"></i>
                    Deleting...
                `;

                $.ajax({
                    // url: baseUrl + '/app/encounter/bulk-delete-prescriptions',
                    url: "{{ route('backend.encounter.bulk-delete-prescriptions') }}",
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        prescription_ids: selectedIds
                    },
                    success: (response) => {
                        if (response.status) {
                            // Update the table content
                            if (response.html) {
                                document.getElementById('prescription_table').innerHTML = response.html;
                                $('#addprescription').modal('hide');
                                resetForm();

                                const encounterId = $('#encounter_id').val();

                                setTimeout(() => {
                                    const $table = $('#prescription_table');
                                    const rowCount = $table.find('tbody tr').length;

                                    if (rowCount > 0) {
                                        switchToEditButton(encounterId);
                                    } else {
                                        switchToAddButton(encounterId);
                                    }

                                }, 200);

                                const message = isEditModeSubmit ?
                                    'Prescription updated successfully' :
                                    `${response.count || 'Multiple'} prescriptions added successfully`;

                                window.successSnackbar(message);
                            }


                            // Clear selections and hide bulk actions
                            clearSelection();

                            // Show success message
                            Swal.fire({
                                title: 'Deleted',
                                text: response.message ||
                                    `${selectedIds.length} prescription(s) deleted successfully.`,
                                icon: 'success',
                                showClass: {
                                    popup: 'animate__animated animate__zoomIn'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__zoomOut'
                                }
                            });
                        } else {
                            console.error(response);
                            Swal.fire({
                                title: 'Error',
                                text: response.message || 'Failed to delete prescriptions.',
                                icon: 'error',
                                showClass: {
                                    popup: 'animate__animated animate__shakeX'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__fadeOut'
                                }
                            });
                        }
                    },

                    error: (xhr, status, error) => {
                        console.error(error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An unexpected error occurred while deleting prescriptions.',
                            icon: 'error'
                        });
                    },
                    complete: () => {
                        // Reset button state
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = originalText;
                    }
                });
            });
        }
        $(document).ready(function() {
            // Add medicine row
            $('#add-medicine-row').on('click', function() {
                addMedicineRow();
            });
            updateBulkDeleteButton();
            // Remove medicine row
            $(document).on('click', '.remove-medicine-row', function() {
                $(this).closest('.medicine-row').remove();
                updateRemoveButtons();
            });

            // Stock checking
            $(document).on('change', '.medicine-select', function() {
                const $row = $(this).closest('.medicine-row');
                const medicineId = $(this).val();

                if (!medicineId) {
                    $row.find('.medicine-stock').hide();
                    return;
                }

                $.ajax({
                    url: {{ __('backend.prescription.medicine_stock', ['id' => '']) }} +
                        medicineId,
                    method: 'GET',
                    success: function(response) {
                        if (response.stock !== undefined) {
                            $row.find('.stock-quantity').text(response.stock);
                            $row.find('.available-stock').val(response.stock);
                            $row.find('.medicine-stock').show();
                        } else {
                            $row.find('.medicine-stock').hide();
                        }
                    },
                    error: function() {
                        $row.find('.medicine-stock').hide();
                    }
                });
            });

            // Quantity validation
            $(document).on('input', '.prescription-quantity', function() {
                const $row = $(this).closest('.medicine-row');
                let enteredQty = parseInt($(this).val()) || 0;
                let availableStock = parseInt($row.find('.available-stock').val()) || 0;

                if (enteredQty > availableStock && availableStock > 0) {
                    $row.find('.stock-warning').show();
                } else {
                    $row.find('.stock-warning').hide();
                }
            });

            // Modal events
            $('#addprescription').on('shown.bs.modal', function() {
                if ($('.medicine-row').length === 0) {
                    editMedicineRow();
                }
            });

            $('#addprescription').on('hidden.bs.modal', function() {
                resetForm();
            });

            // Initialize first row
            // editMedicineRow();
        });
    </script>
@endpush
<style>
    /* Enhanced row loading with blur effect */
    .row-loading-blur {
        filter: blur(1px);
        opacity: 0.7;
        transition: all 0.3s ease-in-out;
        pointer-events: none;
    }

    /* Enhanced loader overlay for table container positioning */
    .enhanced-row-loader {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        overflow: hidden;
    }

    .enhanced-row-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        animation: fadeInLoader 0.3s ease-in-out;
        border: 1px solid rgba(13, 110, 253, 0.1);
    }

    .loader-content {
        text-align: center;
        color: #0d6efd;
        z-index: 101;
    }

    .loader-text {
        font-size: 0.75rem;
        font-weight: 500;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    /* Enhanced spinner */
    .enhanced-row-loader .spinner-border {
        width: 1.5rem;
        height: 1.5rem;
        border-width: 0.15em;
        color: #0d6efd;
    }

    .row-loading-blur .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.1em;
    }

    /* Smooth animations */
    @keyframes fadeInLoader {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes textPulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .table-responsive {
        position: relative;
    }

    .table tbody tr {
        position: relative;
        transition: all 0.2s ease;
    }

    .table tbody tr:not(.row-loading-blur) button {
        pointer-events: auto;
    }

    button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transition: opacity 0.2s ease;
    }

    /* Optional: Pulse effect for the entire row */
    .row-pulse-loading {
        animation: rowGlow 2s ease-in-out infinite;
    }

    @keyframes rowGlow {

        0%,
        100% {
            box-shadow: 0 0 0 rgba(13, 110, 253, 0);
        }

        50% {
            box-shadow: 0 0 20px rgba(13, 110, 253, 0.1);
        }
    }

    /* Table row positioning */
    .table tbody tr {
        position: relative;
    }

    /* Ensure smooth transitions */
    .table tbody tr {
        transition: all 0.3s ease;
    }

    /* Hover effects for non-loading rows */
    .table tbody tr:not(.row-loading-blur):hover {
        background-color: rgba(0, 0, 0, 0.02);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Loading state improvements */
    .row-loading-blur * {
        pointer-events: none;
    }


    /* Mobile responsive */
    @media (max-width: 768px) {
        .enhanced-row-loader .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
        }

        .loader-text {
            font-size: 0.7rem;
        }

        .row-loading-blur {
            filter: blur(0.5px);
        }
    }

    /* Dark mode support (if you use dark theme) */
    @media (prefers-color-scheme: dark) {
        .enhanced-row-overlay {
            background: rgba(33, 37, 41, 0.95);
        }

        .loader-text {
            color: #adb5bd;
        }
    }

    /* Additional enhancement: Gradient loader background */
    .enhanced-row-overlay::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg,
                rgba(13, 110, 253, 0.05) 0%,
                rgba(255, 255, 255, 0.05) 50%,
                rgba(13, 110, 253, 0.05) 100%);
        border-radius: 8px;
        animation: gradientShimmer 2s ease-in-out infinite;
    }

    @keyframes gradientShimmer {

        0%,
        100% {
            opacity: 0.3;
        }

        50% {
            opacity: 0.7;
        }
    }

    .bulk-actions-bar {
        animation: slideDown 0.3s ease-out;
        border: 2px dashed #dee2e6 !important;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .prescription-checkbox:checked+label::after {
        animation: checkScale 0.2s ease-out;
    }

    @keyframes checkScale {
        0% {
            transform: scale(0);
        }

        50% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Highlight selected rows */
    tr:has(.prescription-checkbox:checked) {
        background-color: rgba(13, 110, 253, 0.05);
        border-left: 3px solid #0d6efd;
    }

    /* Indeterminate checkbox styling */
    input[type="checkbox"]:indeterminate {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    input[type="checkbox"]:indeterminate::before {
        content: '−';
        color: white;
        font-size: 14px;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }

    .selected-count .badge {
        animation: pulse 0.3s ease-out;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }
</style>
