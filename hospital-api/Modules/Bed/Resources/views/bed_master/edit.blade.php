@extends('backend.layouts.app')
@section('title')
    {{ __('messages.edit_bed_master') }}
@endsection
@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('messages.edit_bed_master') }}</h5>
            </div>

            <div class="card-body">
                {{ html()->form('PUT', route('backend.bed-master.update', $bedMasterData->id))->attributes(['enctype' => 'multipart/form-data', 'data-toggle' => 'validator', 'id' => 'bed-master-edit'])->open() }}

                <div class="row gy-4">
                     @if(multiVendor() == 1)
                    <!-- Clinic Admin -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.lbl_click_admin') . ' <span class="text-danger">*</span>', 'clinic_admin_id')->class('form-label fw-bold') }}
                            {{ html()->select('clinic_admin_id', $clinicAdmins->pluck('full_name', 'id'), old('clinic_admin_id', $bedMasterData->clinic_admin_id ?? null))->placeholder(__('messages.select_clinic_admin'))->class('form-control select2')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>
                    @endif
                    <!-- Clinic -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.clinics') . ' <span class="text-danger">*</span>', 'clinic_id')->class('form-label fw-bold') }}
                            {{ html()->select('clinic_id', $clinics->pluck('name', 'id'), old('clinic_id', $bedMasterData->clinic_id ?? null))->placeholder(__('messages.select_clinic'))->class('form-control select2')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>
                    <!-- Bed Name -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_name') . ' <span class="text-danger">*</span>', 'bed')->class('form-label fw-bold') }}
                            {{ html()->text('bed', $bedMasterData->bed)->placeholder(__('messages.enter_bed_name'))->class('form-control')->required() }}
                            @error('bed')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Bed Type -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_type') . ' <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                            {{ html()->select('bed_type_id', $bedTypes->pluck('type', 'id'), $bedMasterData->bed_type_id)->placeholder(__('messages.select_bed_type'))->class('form-control select2')->required() }}
                            @error('bed_type_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Charges -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.charges_per_day') . ' <span class="text-danger">*</span>', 'charges')->class('form-label fw-bold') }}

                            <div class="input-group">
                                {{ html()->number('charges', $bedMasterData->charges)->placeholder(__('messages.eg') . ' "50"')->class('form-control')->attributes(['step' => '0.01', 'min' => '0'])->required() }}
                            </div>
                            @error('charges')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Capacity -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_capacity') . ' <span class="text-danger">*</span>', 'Bed capacity')->class('form-label fw-bold') }}
                            {{ html()->number('capacity', $bedMasterData->capacity)->placeholder(__('messages.eg') . ' "50"')->class('form-control')->attributes(['min' => '1'])->required() }}
                            @error('capacity')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Status and Under Maintenance Row -->
                    <!-- Active Status -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        {{ html()->label(__('messages.active_status'), 'status')->class('form-label fw-bold') }}

                        <div class="input-group mt-0.5">
                            {{ html()->text('status_text')->class('form-control')->attribute('readonly', true)->style('cursor: default;')->value($bedMasterData->status ? __('messages.active') : __('messages.inactive'))->id('status_text') }}

                            <div class="input-group-text bg-white">
                                <div class="form-check form-switch m-0">
                                    {{ html()->checkbox('status', $bedMasterData->status, 1)->class('form-check-input')->id('status')->style('width: 40px; height: 20px;') }}
                                    {{ html()->label('', 'status')->class('form-check-label') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Under Maintenance -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        {{ html()->label(__('messages.under_maintenance'), 'is_under_maintenance')->class('form-label fw-bold') }}

                        <div class="input-group mt-0.5">
                            {{ html()->text('maintenance_text')->class('form-control')->attribute('readonly', true)->style('cursor: default;')->value($bedMasterData->is_under_maintenance ? __('messages.unavailable') : __('messages.available'))->id('maintenance_text') }}

                            <div class="input-group-text bg-white">
                                <div class="form-check form-switch m-0">
                                    {{ html()->checkbox('is_under_maintenance', $bedMasterData->is_under_maintenance, 1)->class('form-check-input')->id('is_under_maintenance')->style('width: 40px; height: 20px;') }}
                                    {{ html()->label('', 'is_under_maintenance')->class('form-check-label') }}
                                </div>
                            </div>
                        </div>
                    </div>                       

                    <!-- Description -->
                    <div class="col-md-12 mb-3">
                        <div class="form-group">
                            <label for="description"
                                class="form-label fw-bold d-flex justify-content-between align-items-center">
                                {{ __('messages.description') }}
                                <small class="text-muted ms-auto" style="font-weight: normal;">
                                    {{ __('messages.maximum_characters') }} <span
                                        id="char-count">{{ strlen($bedMasterData->description ?? '') }}</span>/250
                                    {{ __('messages.characters') }}
                                </small>
                            </label>

                            {{ html()->textarea('description', $bedMasterData->description)->class('form-control')->placeholder(__('messages.type_here'))->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description']) }}

                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                   

                </div>

                <div class="form-footer border-top mt-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('backend.bed-master.index') }}" class="btn btn-light">
                            {{ __('messages.cancel') }}
                        </a>
                        {{ html()->submit(trans('messages.update'))->class('btn btn-secondary') }}
                    </div>
                </div>

                {{ html()->form()->close() }}
            </div>
        </div>
    </div>
@endsection

@push('after-styles')
    <!-- Select2 CSS is already loaded in main backend layout (app.blade.php) -->
@endpush

@push('after-scripts')
   
    <script>
        $(document).ready(function() {
            // Initialize Select2 with appropriate placeholders for each field
            $('select[name="clinic_admin_id"].select2').select2({
                placeholder: 'Select clinic admin...',
                allowClear: true,
                width: '100%'
            });
            
            $('select[name="clinic_id"].select2').select2({
                placeholder: 'Select clinic...',
                allowClear: true,
                width: '100%'
            });
            
            $('select[name="bed_type_id"].select2').select2({
                placeholder: 'Select bed type...',
                allowClear: true,
                width: '100%'
            });

            // Filter clinics by selected admin (only when multivendor is enabled)
            @if(multiVendor() == 1)
            var allClinics = @json($clinics->map(function($clinic) {
                return ['id' => $clinic->id, 'name' => $clinic->name, 'vendor_id' => $clinic->vendor_id];
            }));
            function filterClinicsByAdmin(adminId) {
                var $clinicSelect = $('select[name="clinic_id"]');
                var currentValue = $clinicSelect.val(); // Preserve current selection
                $clinicSelect.empty();
                $clinicSelect.append('<option value="">Select clinic...</option>');
                allClinics.forEach(function(clinic) {
                    if (!adminId || clinic.vendor_id == adminId) {
                        var selected = clinic.id == currentValue ? 'selected' : '';
                        $clinicSelect.append('<option value="' + clinic.id + '" ' + selected + '>' + clinic.name + '</option>');
                    }
                });
                $clinicSelect.val(currentValue).trigger('change');
            }
            $('select[name="clinic_admin_id"]').on('change', function() {
                filterClinicsByAdmin($(this).val());
            });
            // On page load, filter clinics if admin is preselected
            var preselectedAdmin = $('select[name="clinic_admin_id"]').val();
            if (preselectedAdmin) {
                filterClinicsByAdmin(preselectedAdmin);
            }
            @endif

            // Status toggle functionality
            $('#status').on('change', function() {
                const isChecked = $(this).is(':checked');
                const $statusText = $('#status-text');

                if (isChecked) {
                    $statusText.text('Active').removeClass('bg-danger').addClass('bg-success');
                } else {
                    $statusText.text('Inactive').removeClass('bg-success').addClass('bg-danger');
                }
            });

            // Under Maintenance toggle functionality - FIXED: Changed ID to is_under_maintenance
            $('#is_under_maintenance').on('change', function() {
                const isChecked = $(this).is(':checked');
                const $maintenanceText = $('#maintenance-text');

                if (isChecked) {
                    $maintenanceText.text('Unavailable').removeClass('bg-info').addClass('bg-warning');
                } else {
                    $maintenanceText.text('Available').removeClass('bg-warning').addClass('bg-info');
                }
            });

            const $description = $('#description');
            const $charCount = $('#char-count');

            // Function to update counter and color
            function updateCounter() {
                const currentLength = $description.val().length;
                $charCount.text(currentLength);

                if (currentLength >= 250) {
                    $charCount.removeClass('text-muted text-warning').addClass('text-danger');
                } else if (currentLength > 200) {
                    $charCount.removeClass('text-muted text-danger').addClass('text-warning');
                } else {
                    $charCount.removeClass('text-warning text-danger').addClass('text-muted');
                }
            }

            // Initial counter update
            updateCounter();

            // On input update counter
            $description.on('input', updateCounter);

            // Custom validation method to check for duplicate bed name within same bed type
            $.validator.addMethod("checkDuplicate", function(value, element) {
                var bedName = value;
                var bedTypeId = $('select[name="bed_type_id"]').val();
                var bedMasterId = {{ $bedMasterData->id ?? 'null' }};
                var isValid = true;

                // Only check if both bed name and bed type are filled
                if (!bedName || !bedTypeId) {
                    return true; // Let other validators handle required fields
                }

                // Make synchronous AJAX call to check duplicate
                $.ajax({
                    url: "{{ route('backend.bed-master.check_duplicate') }}",
                    type: 'POST',
                    async: false, // Synchronous for validation
                    data: {
                        bed: bedName,
                        bed_type_id: bedTypeId,
                        exclude_id: bedMasterId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.exists) {
                            isValid = false;
                        }
                    },
                    error: function() {
                        // On error, allow submission (server-side validation will catch it)
                        isValid = true;
                    }
                });

                return isValid;
            }, "A bed with this name already exists for the selected bed type");

            // Form validation
            var validationRules = {
                bed: {
                    required: true,
                    minlength: 2,
                    maxlength: 50,
                    checkDuplicate: true
                },
                bed_type_id: {
                    required: true
                },
                charges: {
                    required: true,
                    number: true,
                    min: 0
                },
                capacity: {
                    required: true,
                    digits: true,
                    min: 1
                },
                description: {
                    maxlength: 250
                },
                clinic_id: {
                    required: true
                }
            };

            var validationMessages = {
                bed: {
                    required: "Please enter bed name",
                    minlength: "Bed name must be at least 2 characters long",
                    maxlength: "Bed name cannot exceed 50 characters",
                    checkDuplicate: "A bed with this name already exists for the selected bed type"
                },
                bed_type_id: {
                    required: "Please select a bed type"
                },
                charges: {
                    required: "Please enter charges",
                    number: "Please enter a valid number",
                    min: "Charges cannot be negative"
                },
                capacity: {
                    required: "Please enter bed capacity",
                    digits: "Please enter a whole number",
                    min: "Capacity must be at least 1"
                },
                description: {
                    maxlength: "Description cannot exceed 250 characters"
                },
                clinic_id: {
                    required: "clinic is required field"
                }
            };

            @if(multiVendor() == 1)
            validationRules.clinic_admin_id = {
                required: true
            };
            validationMessages.clinic_admin_id = {
                required: "clinic admin is required field"
            };
            @endif

            $("#bed-master-edit").validate({
                rules: validationRules,
                messages: validationMessages,
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-group').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid');
                },
                submitHandler: function(form) {
                    form.submit();
                }
            });

            // Real-time validation
            $('input[name="bed"]').on('input', function() {
                // Only validate if bed type is also selected
                if ($('select[name="bed_type_id"]').val()) {
                    $(this).valid();
                }
            });

            $('select[name="bed_type_id"]').on('change', function() {
                // Validate bed name when bed type changes
                if ($('input[name="bed"]').val()) {
                    $('input[name="bed"]').valid();
                }
                $(this).valid();
            });

            $('input[name="charges"]').on('input', function() {
                $(this).valid();
            });

            $('input[name="capacity"]').on('input', function() {
                $(this).valid();
            });

            // Real-time validation for clinic fields
            $('select[name="clinic_id"]').on('change select2:select', function() {
                $(this).valid();
            });

            @if(multiVendor() == 1)
            $('select[name="clinic_admin_id"]').on('change select2:select', function() {
                $(this).valid();
            });
            @endif
        });
    </script>
@endpush
