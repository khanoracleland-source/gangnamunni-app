@extends('backend.layouts.app')
@section('title')
    {{ __('messages.edit_bed_allocation') }}
@endsection
@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('messages.edit_bed_allocation') }}</h5>
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

                {{ html()->modelForm($allocation, 'PUT', route('backend.bed-allocation.update', $allocation->id))->attributes(['enctype' => 'multipart/form-data', 'id' => 'bed-allocation-form'])->open() }}
                @csrf
                @method('PUT')
                    <input type="hidden" name="id" value="{{ $allocation->id }}">
                <div class="row gy-4">
                    <!-- Row 1: Clinic Admin, Clinic, Patient Encounter, Bed Type -->
                    @if(multiVendor() == 1)
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.lbl_click_admin') . ' <span class="text-danger">*</span>', 'clinic_admin_id')->class('form-label fw-bold') }}
                        {{ html()->select('clinic_admin_id', $clinicAdmins, $allocation->clinic_admin_id)->class('form-control select2')->placeholder(__('messages.select_clinic_admin'))->required() }}
                    </div>

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.clinics') . ' <span class="text-danger">*</span>', 'clinic_id')->class('form-label fw-bold') }}
                        {{ html()->select('clinic_id', $clinics, $allocation->clinic_id)->class('form-control select2')->placeholder(__('messages.select_clinic'))->required() }}
                    </div>
                    @else
                    <!-- Hidden fields for non-multivendor mode -->
                    {{ html()->hidden('clinic_admin_id', $allocation->clinic_admin_id) }}
                    {{ html()->hidden('clinic_id', $allocation->clinic_id) }}
                    @endif

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.patient_encounter') . ' <span class="text-danger">*</span>', 'encounter_id')->class('form-label fw-bold') }}
                        {{ html()->hidden('encounter_id', $allocation->encounter_id) }}
                        {{ html()->select('encounter_id_display', $patientEncounters, $allocation->encounter_id)->class('form-control select2')->required()->attributes(['disabled' => 'disabled', 'style' => 'background-color: #e9ecef;']) }}
                    </div>

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.bed_type') . ' <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                        {{ html()->select('bed_type_id', $bedTypes, optional($allocation->bedMaster)->bed_type_id)->class('form-control select2')->placeholder(__('messages.select_bed_type'))->required() }}
                    </div>

                    <!-- Row 2: Room, Assign Date, Discharge Date, Status -->
                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.room') . ' <span class="text-danger">*</span>', 'room_no')->class('form-label fw-bold') }}
                        {{ html()->select('room_no', $beds, $allocation->bed_master_id)->class('form-control select2')->placeholder(__('messages.select_room'))->required() }}
                    </div>

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.assign_date') . ' <span class="text-danger">*</span>', 'assign_date')->class('form-label fw-bold') }}
                        {{ html()->date('assign_date', $allocation->assign_date)->class('form-control')->required() }}
                    </div>

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.discharge_date'), 'discharge_date')->class('form-label fw-bold') }}
                        {{ html()->date('discharge_date', $allocation->discharge_date)->class('form-control') }}
                    </div>

                    <div class="col-lg-4 col-md-6">
                        {{ html()->label(__('messages.status'), 'status')->class('form-label fw-bold') }}
                        <div class="input-group mt-0.5">
                            {{ html()->text('status_text', $allocation->status ? __('messages.active') : __('messages.inactive'))->class('form-control')->attribute('readonly', true)->style('cursor: default;')->id('status_text') }}

                            <div class="input-group-text bg-white">
                                <div class="form-check form-switch m-0">
                                    {{ html()->hidden('status', 0) }}
                                    {{ html()->checkbox('status', (bool)$allocation->status, 1)->class('form-check-input')->id('status')->style('width: 40px; height: 20px;') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Description -->
                    <div class="col-md-12">
                        {{ html()->label(__('messages.description'), 'description')->class('form-label fw-bold d-flex justify-content-between') }}
                        <small class="text-muted ms-auto">
                            <span id="char-count">{{ strlen($allocation->description ?? '') }}</span>/250
                        </small>
                        {{ html()->textarea('description', $allocation->description)->class('form-control')->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description'])->placeholder(__('messages.type_here')) }}
                    </div>

                    <!-- IPD/OPD Info -->
                    <div class="col-md-12 mt-5 mb-1">
                        <div>
                            <h5>{{ __('messages.ipd_opd_patient') }}</h5>
                        </div>
                    </div>

                    <!-- Row 4: Weight, Height, Blood Pressure -->
                    <div class="col-md-4">
                        {{ html()->label(__('messages.weight_kg'), 'weight')->class('form-label fw-bold') }}
                        {{ html()->text('weight', old('weight', $patientInfo->weight ?? $allocation->weight ?? ''))->class('form-control')->placeholder(__('messages.eg') . ' "60"') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.height_cm'), 'height')->class('form-label fw-bold') }}
                        {{ html()->text('height', old('height', $patientInfo->height ?? $allocation->height ?? ''))->class('form-control')->placeholder(__('messages.eg') . ' "170cm"') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.blood_pressure'), 'blood_pressure')->class('form-label fw-bold') }}
                        {{ html()->text('blood_pressure', old('blood_pressure', $patientInfo->blood_pressure ?? $allocation->blood_pressure ?? ''))->class('form-control')->placeholder(__('messages.eg') . ' "120/80"') }}
                    </div>

                    <!-- Row 5: Heart Rate, Blood Group, Temperature -->
                    <div class="col-md-4">
                        {{ html()->label(__('messages.heart_rate'), 'heart_rate')->class('form-label fw-bold') }}
                        {{ html()->text('heart_rate', old('heart_rate', $patientInfo->heart_rate ?? $allocation->heart_rate ?? ''))->class('form-control')->placeholder(__('messages.eg') . ' "78 bpm"') }}
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
                        ], old('blood_group', $patientInfo->blood_group ?? $allocation->blood_group ?? ''))->class('form-control select2') }}
                    </div>

                    <div class="col-md-4">
                        {{ html()->label(__('messages.temperature_c'), 'temperature')->class('form-label fw-bold') }}
                        {{ html()->text('temperature', old('temperature', $allocation->temperature))->class('form-control')->placeholder(__('messages.eg') . ' "37.4 C"') }}
                    </div>

                    <!-- Row 6: Symptoms, Notes -->
                    <div class="col-md-6">
                        {{ html()->label(__('messages.symptoms'), 'symptoms')->class('form-label fw-bold') }}
                        {{ html()->textarea('symptoms', old('symptoms', $allocation->symptoms))->class('form-control')->attributes(['rows' => '2'])->placeholder(__('messages.eg') . ' "Fever,Cough"') }}
                    </div>

                    <div class="col-md-6">
                        {{ html()->label(__('messages.notes'), 'notes')->class('form-label fw-bold') }}
                        {{ html()->textarea('notes', old('notes', $allocation->notes))->class('form-control')->attributes(['rows' => '2'])->placeholder(__('messages.type_here')) }}
                    </div>
                </div>

                <div class="form-footer border-top mt-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('backend.bed-allocation.index') }}" class="btn btn-light">
                            {{ __('messages.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-secondary" id="update-btn">
                            {{ trans('messages.update') }}
                        </button>
                    </div>
                </div>

                {{ html()->form()->close() }}
            </div>
        </div>
    </div>
@endsection

@push('after-scripts')
<script>
$(document).ready(function () {
    $('.select2').select2({
        width: '100%'
    });

    // Form validation
    $("#bed-allocation-form").validate({
        rules: {
            @if(multiVendor() == 1)
            clinic_admin_id: {
                required: true
            },
            clinic_id: {
                required: true
            },
            @endif
            // encounter_id is now disabled and pre-filled, validation handled by hidden input
            'encounter_id': {
                required: true
            },
            bed_type_id: {
                required: true
            },
            room_no: {
                required: true
            },
            assign_date: {
                required: true,
                date: true
            },
            discharge_date: {
                required: false,
                date: true,
                greaterThan: "#assign_date",
                notSameDay: "#assign_date"
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
                required: "Please select a room"
            },
            assign_date: {
                required: "Please select assign date",
                date: "Please enter a valid date"
            },
            discharge_date: {
                date: "Please enter a valid date",
                greaterThan: "Please select the discharge date after the assign date",
                notSameDay: "Discharge date cannot be the same as assign date"
            }
        },
        errorElement: 'span',
        errorPlacement: function(error, element) {
            error.addClass('invalid-feedback');
            element.closest('.col-lg-4 col-md-6, .col-md-4, .col-md-6, .col-md-12').append(error);
        },
        highlight: function(element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        }
    });

    // Custom validation methods
    $.validator.addMethod("greaterThan", function(value, element, param) {
        if (!value) return true; // Skip validation if empty
        var startDate = $(param).val();
        if (!startDate) return true; // Skip if assign date is not set
        return Date.parse(value) > Date.parse(startDate);
    }, "Please select the discharge date after the assign date");

    $.validator.addMethod("notSameDay", function(value, element, param) {
        if (!value) return true; // Skip validation if empty
        var startDate = $(param).val();
        if (!startDate) return true; // Skip if assign date is not set
        return Date.parse(value) !== Date.parse(startDate);
    }, "Discharge date cannot be the same as assign date");

    // Update status text when toggle changes
    $('#status').on('change', function() {
        const statusText = $(this).is(':checked') ? 'Active' : 'Inactive';
        $('#status_text').val(statusText);
    });

    // Character count for description
    $('#description').on('input', function() {
        var length = $(this).val().length;
        $('#char-count').text(length);
    });

    // Set minimum date for discharge_date based on assign_date
    function updateDischargeDateMin() {
        var assignDate = $('#assign_date').val();
        if (assignDate) {
            // Set minimum date to the day after assign date
            var minDate = new Date(assignDate);
            minDate.setDate(minDate.getDate() + 1);
            $('#discharge_date').attr('min', minDate.toISOString().split('T')[0]);
            
            // Set custom validation message
            $('#discharge_date')[0].setCustomValidity('');
            
            // Trigger validation on discharge date if it has a value
            if ($('#discharge_date').val()) {
                $('#discharge_date').trigger('change');
            }
        }
    }

    // Update discharge_date minimum when assign_date changes
    $('#assign_date').on('change', function() {
        updateDischargeDateMin();
    });

    // Override HTML5 validation message for discharge_date
    $('#discharge_date').on('invalid', function(e) {
        var assignDate = $('#assign_date').val();
        if (assignDate) {
            e.target.setCustomValidity('Please select the discharge date after the assign date');
        } else {
            e.target.setCustomValidity('');
        }
    });

    $('#discharge_date').on('input', function() {
        this.setCustomValidity('');
    });

    // Initialize on page load
    updateDischargeDateMin();

    const $clinicAdmin = $('#clinic_admin_id');
    const $clinic = $('#clinic_id');
    const $encounter = $('#encounter_id_display'); // Use display field
    const $bedType = $('#bed_type_id');
    const $room = $('#room_no');

    const allocation = {
        clinicAdmin: '{{ $allocation->clinic_admin_id }}',
        clinic: '{{ $allocation->clinic_id }}',
        encounter: '{{ $allocation->encounter_id }}',
        bedType: '{{ optional($allocation->bedMaster)->bed_type_id }}',
        room: '{{ $allocation->bed_master_id }}',
    };

    function populateSelect($select, items, selectedId, placeholder, valueKey = 'id', labelKey = 'name') {
        $select.empty().append(new Option(placeholder, '', true, true));
        items.forEach(item => {
            const option = new Option(item[labelKey], item[valueKey], false, item[valueKey] == selectedId);
            $select.append(option);
        });
        $select.prop('disabled', false).trigger('change');
    }

    function fetchAndPopulateClinics(adminId, selectedClinicId) {
        return $.get(`{{ route('backend.bed-allocation.get_clinics_by_admin', '') }}/${adminId}`)
            .then(response => {
                if (response.status) {
                    populateSelect($clinic, response.clinics, selectedClinicId, 'Select Clinic');
                    return selectedClinicId;
                }
            });
    }

    function fetchAndPopulateEncounters(clinicId, selectedEncounterId) {
        return $.get(`{{ route('backend.bed-allocation.get_patient_encounters_by_clinic', '') }}/${clinicId}`)
            .then(response => {
                if (response.status) {
                    populateSelect($encounter, response.encounters, selectedEncounterId, 'Select Patient Encounter', 'id', 'text');
                }
            });
    }

    function fetchAndPopulateRooms(bedTypeId, selectedRoomId) {
        const assignDate = $('#assign_date').val();
        const clinicId = allocation.clinic;
        const currentRoomId = selectedRoomId || $room.val() || allocation.room;
        return $.get(`{{ route('backend.bed-allocation.get_rooms', '') }}/${bedTypeId}`, {
            assign_date: assignDate,
            clinic_id: clinicId,
            current_room_id: currentRoomId
        }).then(response => {
            if (response.status) {
                populateSelect($room, response.rooms, currentRoomId, "{{ __('messages.select_room') }}", 'id', 'bed');
            } else {
                $room.empty().append(new Option(response.message || "{{ __('messages.no_rooms_available') }}", '', true, true));
                $room.prop('disabled', true).trigger('change');
            }
        }).catch(error => {
            console.error('Error fetching rooms:', error);
            $room.empty().append(new Option("{{ __('messages.error_loading_rooms') }}", '', true, true));
            $room.prop('disabled', true).trigger('change');
        });
    }

    // Handle bed type change to load beds of selected type
    $bedType.on('change', function() {
        const bedTypeId = $(this).val();
        if (bedTypeId) {
            fetchAndPopulateRooms(bedTypeId, allocation.room);
        } else {
            $room.empty().append(new Option("{{ __('messages.select_bed_type_first') }}", '', true, true));
            $room.prop('disabled', true).trigger('change');
        }
    });

    // Handle assign date change to reload beds (in case availability changes)
    $('#assign_date').on('change', function() {
        const bedTypeId = $bedType.val();
        if (bedTypeId) {
            fetchAndPopulateRooms(bedTypeId, allocation.room);
        }
    });

    // --- Initialize for Edit Mode ---
    // Note: Encounter field is now disabled and pre-filled, so we don't need to fetch encounters
    if (allocation.clinicAdmin) {
        fetchAndPopulateClinics(allocation.clinicAdmin, allocation.clinic);
    }

    if (allocation.bedType) {
        $bedType.val(allocation.bedType).trigger('change');
        fetchAndPopulateRooms(allocation.bedType, allocation.room);
    }
});
</script>
@endpush