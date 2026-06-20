<div class="modal fade" id="addprescription" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('clinic.add_prescription') }}</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Loader for prescription modal -->
                <div id="prescription-loader" class="text-center py-5" style="display:none;">
                    <span class="spinner-border text-primary"></span>
                </div>
                <form method="post" id="form-submit" class="requires-validation">
                    @csrf
                    <input type="hidden" name="encounter_id" value="{{ $data['id'] }}">
                    <input type="hidden" name="user_id" value="{{ $data['user_id'] }}">
                    <input type="hidden" name="type" value="encounter_prescription">
                    @php
                        $pharmaPluginActive = checkPlugin('pharma') == 'active';
                    @endphp

                    @if ($pharmaPluginActive)
                        <div class="mb-3 col-12 col-md-3 custom-select-input-white" id="pharma-section">
                            <label class="form-label">{{ __('messages.select_pharma') }}</label>
                            <select id="pharma" name="pharma" class="form-control select2"
                                data-placeholder="{{ __('pharma::messages.select_pharma') }}"
                                data-ajax--url="{{ route('backend.get_search_data', ['type' => 'pharma']) }}"
                                data-ajax--cache="true" required>
                            </select>
                        </div>
                    @endif

                    <!-- Medicine Table Header -->
                    <div class="table-responsive">
                        <table class="table" id="prescription-table">
                            <thead>
                                <tr>
                                    <th>
                                        {{ __('clinic.medicines') }} <span class="text-danger">*</span>
                                    </th>

                                    <th>
                                        {{ __('clinic.lbl_frequency') }} <span class="text-danger">*</span>

                                        <i class="fas fa-info-circle" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Format: Morning-Afternoon-Night (e.g., 1-1-1)"
                                            style="cursor: pointer; font-size: 16px; margin-left: 5px;">
                                        </i>
                                    </th>



                                    <th>
                                        {{ __('clinic.lbl_duration') }} <span class="text-danger">*</span>
                                    </th>
                                    <th>
                                        {{ __('clinic.quantity') }} <span class="text-danger">*</span>
                                    </th>
                                    <th>
                                        {{ __('clinic.lbl_instruction') }}
                                    </th>
                                    <th>
                                        {{ __('clinic.lbl_action') }}
                                    </th>
                                </tr>
                            </thead>


                            <tbody id="medicine-rows">
                                <!-- Initial row will be added by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Add More Button -->
                    <div class="d-flex justify-content-between align-items-center my-3 gap-2 d-none"
                        id="add-medicine-section">
                        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2"
                            id="add-medicine-row">
                            <i class="ph ph-plus-circle"></i>
                            <span>{{ __('clinic.add_another_medicine') }}</span>
                        </button>
                        <small class="d-flex align-items-center gap-1 text-heading">
                            <i class="ph ph-info"></i>
                            {{ __('clinic.add_multiple_medicines') }}
                        </small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">{{ __('clinic.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ph ph-check me-1"></i>
                            {{ __('clinic.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Warning Modal -->
<div class="modal fade" id="stockWarningModal" tabindex="-1" aria-labelledby="stockWarningModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockWarningModalLabel">{{ __('Warning') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ __('Some medicines exceed available stock. Do you want to continue?') }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('No') }}</button>
                <button type="button" class="btn btn-primary" id="confirmStockWarning">{{ __('Yes') }}</button>
            </div>
        </div>
    </div>
</div>

@push('after-scripts')
    <script>
        document.dispatchEvent(new CustomEvent('prescriptionSaved', {
            detail: {
                encounterId: {{ $data['id'] }}
            }
        }));




        window.medicineOptionsLoaded = false;
        window.medicineOptionsFirstLoadDone = false;
        $(document).ready(function() {
            var baseUrl = '{{ url('/') }}';
            var rowCounter = 0;
            let medicineOptions = [];

            function fetchMedicineOptions(pharmaId = null) {
                return $.ajax({
                    url: "{{ route('ajax-list', ['type' => 'clinic-medicine']) }}",
                    method: 'GET',
                    data: {
                        pharma_id: pharmaId
                    },
                    dataType: 'json',
                    cache: true
                }).then(function(data) {
                    medicineOptions = data.results || [];
                });
            }
            $('#pharma').on('change', function() {
                const pharmaId = $(this).val();

                // Clear existing medicines
                $('#medicine-rows').empty();
                rowCounter = 0;

                // Reload medicines filtered by pharma
                fetchMedicineOptions(pharmaId).then(function() {
                    addMedicineRow();
                });
            });
            // Medicine row template with fallback support
            function getMedicineRowTemplate(index) {
                const pharmaActive = @json(checkPlugin('pharma') == 'active');
                let optionsHtml = '<option value="">{{ __('clinic.select_medicines') }}</option>';
                medicineOptions.forEach(function(opt) {
                    optionsHtml += `<option value="${opt.id}">${opt.text}</option>`;
                });

                return `
                      <tr class="medicine-row" data-index="${index}">
                <td>
                      ${pharmaActive ? `
                                                                                                                                    <select name="medicines[${index}][medicine_id]" class="form-control select2js medicine-select" required>
                                                                                                                                  ${optionsHtml}
                                                                                                                                    </select>
                                                                                                                                    ` : `
                                                                                                                                    <input type="text" name="medicines[${index}][name]" class="form-control medicine-name" placeholder="{{ __('clinic.medicines') }}" required>
                                                                                                                                    `}
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
                           placeholder="{{ __('clinic.example_frequency') }}" required>
                </td>
                <td>
                    <input type="number" name="medicines[${index}][duration]" class="form-control text-center"
                           placeholder="{{ __('clinic.days') }}" min="1" required>
                </td>
                 <td>
                    <input type="number" name="medicines[${index}][quantity]" class="form-control prescription-quantity text-center"
                           placeholder="{{ __('clinic.qty') }}" min="1" required>
                    <div class="quantity-stock-warning mt-1 text-danger" style="font-size: 0.85em; display:none;">
                        {{ __('clinic.quantity_exceeds_stock') ?? 'Quantity exceeds available stock' }} (Max: <span class="max-available-stock"><b>0</b></span>)
                    </div>
                </td>
                <td>
                    <textarea name="medicines[${index}][instruction]" class="form-control custom-input-box overflow-hidden" rows="1"
                              placeholder="{{ __('clinic.instructions') }}"></textarea>
                </td>
                <td class="text-center">
                   <button type="button" class="btn text-danger p-0 fs-5 remove-medicine-row"
        ${index === 0 ? 'style="display:none;"' : ''} tabindex="-1">

                        <i class="ph ph-trash"></i>
                    </button>
                </td>
              </tr>
               `;
            }

            // Check if form is in edit mode
            function isEditMode() {
                return $('#form-submit').attr('data-mode') === 'edit';
            }

            // Update UI elements based on mode
            function updateUIForMode() {
                $('#add-medicine-section').removeClass('d-none');
            }


            // Add initial row with error handling
            function addMedicineRow() {
                try {
                    const row = getMedicineRowTemplate(rowCounter);
                    $('#medicine-rows').append(row);

                    // Initialize Select2 for new row if pharma plugin is active
                    const pharmaActive = @json(checkPlugin('pharma') == 'active');
                    if (pharmaActive) {
                        const selectElement = $(`[name="medicines[${rowCounter}][medicine_id]"]`);
                        const pharmaId = $('#pharma').val();
                        selectElement.select2({
                            dropdownParent: $('#addprescription'),
                            placeholder: "{{ __('clinic.select_medicines') }}",
                            width: '100%',
                            ajax: {
                                url: "{{ route('ajax-list', ['type' => 'clinic-medicine']) }}",
                                dataType: 'json',
                                data: {
                                    pharma_id: pharmaId
                                },
                                delay: 250,
                                processResults: function(data) {
                                    return {
                                        results: data.results || []
                                    };
                                },
                                cache: true
                            }
                        });

                    }

                    rowCounter++;
                    updateRemoveButtons();
                } catch (error) {
                    console.error('Error adding medicine row:', error);
                    // Show user-friendly message
                    if (typeof window.errorSnackbar === 'function') {
                        window.errorSnackbar('Error adding medicine row. Please refresh the page.');
                    } else {
                        alert('Error adding medicine row. Please refresh the page.');
                    }
                }
            }

            // Update remove button visibility
            function updateRemoveButtons() {
                const rows = $('.medicine-row');
                if (rows.length <= 1) {
                    $('.remove-medicine-row').hide();
                } else {
                    $('.remove-medicine-row').show();
                    $('.medicine-row:first .remove-medicine-row').hide(); // Keep first row's remove button hidden
                }
            }

            // FIXED: Use off() to remove any existing event handlers before binding new ones
            // Add medicine row - unbind first to prevent double binding
            $('#add-medicine-row').off('click').on('click', function(e) {
                e.preventDefault(); // Prevent any default behavior
                e.stopPropagation(); // Stop event bubbling

                // ✅ Allow adding rows in both Add and Edit modes
                addMedicineRow();
            });


            // Remove medicine row - use event delegation to avoid multiple bindings
            $(document).off('click', '.remove-medicine-row').on('click', '.remove-medicine-row', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // In edit mode, don't allow removing the only row
                if (isEditMode() && $('.medicine-row').length <= 1) {
                    return;
                }

                $(this).closest('.medicine-row').remove();
                updateRemoveButtons();
            });

            // Stock checking with error handling - use event delegation
            $(document).off('change', '.medicine-select').on('change', '.medicine-select', function() {
                const $row = $(this).closest('.medicine-row');
                const medicineId = $(this).val();

                // Reset quantity warning when medicine changes
                $row.find('.quantity-stock-warning').hide();
                $row.find('.prescription-quantity').val('');

                if (!medicineId) {
                    $row.find('.medicine-stock').hide();
                    return;
                }

                <?php if (checkPlugin('pharma') == 'active'): ?>
                var medicineStockUrl =
                    "{{ route('backend.prescription.medicine_stock', ['id' => '__ID__']) }}";
                <?php else: ?>
                var medicineStockUrl = null;
                <?php endif; ?>

                if (medicineStockUrl) {
                    $.ajax({
                        url: medicineStockUrl.replace('__ID__', medicineId),
                        method: 'GET',
                        timeout: 5000,
                        success: function(response) {
                            if (response.stock !== undefined) {
                                const stock = parseInt(response.stock) || 0;
                                $row.find('.stock-quantity').text(stock);
                                $row.find('.available-stock').val(stock);
                                $row.find('.medicine-stock').show();
                                
                                // Remove max attribute to prevent HTML5 validation, use JavaScript instead
                                const $quantityInput = $row.find('.prescription-quantity');
                                $quantityInput.removeAttr('max');
                                
                                // Check if current quantity exceeds stock
                                const currentQty = parseInt($quantityInput.val()) || 0;
                                if (stock > 0) {
                                    if (currentQty > stock) {
                                        $quantityInput.val(stock);
                                        $row.find('.max-available-stock').text(stock);
                                        $row.find('.quantity-stock-warning').show();
                                    } else if (currentQty > 0 && currentQty <= stock) {
                                        $row.find('.quantity-stock-warning').hide();
                                    }
                                } else {
                                    if (currentQty > 0) {
                                        $quantityInput.val('');
                                        $row.find('.max-available-stock').text('0');
                                        $row.find('.quantity-stock-warning').show();
                                    } else {
                                        $row.find('.quantity-stock-warning').hide();
                                    }
                                }
                            } else {
                                $row.find('.medicine-stock').hide();
                                $row.find('.quantity-stock-warning').hide();
                                $row.find('.prescription-quantity').removeAttr('max');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.warn('Stock check failed:', error);
                            $row.find('.medicine-stock').hide();
                        }
                    });
                }
            });

            // Quantity validation - use event delegation
            // Quantity input check for stock
            $(document).off('input invalid', '.prescription-quantity').on('input invalid', '.prescription-quantity', function(e) {
                // Prevent HTML5 validation
                e.preventDefault();
                e.stopPropagation();
                
                const $row = $(this).closest('.medicine-row');
                let enteredQty = parseInt($(this).val()) || 0;
                let availableStock = parseInt($row.find('.available-stock').val()) || 0;
                const $input = $(this);
                
                // Remove HTML5 validation styling
                $input[0].setCustomValidity('');

                // Show warning below quantity input if it exceeds stock
                if (enteredQty > availableStock && availableStock > 0) {
                    // Auto-correct to max available stock
                    $input.val(availableStock);
                    $row.find('.max-available-stock').text(availableStock);
                    $row.find('.quantity-stock-warning').show();
                    $row.find('.stock-warning').show();
                } else if (enteredQty > 0 && enteredQty <= availableStock) {
                    $row.find('.quantity-stock-warning').hide();
                    $row.find('.stock-warning').hide();
                } else if (availableStock === 0 && enteredQty > 0) {
                    $input.val('');
                    $row.find('.max-available-stock').text('0');
                    $row.find('.quantity-stock-warning').show();
                } else {
                    $row.find('.quantity-stock-warning').hide();
                    $row.find('.stock-warning').hide();
                }
            });
            
            // Prevent HTML5 validation on blur
            $(document).off('blur', '.prescription-quantity').on('blur', '.prescription-quantity', function(e) {
                const $input = $(this);
                $input[0].setCustomValidity('');
            });


            // Auto-format and auto-complete frequency field
            $(document).on('input', '[name^="medicines"][name$="[frequency]"]', function() {
                let val = this.value.replace(/[^0-9]/g, ''); // allow all digits

                // Limit to max 3 digits
                if (val.length > 3) val = val.slice(0, 3);

                // Format as X-X-X
                let formatted = val.split('').join('-');
                this.value = formatted;
            });

            $(document).on('blur', '[name^="medicines"][name$="[frequency]"]', function() {
                let parts = this.value.split('-').map(p => p.trim()).filter(p => p !== '');

                // Fill with 0 if less than 3
                while (parts.length < 3) {
                    parts.push('0');
                }

                this.value = parts.slice(0, 3).join('-');

                // Trigger quantity recalculation
                const row = this.closest('.medicine-row');
                calculateQuantity(row);
            });


            function calculateQuantity(row) {
                const frequencyInput = row.querySelector('[name^="medicines"][name$="[frequency]"]');
                const daysInput = row.querySelector('[name^="medicines"][name$="[duration]"]');
                const quantityInput = row.querySelector('[name^="medicines"][name$="[quantity]"]');

                const frequency = frequencyInput.value.trim();
                const days = parseInt(daysInput.value, 10);

                if (!frequency || isNaN(days)) return;

                const parts = frequency.split('-').map(Number);
                if (parts.length !== 3) return;

                const freqSum = parts.reduce((sum, val) => sum + (isNaN(val) ? 0 : val), 0);
                const totalQty = freqSum * days;

                quantityInput.value = totalQty;
            }

            $(document).on('input',
                '[name^="medicines"][name$="[frequency]"], [name^="medicines"][name$="[duration]"]',
                function() {
                    const row = this.closest('.medicine-row');
                    calculateQuantity(row);
                });

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



            $('#form-submit').off('submit').on('submit', function(event) {
                event.preventDefault();

                const $form = $(this);
                let form = $form[0];
                let isValid = true;

                // Clear previous errors
                $('.medicine-error').remove();
                $('.medicine-select').each(function() {
                    $(this).next('.select2').find('.select2-selection').css('border', '');
                });


                // Manual validation for Select2 medicine selects
                $('.medicine-select').each(function() {
                    const $select = $(this);
                    const value = $select.val();
                    const $select2Container = $select.next('.select2');

                    // ✅ Skip if Select2 not initialized yet
                    if ($select2Container.length === 0 || !$select2Container.find(
                            '.select2-selection').length) {
                        console.warn(
                            'Select2 not fully initialized. Skipping validation for this field.'
                        );
                        isValid = false;

                        // Optional: block submit with user feedback
                        if (!$('.select2-init-warning').length) {
                            $('<div class="select2-init-warning text-danger mb-2" style="font-size: 0.85em;">Please wait for all medicine dropdowns to fully load.</div>')
                                .insertBefore($form.find('.modal-footer'));
                        }

                        return;
                    }

                    // Now validate
                    if (!value) {
                        isValid = false;
                        $select2Container.find('.select2-selection').css('border', '1px solid red');

                        const $stockWarning = $select.closest('td').find('.stock-warning');

                        if ($stockWarning.length && !$stockWarning.next('.medicine-error').length) {
                            $('<div class="medicine-error text-danger" style="font-size: 0.85em;">Please select a medicine.</div>')
                                .insertAfter($stockWarning);
                        }
                    }

                });


                // Stop if select2s are invalid
                if (!isValid) {
                    return;
                }

                // Validate stock quantities before submission
                let stockValidationFailed = false;
                $('.medicine-row').each(function() {
                    const $row = $(this);
                    const medicineId = $row.find('.medicine-select').val();
                    const enteredQty = parseInt($row.find('.prescription-quantity').val()) || 0;
                    const availableStock = parseInt($row.find('.available-stock').val()) || 0;

                    if (medicineId && availableStock > 0 && enteredQty > availableStock) {
                        stockValidationFailed = true;
                        // Auto-correct to max available stock
                        $row.find('.prescription-quantity').val(availableStock);
                        $row.find('.max-available-stock').text(availableStock);
                        $row.find('.quantity-stock-warning').show();
                    } else if (medicineId && availableStock === 0 && enteredQty > 0) {
                        stockValidationFailed = true;
                        $row.find('.prescription-quantity').val('');
                        $row.find('.max-available-stock').text('0');
                        $row.find('.quantity-stock-warning').show();
                    }
                });

                if (stockValidationFailed) {
                    // Re-validate after auto-correction
                    return;
                }

                // Clear HTML5 validation for quantity fields
                $('.prescription-quantity').each(function() {
                    this.setCustomValidity('');
                });

                // Native form validation (e.g. required inputs) - but skip HTML5 max validation
                if (form.checkValidity() === false) {
                    // Check if it's only the max validation failing
                    let hasOtherErrors = false;
                    $('.prescription-quantity').each(function() {
                        if (!this.validity.valid && this.validity.rangeOverflow) {
                            this.setCustomValidity('');
                        } else if (!this.validity.valid) {
                            hasOtherErrors = true;
                        }
                    });
                    
                    if (hasOtherErrors) {
                        event.stopPropagation();
                        form.classList.add('was-validated');
                        return;
                    }
                }

                // Check for stock warnings
                if ($('.stock-warning:visible').length > 0) {
                    $('#stockWarningModal').modal('show');
                    window._stockWarningContinue = () => {
                        $("#stockWarningModal").modal('hide');
                        $('#form-submit')[0].submitConfirmed = true;
                        $('#form-submit').trigger('submit');
                    };
                    return;
                }

                // Determine if this is edit or add mode
                const isEditModeSubmit = isEditMode();
                const prescriptionId = $('#prescription-id-input').val();
                const url = isEditModeSubmit ?
                    "{{ route('backend.encounter.update-prescription', ['id' => '__ID__']) }}".replace(
                        '__ID__', prescriptionId) :
                    "{{ route('backend.encounter.save-multiple-prescriptions') }}";

                // Get submit button and show loader
                const $submitBtn = $('.modal-footer .btn-primary');
                const originalBtnContent = $submitBtn.html();
                showButtonLoader($submitBtn, isEditModeSubmit);

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        // Reset button state
                        $('#save-btn').prop('disabled', false);
                        $('.btn-text').removeClass('d-none');
                        $('.btn-loading').addClass('d-none');

                        if (response.html) {
                            document.getElementById('prescription_table').innerHTML = response
                                .html;
                            $('#addprescription').modal('hide');
                            resetForm();

                            setTimeout(() => {
                                const $rows = $('#prescription_table tbody tr');

                                const realRows = $rows.filter(function() {
                                    return !$(this).find(
                                        '.no-prescription-message').length;
                                });

                                const rowCount = realRows.length;
                                console.log("Real prescription row count:", rowCount);

                                const encounterId = getEncounterIdFromURL();

                                if (rowCount > 0) {
                                    switchToEditButton(encounterId);
                                } else {
                                    switchToAddButton(encounterId);
                                }

                                const message = isEditModeSubmit ?
                                    `{{ __('pharma::messages.prescription_updated') }}` :
                                    `{{ __('pharma::messages.prescription_added') }}`;

                                window.successSnackbar(message);
                            }, 200);
                        } else {
                            window.errorSnackbar('Something went wrong! Please check.');
                        }
                    },
                    error: function(xhr) {
                        let message = 'An error occurred';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        window.errorSnackbar(message);
                    },
                    complete: function() {
                        hideButtonLoader($submitBtn, originalBtnContent);
                    }
                });

            });

            function showButtonLoader($button, isEditModeSubmit) {
                $button.prop('disabled', true);

                const loadingText = isEditModeSubmit ? 'Updating...' : 'Saving...';

                $button.html(`
              <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
              ${loadingText}
             `);
            }

            function hideButtonLoader($button, originalContent) {
                $button.prop('disabled', false).html(originalContent);
            }

            // Reset form
            function resetForm() {
                $('#form-submit')[0].reset();
                $('#form-submit')[0].classList.remove('was-validated');
                $('#form-submit').removeAttr('data-mode');
                $('#prescription-id-input').remove();

                // Reset button to original state
                $('.modal-footer .btn-primary').html(`
                    <i class="ph ph-check me-1"></i>
                    Save
                `).prop('disabled', false);

                // Reset modal title
                $('#exampleModalLabel').text('Add Prescription');

                // Clear medicine rows and reset counter
                $('#medicine-rows').empty();
                rowCounter = 0;

                // Add initial row
                // addMedicineRow();

                // Update UI for add mode
                updateUIForMode();
            }

            $('#addprescription').off('shown.bs.modal').on('shown.bs.modal', function() {
                const $form = $('#form-submit')[0];

                // ✅ Always initialize pharma select2
                $('#pharma').select2({
                    dropdownParent: $('#addprescription'),
                    placeholder: "{{ __('pharma::messages.select_pharma') }}"
                });

                if (!isEditMode()) {
                    $form.reset();
                    $form.classList.remove('was-validated');
                    $('#form-submit').removeAttr('data-mode');
                    $('#prescription-id-input').remove();
                    $('#medicine-rows').empty();
                    rowCounter = 0;

                    if (!window.medicineOptionsFirstLoadDone) {
                        $('#form-submit').hide();
                        $('#prescription-loader').show();
                    }
                    const pharmaId = $('#pharma').val();
                    fetchMedicineOptions(pharmaId).then(function() {
                        addMedicineRow();
                        updateUIForMode();

                        if (!window.medicineOptionsFirstLoadDone) {
                            $('#prescription-loader').hide();
                            $('#form-submit').show();
                            window.medicineOptionsFirstLoadDone = true;
                        }

                        setTimeout(() => {
                            $('.medicine-select').each(function() {
                                if ($(this).data('select2')) {
                                    $(this).select2('destroy');
                                }
                                $(this).select2({
                                    dropdownParent: $('#addprescription'),
                                    placeholder: "{{ __('clinic.select_medicines') }}"
                                });
                            });

                            // ✅ Auto-open first medicine select
                            setTimeout(() => {
                                const firstSelect = $('.medicine-select').first();
                                if (firstSelect.length && firstSelect.data(
                                        'select2')) {
                                    firstSelect.select2('open');
                                }
                            }, 100);
                        }, 10);
                    });
                } else {
                    // Edit mode
                    updateUIForMode();
                }
            });

            $('#addprescription').off('hidden.bs.modal').on('hidden.bs.modal', function() {
                const $form = $('#form-submit')[0];
                $form.reset();
                $form.classList.remove('was-validated');
                $('#form-submit').removeAttr('data-mode');
                $('#prescription-id-input').remove();
                $('#medicine-rows').empty();
                rowCounter = 0;
                updateUIForMode();
            });
        });
        $(document).off('click', '#confirmStockWarning').on('click', '#confirmStockWarning', function() {
            if (typeof window._stockWarningContinue === 'function') {
                window._stockWarningContinue();
            }
        });
    </script>
@endpush
