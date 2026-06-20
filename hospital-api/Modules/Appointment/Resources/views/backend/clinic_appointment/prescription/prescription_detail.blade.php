@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="table-content mb-3">
        <div class="card mb-4">
            <div class="card-body" id="pharma-detail-section">
                
            </div>
        </div>


        <table id="datatable" class="table table-responsive">
        </table>
        <!-- Static Add Extra Medicine & Payment Detail Section -->
        @if (!($prescriptionStatus == 1 || $paymentStatus == 1))
            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.add_extra_medicine'))
                <div class="d-flex justify-content-between align-items-center mt-5 bg-gray-900 p-3 rounded">
                    <h6 class="fw-bold mb-0">{{ __('pharma::messages.add_extra_medicine') }}</h6>
                    <a href="{{ route('backend.prescription.add_extra_medicine', $encounterId) }}"
                        class="btn btn-primary text-decoration-none">
                        {{ __('pharma::messages.add_medicine') }}
                    </a>
                </div>
            @endif
        @endif


        <h6 class="fw-bold mt-5">{{ __('pharma::messages.payment_detail') }}</h6>
        <div id="payment-detail-section">
            {{-- <div class="card-body">
            <div class="d-flex justify-content-between py-2">
                <div>{{ __('pharma::messages.medicine_total') }}</div>
                <div class="fw-semibold">${{ number_format($totalMedicinePrice, 2) }}</div>
            </div>

            @if (!empty($exclusiveTaxes))
                <div class="d-flex justify-content-between py-2 mt-3">
                    <strong>{{ __('pharma::messages.tax') }}</strong>
                </div>
                @foreach ($exclusiveTaxes as $tax)
                    @php
                        $amount = $tax['type'] === 'percent'
                            ? ($prescription->medicine_price * $tax['value'] / 100)
                            : $tax['value'];
                    @endphp
                    <div class="d-flex justify-content-between py-1">
                        <div>{{ $tax['title'] ?? 'Exclusive Tax' }} ({{ $tax['value'] }}{{ $tax['type'] === 'percent' ? '%' : '' }})</div>
                        <div class="fw-semibold">${{ number_format($amount, 2) }}</div>
                    </div>
                @endforeach
            @endif

            <div class="d-flex justify-content-between pt-3">
                <strong>{{ __('pharma::messages.grand_total') }}</strong>
                <strong class="text-danger">${{ number_format($totalAmount, 2) }}</strong>
            </div>
        </div> --}}
        </div>


    </div>

    <div class="offcanvas offcanvas-end offcanvas-w-40" tabindex="-1" id="supplierDetailsOffcanvas"
        aria-labelledby="supplierDetailsLabel">
        <div class="offcanvas-header mb-5 pb-5 border-bottom-gray-700">
            <h5 class="mb-0" id="supplierDetailsLabel">Supplier Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0" id="supplierDetailsContent">
            {{-- Content loaded via AJAX --}}
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
    </div>
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script src="{{ asset('modules/pharma/script.js') }}"></script>
    <script src="{{ asset('js/form-offcanvas/index.js') }}" defer></script>
    <script src="{{ asset('js/form-modal/index.js') }}" defer></script>
    <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>

    <script type="text/javascript" defer>
        const prescriptionId = @json($encounterId);
        @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.payment_detail'))
            const paymentDetailRoute = @json(route('backend.prescription.payment_detail', ['id' => '___ID___']));
        @else
            const paymentDetailRoute = null;
        @endif
        @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.show-pharma-info'))
            const pharmaDetailRoute = @json(route('backend.prescription.show-pharma-info', ['id' => '___ID___']));
        @else
            const pharmaDetailRoute = null;
        @endif
        // console.log('prescriptionId', prescriptionId);
        const prescriptionStatus = {{ $prescriptionStatus ?? 0 }};
        const paymentStatus = {{ $paymentStatus ?? 0 }};

        window.afterPrescriptionDelete = function(prescriptionId) {
            console.log("ererere");

            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.payment_detail'))
                $.ajax({
                    url: "{{ route('backend.prescription.payment_detail', ['id' => '__ID__']) }}".replace('__ID__',
                        prescriptionId),
                    type: 'GET',
                    success: function(response) {
                        $('#payment-detail-section').html(response);
                    }
                });
            @endif
            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.show-pharma-info'))
                $.ajax({
                    url: "{{ route('backend.prescription.show-pharma-info', ['id' => '__ID__']) }}".replace('__ID__',
                        prescriptionId),
                    type: 'GET',
                    success: function(response) {
                        $('#pharma-detail-section').html(response);
                    }
                });
            @endif
        }

        // console.log('prescriptionId', prescriptionId);
        const columns = [
            // {
            //     name: 'check',
            //     data: 'check',
            //     title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
            //     width: '0%',
            //     exportable: false,
            //     orderable: false,
            //     searchable: false,
            // },
            {
                data: 'name',
                name: 'name',
                title: "{{ __('pharma::messages.medicine_name') }}"
            },
            {
                data: 'category',
                name: 'category',
                title: "{{ __('pharma::messages.category') }}"
            },
            {
                data: 'form',
                name: 'form',
                title: "{{ __('pharma::messages.form') }}"
            },
            {
                data: 'duration',
                name: 'duration',
                title: "{{ __('pharma::messages.days') }}"
            },
            {
                data: 'frequency',
                name: 'frequency',
                title: "{{ __('pharma::messages.frequency') }}"
            },
            {
                data: 'quantity',
                name: 'quantity',
                title: "{{ __('pharma::messages.quantity') }}"
            },
            {
                data: 'price',
                name: 'price',
                title: "{{ __('pharma::messages.price') }}"
            },
            {
                data: 'dosage',
                name: 'dosage',
                title: "{{ __('pharma::messages.dosage') }}"
            },
        ];


        const actionColumn = [{
            data: 'action',
            name: 'action',
            orderable: false,
            searchable: false,
            title: "{{ __('service.lbl_action') }}",
            width: '5%'
        }];

        let finalColumns;
        // console.log(!(prescriptionStatus == 1 || paymentStatus == 1));
        if (!(prescriptionStatus == 1 || paymentStatus == 1)) {
            finalColumns = [...columns, ...actionColumn];
        } else {
            finalColumns = [...columns];
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.user_prescription_detail'))
                initDatatable({
                    url: '{{ route('backend.prescription.user_prescription_detail') }}',
                    finalColumns,
                    advanceFilter: () => {
                        return {
                            'prescription_id': prescriptionId,
                        };
                    },
                    orderColumn: [
                        [1, "asc"]
                    ],
                });
            @endif

            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.payment_detail'))
                reloadPaymentDetail(prescriptionId);
            @endif
            @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.show-pharma-info'))
                reloadPharmaDetail(prescriptionId);
            @endif
            // $('#reset-filter').on('click', function(e) {
            //     $('#supplier_name').val('');
            //     $('#supplier_type').val('');
            //     $('#contact_number').val('');
            //     $('#pharma_id').val('');
            //     window.renderedDataTable.ajax.reload(null, false);
            // });

            // $(document).on('click', '.view-supplier-btn', function () {
            //     let supplierId = $(this).data('id');
            //     $('#supplierDetailsContent').html('<div class="text-center my-5"><div class="spinner-border text-primary" role="status"></div></div>');
            //     $('#supplierDetailsOffcanvas').offcanvas('show');

            //     $.ajax({
            //         url: `/app/suppliers/${supplierId}`,
            //         type: 'GET',
            //         success: function (response) {
            //             $('#supplierDetailsContent').html(response.html); // return HTML partial from controller
            //         },
            //         error: function () {
            //             $('#supplierDetailsContent').html('<p class="text-danger">Failed to load supplier details.</p>');
            //         }
            //     });
            // });
        });

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

        function reloadPaymentDetail(encounterId) {
            if (!paymentDetailRoute) {
                return; // Route doesn't exist, skip
            }
            const routeUrl = paymentDetailRoute.replace('___ID___', encounterId);

            $.ajax({
                url: routeUrl,
                type: 'GET',
                success: function(response) {
                    $('#payment-detail-section').html(response);
                },
                error: function() {
                    alert('Failed to reload payment details.');
                }
            });
        }
        function reloadPharmaDetail(encounterId) {
            if (!pharmaDetailRoute) {
                return; // Route doesn't exist, skip
            }
            const pharmaDetailRouteUrl = pharmaDetailRoute.replace('___ID___', encounterId);

            console.log('pharmaDetailRoute', pharmaDetailRoute);
            $.ajax({
                url: pharmaDetailRouteUrl,
                type: 'GET',
                success: function(response) {
                    console.log('response', response)
                    $('#pharma-detail-section').html(response);
                },
                error: function() {
                    alert('Failed to reload payment details.');
                }
            });
        }
    </script>
@endpush
