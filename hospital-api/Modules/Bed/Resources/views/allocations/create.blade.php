@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __($module_title) }}</h5>
            </div>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ html()->form('POST', route('backend.bed-allocation.store'))->attributes(['enctype' => 'multipart/form-data', 'id' => 'bed-allocation-form'])->open() }}

                <div class="row gy-4">
                    @if(multiVendor() == 1)
                    <!-- Multi-Vendor ON: Clinic Admin → Clinic → Patient Encounter → Bed Type → Assign Date → Discharge Date → Room -->
                    <!-- Row 1: Clinic Admin, Clinic, Patient Encounter -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.lbl_click_admin') . ' <span class="text-danger">*</span>', 'clinic_admin_id')->class('form-label fw-bold') }}
                        @if(isset($preSetClinicAdminId) && $preSetClinicAdminId && isset($clinicAdmins[$preSetClinicAdminId]))
                            {{ html()->hidden('clinic_admin_id', $preSetClinicAdminId) }}
                            {{ html()->select('clinic_admin_id_display', [$preSetClinicAdminId => $clinicAdmins[$preSetClinicAdminId]], $preSetClinicAdminId)->class('form-control select2')->required()->attributes(['disabled' => 'disabled', 'style' => 'background-color: #e9ecef;']) }}
                        @else
                            {{ html()->select('clinic_admin_id', $clinicAdmins, null)->class('form-control select2')->placeholder(__('messages.select_clinic_admin'))->required() }}
                        @endif
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.clinics') . ' <span class="text-danger">*</span>', 'clinic_id')->class('form-label fw-bold') }}
                        @if(isset($preSetClinicId) && $preSetClinicId && isset($clinics[$preSetClinicId]))
                            {{ html()->hidden('clinic_id', $preSetClinicId) }}
                            {{ html()->select('clinic_id_display', [$preSetClinicId => $clinics[$preSetClinicId]], $preSetClinicId)->class('form-control select2')->required()->attributes(['disabled' => 'disabled', 'style' => 'background-color: #e9ecef;']) }}
                        @else
                            {{ html()->select('clinic_id', $clinics, null)->class('form-control select2')->placeholder(__('messages.select_clinic'))->required() }}
                        @endif
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.patient_encounter') . ' <span class="text-danger">*</span>', 'encounter_id')->class('form-label fw-bold') }}
                        @if(isset($encounterId) && $encounterId && !empty($patientEncounters))
                            {{ html()->hidden('encounter_id', $encounterId) }}
                            {{ html()->select('encounter_id_display', $patientEncounters, $encounterId)->class('form-control select2')->required()->attributes(['disabled' => 'disabled', 'style' => 'background-color: #e9ecef;']) }}
                        @else
                            {{ html()->select('encounter_id', $patientEncounters, null)->class('form-control select2')->placeholder(__('messages.select_patient_encounter'))->required()->disabled() }}
                        @endif
                    </div>
                    
                    <!-- Row 2: Bed Type, Assign Date, Discharge Date -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.bed_type') . ' <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                        {{ html()->select('bed_type_id', $bedTypes, null)->class('form-control select2')->placeholder(__('messages.select'))->required() }}
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.assign_date') . ' <span class="text-danger">*</span>', 'assign_date')->class('form-label fw-bold') }}
                        <div class="input-group">
                            {{ html()->text('assign_date', now()->format('Y-m-d'))->class('form-control date_flatpicker')->id('assign_date')->required()->attributes(['placeholder' => __('messages.select_assign_date')]) }}
                            <span class="input-group-text" id="assign-date-icon" style="cursor: pointer;">
                                <i class="ph ph-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.discharge_date') . '<span class="text-danger">*</span>', 'discharge_date')->class('form-label fw-bold') }}
                        <div class="input-group">
                            {{ html()->text('discharge_date', \Carbon\Carbon::tomorrow()->format('Y-m-d'))->class('form-control date_flatpicker')->id('discharge_date')->attributes(['placeholder' => __('messages.select_discharge_date')]) }}
                            <span class="input-group-text" id="discharge-date-icon" style="cursor: pointer;">
                                <i class="ph ph-calendar"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Row 3: Bed -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.bed') . ' <span class="text-danger">*</span>', 'room_no')->class('form-label fw-bold') }}
                        {{ html()->select('room_no', $beds, null)->class('form-control select2')->placeholder(__('messages.select'))->required() }}
                    </div>
                    @else
                    <!-- Multi-Vendor OFF: Patient Encounter → Bed Type → Assign Date → Discharge Date → Room -->
                    <!-- Row 1: Patient Encounter, Bed Type, Assign Date -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.patient_encounter') . ' <span class="text-danger">*</span>', 'encounter_id')->class('form-label fw-bold') }}
                        @if(isset($encounterId) && $encounterId && !empty($patientEncounters))
                            {{ html()->hidden('encounter_id', $encounterId) }}
                            {{ html()->select('encounter_id_display', $patientEncounters, $encounterId)->class('form-control select2')->required()->attributes(['disabled' => 'disabled', 'style' => 'background-color: #e9ecef;']) }}
                        @else
                            {{ html()->select('encounter_id', $patientEncounters, null)->class('form-control select2')->placeholder(__('messages.select_patient_encounter'))->required()->disabled() }}
                        @endif
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.bed_type') . ' <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                        {{ html()->select('bed_type_id', $bedTypes, null)->class('form-control select2')->placeholder(__('messages.select'))->required() }}
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.assign_date') . ' <span class="text-danger">*</span>', 'assign_date')->class('form-label fw-bold') }}
                        <div class="input-group">
                            {{ html()->text('assign_date', now()->format('Y-m-d'))->class('form-control date_flatpicker')->id('assign_date')->required()->attributes(['placeholder' => __('messages.select_assign_date')]) }}
                            <span class="input-group-text" id="assign-date-icon" style="cursor: pointer;">
                                <i class="ph ph-calendar"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Row 2: Discharge Date, Bed -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.discharge_date') . '<span class="text-danger">*</span>', 'discharge_date')->class('form-label fw-bold') }}
                        <div class="input-group">
                            {{ html()->text('discharge_date', \Carbon\Carbon::tomorrow()->format('Y-m-d'))->class('form-control date_flatpicker')->id('discharge_date')->attributes(['placeholder' => __('messages.select_discharge_date')]) }}
                            <span class="input-group-text" id="discharge-date-icon" style="cursor: pointer;">
                                <i class="ph ph-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.bed') . ' <span class="text-danger">*</span>', 'room_no')->class('form-label fw-bold') }}
                        {{ html()->select('room_no', $beds, null)->class('form-control select2')->placeholder(__('messages.select'))->required() }}
                    </div>
                    @endif

                    <!-- Status field (common for both) -->

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.status'), 'status')->class('form-label fw-bold') }}
                        <div class="d-flex justify-content-between align-items-center form-control">
                            <span class="mb-0" id="status_text">{{ old('status', isset($allocation) ? $allocation->status : 1) ? __('messages.active') : __('messages.inactive') }}</span>
                            <div class="form-check form-switch m-0">
                                {{ html()->hidden('status', 0) }}
                                {{ html()->checkbox('status', (bool)old('status', isset($allocation) ? $allocation->status : 1), 1)
                                    ->class('form-check-input')
                                    ->id('status')
                                    ->attribute('value', '1') }}
                            </div>
                        </div>
                    </div>


                    <!-- Row 3: Description -->
                    <div class="col-md-12">
                        {{ html()->label(__('messages.description'), 'description')->class('form-label fw-bold') }}
                        <div class="position-relative">
                            {{ html()->textarea('description')->class('form-control')->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description', 'style' => 'padding-bottom: 30px;'])->placeholder(__('messages.type_here')) }}
                            <small class="text-muted position-absolute" style="bottom: 8px; right: 12px; font-size: 0.875rem; pointer-events: none;">
                                <span id="char-count">0</span>/250
                            </small>
                        </div>
                    </div>

                    <!-- IPD/OPD Info Message -->
                    <div class="col-md-12 mt-5 mb-1">
                        <div>
                            <h5>{{ __('messages.ipd_patient') }}</h5>
                        </div>
                    </div>

                    <!-- Row 4: Weight, Height, Blood Pressure -->
                    <div class="col-md-4">
                        {{ html()->label(__('messages.weight_kg'), 'weight')->class('form-label fw-bold') }}
                        {{ html()->text('weight')->class('form-control')->placeholder(__('messages.eg') . ' "60"') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.height_cm'), 'height')->class('form-label fw-bold') }}
                        {{ html()->text('height')->class('form-control')->placeholder(__('messages.eg') . ' "170cm"') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.blood_pressure'), 'blood_pressure')->class('form-label fw-bold') }}
                        {{ html()->text('blood_pressure')->class('form-control')->placeholder(__('messages.eg') . ' "120/80"') }}
                    </div>

                    <!-- Row 5: Heart Rate, Blood Group, Temperature -->
                    <div class="col-md-4">
                        {{ html()->label(__('messages.heart_rate'), 'heart_rate')->class('form-label fw-bold') }}
                        {{ html()->text('heart_rate')->class('form-control')->placeholder(__('messages.eg') . ' "78 bpm"') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.blood_group'), 'blood_group')->class('form-label fw-bold') }}
                        {{ html()->select('blood_group', [
                            '' => __('messages.select_blood_group'),
                            'A+' => 'A+',
                            'A-' => 'A-',
                            'B+' => 'B+',
                            'B-' => 'B-',
                            'AB+' => 'AB+',
                            'AB-' => 'AB-',
                            'O+' => 'O+',
                            'O-' => 'O-'
                        ])->class('form-control select2') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.temperature_c'), 'temperature')->class('form-label fw-bold') }}
                        {{ html()->text('temperature')->class('form-control')->placeholder(__('messages.eg') . ' "37.4 C"') }}
                    </div>

                    <!-- Row 6: Symptoms, Notes -->
                    <div class="col-md-6">
                        {{ html()->label(__('messages.symptoms'), 'symptoms')->class('form-label fw-bold') }}
                        {{ html()->textarea('symptoms')->class('form-control')->attributes(['rows' => '2'])->placeholder(__('messages.eg') . ' "Fever,Cough"') }}
                    </div>

                    <div class="col-md-6">
                        {{ html()->label(__('messages.notes'), 'notes')->class('form-label fw-bold') }}
                        {{ html()->textarea('notes')->class('form-control')->attributes(['rows' => '2'])->placeholder(__('messages.type_here')) }}
                    </div>
                </div>

                <div class="form-footer mt-4">
                    <div class="d-flex gap-3 flex-wrap justify-content-end">
                        <a href="{{ route('backend.bed-allocation.index') }}" class="btn btn-light">
                            {{ __('messages.cancel') }}
                        </a>
                        {{ html()->submit(trans('messages.save'))->class('btn btn-secondary') }}
                    </div>
                </div>

                {{ html()->form()->close() }}
            </div>
        </div>
    </div>
@endsection

@push('after-styles')
<style>
    /* .select2-container .select2-selection.is-invalid {
        border-color: transparent !important;
    }
    .select2-container--default .select2-selection.is-invalid {
        border-color: transparent !important;
    }
    .invalid-feedback {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    .is-invalid {
        border-color: transparent !important;
        padding-right: inherit;
        background-image: none !important;
        background-repeat: no-repeat;
        background-position: inherit;
        background-size: inherit;
    } */
</style>
@endpush

@push('after-scripts')
<script>
    $(document).ready(function () {
        // Initialize Select2
        $('.select2').select2({
            width: '100%'
        });

        // Custom validation methods (must be defined before validate())
        $.validator.addMethod("notPastDate", function(value, element) {
            if (!value) return true; // Skip validation if empty
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            return Date.parse(value) >= today;
        }, "Date cannot be in the past");

        $.validator.addMethod("greaterThan", function(value, element, param) {
            if (!value) return true; // Skip validation if empty
            var startDate = $(param).val();
            if (!startDate) return true; // Skip if start date is empty
            return Date.parse(value) > Date.parse(startDate);
        }, "Discharge date must be after assign date");

        $.validator.addMethod("notSameDay", function(value, element, param) {
            if (!value) return true; // Skip validation if empty
            var startDate = $(param).val();
            if (!startDate) return true; // Skip if start date is empty
            return Date.parse(value) !== Date.parse(startDate);
        }, "Discharge date cannot be the same as assign date");

        $.validator.addMethod("notToday", function(value, element) {
            if (!value) return true; // Skip validation if empty
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var selectedDate = new Date(value);
            selectedDate.setHours(0, 0, 0, 0);
            return selectedDate.getTime() > today.getTime();
        }, "Discharge date cannot be today");

        $.validator.addMethod("select2Required", function(value, element) {
            return value !== null && value !== '' && value !== undefined;
        }, "This field is required");

        // Form validation - only on submit
        $("#bed-allocation-form").validate({
            onkeyup: false, // Don't validate on keyup
            onfocusout: false, // Don't validate on focusout
            onclick: false, // Don't validate on click
            rules: {
                @if(multiVendor() == 1)
                clinic_admin_id: {
                    select2Required: true
                },
                clinic_id: {
                    select2Required: true
                },
                @endif
                encounter_id: {
                    select2Required: true
                },
                bed_type_id: {
                    select2Required: true
                },
                room_no: {
                    select2Required: true
                },
                assign_date: {
                    required: true,
                    date: true,
                    notPastDate: true
                },
                discharge_date: {
                    required: true,
                    date: true,
                    notPastDate: true,
                    notToday: true,
                    greaterThan: "#assign_date",
                    notSameDay: "#assign_date"
                },
                weight: {
                    number: true,
                    min: 0,
                    max: 500
                },
                height: {
                    number: true,
                    min: 0,
                    max: 300
                },
                heart_rate: {
                    number: true,
                    min: 0,
                    max: 250
                },
                temperature: {
                    number: true,
                    min: 35,
                    max: 50
                }
            },
            messages: {
                @if(multiVendor() == 1)
                clinic_admin_id: {
                    required: "Please select a clinic admin"
                },
                clinic_id: {
                    required: "Please select a clinic"
                },
                @endif
                encounter_id: {
                    required: "Please select a patient encounter"
                },
                bed_type_id: {
                    required: "Please select a bed type"
                },
                room_no: {
                    required: "{{ __('messages.select_bed') }}"
                },
                assign_date: {
                    required: "Please select assign date",
                    date: "Please enter a valid date",
                    notPastDate: "Assign date cannot be in the past"
                },
                discharge_date: {
                    required: "Please select discharge date",
                    date: "Please enter a valid date",
                    notPastDate: "Discharge date cannot be in the past",
                    notToday: "Discharge date cannot be today",
                    greaterThan: "Discharge date must be after assign date",
                    notSameDay: "Discharge date cannot be the same as assign date"
                },
                weight: {
                    number: "Please enter a valid number",
                    min: "Weight cannot be negative",
                    max: "Weight seems too high"
                },
                height: {
                    number: "Please enter a valid number",
                    min: "Height cannot be negative",
                    max: "Height seems too high"
                },
                heart_rate: {
                    number: "Please enter a valid number",
                    min: "Heart rate cannot be negative",
                    max: "Heart rate seems too high"
                },
                temperature: {
                    number: "Please enter a valid number",
                    min: "Temperature seems too low",
                    max: "Temperature seems too high"
                }
            },
            errorElement: 'span',
            errorPlacement: function(error, element) {
                error.addClass('invalid-feedback d-block');
                // For Select2, place error after the select2 container
                if (element.hasClass('select2')) {
                    error.insertAfter(element.next('.select2-container'));
                } else {
                    element.closest('.col-lg-4 col-md-6, .col-md-4, .col-md-6, .col-md-12').append(error);
                }
            },
            highlight: function(element, errorClass, validClass) {
                // Don't add is-invalid class to avoid border and icon
                // Only show error message
            },
            unhighlight: function(element, errorClass, validClass) {
                // Don't need to remove anything since we're not adding classes
            },
            invalidHandler: function(event, validator) {
                // Scroll to first error
                var firstError = $(validator.errorList[0].element);
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            },
            submitHandler: function(form) {
                // Show loading state
                const submitBtn = $(form).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

                // Submit the form
                form.submit();
            }
        });

        // Update status text when toggle changes
        $('#status').on('change', function() {
            const statusText = $(this).is(':checked') ? 'Active' : 'Inactive';
            $('#status_text').text(statusText);
        });

        // Character count for description
        $('#description').on('input', function() {
            var length = $(this).val().length;
            $('#char-count').text(length);
        });

        // Remove error messages when user enters data
        // For Select2 fields
        $('.select2').on('change', function() {
            if ($(this).val()) {
                $(this).valid(); // Validate and remove error if valid
            }
        });
        
        // For date fields
        $('#assign_date, #discharge_date').on('change', function() {
            if ($(this).val()) {
                $(this).valid(); // Validate and remove error if valid
            }
        });
        
        // For text input fields
        $('#weight, #height, #heart_rate, #temperature').on('input', function() {
            if ($(this).val()) {
                $(this).valid(); // Validate and remove error if valid
            }
        });

        // Initialize Flatpickr for date fields with calendar icon support
        let assignDatePicker, dischargeDatePicker;
        
        // Calculate tomorrow's date
        function getTomorrowDate() {
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            return tomorrow.toISOString().split('T')[0];
        }
        
        // Calculate date after assign date
        function getDateAfterAssignDate(assignDateStr) {
            if (!assignDateStr) return getTomorrowDate();
            var dateAfter = new Date(assignDateStr);
            dateAfter.setDate(dateAfter.getDate() + 1);
            return dateAfter.toISOString().split('T')[0];
        }
        
        // Initialize discharge date picker first (needed for assign date onChange)
        const dischargeDateInput = document.getElementById('discharge_date');
        const dischargeDateIcon = document.getElementById('discharge-date-icon');
        if (dischargeDateInput && typeof flatpickr !== 'undefined') {
            // Get initial assign date value
            const initialAssignDate = $('#assign_date').val();
            const tomorrowDate = getTomorrowDate();
            const defaultDischargeDate = initialAssignDate ? getDateAfterAssignDate(initialAssignDate) : tomorrowDate;
            
            dischargeDatePicker = flatpickr(dischargeDateInput, {
                dateFormat: 'Y-m-d',
                minDate: tomorrowDate, // Minimum is tomorrow (cannot select today)
                defaultDate: defaultDischargeDate, // Default to tomorrow or day after assign date
                allowInput: true,
                clickOpens: false
            });
            
            // Set the default value in the input field
            if (defaultDischargeDate) {
                dischargeDateInput.value = defaultDischargeDate;
            }
            
            // Open picker when input or icon is clicked
            dischargeDateInput.addEventListener('click', function() {
                dischargeDatePicker.open();
            });
            if (dischargeDateIcon) {
                dischargeDateIcon.addEventListener('click', function() {
                    dischargeDatePicker.open();
                });
            }
        }
        
        // Initialize assign date picker
        const assignDateInput = document.getElementById('assign_date');
        const assignDateIcon = document.getElementById('assign-date-icon');
        if (assignDateInput && typeof flatpickr !== 'undefined') {
            assignDatePicker = flatpickr(assignDateInput, {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                defaultDate: assignDateInput.value || 'today',
                allowInput: true,
                clickOpens: false,
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr && dischargeDatePicker) {
                        // Set minimum date to the day after assign date (cannot be same day or today)
                        var minDate = new Date(dateStr);
                        minDate.setDate(minDate.getDate() + 1);
                        var minDateStr = minDate.toISOString().split('T')[0];
                        dischargeDatePicker.set('minDate', minDateStr);
                        
                        // Set default date to tomorrow from assign date
                        dischargeDatePicker.setDate(minDateStr, false);
                        dischargeDateInput.value = minDateStr;
                        
                        // Trigger validation on discharge date if it has a value
                        if ($('#discharge_date').val()) {
                            $('#discharge_date').valid();
                        }
                    }
                }
            });
            
            // Open picker when input or icon is clicked
            assignDateInput.addEventListener('click', function() {
                assignDatePicker.open();
            });
            if (assignDateIcon) {
                assignDateIcon.addEventListener('click', function() {
                    assignDatePicker.open();
                });
            }
        }

        // Room selection handling
        // Check if encounter_id, clinic_admin_id, and clinic_id are already set (from encounter detail page)
        const $patientEncounterSelect = $('#encounter_id');
        const encounterIdPreSet = $patientEncounterSelect.length > 0 && $patientEncounterSelect.val();
        @if(multiVendor() == 1)
        const clinicAdminPreSet = @if(isset($preSetClinicAdminId) && $preSetClinicAdminId) true @else false @endif;
        const clinicPreSet = @if(isset($preSetClinicId) && $preSetClinicId) true @else false @endif;
        // Use display version if pre-set, otherwise use regular select
        const $clinicAdminSelect = clinicAdminPreSet ? $('#clinic_admin_id_display') : $('#clinic_admin_id');
        const $clinicSelect = clinicPreSet ? $('#clinic_id_display') : $('#clinic_id');
        @endif
        const $patientEncounterDisplay = $('#encounter_id_display');
        const $bedTypeSelect = $('#bed_type_id');
        const $roomSelect = $('#room_no');
        const $assignDateInput = $('#assign_date');

        @if(multiVendor() == 1)
        function updateClinicSelect(message, disabled = true) {
            // Don't update if clinic is pre-set
            if (clinicPreSet) {
                return;
            }
            $clinicSelect.empty()
                .append(new Option(message, '', true, true))
                .prop('disabled', disabled)
                .trigger('change');
        }
        @endif

        function updatePatientEncounterSelect(message, disabled = true) {
            $patientEncounterSelect.empty()
                .append(new Option(message, '', true, true))
                .prop('disabled', disabled)
                .trigger('change');
        }

        function updateRoomSelect(message, disabled = true) {
            $roomSelect.empty()
                .append(new Option(message, '', true, true))
                .prop('disabled', disabled)
                .trigger('change');
        }

        @if(multiVendor() == 1)
        function fetchClinicsByAdmin() {
            const adminId = $clinicAdminSelect.val();
            if (!adminId) {
                updateClinicSelect("{{ __('messages.select_clinic_admin_first') }}", true);
                updatePatientEncounterSelect("{{ __('messages.select_clinic_first') }}", true);
                return;
            }
            updateClinicSelect("{{ __('messages.loading') }}", true);
            $.ajax({
                url: `{{ route('backend.bed-allocation.get_clinics_by_admin', '') }}/${adminId}`,
                method: 'GET',
                success: function(response) {
                    if (!response.status || !response.clinics || response.clinics.length === 0) {
                        updateClinicSelect(response.message || 'No clinics available', true);
                        return;
                    }
                    $clinicSelect.empty().append(new Option('Select Clinic', '', true, true));
                    response.clinics.forEach(function(clinic) {
                        $clinicSelect.append(new Option(clinic.name, clinic.id));
                    });
                    $clinicSelect.prop('disabled', false).trigger('change');
                },
                error: function(xhr) {
                    let errorMessage = 'Error loading clinics';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    updateClinicSelect(errorMessage, true);
                }
            });
        }
        @endif

        function fetchPatientEncountersByClinic() {
            @if(multiVendor() == 1)
            const clinicId = $clinicSelect.val();
            if (!clinicId) {
                updatePatientEncounterSelect("{{ __('messages.select_clinic_first') }}", true);
                return;
            }
            @else
            // For non-multivendor mode, get all patient encounters
            const clinicId = null;
            @endif
            
            updatePatientEncounterSelect("{{ __('messages.loading_patient_encounters') }}", true);
            $.ajax({
                url: `{{ route('backend.bed-allocation.get_patient_encounters_by_clinic', '') }}/${clinicId || 'all'}`,
                method: 'GET',
                success: function(response) {
                    console.log('Patient encounters response:', response);
                    console.log('Response status:', response.status);
                    console.log('Response encounters:', response.encounters);
                    console.log('Encounters length:', response.encounters ? response.encounters.length : 'undefined');
                    
                    if (!response.status || !response.encounters || response.encounters.length === 0) {
                        console.log('No encounters found or invalid response');
                        updatePatientEncounterSelect(response.message || 'No patient encounters available', true);
                        return;
                    }
                    
                    console.log('Populating dropdown with encounters...');
                    $patientEncounterSelect.empty().append(new Option('Select Patient Encounter', '', true, true));
                    response.encounters.forEach(function(encounter) {
                        console.log('Adding encounter:', encounter);
                        $patientEncounterSelect.append(new Option(encounter.text, encounter.id));
                    });
                    $patientEncounterSelect.prop('disabled', false).trigger('change');
                    console.log('Dropdown populated successfully');
                },
                error: function(xhr) {
                    console.log('AJAX error:', xhr);
                    let errorMessage = 'Error loading patient encounters';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    updatePatientEncounterSelect(errorMessage, true);
                }
            });
        }

        function fetchAvailableRooms() {
            const bedTypeId = $bedTypeSelect.val();
            const assignDate = $assignDateInput.val();
            @if(multiVendor() == 1)
            // Get clinic ID from hidden input if pre-set, otherwise from select
            const clinicId = clinicPreSet ? $('input[name="clinic_id"]').val() : $clinicSelect.val();
            if (!clinicId) {
                updateRoomSelect("{{ __('messages.select_clinic_first') }}", true);
                return;
            }
            @else
            // For non-multivendor mode, get all available rooms
            const clinicId = null;
            @endif
            if (!bedTypeId) {
                updateRoomSelect("{{ __('messages.select_bed_type_first') }}", true);
                return;
            }
            if (!assignDate) {
                updateRoomSelect("{{ __('messages.select_assign_date_first') }}", true);
                return;
            }
            updateRoomSelect("{{ __('messages.loading_rooms') }}", true);
            $.ajax({
                url: `{{ route('backend.bed-allocation.get_rooms', '') }}/${bedTypeId}`,
                method: 'GET',
                data: { assign_date: assignDate, clinic_id: clinicId },
                success: function(response) {
                    if (!response.status || !response.rooms || response.rooms.length === 0) {
                        updateRoomSelect(response.message || "{{ __('messages.no_rooms_available') }}", true);
                        return;
                    }
                    $roomSelect.empty().append(new Option("{{ __('messages.select_bed') }}", '', true, true));
                    response.rooms.forEach(function(room) {
                        $roomSelect.append(new Option(room.bed, room.id));
                    });
                    $roomSelect.prop('disabled', false).trigger('change');
                },
                error: function(xhr) {
                    let errorMessage = "{{ __('messages.error_loading_rooms') }}";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    updateRoomSelect(errorMessage, true);
                }
            });
        }

        @if(multiVendor() == 1)
        // Only attach change handlers if clinic admin and clinic are not pre-set
        if (!clinicAdminPreSet) {
            $clinicAdminSelect.on('change', function() {
                $clinicSelect.val('').trigger('change');
                $patientEncounterSelect.val('').trigger('change');
                $bedTypeSelect.val('').trigger('change');
                updateRoomSelect("{{ __('messages.select_bed_type_first') }}", true);
                fetchClinicsByAdmin();
            });
        }

        if (!clinicPreSet) {
            $clinicSelect.on('change', function() {
                $patientEncounterSelect.val('').trigger('change');
                $bedTypeSelect.val('').trigger('change');
                updateRoomSelect("{{ __('messages.select_bed_type_first') }}", true);
                fetchPatientEncountersByClinic();
            });
        }
        @else
        // For non-multivendor mode, load patient encounters on page load (only if not pre-set)
        $(document).ready(function() {
            if (!encounterIdPreSet) {
                fetchPatientEncountersByClinic();
            } else {
                // Encounter is pre-set, update clinic admin from encounter
                updateClinicAdminFromEncounter();
            }
        });
        @endif

        $bedTypeSelect.on('change', fetchAvailableRooms);
        $assignDateInput.on('change', fetchAvailableRooms);

        // Initial load
        @if(multiVendor() == 1)
        if (!clinicAdminPreSet) {
            updateClinicSelect("{{ __('messages.select_clinic_admin_first') }}", true);
        }
        @endif
        if (!encounterIdPreSet) {
            updatePatientEncounterSelect("{{ __('messages.loading_patient_encounters') }}", true);
        }
        updateRoomSelect("{{ __('messages.select_bed_type_first') }}", true);

        @if(multiVendor() == 0)
        // Update clinic admin when patient encounter is selected
        if ($patientEncounterSelect.length > 0) {
            $patientEncounterSelect.on('change', function() {
                updateClinicAdminFromEncounter();
            });
        }
        
        function updateClinicAdminFromEncounter() {
            // Get encounter ID from hidden field if pre-set, otherwise from select
            const encounterId = encounterIdPreSet ? $patientEncounterSelect.val() : ($patientEncounterSelect.length > 0 ? $patientEncounterSelect.val() : null);
            if (!encounterId) {
                // Clear clinic admin fields if no encounter selected
                $('#clinic_admin_name').val('--');
                $('#clinic_admin_id').val('');
                return;
            }
            
            $.ajax({
                url: `{{ route('backend.bed-allocation.get_encounter_details', '') }}/${encounterId}`,
                method: 'GET',
                success: function(response) {
                    console.log('Encounter details response:', response);
                    if (response.success && response.data) {
                        const data = response.data;
                        $('#clinic_admin_name').val(data.clinic_admin_name || '--');
                        $('#clinic_admin_id').val(data.clinic_admin_id || '');
                        console.log('Updated clinic admin:', data.clinic_admin_name);
                    } else {
                        $('#clinic_admin_name').val('--');
                        $('#clinic_admin_id').val('');
                        console.log('No clinic admin found for this encounter');
                    }
                },
                error: function(xhr) {
                    console.log('Error fetching encounter details:', xhr);
                    $('#clinic_admin_name').val('--');
                    $('#clinic_admin_id').val('');
                }
            });
        }
        @endif
    });
</script>
@endpush