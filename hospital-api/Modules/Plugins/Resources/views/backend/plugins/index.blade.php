@extends('backend.layouts.app')

@section('title')
{{ __($module_title) }}
@endsection
<style>
    .line-one {
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        line-clamp: 1;
        -webkit-box-orient: vertical
    }

    .loader-div {
        display: none;
        position: fixed;
        z-index: 30001;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.6);
        /* Light overlay */
    }

    .loader-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 14px;
        /* Small text */
        font-weight: normal;
        color: #6c757d;
        /* Bootstrap muted gray */
        font-family: sans-serif;
        opacity: 0.9;
    }

    .loader-img {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        margin: auto;
    }
</style>
@section('content')
<div class="col-lg-12">
    <div class="row gy-3 mb-5">
        <div class="col-md-6">
            <a class="btn btn-primary" href="{{ route('backend.plugins.create') }}"> <i class="me-2 fa-solid fa-upload"></i> {{ __('messages.upload_file') }} </a>
        </div>
        <div class="col-md-6 btn-toolbar gap-3 align-items-center justify-content-end">
            <div class="input-group flex-nowrap border rounded">
                <span class="input-group-text" id="addon-wrapping"><i
                        class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="searchMe" value="{{ @$queryString }}" class="form-control dt-search" placeholder="{{ __('messages.search') }}" aria-label="Search"
                    aria-describedby="addon-wrapping">
            </div>
        </div>
    </div>
    @if(count($result) > 0)


    <div class="row" id="filteredData">
        @foreach($result as $key=>$value)
        @php
        if($value['status'] == 1)
        {
        $statusVal = 0;
        $status = __("messages.active");
        $class = "success";
        $statusName = __("messages.deactivated");
        $statusNameClass = "danger";
        }else{
        $statusVal = 1;
        $status = __("messages.deactive");
        $class = "danger";
        $statusName = __("messages.activated");
        $statusNameClass = "success";
        }

        @endphp

        @php
        $oldPlugins = Modules\Plugins\Models\Plugins::whereNull('deleted_at')
        ->where('old_plugin_id', $value->id)
        ->orderBy('id','DESC')
        ->first(['id','version']);
        @endphp
        <div class="col-lg-4">
            <div class="card card-block card-stretch card-height overflow-hidden">
                <div class="card-body">
                    <div>
                        <div class="row">
                            <div class="col-lg-2">
                                @if(strtolower($value['plugin_name']) == 'pharma')
                                <i class="px-2 fa-solid fa-pills text-primary" style="font-size:35px"></i>
                                @else
                                <i class="px-2 fa-solid fa-gear" style="font-size:35px"></i>
                                @endif
                            </div>
                            <div class="col-lg-8">
                                <h5 class="line-one"> {{ ucwords($value['plugin_name']) }}</h5>

                                <p> {{ __('messages.version') }} : <span class="text-info fw-bold"> {{ $value['version'] }} </span> </p>
                            </div>
                            <div class="col-lg-2" style="padding-left:0px">
                                <span class="badge bg-{{ $class }}">{{ $status }}</span>
                            </div>
                        </div>
                        @if (empty($oldPlugins))
                        <p class="line-count-5">@if(strtolower($value['plugin_name']) == 'pharma'){{ __('messages.pharma_description') }}@else{{ $value['description'] }}@endif</p>
                        @else
                        <p class="line-count-3">@if(strtolower($value['plugin_name']) == 'pharma'){{ __('messages.pharma_description') }}@else{{ $value['description'] }}@endif</p>
                        @endif

                        @if($value['status'] == 0)
                        <button type="button" id="actionBtn" data-id="{{ $value['id'] }}" data-type="activated" title="{{ __('messages.if_you_want_to_active_this_plugin') }}" class="me-3 btn btn-success">
                            {{ __('messages.activate') }}
                        </button>
                        <button type="button" id="actionBtn" data-id="{{ $value['id'] }}" data-type="deleted" data-plugin="{{ $value['plugin_name'] }}" title="{{ __('messages.if_you_want_to_uninstall_this_plugin') }}" class="btn btn-danger">
                            {{ __('messages.delete') }}
                        </button>
                        @else
                        <button type="button" id="actionBtn" data-id="{{ $value['id'] }}" data-type="deactivated" title="{{ __('messages.if_you_want_to_deactive_this_plugin') }}" class="btn btn-danger">
                            {{ __('messages.deactivate') }}
                        </button>

                        <div class="col-lg-12 mt-4">
                            <div class="row gy-2">
                                @if (!empty($oldPlugins))
                                <div class="col-lg-7">
                                    <p>{{ __('messages.latest_version') }} : <span class="text-info fw-bold"> {{ @$oldPlugins->version }} </span> </p>
                                    <p class="m-0 cursor-pointer text-success fw-bold" id="actionBtn" data-id="{{ $value['id'] }}" data-type="updated" title="{{ __('messages.if_you_want_to_update_this_plugin') }}">
                                        <i class="fa fa-refresh me-2"></i>
                                        {{__('messages.update_available')}}
                                    </p>
                                </div>

                                <div class="col-lg-5">
                                    {{-- <p class="m-0">Latest Version : <span class="text-info fw-bold"> {{ @$oldPlugins->version }} </span> </p> --}}
                                    <p  class="cursor-pointer text-heading fw-bold text-end"  id="actionBtn" data-id="{{ $value['id'] }}" data-type="change_log" title="{{ __('messages.if_you_want_to_show_change_log') }}">
                                        <i class="fa fa-clock me-2" aria-hidden="true"></i>
                                        {{ __('messages.change_log') }}
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>

                        @endif


                    </div>
                </div>
            </div>
        </div>
        <!-- static card -->
        {{-- <div class="col-md-6 col-lg-4">
            <div class="card card-block card-stretch card-height overflow-hidden">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-body p-3 rounded-circle">
                                <i class="ph ph-gear-six text-heading"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-1">Pharma</h3>
                                <p class="font-size-14 fw-semibold text-body mb-0">Version : <span class="fw-semibold text-primary">20.6v</span></p>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success rounded-pill font-size-12 px-3 py-2">Active</span>
                    </div>

                    <div class="mb-4">
                        <p class="fs-6 fw-normal lh-base">
                            Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.
                        </p>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-danger fw-semibold px-4 py-2">
                            Deactivate
                        </button>
                    </div>

                </div>
            </div>
        </div> --}}
        <!-- end static card -->
        @endforeach

        <div class="pagination">
            {{ $result->appends(['queryString' => @$queryString])->render() }}
        </div>
    </div>
    @else
    <div class="col-lg-12 text-center">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-0">{{ __('messages.no_record_found') }}</h5>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- change log model -->
