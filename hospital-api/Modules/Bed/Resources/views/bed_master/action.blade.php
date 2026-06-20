<?php
$auth_user = authSession();
?>
<div class="d-flex justify-content-end align-items-center gap-2">
    @if ($auth_user->can('edit_bed_master') && !$auth_user->hasRole('doctor') && !$auth_user->hasRole('receptionist'))
        <a class="btn text-success p-0 fs-5" href="{{ route('backend.bed-master.edit', $bedMaster->id) }}"
            title="{{ __('messages.edit') }}" data-bs-toggle="tooltip">
            <i class="ph ph-pencil-simple-line"></i>
        </a>
    @endif
    @if ($auth_user->can('delete_bed_master') && !$auth_user->hasRole('doctor') && !$auth_user->hasRole('receptionist'))
        <a href="{{ route('backend.bed-master.destroy', $bedMaster->id) }}" id="delete-bed-master-{{ $bedMaster->id }}"
            class="btn text-danger p-0 fs-5" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}" 
            data-confirm="Are you sure you want to delete bed &quot;{{ $bedMaster->bed }}&quot;?">
            <i class="ph ph-trash"></i>
        </a>
    @endif
</div>

