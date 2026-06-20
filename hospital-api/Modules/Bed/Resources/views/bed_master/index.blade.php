@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="card-main mb-5">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                 @if ((auth()->user()->can('edit_bed_master') || auth()->user()->can('delete_bed_master')) && !auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist')) 
                <x-backend.quick-action url="{{ route('backend.' . $module_name . '.bulk_action') }}">
                    <div class="">
                        <select name="action_type" class="form-control select2 col-12" id="quick-action-type"
                            style="width:100%">
                            <option value="">{{ __('messages.no_action') }}</option>
                             @can('edit_bed_master') 
                            <option value="change-status">{{ __('messages.lbl_status') }}</option>
                             @endcan
                            @can('delete_bed_master')
                            @if(!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
                            <option value="delete">{{ __('messages.delete') }}</option>
                            @endif
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
                    {{-- <div class="datatable-filter">
                        <select name="column_status" id="column_status" class="select2 form-control" data-filter="select"
                            style="width: 100%">
                            <option value="">{{ __('messages.all') }}</option>
                            <option value="0" {{ $filter['status'] == '0' ? 'selected' : '' }}>
                                {{ __('messages.inactive') }}</option>
                            <option value="1" {{ $filter['status'] == '1' ? 'selected' : '' }}>
                                {{ __('messages.active') }}</option>
                        </select>
                    </div> --}}
                </div>
                <div class="input-group flex-nowrap">
                    <span class="input-group-text" id="addon-wrapping"><i
                            class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control dt-search" placeholder="{{ __('messages.search') }}"
                        aria-label="Search" aria-describedby="addon-wrapping">
                </div>

                @can('add_bed_master')
                @if(!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
                <a href="{{ route('backend.' . $module_name . '.create') }}"
                    class="btn btn-primary d-flex align-items-center gap-1" id="add-post-button">
                    <i class="ph ph-plus-circle"></i>{{ __('messages.new') }}
                </a>
                @endif
                @endcan 
            </x-slot>
        </x-backend.section-header>

        <table id="datatable" class="table table-responsive">
            <!-- Table header with the required fields -->
        </table>
    </div>

    @if (session('success'))
        <div class="snackbar" id="snackbar">
            <div class="d-flex justify-content-around align-items-center">
                <p class="mb-0">{{ session('success') }}</p>
                <a href="#" class="dismiss-link text-decoration-none text-success"
                    onclick="dismissSnackbar(event)">{{ __('messages.dismiss') }}</a>
            </div>
        </div>
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
        const columns = [
            @if(!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
            {
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" data-type="bedmaster" onclick="selectAllTable(this)">',
                orderable: false,
                searchable: false,
            },
            @endif
            {
                data: 'updated_at',
                name: 'updated_at',
                title: "{{ __('messages.update_at') }}",
                visible: false,
            },
            {
                data: 'bed',
                name: 'bed',
                title: "{{ __('messages.bed') }}"
            },
            {
                data: 'bed_type',
                name: 'bed_type',
                title: "{{ __('messages.bed_type') }}"
            },
            @if(multiVendor() == 1)
            {
                data: 'clinic_admin',
                name: 'clinic_admin',
                title: "{{ __('messages.lbl_click_admin') }}"
            },
            @endif
            @if(multiVendor() == 1)
            {
                data: 'clinic',
                name: 'clinic',
                title: "{{ __('messages.clinics') }}"
            },
            @endif
            {
                data: 'charges',
                name: 'charges',
                title: "{{ __('messages.charges') }}"
            },
            {
                data: 'capacity',
                name: 'capacity',
                title: "{{ __('messages.bed_capacity') }}"
            },
            {
                data: 'status',
                name: 'status',
                title: "{{ __('messages.status') }}"
            },
            {
                data: 'is_under_maintenance',
                name: 'is_under_maintenance',
                title: "{{ __('messages.under_maintenance') }}"
            },
            // {
            //     data: 'description',
            //     name: 'description',
            //     title: "{{ __('messages.description') }}"
            // },
            @if(!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false,
                title: "{{ __('messages.action') }}",
                className: 'text-end'
            }
            @endif
        ];
        document.addEventListener('DOMContentLoaded', (event) => {
            // Initialize Select2 with width 100%
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2').select2({
                    width: '100%'
                });
            }
            
            initDatatable({
                url: '{{ route('backend.bed-master.index_data') }}',
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
                    // Always set status dropdown to "Active" (value="1") when showing
                    $('#status').val('1');
                    if (typeof $.fn.select2 !== 'undefined' && $('#status').hasClass('select2-hidden-accessible')) {
                        $('#status').trigger('change.select2');
                    } else {
                        $('#status').trigger('change');
                    }
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

        $(document).on('change', '.maintenance-toggle', function() {
            var id = $(this).data('id');
            var url = "{{ route('backend.bed-master.toggle_maintenance', ':id') }}".replace(':id', id);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status) {
                        window.renderedDataTable.ajax.reload(null, false);
                        
                        // Reset quick action status dropdown to "Active" (default)
                        $('#status').val('1');
                        if (typeof $.fn.select2 !== 'undefined' && $('#status').hasClass('select2-hidden-accessible')) {
                            $('#status').trigger('change.select2');
                        } else {
                            $('#status').trigger('change');
                        }
                        
                        // Reset quick action type dropdown
                        $('#quick-action-type').val('');
                        if (typeof $.fn.select2 !== 'undefined' && $('#quick-action-type').hasClass('select2-hidden-accessible')) {
                            $('#quick-action-type').trigger('change.select2');
                        } else {
                            $('#quick-action-type').trigger('change');
                        }
                        resetQuickAction();
                    }
                }
            });
        });

        $(document).on('change', '.status-toggle', function() {
            var id = $(this).data('id');
            var url = "{{ route('backend.bed-master.toggle_status', ':id') }}".replace(':id', id);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status) {
                        window.renderedDataTable.ajax.reload(null, false);
                        
                        // Reset quick action status dropdown to "Active" (default)
                        $('#status').val('1');
                        if (typeof $.fn.select2 !== 'undefined' && $('#status').hasClass('select2-hidden-accessible')) {
                            $('#status').trigger('change.select2');
                        } else {
                            $('#status').trigger('change');
                        }
                        
                        // Reset quick action type dropdown
                        $('#quick-action-type').val('');
                        if (typeof $.fn.select2 !== 'undefined' && $('#quick-action-type').hasClass('select2-hidden-accessible')) {
                            $('#quick-action-type').trigger('change.select2');
                        } else {
                            $('#quick-action-type').trigger('change');
                        }
                        resetQuickAction();
                    }
                }
            });
        });
    </script>
@endpush
