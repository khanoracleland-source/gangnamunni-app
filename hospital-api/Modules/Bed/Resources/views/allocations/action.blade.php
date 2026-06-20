<?php $auth_user = authSession(); ?>

<div class="d-flex justify-content-end align-items-center gap-2">
    @if ($auth_user->can('edit_allocations'))
        <a class="btn text-success p-0 fs-5" href="{{ route('backend.bed-allocation.edit', $allocation->id) }}"
            title="{{ __('messages.edit') }}" data-bs-toggle="tooltip">
            <i class="ph ph-pencil-simple-line"></i>
        </a>
    @endif
    @if ($auth_user->can('delete_allocations'))
        <a href="{{ route('backend.bed-allocation.destroy', $allocation->id) }}" id="delete-bed-allocation-{{ $allocation->id }}"
            class="btn text-danger p-0 fs-5" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}" 
            data-confirm="Are you sure you want to delete bed allocation for &quot;{{ $allocation->patient ? ($allocation->patient->first_name . ' ' . $allocation->patient->last_name) : 'Patient' }}&quot;?">
            <i class="ph ph-trash"></i>
        </a>
    @endif
</div>

