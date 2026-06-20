
@extends('backend.layouts.app')
@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
<form method="POST" id="form" action="{{ route('backend.plugins.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if (isset($data->id))
        @method('PUT')
    @endif
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-sm-6 mb-3">
                    <label for="plugin_name" class="form-label">{{ __('messages.plugin_name') }}<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="{{  old('plugin_name') }}" name="plugin_name"
                        id="plugin_name" placeholder="{{ __('messages.enter_plugin_name') }}" required>
                         @error('plugin_name')
                            <span class="text-danger">{{ $message }}</span>
                         @enderror
                </div>
                <div class="col-sm-6 mb-3">
                    <label for="file_name" class="form-label">{{ __('messages.file') }}<span class="text-danger">*</span></label>
                    <input type="file" class="form-control" accept=".zip,.rar,.7zip" value="{{  old('file_name') }}" name="file_name"
                        id="file_name" required>
                         @error('file_name')
                            <span class="text-danger">{{ $message }}</span>
                         @enderror
                </div>
                <div class="col-sm-6 mb-3">
                    <label for="old_plugin_id" class="form-label">{{ __('messages.old_plugin') }}</label>
                    <select class="form-control" name="old_plugin_id" id="old_plugin_id">
                        <option value="">{{ __('messages.select_old_plugin') }}</option>
                        @foreach ($plugins as $key => $value)
                        <option value="{{ $value->id }}" {{ old('old_plugin_id', $data->old_plugin_id ?? '') == $value->id ? 'selected' : '' }}>{{ $value->plugin_name. ' - ' .$value->version }}</option>
                        @endforeach
                    </select>
                    @error('old_plugin_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-sm-6 mb-3">
                    <label for="description" class="form-label">{{ __('messages.description') }}<span class="text-danger">*</span></label>
                    <textarea class="form-control" value="{{  old('description') }}" name="description"
                        id="description" required>{{  old('description') }}
                    </textarea>
                         @error('description')
                            <span class="text-danger">{{ $message }}</span>
                         @enderror
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="{{ route('backend.plugins.index') }}" class="btn btn-secondary">{{ __('messages.close') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('messages.submit') }} 
            <span id="button-loader" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    </div>

</form>
@endsection
@push('after-scripts')
<script>
    const form = document.getElementById('form');
    const btn = form.querySelector('button[type="submit"]');
    const spinner = document.getElementById('button-loader');

    form.addEventListener('submit', function(event) {
        let valid = true;

        form.querySelectorAll('.js-error').forEach(el => el.remove());

        function showError(input, message) {
            const error = document.createElement('div');
            error.className = 'text-danger js-error mt-1';
            error.innerText = message;
            input.insertAdjacentElement('afterend', error);
        }

        const pluginName = document.getElementById('plugin_name');
        if (!pluginName.value.trim()) {
            valid = false;
            showError(pluginName, 'Plugin name is required.');
        }

        const fileInput = document.getElementById('file_name');
        if (!fileInput.files.length) {
            valid = false;
            showError(fileInput, 'File is required.');
        }

        const description = document.getElementById('description');
        if (!description.value.trim()) {
            valid = false;
            showError(description, 'Description is required.');
        }

        if (!valid) {
            event.preventDefault();
            return;
        }

        btn.disabled = true;
        spinner.classList.remove('d-none');
    });

    ['plugin_name', 'file_name', 'description'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function() {
                const error = el.parentNode.querySelector('.js-error');
                if (error) error.remove();
            });

            if (id === 'file_name') {
                el.addEventListener('change', function() {
                    const error = el.parentNode.querySelector('.js-error');
                    if (error) error.remove();
                });
            }
        }
    });
</script>
@endpush


