@extends('backend.layouts.app')
@section('title')
    {{ __('messages.edit_bed_type') }}
@endsection
@section('content')
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('messages.edit_bed_type') }}</h5>
            </div>

            <div class="card-body">
                {{ html()->form('PUT', route('backend.bed-type.update', $beddata->id))->attributes(['enctype' => 'multipart/form-data', 'data-toggle' => 'validator', 'id' => 'bed-type-edit'])->open() }}
                <input type="hidden" name="id" value="{{ $beddata->id }}">
                <div class="row g-4">
                    <!-- Type -->
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ html()->label(trans('messages.type') . ' <span class="text-danger">*</span>', 'type')->class('form-label fw-bold') }}
                            {{ html()->text('type', $beddata->type)->placeholder(__('messages.type_here'))->class('form-control')->required() }}
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="col-md-12">
                        <div class="form-group">
                            {{ html()->label(__('messages.description'), 'description')->class('form-label fw-bold d-flex justify-content-between') }}
                            <small class="text-muted ms-auto">
                                <span id="char-count">{{ strlen($beddata->description ?? '') }}</span>/250
                            </small>

                            {{ html()->textarea('description', $beddata->description)->class('form-control')->placeholder(__('messages.type_here'))->attributes(['rows' => '4', 'maxlength' => '250', 'id' => 'description']) }}

                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-footer border-top pt-3 mt-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('backend.bed-type.index') }}" class="btn btn-light px-4">
                            {{ __('messages.cancel') }}
                        </a>
                        {{ html()->submit(trans('messages.update'))->class('btn btn-primary px-4') }}
                    </div>
                </div>

                {{ html()->form()->close() }}
            </div>
        </div>
    </div>
@endsection

@push('after-scripts')
    <script>
        $(document).ready(function() {
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
            $('#bed-type-edit').on('submit', function(e) {
                let isValid = true;

                // Validate "type"
                const $type = $('input[name="type"]');
                if ($type.val().trim() === '') {
                    $type.addClass('is-invalid');
                    isValid = false;
                } else {
                    $type.removeClass('is-invalid');
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });

            // Real-time remove error state
            $('input[name="type"]').on('input', function() {
                $(this).removeClass('is-invalid');
            });
        });
    </script>
@endpush
