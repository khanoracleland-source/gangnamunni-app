@extends('frontend::layouts.master')

@section('title', __('frontend.my_appointments'))

@section('content')
@include('frontend::components.section.breadcrumb')
    <div class="list-page section-spacing px-0">
        <div class="page-title" id="page_title">
            <div class="container">
            <div class="row gy-2">
                    <div class="col-xl-7 col-lg-9 filter">
                        <div class="d-flex flex-lg-row flex-column gap-3 align-items-lg-center">
                            <!-- <h6 class="m-0 flex-shrink-0">{{ __('frontend.filter_by') }}</h6> -->
                            
                        </div>
                    </div>
                    <div class="col-xl-2 d-xl-block d-none"></div>
                    <div class="col-lg-3">
                        <div class="d-flex align-items-center p-2 rounded section-bg">
                            <div class="icon ps-2">
                                <i class="ph ph-magnifying-glass align-middle"></i>
                            </div>
                            <input type="text" id="datatable-search"
                                class="form-control px-2 py-2 h-auto border-0 focus:ring-0" placeholder="{{ __('messages.search') }}...">
                        </div>
                    </div>
                </div>
                <div class="tab-content mt-5">
                    <div class="tab-pane active p-0 all-appointments" id="all-medicine">
                        <ul class="list-inline m-0">
                            <div id="shimmer-loader" class="d-flex gap-3 flex-wrap p-4 shimmer-loader">
                                @for ($i = 0; $i < 8; $i++)
                                    @include('frontend::components.card.shimmer_medicine_card')
                                @endfor
                            </div>
                            <table id="datatable" class="table table-responsive custom-card-table">
                            </table>
                        </ul>
                    </div>
                   
                   
                </div>
            </div>
        </div>
    </div>
  


    <!-- rate us modal -->
    <x-frontend::section.review />
@endsection

@push('after-styles')
    <!-- DataTables Core and Extensions -->
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('after-scripts')
    <!-- DataTables Core and Extensions -->
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Add Axios CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script type="text/javascript" defer>
        let finalColumns = [{
            data: 'card',
            name: 'card',
            orderable: false,
            searchable: true
        }];
        let datatable = null;
        let encounterId = @json(optional($encounter)->id ?? null);
        const searchInput = document.getElementById('datatable-search');



        document.addEventListener('DOMContentLoaded', (event) => {

            let activeTab = "all-medicine"; // Default active tab
            dataTableReload(activeTab); // Load for the default tab

        });

        function dataTableReload(activeTab) {
            if ($.fn.dataTable.isDataTable('#datatable')) {
                $('#datatable').DataTable().clear().destroy();
            }
            const shimmerLoader = document.querySelector('.shimmer-loader');
            const dataTableElement = document.getElementById('datatable');

            frontInitDatatable({
                url: '{{ route('medicine.index_data') }}',
                finalColumns,
                cardColumnClass: 'row-cols-1',
                advanceFilter: () => {
                    return {
                        activeTab: activeTab,
                        encounterId: encounterId,
                        search: searchInput.value // ✅ FIXED
                    }
                },
                onLoadStart: () => {
                    shimmerLoader.classList.remove('d-none');
                    dataTableElement.classList.add('d-none');
                },
                onLoadComplete: () => {
                    shimmerLoader.classList.add('d-none');
                    dataTableElement.classList.remove('d-none');
                },
            });

            // ✅ Search listener
            searchInput.addEventListener('keyup', function () {
                $('#datatable').DataTable().ajax.reload();
            });
        }


        // Initialize Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        

    </script>
@endpush