<div class="modal fade change-log-modal" id="change-log-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content position-relative">
            <button type="button" class="position-absolute custom-close-btn btn btn-primary p-1 rounded-circle" data-bs-dismiss="modal">
                <i class="ph ph-x text-white fw-bold align-middle"></i>
            </button>
            <div class="bg-body rounded p-5 text-center mb-0">
                <h5 class="mb-3">{{ __('messages.change_logs') }}</h5>

                <div class="col-lg-12">
                    <p id="log-details"></p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- end change log model -->
<div class="loader-div" style="display: none">
    <div class="loader-text">{{ __('messages.processing') }}</div>
</div>

@endsection

@push('after-scripts')

<script type="text/javascript" defer>
    // $('#actionBtn').on('click', function(e)
    $('body').on('click', '#actionBtn', function(e) {
        const dataId = this.getAttribute('data-id');
        const dataType = this.getAttribute('data-type');

        if (dataType == 'activated') {
            var $btn = $(this).prop('disabled', true).text(@json(__('messages.activating')));
            PluginActivated(dataId);
        }

        if (dataType == 'deactivated') {
            var $btn = $(this).prop('disabled', true).text(@json(__('messages.deactivating')));
            PluginDeActivated(dataId);
        }

        if (dataType == 'deleted') {
            const dataPlugin = this.getAttribute('data-plugin');
            // var $btn = $(this).prop('disabled', true).text('Deleteting...');
            DeletePlugin(dataId, dataPlugin);
        }

        if (dataType == 'change_log') {
            // var $btn = $(this).prop('disabled', true).text('Deleteting...');
            ChangeLogs(dataId);
        }

        if (dataType == 'updated') {
            var $btn = $(this).prop('disabled', true).text(@json(__('messages.updating')));
            PluginUpdated(dataId);
        }


    });


    function PluginActivated(id) {
        const actionValue = id;
        var url = "{{ route('backend.plugins.activate', ['id' => ':id']) }}";
        url = url.replace(':id', id);

        $.ajax({
            type: 'GET',
            url: url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                successSnackbar(response.message);
                setTimeout(function() {
                    location.reload();
                }, 2500);
            },
            error: function(response) {
                window.errorSnackbar(@json(__('messages.something_went_wrong')))
                $('#actionBtn').prop('disabled', false).text(@json(__('messages.activate')));
            }
        })
    }

    function PluginDeActivated(id) {
        const actionValue = id;
        var url = "{{ route('backend.plugins.deactivate', ['id' => ':id']) }}";
        url = url.replace(':id', id);

        $.ajax({
            type: 'GET',
            url: url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                successSnackbar(response.message);
                setTimeout(function() {
                    location.reload();
                }, 2500);
            },
            error: function(response) {
                window.errorSnackbar(@json(__('messages.something_went_wrong')))
                $('#actionBtn').prop('disabled', false).text(@json(__('messages.deactivate')));
            }
        })
    }

    function DeletePlugin(id, dataPlugin) {

        const actionValue = id;
        var url = "{{ route('backend.plugins.delete', ['id' => ':id']) }}";
        url = url.replace(':id', id);

        var message = @json(__('messages.confirm_delete', ['plugin' => ':plugin']));
        message = message.replace(':plugin', dataPlugin);
        confirmSwal(message).then((result) => {
            if (!result.isConfirmed) return
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    Swal.fire({
                        title: @json(__('messages.deleted_title')),
                        text: response.message,
                        icon: 'success',
                        didOpen: () => {
                            document.querySelector('.swal2-html-container').style.color = 'var(--bs-body-color)';
                            document.querySelector('.swal2-title').style.color = 'var(--bs-body-color)';
                        }
                    })
                    window.location.reload();
                    successSnackbar(response.message);
                },
                error: function(response) {
                    errorSnackbar(@json(__('messages.something_went_wrong')));
                }
            });
        })
    }

    function PluginUpdated(id) {
        const actionValue = id;
        if (id != '') {
            var url = "{{ route('backend.plugins.update-plugin', ['id' => ':id']) }}";
            url = url.replace(':id', id);

            $.ajax({
                type: 'GET',
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    successSnackbar(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2500);
                },
                error: function(response) {
                    window.errorSnackbar(@json(__('messages.something_went_wrong')))
                    $('#actionBtn').prop('disabled', false).text(@json(__('messages.update_now')));
                }
            })
        }
    }

    function ChangeLogs(id) {
        const actionValue = id;
        if (id != '') {
            var url = "{{ route('backend.plugins.logs', ['id' => ':id']) }}";
            url = url.replace(':id', id);

            $.ajax({
                type: 'GET',
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#log-details').html(response.data);
                    $('#change-log-modal').modal('show');
                },
                error: function(response) {
                    window.errorSnackbar('Something went wrong.')
                }
            })
        }
    }

    let searchTimeout = null;
    $('#searchMe').on('keyup', function() {
        clearTimeout(searchTimeout);
        const value = this.value;
        searchTimeout = setTimeout(function() {
            lookup(value);
        }, 300); // 300ms debounce
    });

    function lookup(searchinput) {
        if (searchinput.length == 0) {
            $('.loader-div').show();
            window.location.replace("{{ route('backend.plugins.index') }}");
        } else {
            var url1 = "{{ route('backend.plugins.index') }}";
            $('.loader-div').show();
            $.ajax({
                type: 'GET',
                url: url1,
                data: {
                    "queryString": searchinput
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $("#filteredData").html(response.data).show();
                    $('.loader-div').hide();
                },
                error: function(response) {
                    $('.loader-div').hide();
                    window.errorSnackbar(@json(__('messages.something_went_wrong')))
                }
            })
        }
    }
    @if(session('success'))
    window.addEventListener('DOMContentLoaded', () => {
        window.successSnackbar(@json(session('success')));
    });
    @endif

    @if(session('error'))
    window.addEventListener('DOMContentLoaded', () => {
        window.errorSnackbar(@json(session('error')));
    });
    @endif
</script>
@endpush