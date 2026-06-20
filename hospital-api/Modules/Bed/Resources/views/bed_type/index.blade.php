@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="card-main mb-5">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                @if (auth()->user()->can('delete_bed_type')) 
                <x-backend.quick-action url="{{ route('backend.' . $module_name . '.bulk_action') }}">
                    <div class="">
                        <select name="action_type" class="form-control select2 col-12" id="quick-action-type"
                            style="width:100%">
                            <option value="">{{ __('messages.no_action') }}</option>
                            <!-- @can('edit_bed_type')
                            <option value="change-status">{{ __('messages.lbl_status') }}</option>
                            @endcan -->
                            @can('delete_bed_type')
                            <option value="delete">{{ __('messages.delete') }}</option>
                            @endcan
                          
                        </select>
                    </div>
                    <div class="select-status d-none quick-action-field" id="change-status-action">
                        <select name="status" class="form-control select2" id="status" style="width:100%">
                            <option value="1" selected>{{ __('messages.active') }}</option>
                            <option value="0">{{ __('messages.inactive') }}</option>
                        </select>
                    </div>
                </x-backend.quick-action>
                @endif
            </div>

            <x-slot name="toolbar">
                <div>
                    <!-- <div class="datatable-filter">
                        <select name="column_status" id="column_status" class="select2 form-control" data-filter="select"
                            style="width: 100%">
                            <option value="">{{ __('messages.all') }}</option>
                            <option value="0" {{ $filter['status'] == '0' ? 'selected' : '' }}>
                                {{ __('messages.inactive') }}</option>
                            <option value="1" {{ $filter['status'] == '1' ? 'selected' : '' }}>
                                {{ __('messages.active') }}</option>
                        </select>
                    </div> -->
                </div>
                 <div class="input-group flex-nowrap">
                    <span class="input-group-text" id="addon-wrapping"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control dt-search" placeholder="{{ __('messages.search') }}..."
                        aria-label="Search" aria-describedby="addon-wrapping">
                </div>

                @can('add_bed_type')
                <a href="{{ route('backend.' . $module_name . '.create') }}"
                    class="btn btn-primary d-flex align-items-center gap-1" id="add-post-button">
                    <i class="ph ph-plus-circle"></i>{{ __('messages.new') }}
                </a>
                @endcan
            </x-slot>
        </x-backend.section-header>

        <table id="datatable" class="table table-responsive">
            <!-- Table header with the required fields -->
        </table>
    </div>

    @if (session('success'))
        <div class="snackbar" id="snackbar" style="display: block !important;">
            <div class="d-flex justify-content-around align-items-center">
                <p class="mb-0">{{ session('success') }}</p>
                <a href="#" class="dismiss-link text-decoration-none text-success"
                    onclick="dismissSnackbar(event)">{{ __('messages.dismiss') }}</a>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '{{ __('messages.success') ?? 'Success' }}',
                        text: '{{ session('success') }}',
                        icon: 'success',
                        showConfirmButton: true,
                        timer: 5000,
                        timerProgressBar: true
                    });
                }
            });
        </script>
    @endif
@endsection

@push('after-styles')
    <!-- DataTables Core and Extensions -->
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <!-- DataTables Core and Extensions -->
    <script src="{{ asset('js/form-modal/index.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script type="text/javascript" defer>
        const columns = [{
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" data-type="bed" onclick="selectAllTable(this)">',
                exportable: false,
                orderable: false,
                searchable: false,
            },
            {
                data: 'updated_at',
                name: 'updated_at',
                title: "{{ __('messages.update_at') }}",
                orderable: true,
                visible: false,
            },
            {
                data: 'type',
                name: 'type',
                title: "{{ __('messages.type') }}"
            },
            {
                data: 'description',
                name: 'description',
                title: "{{ __('messages.description') }}"
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false,
                title: "{{ __('messages.action') }}",
                className: 'text-end'
            }
        ]

        document.addEventListener('DOMContentLoaded', (event) => {
            // Initialize Select2 with width 100%
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2').select2({
                    width: '100%'
                });
            }
            
            initDatatable({
                url: '{{ route('backend.bed-type.index_data') }}',
                finalColumns: columns,
                orderColumn: [
                    [1, "desc"]
                ],
                advanceFilter: () => {
                    return {
                        status: $('#column_status').val()
                    }
                }
            });
        })

        $('#reset-filter').on('click', function(e) {
            $('#column_status').val('')
            window.renderedDataTable.ajax.reload(null, false)
        })

        function resetQuickAction() {
            const actionValue = $('#quick-action-type').val();
            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');

                if (actionValue == 'change-status') {
                    $('.quick-action-field').addClass('d-none');
                    $('#change-status-action').removeClass('d-none');
                } else {
                    $('.quick-action-field').addClass('d-none');
                }
            } else {
                $('#quick-action-apply').attr('disabled', true);
                $('.quick-action-field').addClass('d-none');
            }
        }

        $('#quick-action-type').change(function() {
            resetQuickAction()
        });

        $(document).on('update_quick_action', function() {
            // resetActionButtons()
        })
    </script>
@endpush
