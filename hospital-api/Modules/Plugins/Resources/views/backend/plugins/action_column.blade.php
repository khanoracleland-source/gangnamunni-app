<div class="d-flex gap-2 align-items-center">
  @hasPermission('edit_coupons')
       <a  class="btn btn-primary" href="{{ route('backend.coupons.edit', $data->id) }}"> <i class="fa-solid fa-pen-clip"></i></a>

  @endhasPermission
  @hasPermission('delete_coupons')
        <button type="button" class="btn btn-danger" data-form-delete="{{ route('backend.coupons.destroy', $data->id) }}">{{ __('messages.delete') }}</button>
  @endhasPermission
</div>

