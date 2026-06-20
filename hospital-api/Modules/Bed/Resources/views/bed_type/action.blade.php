<?php
$auth_user = authSession();
?>
<div class="d-flex justify-content-end align-items-center">
    @if ($auth_user->can('edit_bed_type'))
        <a class="btn text-success p-0 fs-5" href="{{ route('backend.bed-type.edit', $bed->id) }}"
            title="{{ __('messages.edit') }}" data-bs-toggle="tooltip"><i class="ph ph-pencil-simple-line"></i></a>
    @endif
    @if ($auth_user->can('delete_bed_type'))
        <a href="{{ route('backend.bed-type.destroy', $bed->id) }}" id="delete-bed_type-{{ $bed->id }}"
            class="btn text-danger p-0 fs-5" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}" 
            data-confirm="Are you sure you want to delete bed type &quot;{{ $bed->type }}&quot;?">
            <i class="ph ph-trash"></i>
        </a>
    @endif
</div>
