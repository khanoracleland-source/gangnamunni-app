<div class="table-responsive rounded mb-0 mx-3">
    <table class="table table-lg m-0" id="prescription_table">
        <thead>

            <tr class="text-white">
                <th scope="col">{{ __('appointment.name') }}</th>
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
                    <td>
                        <p class="m-0">
                            {{ $prescription['name'] }}
                        </p>
                        <p class="m-0">
                            {{ $prescription['instruction'] }}
                        </p>
                    </td>
                    <td>
                        {{ $prescription['frequency'] }}
                    </td>
                    <td>
                        {{ $prescription['duration'] }}
                    </td>
                    @if ($data['status'] == 1)
                        <td class="action">
                            <div class="d-flex align-items-center gap-3">
                                {{-- <button type="button" class="btn text-primary p-0 fs-5 me-2" data-bs-toggle="modal"
                                    data-bs-target="#exampleModal" onclick="editPrescription({{ $prescription['id'] }})"
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
                    <td colspan="5">
                        <div class="my-1 text-danger text-center">{{ __('appointment.no_prescription_found') }}
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- @if (count($data['prescriptions']) > 0)
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
    @endif --}}
</div>

@push('after-scripts')
    <script>
        var baseUrl = '{{ url('/') }}';
        var rowCounter = 0;

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

            // Update text (replace entire HTML inside the <div>)
            const editText = $('#prescription_btn div').text().trim().replace(/add/i, 'edit');
            btn.find('div').html(`<i class="ph ph-pencil-simple"></i> ${editText}`);
        }

        function switchToAddButton(encounterId) {
            const btn = $('#prescription_btn');

            btn.off('click');
            btn.removeAttr('onclick');
            btn.removeAttr('href');
            btn.removeAttr('data-encounter-id');

            btn.attr('data-bs-toggle', 'modal');
         btn.attr('data-bs-target', '#addprescriptiontemplae');

            // Update icon
            btn.find('i').removeClass('ph-pencil-simple').addClass('ph-plus');

            // Update text (replace entire HTML inside the <div>)
            const addText = $('#prescription_btn div').text().trim().replace(/edit/i, 'add');
            btn.find('div').html(`<i class="ph ph-plus"></i> ${addText}`);
        }

        function destroyData(id, message) {

            confirmDeleteSwal({
                message
            }).then((result) => {

                if (!result.isConfirmed) return;

                $.ajax({
                    url: baseUrl + '/app/encounter-template/delete-prescription/' + id,
                    type: 'GET',
                    success: (response) => {
                        if (response.html) {
                            // 🔁 Update prescription table content
                            $('#prescription_table').html(response.html);

                            // ✅ Only check <tbody> rows for actual prescription data
                            const hasRows = $('#prescription_table tbody tr').length > 1;
console.log('Has rows after deletion:', hasRows);

                            if (!hasRows) {
                                // 🔍 Try to get encounterId from data attribute
                                let encounterId = $('#prescription_btn').data('encounter-id');

                                // 🔁 Fallback: try to fetch it from a hidden field if needed
                                if (!encounterId) {
                                    encounterId = $('#encounter_id')
                                .val(); // you must have a fallback input if required
                                }

                                console.log('No rows found. Switching to Add button for encounterId:',
                                    encounterId);
                                switchToAddButton(encounterId);
                            } else {
                                console.log('Rows exist after deletion, keeping Edit button.');
                            }

                            // ✅ Show success alert
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
                            // ❌ No HTML in response or something went wrong
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

        function addMedicineRowWithData(prescriptionsArray = []) {
            prescriptionsArray.forEach((prescriptionData, idx) => {
                if (!prescriptionData) return;

                const index = idx;
                const row = editMedicineRowTemplate(index);
                $('#medicine-rows').append(row);

                const $row = $(`[data-index="${index}"]`);

                $row.find(`input[name="medicines[${index}][quantity]"]`).val(prescriptionData.quantity || '');
                $row.find(`input[name="medicines[${index}][frequency]"]`).val(prescriptionData.frequency || '');
                $row.find(`input[name="medicines[${index}][duration]"]`).val(prescriptionData.duration || '');
                $row.find(`textarea[name="medicines[${index}][instruction]"]`).val(prescriptionData.instruction ||
                    '');

                @if (checkPlugin('pharma') == 'active')
                    const medicineSelect = $row.find(`[name="medicines[${index}][medicine_id]"]`);

                    if (prescriptionData.medicine_id && prescriptionData.name) {
                        const option = new Option(prescriptionData.name, prescriptionData.medicine_id, true, true);
                        medicineSelect.append(option).trigger('change.select2');
                    }

                    if (medicineSelect.data('select2')) {
                        medicineSelect.select2('destroy');
                    }

                    setTimeout(() => {
                        initMedicineSelect(medicineSelect);
                    }, 100);
                @else
                    $row.find(`input[name="medicines[${index}][name]"]`).val(prescriptionData.name || '');
                @endif
            });

            updateRemoveButtons();
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

        function initMedicineSelect($select) {
            console.log("Initializing Select2 for:", $select);
            $select.select2({
                placeholder: "{{ __('clinic.select_medicines') }}",
                width: '100%',
               dropdownParent: $('#addprescriptiontemplae'),
                ajax: {
                    url: '{{ route('ajax-list', ['type' => 'medicine-template']) }}',
                    dataType: 'json',
                    delay: 200,
                    cache: true,
                    data: function(params) {
                        return {
                            q: params.term,
                            template_id: $('#template_id').val()
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: (data.results || []).map(item => ({
                                id: item.id,
                                text: item.text
                            }))
                        };
                    }
                }

            });
        }



        function editPrescription(id) {
            $.ajax({
                url: baseUrl + '/app/encounter-template/edit-prescription/' + id,
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: (response) => {
                    if (response.status && response.prescriptions.length > 0) {
                        const $form = $('#form-submit');

                        $form.attr('data-mode', 'edit');

                        $('#medicine-rows').empty();
                        $('#prescription-id-input').remove();

                        const hiddenInput =
                            `<input type="hidden" name="id" id="prescription-id-input" value="${id}">`;
                        $form.append(hiddenInput);

                        if (response.template_id) {
                            $('#template_id').val(response.template_id);
                        }
                        addMedicineRowWithData(response.prescriptions);

                        $('#addprescriptiontemplae').modal('show');
                    } else {
                        alert(response.message || 'No prescriptions found.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error(error);
                    alert('An unexpected error occurred.');
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
                url: baseUrl + '/app/encounter-template/send-prescription?id=' + id,
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
    </script>
@endpush
