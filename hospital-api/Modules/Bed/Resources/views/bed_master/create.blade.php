@extends('backend.layouts.app')
@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('messages.bed_master') }}</h5>
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

                {{ html()->form('POST', route('backend.bed-master.store'))->attributes(['enctype' => 'multipart/form-data', 'id' => 'bed-master-form'])->open() }}
                {{ html()->hidden('id', $bedMasterData->id ?? null) }}

                <div class="row g-4">
                  
                    @if(multiVendor() == 1)
                    <!-- Clinic Admin -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.lbl_click_admin') . ' <span class="text-danger">*</span>', 'clinic_admin_id')->class('form-label fw-bold') }}
                            @if(auth()->user()->hasRole('vendor'))
                                {{ html()->select('clinic_admin_id', $clinicAdmins->pluck('full_name', 'id'), old('clinic_admin_id', $bedMasterData->clinic_admin_id ?? auth()->user()->id))->class('form-control select2')->required()->attribute('disabled', true) }}
                                {{ html()->hidden('clinic_admin_id', old('clinic_admin_id', $bedMasterData->clinic_admin_id ?? auth()->user()->id)) }}
                            @else
                                {{ html()->select('clinic_admin_id', $clinicAdmins->pluck('full_name', 'id'), old('clinic_admin_id', $bedMasterData->clinic_admin_id ?? null))->placeholder(__('messages.select_clinic_admin'))->class('form-control select2')->required() }}
                            @endif
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>
                    @endif
                    <!-- Clinic -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.clinics') . ' <span class="text-danger">*</span>', 'clinic_id')->class('form-label fw-bold') }}
                            {{ html()->select('clinic_id', $clinics->pluck('name', 'id'), old('clinic_id', $bedMasterData->clinic_id ?? null))->placeholder(__('messages.select_clinic'))->class('form-control select2')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>
                

                    <!-- Bed Name -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_name') . ' <span class="text-danger">*</span>', 'bed')->class('form-label fw-bold') }}
                            {{ html()->text('bed', $bedMasterData->bed ?? null)->placeholder(__('messages.enter_bed_name'))->class('form-control')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>

                    <!-- Bed Type -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_type') . ' <span class="text-danger">*</span>', 'bed_type_id')->class('form-label fw-bold') }}
                            {{ html()->select('bed_type_id', $bedTypes->pluck('type', 'id'), $bedMasterData->bed_type ?? null)->placeholder(__('messages.select_bed_type'))->class('form-control select2')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>

                    <!-- Charges -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.charges_per_day') . ' <span class="text-danger">*</span>', 'charges')->class('form-label fw-bold') }}
                            <div class="input-group">
                                {{ html()->number('charges', $bedMasterData->charges ?? null)->placeholder(__('messages.eg') . ' "50"')->class('form-control')->attributes(['step' => '0.01', 'min' => '0'])->required() }}
                            </div>
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>

                    <!-- Capacity -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(__('messages.bed_capacity') . ' <span class="text-danger">*</span>', 'Bed capacity')->class('form-label fw-bold') }}
                            {{ html()->number('capacity', $bedMasterData->capacity ?? null)->placeholder(__('messages.eg') . ' "50"')->class('form-control')->attributes(['min' => '1'])->required() }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>

                    <!-- Status and Under Maintenance Row -->
                    <div class="col-md-12">
                        <div class="row">
                            <!-- Status -->
                            <div class="col-md-6 mb-3">
                                {{ html()->label(__('messages.active_status'), 'status')->class('form-label fw-bold') }}
                                <div class="input-group mt-0.5">
                                    {{ html()->text('status_text')->class('form-control')->attribute('readonly', true)->style('cursor: default;')->value(isset($bedMasterData->status) && !$bedMasterData->status ? __('messages.inactive') : __('messages.active'))->id('status_text') }}
                                    <div class="input-group-text">
                                        <div class="form-check form-switch m-0">
                                            {{ html()->checkbox('status', $bedMasterData->status ?? true, 1)->class('form-check-input')->id('status')->style('width: 40px; height: 20px;') }}
                                            {{ html()->label('', 'status')->class('form-check-label') }}
                                        </div>
                                    </div>
                                </div>
                            </div> 

                            <!-- Under Maintenance -->
                            <div class="col-md-6 mb-3">
                                {{ html()->label(__('messages.under_maintenance'), 'is_under_maintenance')->class('form-label fw-bold') }}
                                <div class="input-group mt-0.5">
                                    {{ html()->text('maintenance_text')->class('form-control')->attribute('readonly', true)->style('cursor: default;')->value(
                                            isset($bedMasterData->is_under_maintenance) && $bedMasterData->is_under_maintenance
                                                ? __('messages.unavailable')
                                                : __('messages.available'),
                                        )->id('maintenance_text') }}
                                    <div class="input-group-text">
                                        <div class="form-check form-switch m-0">
                                            {{ html()->checkbox('is_under_maintenance', $bedMasterData->is_under_maintenance ?? false, 1)->class('form-check-input')->id('is_under_maintenance')->style('width: 40px; height: 20px;') }}
                                            {{ html()->label('', 'is_under_maintenance')->class('form-check-label') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="col-md-12 mb-3">
                        <div class="form-group">
                            <label for="description" class="form-label fw-bold d-flex justify-content-between align-items-center">
                                {{ __('messages.description') }}
                                <small class="text-muted ms-auto" style="font-weight: normal;">
                                    {{ __('messages.maximum_characters') }} <span id="char-count">{{ isset($bedMasterData->description) ? strlen($bedMasterData->description) : 0 }}</span>/250 {{ __('messages.characters') }}
                            </small>
                            </label>
                            {{ html()->textarea('description', $bedMasterData->description ?? null)->class('form-control')->placeholder(__('messages.type_here'))->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description']) }}
                            <span class="invalid-feedback" role="alert"></span>
                        </div>
                    </div>
                </div>

                <div class="form-footer mt-4">
                    <div class="d-flex gap-3 flex-wrap justify-content-end">
                        <a href="{{ route('backend.bed-master.index') }}" class="btn btn-light">
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
@endpush

@push('after-scripts')
<script>
        $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            placeholder: '{{ __('messages.select') }}...',
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
                $clinicSelect.empty();
                $clinicSelect.append('<option value="">{{ __('messages.select_clinic') }}...</option>');
            allClinics.forEach(function(clinic) {
                if (!adminId || clinic.vendor_id == adminId) {
                    $clinicSelect.append('<option value="' + clinic.id + '">' + clinic.name + '</option>');
                }
            });
            $clinicSelect.val('').trigger('change');
        }
        
        @if(auth()->user()->hasRole('vendor'))
        // For vendors, clinics are already filtered in controller
        // The clinic admin field is disabled and auto-selected
        // Just ensure clinics dropdown is populated (it's already filtered in controller)
        var adminId = $('input[name="clinic_admin_id"][type="hidden"]').val();
        if (adminId) {
            // Ensure the disabled select shows the correct value
            var $clinicAdminSelect = $('select[name="clinic_admin_id"]');
            if ($clinicAdminSelect.length) {
                $clinicAdminSelect.val(adminId).trigger('change.select2');
            }
        }
        @else
        // For admins, allow changing clinic admin and filtering clinics
        $('select[name="clinic_admin_id"]').on('change', function() {
            filterClinicsByAdmin($(this).val());
        });
        // On page load, filter clinics if admin is preselected
        var preselectedAdmin = $('select[name="clinic_admin_id"]').val();
        if (preselectedAdmin) {
            filterClinicsByAdmin(preselectedAdmin);
        }
        @endif
        @endif

            // Form validation
            var validationRules = {
                bed: {
                    required: true,
                    minlength: 2,
                    maxlength: 50,
                    noSpecialChars: true,
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
                    noSpecialChars: "Bed name cannot contain special characters",
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

            $("#bed-master-form").validate({
                rules: validationRules,
                messages: validationMessages,
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-group').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    // Don't add is-invalid class to prevent red border
                },
                unhighlight: function(element, errorClass, validClass) {
                    // Don't remove is-invalid class since we're not adding it
                },
                submitHandler: function(form) {
                    // Show loading state
                    const submitBtn = $(form).find('button[type="submit"]');
                    const originalText = submitBtn.html();
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __('messages.saving') }}');

                    // Submit the form
                    form.submit();
                }
            });

            // Custom validation method for no special characters
            $.validator.addMethod("noSpecialChars", function(value, element) {
                return this.optional(element) || /^[a-zA-Z0-9\s-]+$/.test(value);
            }, "Please enter only letters, numbers, spaces, and hyphens");

            // Custom validation method to check for duplicate bed name within same bed type
            $.validator.addMethod("checkDuplicate", function(value, element) {
                var bedName = value;
                var bedTypeId = $('select[name="bed_type_id"]').val();
                var isValid = true;
                var $element = $(element);

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

            // Status toggle functionality
            // $('#status').on('change', function() {
            //     const isChecked = $(this).is(':checked');
            //     $('#status_text').val(isChecked ? 'Active' : 'Inactive');
            // });

            // Under Maintenance toggle functionality
            $('#is_under_maintenance').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('#maintenance_text').val(isChecked ? '{{ __('messages.unavailable') }}' : '{{ __('messages.available') }}');
            });

            // Character counter for description
        const $description = $('#description');
        const $charCount = $('#char-count');

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

        updateCounter();
        $description.on('input', updateCounter);

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

            $('textarea[name="description"]').on('input', function() {
                $(this).valid();
            });

            // Real-time validation for clinic fields
            $('select[name="clinic_id"]').on('change select2:select', function() {
                $(this).valid();
            });

            @if(multiVendor() == 1)
            // Add validation handler for clinic_admin_id (in addition to the filter handler)
            $('select[name="clinic_admin_id"]').on('select2:select', function() {
                $(this).valid();
            });
            // Validate on change event as well (this will work alongside the existing filter handler)
            $('select[name="clinic_admin_id"]').on('change', function() {
                $(this).valid();
            });
            @endif
    });
</script>
@endpush
