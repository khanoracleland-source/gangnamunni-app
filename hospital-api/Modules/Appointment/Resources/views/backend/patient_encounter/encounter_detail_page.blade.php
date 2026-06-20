@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
    <div class="row">
        <div class="col-xxl-3 col-lg-4 col-md-5 mb-4 mb-md-0">
            <h4 class="mb-3">{{ __('appointment.encounter_detail') }}</h4>
            <div class="card shadow-sm mb-3 overflow-hidden">
                <div class="card-header bg-primary py-2">
                    <h5 class="mb-0 text-white">{{ __('appointment.about_clinic') }}</h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex gap-3 align-items-start">
                        <img src="{{ optional($data->clinic)->file_url }}" alt="clinic-logo" class="avatar avatar-64 rounded"
                            style="width: 64px; height: 64px; object-fit: cover;">
                        <div class="flex-grow-1" style="min-width: 0;">
                            <h5 class="m-0 text-truncate" title="{{ optional($data->clinic)->name ?? '--' }}">
                                {{ optional($data->clinic)->name ?? '--' }}
                            </h5>
                            <p class="mb-2 font-size-14 text-truncate" title="{{ optional($data->clinic)->email ?? '--' }}">
                                {{ optional($data->clinic)->email ?? '--' }}
                            </p>
                            <h6 class="m-0 text-primary">Dr. {{ optional($data->doctor)->full_name ?? '--' }}</h6>
                        </div>
                    </div>
                    @if ($data->description)
                        <div class="mt-2 font-size-14 text-break">
                            {{ $data->description }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3 overflow-hidden">
                <div class="card-header bg-primary py-2">
                    <h5 class="mb-0 text-white">{{ __('appointment.about_patient') }}</h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <img src="{{ optional($data->user)->profile_image ?? default_user_avatar() }}" alt="patient-avatar"
                            class="avatar avatar-64 rounded-pill" style="width: 64px; height: 64px; object-fit: cover;">
                        <div class="flex-grow-1" style="min-width: 0;">
                            <h5 class="m-0 text-truncate"
                                title="{{ optional($data->user)->full_name ?? default_user_name() }}">
                                {{ optional($data->user)->full_name ?? default_user_name() }}
                            </h5>
                            <p class="mb-0 font-size-14 text-truncate" title="{{ optional($data->user)->email ?? '--' }}">
                                {{ optional($data->user)->email ?? '--' }}
                            </p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 border-top">
                        <span class="font-size-14">{{ __('appointment.encounter_date') }}:</span>
                        <span class="heading-color small">{{ formatDate($data->encounter_date) ?? '--' }}</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 border-top">
                        <span class="font-size-14">{{ __('appointment.address') }}:</span>
                        <span class="font-size-14">
                            @if ($data->user->address ?? false)
                                <div class="heading-color font-size-14">{{ $data->user->address }}</div>
                            @endif
                            <div>
                                @if ($data->user->cities->name ?? false)
                                    <span class="heading-color">{{ $data->user->cities->name }},</span>
                                @endif
                                @if ($data->user->countries->name ?? false)
                                    <span class="heading-color">{{ $data->user->countries->name }}</span>
                                @endif
                                @if ($data->user->pincode ?? false)
                                    <span class="heading-color">- {{ $data->user->pincode }}</span>
                                @endif
                            </div>
                        </span>

                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="font-size-14">{{ __('appointment.status') }}:</span>
                        @if ($data->status == 1)
                            <span class="badge bg-success">{{ __('appointment.open') }}</span>
                        @else
                            <span class="badge bg-danger">{{ __('appointment.close') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($data['status'] == 1)
                <div class="card shadow-sm custom-select-input overflow-hidden">
                    <div class="card-body p-3">
                        <h6 class="mb-2 text-primary">{{ __('appointment.select_encounter_templates') }}</h6>
                        <select name="template_id" id="template_id" class="form-control select2" data-filter="select"
                            data-placeholder="{{ __('clinic.lbl_select_template') }}">
                            <option value="">{{ __('clinic.lbl_select_template') }}</option>
                            @foreach ($template_data as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

        </div>
        <div class="col-xxl-9 col-lg-8 col-md-7">
            <h4 class="mb-3">{{ __('appointment.other_detail') }}</h4>

            <div>

                <nav class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div class="nav nav-tabs bg-transparent gap-4" id="nav-tab" role="tablist">
                        <button class="nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-home"
                            type="button" role="tab" aria-controls="nav-home"
                            aria-selected="true">{{ __('appointment.clinic_details') }}
                        </button>
                        <button class="nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-profile"
                            type="button" role="tab" aria-controls="nav-profile"
                            aria-selected="false">{{ __('appointment.soap') }}</button>
                        <button class="nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-contact"
                            type="button" role="tab" aria-controls="nav-contact"
                            aria-selected="false">{{ __('appointment.body_chart') }}</button>

                        @if (count($data['customform']) > 0)
                            <button class="nav-link" id="nav-custom-form-tab" data-bs-toggle="tab"
                                data-bs-target="#nav-custom-form" type="button" role="tab"
                                aria-controls="nav-custom-form" aria-selected="false">Custom Form</button>
                        @endif

                    </div>
                    @if ($data['status'] == 1)
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#generate_invoice">
                            <div class="d-inline-flex align-items-center gap-1">
                                <i class="ph ph-plus"></i>
                                {{ __('appointment.close_encounter') }} & {{ __('appointment.check_out') }}
                            </div>
                        </button>
                    @else
                        <a href="{{ url('app/billing-record/encounter_billing_detail') }}?id={{ $data['id'] }}">
                            <button class="btn btn-primary">
                                <i class="ph ph-file-text me-1"></i>
                                {{ __('appointment.billing_details') }}
                            </button>
                        </a>
                    @endif
                </nav>

                <div class="card">
                    <div class="card-body">
                        <div class="tab-content" id="nav-tabContent">
                            <div class="tab-pane fade show active" id="nav-home" role="tabpanel"
                                aria-labelledby="nav-home-tab" tabindex="0">

                                <div class="row">
                                    @if ($encounter_data['is_encounter_problem'] == 1)
                                        <div class="col-xl-4 col-lg-6" id="encounter_problem">
                                            @include(
                                                'appointment::backend.patient_encounter.component.encounter_problem',
                                                ['data' => $data, 'problem_list' => $problem_list]
                                            )
                                        </div>
                                    @endif

                                    @if ($encounter_data['is_encounter_observation'] == 1)
                                        <div class="col-xl-4 col-lg-6" id="encounter_observation">
                                            @include(
                                                'appointment::backend.patient_encounter.component.encounter_observation',
                                                [
                                                    'data' => $data,
                                                    'observation_list' => $observation_list,
                                                ]
                                            )
                                        </div>
                                    @endif

                                    @if ($encounter_data['is_encounter_note'] == 1)
                                        <div class="col-xl-4" id="encounter_note">
                                            @include(
                                                'appointment::backend.patient_encounter.component.encounter_note',
                                                ['data' => $data]
                                            )
                                        </div>
                                    @endif
                                </div>

                                <div class="mb-4">
                                    <div class="card-header px-0 mb-3 d-flex justify-content-between flex-wrap gap-3">
                                        <h5 class="card-title">{{ __('appointment.medical_report') }}</h5>
                                        @if ($data['status'] == 1)
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#addMedicalreport">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <i class="ph ph-plus"></i>
                                                    {{ __('appointment.add_medical_report') }}
                                                </div>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="card-body bg-body" style="padding: 1px">
                                        <div id="medical_report_table">
                                            @include(
                                                'appointment::backend.patient_encounter.component.medical_report_table',
                                                ['data' => $data]
                                            )
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="card-header d-flex justify-content-between flex-wrap gap-3 px-0 mb-3">
                                        <h5 class="card-title">{{ __('appointment.prescription') }}</h5>
                                        <div>
                                            <div class="d-flex align-items-center flex-wrap gap-3">
                                                @if ($data['status'] == 1)
                                                    @if (checkPlugin('pharma') == 'active')
                                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                            data-bs-target="#addprescription">
                                                            <div class="d-inline-flex align-items-center gap-1">
                                                                <i class="ph ph-plus"></i>
                                                                {{ __('appointment.add_prescription') }}
                                                            </div>
                                                        </button>
                                                    @else
                                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                            data-bs-target="#addprescriptionswithoutpharma">
                                                            <div class="d-inline-flex align-items-center gap-1">
                                                                <i class="ph ph-plus"></i>
                                                                {{ __('appointment.add_prescription') }}
                                                            </div>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body bg-body" style="padding: 1px">
                                        <div id="prescription_table">
                                            @if (checkPlugin('pharma') != 'active')
                                                @include(
                                                    'appointment::backend.patient_encounter.component.prescription_without_pharma_table',
                                                    ['data' => $data]
                                                )
                                            @else
                                                @include(
                                                    'appointment::backend.patient_encounter.component.prescription_table',
                                                    ['data' => $data]
                                                )
                                            @endif
                                        </div>
                                    </div>
                                </div>


                                <div class="mb-4">
                                    <div class="card-header px-0 mb-3 d-flex justify-content-between flex-wrap gap-3">
                                        <h5 class="card-title">Bed Allocation</h5>
                                        @if ($data['status'] == 1)
                                            <a href="{{ route('backend.bed-allocation.create') }}?encounter_id={{ $data['id'] }}"
                                                class="btn btn-sm btn-primary gap-1">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <i class="ph ph-plus"></i>{{ __('messages.add_bed_allocation') }}
                                                </div>
                                            </a>
                                        @endif
                                    </div>

                                    <div class="card-body bg-body" style="padding: 1px">
                                        <div id="bed_allocation_table">
                                            @include(
                                                'appointment::backend.patient_encounter.component.bed_allocation_table',
                                                [
                                                    'data' => $data,
                                                    'bedAllocations' => $bedAllocations ?? [],
                                                ]
                                            )
                                        </div>
                                    </div>
                                </div>


                                <div class="other-detail">
                                    <div class="card-header px-0 mb-3">
                                        <h6 class="card-title mb-0">{{ __('appointment.other_information') }}
                                        </h6>
                                    </div>
                                    <div>
                                        <textarea class="form-control h-auto bg-body" rows="3"
                                            placeholder="{{ __('appointment.enter_other_details') }}" name="other_details" id="other_details"
                                            style="min-height: max-content">{{ old('other_details', $data['EncounterOtherDetails']['other_details'] ?? '') }}</textarea>
                                    </div>
                                </div>

                                @if ($data['status'] == 1)
                                    <div class="offcanvas-footer border-top pt-4" id="save_button">
                                        <div class="d-grid d-sm-flex justify-content-sm-end gap-3">
                                            <button class="btn btn-secondary" type="submit">
                                                {{ __('messages.save') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="nav-profile" role="tabpanel"
                                aria-labelledby="nav-profile-tab" tabindex="0">
                                <div id="soap">
                                    @include('appointment::backend.patient_encounter.component.soap', [
                                        'data' => $data,
                                    ])
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nav-contact" role="tabpanel"
                                aria-labelledby="nav-contact-tab" tabindex="0">

                                <div id="body_chart_list">
                                    @include(
                                        'appointment::backend.patient_encounter.component.body_chart_list',
                                        ['data' => $data]
                                    )
                                </div>

                                {{-- <div id="add_body_chart" class="" >

                                @include('appointment::backend.clinic_appointment.apointment_bodychartform', [
                                    'encounter_id' => $data['id'],
                                    'appointment_id' => $data['appointment_id'],
                                    'patient_id' => $data['user_id']
                                ])

                            </div> --}}

                            </div>

                            @if (count($data['customform']) > 0)
                                <div class="tab-pane fade" id="nav-custom-form" role="tabpanel"
                                    aria-labelledby="nav-custom-form-tab" tabindex="0">
                                    <div id="custom_form">
                                        @include(
                                            'appointment::backend.patient_encounter.component.custom_form',
                                            [
                                                'data' => $data['customform'],
                                                'encounter_id' => $data['id'],
                                                'appointment_id' => $data['appointment_id'],
                                            ]
                                        )
                                    </div>

                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if (checkPlugin('pharma') == 'active')
                    @include('appointment::backend.patient_encounter.component.prescription', [
                        'data' => $data,
                    ])
                @else
                    @include(
                        'appointment::backend.patient_encounter.component.without_pharma_prescription',
                        [
                            'data' => $data,
                        ]
                    )
                @endif

                @include('appointment::backend.patient_encounter.component.medical_report', [
                    'data' => $data,
                ])
                @include('appointment::backend.patient_encounter.component.billing_details', [
                    'data' => $data,
                    'bedAllocations' => $bedAllocations ?? [],
                ])
            </div>


            <!-- View Bed Allocation Modal -->
            <div class="modal fade" id="viewBedAllocationModal" tabindex="-1"
                aria-labelledby="viewBedAllocationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewBedAllocationModalLabel">Bed Allocation Details
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Patient Name:</label>
                                    <p id="view_patient_name">--</p>
                                </div>
                                @if (multiVendor() == 1)
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Clinic Admin:</label>
                                        <p id="view_clinic_admin">--</p>
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Bed Type:</label>
                                    <p id="view_bed_type">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Room/Bed:</label>
                                    <p id="view_room_bed">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Assign Date:</label>
                                    <p id="view_assign_date">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Discharge Date:</label>
                                    <p id="view_discharge_date">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Charge:</label>
                                    <p id="view_charge">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Temperature:</label>
                                    <p id="view_temperature">--</p>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Symptoms:</label>
                                    <p id="view_symptoms">--</p>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Description:</label>
                                    <p id="view_description">--</p>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Notes:</label>
                                    <p id="view_notes">--</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Price:</label>
                                    <p id="view_price">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        @endsection


        @push('after-scripts')
            <script>
                // Log script initialization
                console.log('Encounter Detail Page - Scripts Loading', {
                    'encounter_id': {{ $data->id ?? 'null' }},
                    'encounter_status': {{ $data->status ?? 'null' }},
                    'timestamp': new Date().toISOString(),
                    'jquery_loaded': typeof $ !== 'undefined',
                    'bootstrap_loaded': typeof bootstrap !== 'undefined'
                });
                
                // Check if view bed allocation buttons exist
                $(document).ready(function() {
                    const viewButtons = $('.view-bed-allocation');
                    console.log('View Bed Allocation - Buttons Found on Page Load', {
                        'button_count': viewButtons.length,
                        'button_ids': viewButtons.map(function() { return $(this).data('id'); }).get(),
                        'encounter_status': {{ $data->status ?? 'null' }}
                    });
                });
                
                // Only attach save button handler if button exists (may not exist when encounter is closed)
                const saveButton = document.getElementById('save_button');
                if (saveButton) {
                    saveButton.addEventListener('click', function() {
                    const encounterId = {{ $data->id }};
                    const userId = {{ $data->user_id }};
                        const template_id = document.getElementById('template_id') ? document.getElementById('template_id').value : '';
                        const other_details = document.getElementById('other_details') ? document.getElementById('other_details').value : '';

                    const data = {
                        encounter_id: encounterId,
                        template_id: template_id,
                        other_details: other_details,
                        user_id: userId,
                        _token: '{{ csrf_token() }}'
                    };

                    fetch('{{ route('backend.encounter.save-encounter') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': data._token
                            },
                            body: JSON.stringify(data)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data) {

                                window.successSnackbar(`Encounter saved successfully`);

                            } else {
                                window.errorSnackbar('Something went wrong! Please check.');
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                        });
                });
                } else {
                    console.log('Save button not found - encounter may be closed');
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const hash = window.location.hash;
                    if (hash) {
                        const tabButton = document.querySelector(`[data-bs-target="${hash}"]`);
                        if (tabButton) {

                            tabButton.click();

                            const tabContent = document.querySelector(hash);
                            if (tabContent) {
                                tabContent.scrollIntoView({
                                    behavior: 'smooth'
                                });
                            }
                        }
                    }
                });

                $(document).ready(function() {
                    var baseUrl = '{{ url('/') }}';
                    $('#template_id').change(function() {
                        let templateId = $(this).val();
                        let additionalData = {
                            user_id: '{{ $data['user_id'] ?? '' }}', // Use null coalescing operator for safety
                            encounter_id: '{{ $data['id'] ?? '' }}', // Same for the encounter ID
                            status: '{{ $data['status'] ?? '' }}',

                        };
                        // Clear the components section


                        if (templateId) {
                            $.ajax({
                                url: baseUrl + `/app/encounter/get-template-data/${templateId}`,
                                type: 'GET',
                                data: additionalData,
                                success: function(response) {
                                    console.log(response);
                                    // Append problems if available
                                    if (response.is_encounter_problem) {
                                        $('#encounter_problem').html('');
                                        $('#encounter_problem').append(response.problem_html);
                                    }
                                    // Append observations if available
                                    if (response.is_encounter_observation) {

                                        console.log(response.observation_html);
                                        $('#encounter_observation').html('');
                                        $('#encounter_observation').append(response.observation_html);
                                    }
                                    // Append notes if available
                                    if (response.is_encounter_note) {
                                        $('#encounter_note').html('');
                                        $('#encounter_note').append(response.note_html);
                                    }

                                    if (response.is_encounter_precreption) {
                                        $('#prescription_table').html('');
                                        $('#prescription_table').append(response.precreption_html);

                                        // const encounterId = '{{ $data['id'] ?? '' }}';
                                        // document.dispatchEvent(new CustomEvent('prescriptionSaved', {
                                        //     detail: {
                                        //         encounterId: encounterId
                                        //     }
                                        // }));
                                    }
                                    if (response.is_encounter_otherdetail) {

                                        $('#other_details').val(response.other_detail_html);
                                    }
                                },
                                error: function() {
                                    console.error('Failed to load template data.');
                                }
                            });
                        }
                    });


                    // Function to update bed allocation button visibility
                    function updateBedAllocationButton() {
                        const bedAllocationTable = $('#bed_allocation_table tbody');
                        // const addButton = $('#addBedAllocationBtn');

                        // Check if there are any actual bed allocation rows (not the "No bed allocation found" message)
                        const hasAllocations = bedAllocationTable.find('tr').filter(function() {
                            const rowText = $(this).find('td').text().trim();
                            return rowText && !rowText.includes('No bed allocation found');
                        }).length > 0;

                        console.log('Bed allocation check:', {
                            totalRows: bedAllocationTable.find('tr').length,
                            hasAllocations: hasAllocations,
                            // buttonFound: addButton.length > 0,
                            // buttonVisible: addButton.is(':visible')
                        });

                        if (hasAllocations) {
                            // addButton.hide();
                            console.log('Hiding Add Bed Allocation button');
                        } else {
                            // addButton.show();
                            console.log('Showing Add Bed Allocation button');
                        }
                    }

                    // Initial check for button visibility
                    updateBedAllocationButton();


                    // $('#editBedAllocationForm').on('submit', function(e) {
                    //     e.preventDefault();

                    //     const formData = new FormData(this);
                    //     const bedAllocationId = $('#edit_bed_allocation_id').val();
                    //     formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                    //     formData.append('_method', 'PUT');

                    //     $.ajax({
                    //         url: baseUrl + '/app/bed-allocation/' + bedAllocationId,
                    //         type: 'POST',
                    //         data: formData,
                    //         processData: false,
                    //         contentType: false,
                    //         success: function(response) {
                    //             if (response.success) {
                    //                 // Refresh the bed allocation table
                    //                 location.reload();

                    //                 $('#editBedAllocationModal').modal('hide');
                    //                 $('#editBedAllocationForm')[0].reset();

                    //                 // Reset select2 dropdowns
                    //                 $('#edit_clinic_admin_id').val('').trigger('change');
                    //                 $('#edit_bed_type_id').val('').trigger('change');
                    //                 $('#edit_room_no').empty().append('<option value="">Select Room/Bed</option>');

                    //                 // Update button visibility after successful update
                    //                 setTimeout(function() {
                    //                     updateBedAllocationButton();
                    //                 }, 100);

                    //                 Swal.fire({
                    //                     title: 'Success',
                    //                     text: response.message || 'Bed allocation updated successfully',
                    //                     icon: 'success',
                    //                     showClass: {
                    //                         popup: 'animate__animated animate__zoomIn'
                    //                     },
                    //                     hideClass: {
                    //                         popup: 'animate__animated animate__zoomOut'
                    //                     }
                    //                 });
                    //             } else {
                    //                 Swal.fire({
                    //                     title: 'Error',
                    //                     text: response.message || 'Failed to update bed allocation',
                    //                     icon: 'error',
                    //                     showClass: {
                    //                         popup: 'animate__animated animate__shakeX'
                    //                     },
                    //                     hideClass: {
                    //                         popup: 'animate__animated animate__fadeOut'
                    //                     }
                    //                 });
                    //             }
                    //         },
                    //         error: function(xhr, status, error) {
                    //             console.error(error);
                    //             let errorMessage = 'An unexpected error occurred.';

                    //             if (xhr.responseJSON && xhr.responseJSON.message) {
                    //                 errorMessage = xhr.responseJSON.message;
                    //             } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    //                 errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    //             }

                    //             Swal.fire({
                    //                 title: 'Error',
                    //                 text: errorMessage,
                    //                 icon: 'error',
                    //                 showClass: {
                    //                     popup: 'animate__animated animate__shakeX'
                    //                 },
                    //                 hideClass: {
                    //                     popup: 'animate__animated animate__fadeOut'
                    //                 }
                    //             });
                    //         }
                    //     });
                    // });


                    //     function switchToEditButton(encounterId) {
                    //         const btn = $('#prescription_btn');

                    //         btn.removeAttr('data-bs-toggle');
                    //         btn.removeAttr('data-bs-target');

                    //         btn.attr('data-encounter-id', encounterId);

                    //         btn.off('click').on('click', function() {
                    //             const id = $(this).data('encounter-id');
                    //             editPrescription(id);

                    //        // Update icon
                    //         btn.find('i').removeClass('ph-plus').addClass('ph-pencil-simple');

                    //         // Update text
                    //         btn.find('div').contents().filter(function() {
                    //             return this.nodeType === 3;
                    //         }).last().replaceWith(" {{ __('appointment.edit_prescription') }}");
                    //     });

                    // }


                    // Handle delete bed allocation
                    $(document).on('click', '.delete-bed-allocation', function() {
                        const bedAllocationId = $(this).data('id');

                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this!",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, delete it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const formData = new FormData();
                                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                                formData.append('_method', 'DELETE');

                                $.ajax({
                                    url: baseUrl + '/app/bed-allocation/' + bedAllocationId,
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        // Check for 'status' (the controller returns 'status', not 'success')
                                        if (response.status === true || response.status === 1) {
                                            Swal.fire(
                                                'Deleted!',
                                                response.message || 'Bed allocation deleted successfully.',
                                                'success'
                                            ).then(() => {
                                                // Refresh the bed allocation table after showing success message
                                                location.reload();
                                            });
                                        } else {
                                            Swal.fire(
                                                'Error!',
                                                response.message || 'Failed to delete bed allocation.',
                                                'error'
                                            );
                                        }
                                    },
                                    error: function() {
                                        Swal.fire(
                                            'Error!',
                                            'An unexpected error occurred.',
                                            'error'
                                        );
                                    }
                                });
                            }
                        });


                    });


                    // Listen for event dispatched from the modal
                    document.addEventListener('prescriptionSaved', function(e) {
                        switchToEditButton(e.detail.encounterId);
                    });

                    // Handle view bed allocation
                    // Use both document.on and direct binding to ensure it works
                    $(document).on('click', '.view-bed-allocation', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const bedAllocationId = $(this).data('id');
                        const button = $(this);
                        
                        console.log('View Bed Allocation - Button Clicked', {
                            'bedAllocationId': bedAllocationId,
                            'encounter_status': '{{ $data["status"] ?? null }}',
                            'button_exists': button.length > 0,
                            'button_html': button.html(),
                            'data_id': button.attr('data-id'),
                            'timestamp': new Date().toISOString()
                        });
                        
                        if (!bedAllocationId) {
                            console.error('View Bed Allocation - No ID found', {
                                'button': button,
                                'data_attributes': button.data()
                            });
                            Swal.fire({
                                title: 'Error',
                                text: 'Bed allocation ID not found.',
                                icon: 'error'
                            });
                            return false;
                        }

                        // Show loading state
                        $('#view_patient_name, #view_clinic_admin, #view_bed_type, #view_room_bed, #view_assign_date, #view_discharge_date, #view_charge, #view_temperature, #view_symptoms, #view_description, #view_notes, #view_price').text('Loading...');

                        const ajaxUrl = baseUrl + '/app/encounter/view-bed-allocation/' + bedAllocationId;
                        console.log('View Bed Allocation - Making AJAX Request', {
                            'url': ajaxUrl,
                            'bedAllocationId': bedAllocationId,
                            'baseUrl': baseUrl
                        });

                        $.ajax({
                            url: ajaxUrl,
                            type: 'GET',
                            beforeSend: function(xhr) {
                                console.log('View Bed Allocation - AJAX Before Send', {
                                    'url': ajaxUrl,
                                    'headers': xhr.getAllResponseHeaders()
                                });
                            },
                            success: function(response) {
                                console.log('View Bed Allocation - AJAX Success', {
                                    'response_status': response.status,
                                    'has_data': !!response.data,
                                    'response': response
                                });
                                
                                if (response.status && response.data) {
                                    const data = response.data;
                                    
                                    // Populate modal fields - controller returns data directly (not nested)
                                    $('#view_patient_name').text(data.patient_name || '--');
                                    
                                    @if (multiVendor() == 1)
                                    // Clinic admin is not returned by controller, so leave it as is or fetch separately
                                    // $('#view_clinic_admin').text(data.clinic_admin_name || '--');
                                    @endif
                                    
                                    $('#view_bed_type').text(data.bed_type || '--');
                                    $('#view_room_bed').text(data.room_bed || '--');
                                    $('#view_assign_date').text(data.assign_date || '--');
                                    $('#view_discharge_date').text(data.discharge_date || '--');
                                    
                                    // Charge is already formatted by controller (e.g., "₹467.00")
                                    $('#view_charge').text(data.charge || '--');
                                    
                                    $('#view_temperature').text(data.temperature || '--');
                                    $('#view_symptoms').text(data.symptoms || '--');
                                    $('#view_description').text(data.description || '--');
                                    $('#view_notes').text(data.notes || '--');
                                    
                                    // Price - extract numeric value from charge if needed, or use charge directly
                                    const chargeText = data.charge || '';
                                    const chargeMatch = chargeText.match(/[\d.]+/);
                                    const price = chargeMatch ? parseFloat(chargeMatch[0]) : 0;
                                    $('#view_price').text(price > 0 ? (typeof currencyFormat !== 'undefined' ? currencyFormat(price) : formatCurrency(price)) : '--');
                                    
                                    // Show modal
                                    const modalElement = document.getElementById('viewBedAllocationModal');
                                    if (modalElement) {
                                        const modal = new bootstrap.Modal(modalElement);
                                    modal.show();
                                        console.log('View Bed Allocation - Modal Opened');
                                } else {
                                        console.error('View Bed Allocation - Modal element not found');
                                        Swal.fire({
                                            title: 'Error',
                                            text: 'Modal element not found.',
                                            icon: 'error'
                                        });
                                    }
                                } else {
                                    console.error('View Bed Allocation - Invalid Response', response);
                                    Swal.fire({
                                        title: 'Error',
                                        text: response.message || 'Failed to load bed allocation details.',
                                        icon: 'error'
                                    });
                                }
                            },
                            error: function(xhr) {
                                console.error('View Bed Allocation - AJAX Error', {
                                    'status': xhr.status,
                                    'statusText': xhr.statusText,
                                    'response': xhr.responseJSON || xhr.responseText,
                                    'bedAllocationId': bedAllocationId
                                });
                                
                                let errorMessage = 'An unexpected error occurred.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                } else if (xhr.status === 404) {
                                    errorMessage = 'Bed allocation not found.';
                                } else if (xhr.status === 500) {
                                    errorMessage = 'Server error occurred. Please try again.';
                                }
                                
                                Swal.fire({
                                    title: 'Error',
                                    text: errorMessage,
                                    icon: 'error'
                                });
                            }
                        });
                    });
                    
                    // Helper function to format currency using user's currency settings
                    // The currencyFormat function is defined in the layout and uses the user's default currency settings
                    // from the database (symbol, position, decimal places, separators, etc.)
                    function formatCurrency(amount) {
                        // Use the currency format from the layout (uses user's default currency settings)
                        if (typeof currencyFormat !== 'undefined') {
                            return currencyFormat(amount);
                        }
                        // Fallback to window.currencyFormat if available
                        if (typeof window.currencyFormat !== 'undefined') {
                            return window.currencyFormat(amount);
                        }
                        // Last resort fallback - should not be needed as currencyFormat is defined in layout
                        return amount.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }


                });
            </script>
        @endpush
