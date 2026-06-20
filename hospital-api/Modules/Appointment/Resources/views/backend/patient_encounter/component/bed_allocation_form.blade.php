<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ ('Bed Assign') }}</h5>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

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

        {{-- Always set the encounter ID for both add and edit forms --}}
        <input type="hidden" name="encounter_id" id="encounter_id" value="{{ $encounter_id ?? ($data->id ?? '') }}">

        <div class="row g-4">
            <!-- Row 1: Patient, Bed Type, Room Number -->
            {{-- <div class="col-md-4">
                {{ html()->label('Encounter <span class="text-danger">*</span>', 'billing_record_id')->class('form-label fw-bold') }}
                {{ html()->select('billing_record_id', $billingRecords, null)->class('form-control select2')->placeholder('Select Encounter')->required() }}
            </div> --}}

            <div class="col-md-4">
                {{ html()->label('Bed Type <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                {{ html()->select('bed_type_id', $bedTypes, null)->class('form-control select2')->placeholder('Select')->required() }}
            </div>

            <div class="col-md-4">
                {{ html()->label('Room <span class="text-danger">*</span>', 'room_no')->class('form-label fw-bold') }}
                {{ html()->select('room_no', $beds, null)->class('form-control select2')->placeholder('Select')->required() }}
            </div>

            <!-- Row 2: Assign Date, Discharge Date -->
            <div class="col-md-4">
                {{ html()->label('Assign Date <span class="text-danger">*</span>', 'assign_date')->class('form-label fw-bold') }}
                {{ html()->text('assign_date')->class('form-control')->attributes(['id' => 'assign_date'])->required() }}
            </div>

            <div class="col-md-4">
                {{ html()->label('Discharge Date <span class="text-danger">*</span>', 'discharge_date')->class('form-label fw-bold')->toHtml()|raw }}
                {{ html()->text('discharge_date')->class('form-control')->attributes(['id' => 'discharge_date'])->required() }}
            </div>

            <!-- Row 3: Description -->
            <div class="col-md-12">
                {{ html()->label('Description', 'description')->class('form-label fw-bold d-flex justify-content-between') }}
                <small class="text-muted ms-auto">
                    <span id="char-count">0</span>/250
                </small>
                {{ html()->textarea('description')->class('form-control')->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description'])->placeholder('Type here...') }}
            </div>

            <!-- IPD/OPD Info Message -->
            <div class="col-md-12 mt-5 mb-1">
                <div>
                    <h5>IPD Patient</h5>
                </div>
            </div>

            <!-- Row 4: Weight, Height, Blood Pressure -->
            <div class="col-md-4">
                {{ html()->label('Weight (Kg)', 'weight')->class('form-label fw-bold') }}
                {{ html()->text('weight')->class('form-control')->placeholder('eg"60"') }}
            </div>

            <div class="col-md-4">
                {{ html()->label('Height (cm)', 'height')->class('form-label fw-bold') }}
                {{ html()->text('height')->class('form-control')->placeholder('eg"170cm"') }}
            </div>

            <div class="col-md-4">
                {{ html()->label('Blood Pressure', 'blood_pressure')->class('form-label fw-bold') }}
                {{ html()->text('blood_pressure')->class('form-control')->placeholder('eg"120/80"') }}
            </div>

            <!-- Row 5: Heart Rate, Blood Group, Temperature -->
            <div class="col-md-4">
                {{ html()->label('Heart Rate', 'heart_rate')->class('form-label fw-bold') }}
                {{ html()->text('heart_rate')->class('form-control')->placeholder('eg"78 bpm"') }}
            </div>

            <div class="col-md-4">
                {{ html()->label('Blood Group', 'blood_group')->class('form-label fw-bold') }}
                {{ html()->text('blood_group')->class('form-control')->placeholder('eg"A+"')}}
            </div>

            <div class="col-md-4">
                {{ html()->label('Temperature (°C)', 'temperature')->class('form-label fw-bold') }}
                {{ html()->text('temperature')->class('form-control')->placeholder('eg"37.4 C"') }}
            </div>

            <!-- Row 6: Symptoms, Notes -->
            <div class="col-md-6">
                {{ html()->label('Symptoms', 'symptoms')->class('form-label fw-bold') }}
                {{ html()->textarea('symptoms')->class('form-control')->attributes(['rows' => '2'])->placeholder('eg"Fever,Cough"') }}
            </div>

            <div class="col-md-6">
                {{ html()->label('Notes', 'notes')->class('form-label fw-bold') }}
                {{ html()->textarea('notes')->class('form-control')->attributes(['rows' => '2'])->placeholder('"Type here.."') }}
            </div>
        </div>

        <div class="form-footer border-top pt-3 mt-4">
            <div class="d-flex gap-2 justify-content-end">
                {{ html()->submit(trans('messages.save'))->class('btn btn-primary px-4') }}
            </div>
        </div>

        {{ html()->form()->close() }}
    </div>
</div>

@push('after-scripts')
<script>
    $(document).ready(function () {
        // Initialize Select2
        $('.select2').select2();

        // Ensure encounter_id is set from URL if missing
        var $encounterInput = $('#encounter_id');
        if (!$encounterInput.val()) {
            var urlParams = new URLSearchParams(window.location.search);
            var encounterId = urlParams.get('encounter_id');
            if (!encounterId) {
                var pathSegments = window.location.pathname.split('/');
                var idx = pathSegments.indexOf('encounter-detail-page');
                if (idx !== -1 && pathSegments.length > idx + 1) {
                    encounterId = pathSegments[idx + 1];
                }
            }
            if (encounterId) {
                $encounterInput.val(encounterId);
            }
        }

        // Before form submit, double-check the value
        $('#bed-allocation-form').on('submit', function(e) {
            if (!$('#encounter_id').val()) {
                alert('Encounter ID is required!');
                e.preventDefault();
                return false;
            }
        });

        // Form validation
        $("#bed-allocation-form").validate({
            rules: {
                encounter_id: {
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
                    date: true,
                    notPastDate: true
                },
                discharge_date: {
                    required: true,
                    date: true,
                    notPastDate: true,
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
                blood_pressure: {
                    pattern: /^\d{2,3}\/\d{2,3}$/
                },
                heart_rate: {
                    number: true,
                    min: 0,
                    max: 250
                },
                temperature: {
                    number: true,
                    min: 35,
                    max: 42
                },
                blood_group: {
                    pattern: /^(A|B|AB|O)[+-]$/
                }
            },
            messages: {
                encounter_id: {
                    required: "Encounter ID is required"
                },
                bed_type_id: {
                    required: "Please select a bed type"
                },
                room_no: {
                    required: "Please select a room"
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
                blood_pressure: {
                    pattern: "Please enter blood pressure in format: systolic/diastolic (e.g., 120/80)"
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
                },
                blood_group: {
                    pattern: "Please enter a valid blood group (e.g., A+, B-, AB+, O-)"
                }
            },
            submitHandler: function(form) {
                // Ensure encounter ID is set before submission
                if (!$('#encounter_id').val()) {
                    alert('Encounter ID is required');
                    return false;
                }
                form.submit();
            }
        });

        // Character count for description
        $('#description').on('input', function() {
            var length = $(this).val().length;
            $('#char-count').text(length);
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize flatpickr for assign date
            flatpickr("#assign_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    // Set minDate for discharge date
                    dischargePicker.set('minDate', dateStr);
                    // If discharge date is before assign date, clear it
                    if ($('#discharge_date').val() && $('#discharge_date').val() < dateStr) {
                        $('#discharge_date').val('');
                    }
                }
            });

            // Initialize flatpickr for discharge date
            var dischargePicker = flatpickr("#discharge_date", {
                dateFormat: "Y-m-d",
                minDate: $("#assign_date").val() || "today"
            });
        });
    });
</script>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush 

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"> 