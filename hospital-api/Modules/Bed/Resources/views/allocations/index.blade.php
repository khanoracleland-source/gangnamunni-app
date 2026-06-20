@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="card-main mb-5">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                @if (auth()->user()->can('edit_allocations') ||
                        auth()->user()->can('delete_allocations'))
                    <x-backend.quick-action url="{{ route('backend.bed-allocation.bulk_action') }}">
                        <div class="">
                            <select name="action_type" class="form-control select2 col-12" id="quick-action-type"
                                style="width:100%">
                                <option value="">{{ __('messages.no_action') }}</option>
                                @can('delete_allocations')
                                    <option value="delete">{{ __('messages.delete') }}</option>
                                @endcan
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
                            <option value="0">{{ __('messages.inactive') }}</option>
                            <option value="1">{{ __('messages.active') }}</option>
                        </select>
                    </div> --}}
                </div>
                <div class="input-group flex-nowrap">
                    <span class="input-group-text" id="addon-wrapping"><i
                            class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control dt-search" placeholder="{{ __('messages.search') }}"
                        aria-label="Search" aria-describedby="addon-wrapping">
                </div>
                
                @can('add_allocations') 
                <a href="{{ route('backend.' . $module_name . '.create') }}"
                    class="btn btn-primary d-flex align-items-center gap-1">
                    <i class="ph ph-plus-circle"></i>{{ __('messages.new') }}
                </a>
                @endcan 
            </x-slot>
        </x-backend.section-header>

        <table id="datatable" class="table table-responsive">
            <!-- Table will be rendered by DataTable -->
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
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script src="{{ asset('js/form-modal/index.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script type="text/javascript" defer>
        const columns = [{
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" data-type="bed-allocation" onclick="bedAllocationSelectAll(this)">',
                exportable: false,
                orderable: false,
                searchable: false,
            },
            {
                data: 'patient_name',
                name: 'patient_name',
                title: "{{ __('messages.patient_name') }}",
            },
            @if(multiVendor() == 1)
            {
                data: 'clinic_admin_name',
                name: 'clinic_admin_name',
                title: "{{ __('messages.lbl_click_admin') }}",
            },
            @endif
            {
                data: 'room_number',
                name: 'room_number',
                title: "{{ __('messages.room') }}",
            },
            {
                data: 'bed_type',
                name: 'bed_type',
                title: "{{ __('messages.bed_type') }}",
            },
            {
                data: 'assign_date',
                name: 'assign_date',
                title: "{{ __('messages.assign_date') }}",
            },
            {
                data: 'discharge_date',
                name: 'discharge_date',
                title: "{{ __('messages.discharge_date') }}",
            },
            {
                data: 'temperature',
                name: 'temperature',
                title: "{{ __('messages.temperature') }}",
            },
            {
                data: 'symptoms',
                name: 'symptoms',
                title: "{{ __('messages.symptoms') }}",
                render: function(data, type, row) {
                    if (!data || data === '') {
                        return '';
                    }
                    // Split text into chunks of 30 characters and join with <br> for line breaks
                    var formattedText = '';
                    for (var i = 0; i < data.length; i += 30) {
                        if (i > 0) {
                            formattedText += '<br>';
                        }
                        formattedText += data.substring(i, i + 30);
                    }
                    return formattedText;
                }
            },
            {
                data: 'charge',
                name: 'charge',
                title: "{{ __('messages.charges') }}",
            },
            {
                data: 'payment_status',
                name: 'payment_status',
                title: "{{ __('messages.payment_status') }}",
            },
            // {
            //     data: 'status_toggle',
            //     name: 'status_toggle',
            //     title: "{{ __('Status') }}",
            //     orderable: false,
            // },
            {
                data: 'action',
                name: 'action',
                title: "{{ __('messages.action') }}",
                orderable: false,
                searchable: false,
                className: 'text-end'
            }
        ];

        document.addEventListener('DOMContentLoaded', (event) => {
            // Initialize Select2 for quick action dropdown
            if (typeof $.fn.select2 !== 'undefined') {
                if (!$('#quick-action-type').hasClass('select2-hidden-accessible')) {
                    $('#quick-action-type').select2({ width: '100%', minimumResultsForSearch: Infinity });
                }
            }

            // Initialize DataTable
            const dataTable = initDatatable({
                url: '{{ route('backend.bed-allocation.index_data') }}',
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

            // Add search functionality
            let searchTimeout;
            $('.dt-search').on('keyup', function() {
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Set a new timeout to debounce the search
                searchTimeout = setTimeout(() => {
                    if (window.renderedDataTable) {
                        // Trigger server-side search by reloading the DataTable
                        window.renderedDataTable.ajax.reload(null, false);
                    }
                }, 300); // Wait 300ms after user stops typing
            });

            // Handle checkbox selection - use global handler pattern
            $(document).on('change', '.select-table-row[data-type="bed-allocation"]', function() {
                console.log('[Bed Allocation] Checkbox changed');
                const checkboxId = $(this).attr('id');
                const rowId = checkboxId.replace('datatable-row-', '');
                
                // Call global dataTableRowCheck function if available
                if (typeof window.dataTableRowCheck === 'function') {
                    window.dataTableRowCheck(rowId);
                } else {
                    // Fallback: use global checkRow function
                    if (typeof window.checkRow === 'function') {
                        window.checkRow();
                    }
                }
                
                const selectedCount = $('.select-table-row:checked').length;
                console.log('[Bed Allocation] Selected count:', selectedCount);
                
                // Update form disabled state
                if (selectedCount > 0) {
                    $('#quick-action-form').removeClass('form-disabled');
                    $('#quick-action-type').removeAttr('disabled');
                    if (typeof $.fn.select2 !== 'undefined' && $('#quick-action-type').hasClass('select2-hidden-accessible')) {
                        $('#quick-action-type').prop('disabled', false).trigger('change.select2');
                    }
                } else {
                    $('#quick-action-form').addClass('form-disabled');
                    $('#quick-action-type').attr('disabled', true);
                    if (typeof $.fn.select2 !== 'undefined' && $('#quick-action-type').hasClass('select2-hidden-accessible')) {
                        $('#quick-action-type').prop('disabled', true).trigger('change.select2');
                    }
                }
            });
            
            // Handle select-all-table checkbox
            $(document).on('change', '#select-all-table', function() {
                console.log('[Bed Allocation] Select all changed');
                // The bedAllocationSelectAll function will handle this
            });

            // Let global handler in app.js handle form submission
            // Just add logging to verify it's working
            $(document).on('submit', '#quick-action-form', function(e) {
                console.log('[Bed Allocation] Form submit event triggered');
                const selectedIds = $("#datatable_wrapper .select-table-row:checked").map(function() {
                    return $(this).val();
                }).get();
                console.log('[Bed Allocation] Selected IDs for global handler:', selectedIds);
                const actionType = $('[name="action_type"]').val();
                console.log('[Bed Allocation] Action type:', actionType);
            });

            // Handle action type change
            function resetQuickAction() {
                const actionValue = $('#quick-action-type').val();
                const $quickActionApply = $('#quick-action-apply');

                if (actionValue != '') {
                    $quickActionApply.removeAttr('disabled');
                } else {
                    $quickActionApply.attr('disabled', true);
                }
            }

            $('#quick-action-type').on('change', function() {
                resetQuickAction();
            });

            // Log apply button click - let global handler in app.js handle form submission
            $('#quick-action-apply').on('click', function(e) {
                console.log('[Bed Allocation] Apply button clicked');
                const selectedIds = $('.select-table-row:checked').map(function() {
                    return $(this).val();
                }).get();
                console.log('[Bed Allocation] Selected IDs:', selectedIds);
                const actionType = $('#quick-action-type').val();
                console.log('[Bed Allocation] Action type:', actionType);
                // Let the form submit normally - global handler will process it
            });
        });

        function bedAllocationSelectAll(elem) {
            console.log('[Bed Allocation] selectAllTable called');
            // Use global selectAllTable function if available
            if (typeof window.selectAllTable === 'function') {
                window.selectAllTable(elem);
            } else {
                // Fallback: manual implementation
                const isChecked = $(elem).prop('checked');
                $('.select-table-row[data-type="bed-allocation"]').prop('checked', isChecked);
                // Trigger change event on all checkboxes
                $('.select-table-row[data-type="bed-allocation"]').trigger('change');
                // Call checkRow to update UI
                if (typeof window.checkRow === 'function') {
                    window.checkRow();
                }
            }
        }

        $('#reset-filter').on('click', function(e) {
            $('#column_status').val('');
            if (window.renderedDataTable) {
                window.renderedDataTable.ajax.reload(null, false);
            }
        });
    </script>
@endpush
