@extends('backend.layouts.app')
@section('title')
    {{ __('messages.bed_type') }}
@endsection
@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('messages.bed_type') }}</h5>
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

                {{ html()->form('POST', route('backend.bed-type.store'))->attributes(['enctype' => 'multipart/form-data', 'id' => 'bed-type-form', 'novalidate' => 'novalidate'])->open() }}

                <div class="row g-4">
                    <!-- Type -->
                    <div class="col-md-12">
                        <div class="form-group">
                            {{ html()->label(trans('messages.bed_type') . ' <span class="text-danger">*</span>', 'type')->class('form-label fw-bold') }}
                            {{ html()->text('type')->placeholder(__('messages.type_here'))->class('form-control')->required() }}
                            <span class="invalid-feedback" role="alert"></span>
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
                        <a href="{{ route('backend.bed-type.index') }}" class="btn btn-light">
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
        // Prevent browser's native HTML5 validation messages
        $('#bed-type-form').on('submit', function(e) {
            if (!$(this).valid()) {
                e.preventDefault();
                return false;
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

            // Form validation
            $("#bed-type-form").validate({
                rules: {
                    type: {
                        required: true,
                        minlength: 2,
                        maxlength: 50,
                        noSpecialChars: true
                    },
                    description: {
                        maxlength: 250
                    }
                },
                messages: {
                    type: {
                        required: "Please enter bed type",
                        minlength: "Bed type must be at least 2 characters long",
                        maxlength: "Bed type cannot exceed 50 characters",
                        noSpecialChars: "Bed type cannot contain special characters"
                    },
                    description: {
                        maxlength: "Description cannot exceed 250 characters"
                    }
                },
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

            // Real-time validation
            $('input[name="type"]').on('input', function() {
                $(this).valid();
            });

            $('textarea[name="description"]').on('input', function() {
                $(this).valid();
            });
        });
    </script>
@endpush
