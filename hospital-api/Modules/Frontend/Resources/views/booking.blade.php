@extends('frontend::layouts.master')
@section('title', __('frontend.booking'))

@section('content')
    <div class="list-page section-spacing px-0">
        <div class="container">

            {{--<div class="widget-tabs px-3 mb-5 pb-3 ">
                <div class="row tab-list">
                    @foreach ($tabs as $index => $tab)
                        <div class="col-4 tab-item" style="--before-content: '{{ $index + 1 }}'"
                            @class(['active' => $index === $currentStep]) data-check="{{ $index < $currentStep ? 'true' : 'false' }}"
                            data-label="{{ $tab['label'] }}">
                            <a href="#" class="nav-link tab-index" data-index="{{ $index }}">
                                <h6
                                    class="mb-0 ms-3 {{ $index === $currentStep ? 'text-primary' : ($index < $currentStep ? 'text-black' : 'text-body') }}">
                                    {{ $tab['label'] }}
                                </h6>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>--}}

            <div class="mb-5 pb-3">
                <ul class="appointments-steps-list">
                    @foreach ($tabs as $index => $tab)
                        <li
                            class="appointments-steps-item
                            {{ $index < $currentStep ? 'complete' : '' }}
                            {{ $index === $currentStep ? 'active' : '' }}"
                            data-check="{{ $index < $currentStep ? 'true' : 'false' }}">
                            <div class="appointments-step d-flex align-items-center gap-3">
                                <span class="flex-shrink-0">
                                    <a href="#" class="appointments-step-inner tab-index" data-index="{{ $index }}">
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="step-counter">{{ $index + 1 }}</span>
                                            <span
                                                class="step-text mb-0 ms-3 {{ $index === $currentStep ? 'text-primary' : ($index < $currentStep ? 'text-black' : 'text-body') }}">
                                                {{ $tab['label'] }}
                                            </span>
                                        </span>
                                    </a>
                                </span>
                                @if($index < count($tabs) - 1)
                                    <span class="flex-grow-1 separator">
                                        <span class="line"></span>
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="row">
                <div class="col-lg-8">

                    <div>
                        <div class="mb-3">
                            <i class="ph ph-caret-left align-middle"></i>
                            <a id="prev-step-btn" href="javascript:void(0);" class="text-body font-size-14 fw-semibold">
                                {{ __('frontend.previous_step') }} </a>
                        </div>

                        <div class="row gy-3">
                            @if (isset($selectedService))
                                <div class="col-lg-4 ">
                                    <div class="bg-primary-subtle service-box-wizard rounded p-3 position-relative">
                                        <div class="position-absolute top-0 end-0 m-2 ">
                                            <a href="{{ $previousUrl ?? '#' }}" class="text-muted" id="service-edit-button"
                                                data-step="0">
                                                <i class="ph ph-pencil-simple heading-color"></i>
                                            </a>
                                        </div>
                                        <div>
                                            <p class="font-size-14 heading-color mb-2">{{ __('frontend.service') }}</p>
                                            <h6 class="font-size-14 text-heading fw-semibold mb-0">
                                                {{ $selectedService->name }}</h6>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @php
                                $clinicTab = collect($tabs)->firstWhere('value', 'Choose Clinics');
                                $doctorTab = collect($tabs)->firstWhere('value', 'Choose Doctors');
                            @endphp

                            @if ($clinicTab && $doctorTab)
                                @if ($clinicTab['index'] < $doctorTab['index'])
                                    <div id="selected-clinic-container" class="col-lg-4">
                                        @if (isset($selectedClinic))
                                            <div
                                                class="bg-primary-subtle rounded p-3  clinic-box-wizard p-3 position-relative">
                                                <div class="position-absolute top-0 end-0 m-2" id="clinic-edit-button"
                                                    data-step="{{ $clinicTab['index'] }}">
                                                    <a href="#" class="text-muted">
                                                        <i class="ph ph-pencil-simple heading-color"></i>
                                                    </a>
                                                </div>
                                                <div>
                                                    <p class="font-size-14 heading-color mb-2">{{ __('frontend.clinic') }}</p>
                                                    <h6 class="font-size-14 text-heading fw-semibold mb-0">
                                                        {{ $selectedClinic->name }}</h6>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div id="selected-doctor-container" class="col-lg-4">
                                        @if (isset($selectedDoctor))
                                            <div class="card shadow-sm small-card doctor-card p-3 position-relative">
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <a href="#" class="text-muted" id="doctor-edit-button"
                                                        data-step="{{ $doctorTab['index'] }}">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </a>
                                                </div>

                                                <div>
                                                    <p class="mb-1">{{ __('frontend.doctor') }}</p>
                                                    <h6 class="card-title mb-1">
                                                        {{ $selectedDoctor->user->first_name }}
                                                        {{ $selectedDoctor->user->last_name }}
                                                    </h6>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div id="selected-doctor-container" class="col-lg-4">
                                        @if (isset($selectedDoctor))
                                            <div class="bg-primary-subtle doctor-box-wizard rounded p-3 position-relative">
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <a href="#" class="text-muted" id="doctor-edit-button"
                                                        data-step="{{ $doctorTab['index'] }}">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </a>
                                                </div>
                                                <div>
                                                    <p class="font-size-14 text-body mb-2">{{ __('frontend.doctor') }}
                                                    </p>
                                                    <h6 class="font-size-14 text-heading fw-semibold mb-0">
                                                        {{ $selectedDoctor->user->first_name }}
                                                        {{ $selectedDoctor->user->last_name }}
                                                    </h6>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div id="selected-clinic-container" class="col-lg-4">
                                        @if (isset($selectedClinic))
                                            <div class="bg-primary-subtle clinic-box-wizard rounded p-3 position-relative">
                                                <div class="position-absolute top-0 end-0 m-2" id="clinic-edit-button"
                                                    data-step="{{ $clinicTab['index'] }}">
                                                    <a href="#" class="text-muted">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </a>
                                                </div>
                                                <div>
                                                    <p class="font-size-14 text-body mb-2">{{ __('frontend.clinic') }}</p>
                                                    <h6 class="font-size-14 text-heading fw-semibold mb-0">{{ $selectedClinic->name }}</h6>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if (isset($appointmentDate) || isset($selectedSlot))
                                <div class="col-3">
                                    <div class="card shadow-sm small-card appointment-card p-3">
                                        <div>
                                            <p class="mb-1">{{ __('frontend.appointment_details') }}</p>
                                            <h6 class="card-title mb-1">
                                                {{ $appointmentDate ?? '' }}{{ isset($appointmentDate) && isset($selectedSlot) ? ' , ' : '' }}{{ $selectedSlot ?? '' }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div id="step-content">
                            <div id="step-content-3" class="step-content d-none">

                                <!-- Choose Date and Time Section -->
                                <div class="mb-50 mt-5">
                                    <h6 class="mb-2">{{ __('frontend.choose_date') }}
                                    </h6>
                                    <div class="form-group position-relative">
                                        <div class="input-group">

                                            <input type="text" id="appointment_date" class="form-control"
                                                name="appointment_date" placeholder="Select appointment date" value="{{ date('Y-m-d') }}">
                                            <span class="input-group-text" id="calendar-icon">
                                                <i class="ph ph-calendar"></i>
                                                <!-- Replace with your preferred icon library -->
                                            </span>
                                        </div>
                                    </div>

                                    <div class="section-bg rounded p-3 mt-3" id="time-slot-card">
                                        <div class="booked-time">
                                            <span class="font-size-14 mb-2">{{ __('frontend.choose_time') }}                                            </span>
                                            <div class="d-flex flex-wrap justify-content-start" id="time-slots-container">
                                                <!-- Available time slots will be dynamically inserted here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">{{ __('messages.booking_for') }}</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="bookForOthers">
                                                                          </div>
                                </div>

                                <!-- Add this new section for other patients -->
                                <div id="otherPatientsSection" class="my-3 d-none">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">{{ __('clinic.lbl_select_patient') }}</h6>
                                        <button type="button" class="btn btn-link p-0 fw-semibold text-secondary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                                            {{ __('clinic.add_other_patient') }}
                                        </button>
                                    </div>

                                    <div class="other-patients-list d-flex flex-wrap gap-3 mt-3">
                                        <!-- Other patients will be loaded here dynamically -->
                                    </div>
                                </div>
                                <!-- Medical History Section -->
                                <div class="mb-50">
                                    <h6 class ="font-size-18 fw-semibold">{{ __('frontend.medical_history') }}
                                    </h6>
                                    <textarea class="form-control" id="appointment_extra_info" name="appointment_extra_info" placeholder="{{ __('frontend.enter_medical_history') }}"></textarea>
                                </div>

                                <!-- Upload Medical Report Section -->

                                <div class="mb-50">
                                    <h6 class ="font-size-18 fw-semibold">{{ __('frontend.upload_medical_report') }}
                                    </h6>

                                    <!-- Uppy Dashboard Container -->
                                    <div id="uppy-dashboard"></div>

                                    <!-- Display the selected file name -->
                                    <div id="file-info"></div>
                                </div>

                                <!-- Available Coupons Section -->
                                {{--<div class="mb-40">
                                    <div class="d-flex justify-content-between gap-3 flex-warp mb-3">
                                        <h6 class ="font-size-18 fw-semibold m-0">Available Coupons</h6>
                                        <a data-bs-toggle="modal" data-bs-target="#all-coupons"
                                            class="font-size-14 fw-bold text-secondary">View All</a>
                                    </div>
                                    <!-- <div class="section-bg p-3 rounded">
                                                <p class="m-0">Coupon not Available</p>
                                            </div> -->
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div
                                                class="d-flex justify-content-between gap-3 p-3 section-bg rounded coupons-code active">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="flexRadioDefault" id="flexRadioDefault2" checked>
                                                    <label class="form-check-label" for="flexRadioDefault2">Get extra 10%
                                                        off on first appointment
                                                    </label>
                                                </div>
                                                <span class="fw-bold font-size-12 coupons-status">Applied</span>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 mt-lg-0 mt-3">
                                            <div
                                                class="d-flex justify-content-between gap-3 p-3 section-bg rounded coupons-code">
                                                <div class="form-check">
                                                    <label class="form-check-label" for="flexRadioDefault2">
                                                        Get extra 10% off on first appointment
                                                    </label>
                                                    <input class="form-check-input" type="radio"
                                                        name="flexRadioDefault" id="flexRadioDefault2">
                                                </div>
                                                <a href="#" class="font-size-12 fw-bold coupons-status">Apply</a>
                                            </div>
                                        </div>
                                    </div>
                                </div> --}}

                                <!-- Choose Payment Method Section -->
                                <div>
                                    <h6 class="mb-3">{{ __('frontend.payment_method') }}
                                    </h6>
                                    <div class="payments-container section-bg rounded mt-3">
                                        <a class="d-flex justify-content-between align-items-center gap-3 payments-show-list"
                                            href="#booking-payments-method" data-bs-toggle="collapse" aria-expanded="true">
                                            <p class="mb-0 h6" id="selected-payment-method">Select Payment Method</p>
                                            <i class="ph ph-caret-down"></i>
                                        </a>
                                    </div>
                                    <div id="booking-payments-method"
                                        class="section-bg rounded booking-payment-method collapse show mt-3">
                                        @foreach ($enabledPaymentMethods as $method)
                                            <div
                                                class="form-check payment-method-items ps-0 d-flex justify-content-between align-items-center gap-3">
                                                <label class="form-check-label d-flex gap-2 align-items-center"
                                                    for="method-{{ $method }}">
                                                    <img src="{{ asset('dummy-images/payment_icons/' . strtolower($method) . '.svg') }}"
                                                        alt="{{ $method }}" style="width: 20px; height: 20px;">
                                                        <span class="h6 fw-semibold m-0">{{ ucwords($method) }}</span>
                                                </label>
                                                <input class="form-check-input payment-radio" type="radio" name="payment_method"
                                                    value="{{ $method }}" id="method-{{ $method }}"
                                                    @if ($loop->first) checked @endif>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
                <div class="col-lg-3 mt-lg-0 mt-5">
                    <div class="payment-container" id="payment-container">

                    </div>
                </div>

                <div class="mt-5">
                    <div id="step-content">

                        <div id="step-content-0" class="step-content">
                            <!-- Content for Step 1 (e.g. Select Service) -->
                        </div>

                        <div id="service-shimmer-loader" class="d-flex gap-3 flex-wrap p-4 d-none">
                             @for ($i = 0; $i < 4; $i++)
                                 @include('frontend::components.card.shimmer_service_card')
                             @endfor
                         </div>


                        <div id="step-content-1" class="step-content">

                        <!-- Content for Step 2 (e.g. Select Clinic) -->
                        </div>

                     


                        <div id="step-content-2" class="step-content">

                        </div>

                        <div id="doctor-shimmer-loader" class="d-flex gap-3 flex-wrap p-4 d-none">
                           @for ($i = 0; $i < 4; $i++)
                               @include('frontend::components.card.shimmer_doctor_card')
                           @endfor
                       </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-5 pt-2">
                <button class="btn btn-secondary" id="nextButton">{{ __('frontend.next') }}
                </button>
            </div>
        </div>
    </div>

    @if (!empty($paymentDetails))
        <!-- Payment Success Modal -->
        <div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-labelledby="paymentSuccessModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-3 shadow">
                    <!-- Header with Success Icon -->
                    <div class="modal-header justify-content-center bg-light">
                        <div class="text-center">
                            <img src="/path/to/success-icon.svg" alt="Success Icon" class="img-fluid mb-2"
                                style="width: 50px;" />
                            <h5 class="modal-title text-success fw-bold" id="paymentSuccessModalLabel">
                                {{ $paymentDetails['message'] }}
                            </h5>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body heading-color">
                        <p>
                            Your appointment with <strong>Dr. {{ $paymentDetails['doctorName'] }}</strong> at
                            <strong>{{ $paymentDetails['clinicName'] }}</strong> has been confirmed on
                            <strong>{{ date('d M, Y', strtotime($paymentDetails['appointmentDate'])) }}</strong> at
                            <strong>{{ date('h:i A', strtotime($paymentDetails['appointmentTime'])) }}</strong>.
                        </p>
                        <div class="mt-3 pt-3 border-top text-start">
                            <p><strong>Booking ID:</strong> <span
                                    class="heading-color">#{{ $paymentDetails['bookingId'] }}</span></p>
                            <p><strong>Payment via:</strong> <span
                                    class="heading-color">{{ $paymentDetails['paymentVia'] }}</span></p>
                            <p><strong>Total Payment:</strong> <span class="heading-color">{{ $paymentDetails['currency'] }}
                                    {{ $paymentDetails['totalAmount'] }}</span></p>
                        </div>
                    </div>

                    <!-- Footer with Button -->
                    <div class="modal-footer justify-content-center bg-light">
                        <button type="button" class="btn btn-danger px-4" id="back-to-appointments">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- id="all-coupons" -->
    <div class="modal fade" id="all-coupons">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content section-bg">
                <div class="modal-body modal-body-inner">
                    <div class="close-modal-btn" data-bs-dismiss="modal">
                        <i class="ph ph-x align-middle"></i>
                    </div>
                    <div>
                        <h6 class="font-size-18 mb-3">Available coupons</h6>
                        <form>
                            <ul class="list-inline m-0 coupons-inner">
                                <li>
                                    <div class="d-flex justify-content-between gap-3 p-3 rounded coupons-code active">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2" checked>
                                            <label class="form-check-label" for="flexRadioDefault2">Get extra 10% off on
                                                first appointment
                                            </label>
                                        </div>
                                        <span class="fw-bold font-size-12 coupons-status">Applied</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex justify-content-between gap-3 p-3 rounded coupons-code">
                                        <div class="form-check">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                Get extra 10% off on first appointment
                                            </label>
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2">
                                        </div>
                                        <a href="#" class="font-size-12 fw-bold coupons-status">Apply</a>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex justify-content-between gap-3 p-3 rounded coupons-code">
                                        <div class="form-check">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                Get extra 10% off on first appointment
                                            </label>
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2">
                                        </div>
                                        <a href="#" class="font-size-12 fw-bold coupons-status">Apply</a>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex justify-content-between gap-3 p-3 rounded coupons-code">
                                        <div class="form-check">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                Get extra 10% off on first appointment
                                            </label>
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2">
                                        </div>
                                        <a href="#" class="font-size-12 fw-bold coupons-status">Apply</a>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex justify-content-between gap-3 p-3 rounded coupons-code">
                                        <div class="form-check">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                Get extra 10% off on first appointment
                                            </label>
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2">
                                        </div>
                                        <a href="#" class="font-size-12 fw-bold coupons-status">Apply</a>
                                    </div>
                                </li>
                            </ul>
                            <div class="d-flex justify-content-end mt-5">
                                <button type="button" class="btn btn-secondary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- modal end -->

    <!-- Add Patient Modal -->
    <div class="modal fade add-patient-modal" id="addPatientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('customer.add_new_patient') }}</h5>
                    <div class="close-modal-btn" data-bs-dismiss="modal"><i class="ph ph-x align-middle"></i></div>
                </div>
                <div class="modal-body">
                    <form id="addPatientForm">
                        <div class="row">
                            <div class="col-lg-5">
                                <div class="form-group mb-3">

                                    <div class="col-md-12">
                                        <div class="d-flex align-items-center justify-content-center p-3">
                                            <img id="miniLogoViewer"  src={{ $data['profile_image'] ?? asset('img/avatar/avatar.webp')  }} class="img-fluid avatar-130 rounded-pill" alt="mini_logo" />
                                        </div>

                                        <div class="d-flex align-items-center gap-3 justify-content-center mt-5">
                                            <input type="file" class="form-control d-none" id="mini_logo" name="profile_image" accept=".jpeg, .jpg, .png, .gif">
                                            <button type="button" class="btn btn-info" onclick="document.getElementById('mini_logo').click();">{{ __('messages.upload') }}</button>
                                            <button type="button" class="btn btn-danger" id="removeMiniLogoButton">{{ __('messages.remove') }}</button>
                                        </div>
                                        <span class="text-danger" id="error_mini_logo"></span>
                                    </div>

                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="row g-3">
                                    <!-- Name Fields -->

                                    <div class="col-xl-6 col-lg-12">
                                        <label class="form-label">{{ __('clinic.lbl_first_name') }} <span class="text-danger">*</span></label>
                                        <div class="input-group custom-input-group mb-1">
                                            <input type="text" class="form-control" name="first_name" placeholder="First Name" required>
                                            <span class="input-group-text"><i class="ph ph-user"></i></span>
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-12">
                                        <label class="form-label">{{ __('clinic.lbl_last_name') }} <span class="text-danger">*</span></label>
                                        <div class="input-group custom-input-group mb-1">
                                            <input type="text" class="form-control" name="last_name" placeholder="Last Name" required>
                                            <span class="input-group-text"><i class="ph ph-user"></i></span>
                                        </div>
                                    </div>

                                    <!-- Contact Information -->

                                    <div class="col-xl-6 col-lg-12">
                                        <label class="form-label">{{ __('clinic.lbl_phone_number') }} <span class="text-danger">*</span></label>
                                        <div class="input-group custom-input-group mb-1">
                                            <input type="tel" class="form-control" name="contactNumber_display" placeholder="Phone Number" id="mobile" required>
                                            <input type="hidden" name="contactNumber" id="fullPhoneNumber"> <!-- This will store full number -->
                                            <span class="input-group-text"><i class="ph ph-phone"></i></span>
                                        </div>
                                    </div>

                                    <!-- Date of Birth -->
                                    <div class="col-xl-6 col-lg-12">
                                        <label class="form-label">{{ __('clinic.date_of_birth') }} <span class="text-danger">*</span></label>
                                        <div class="input-group custom-input-group mb-1">
                                            <input type="date" class="form-control" name="dob" id="dob" placeholder="DOB" required>
                                            <span class="input-group-text"><i class="ph ph-cake"></i></span>
                                        </div>
                                    </div>

                                    <!-- Gender Selection -->
                                    <div class="col-lg-12">
                                        <label class="form-label">{{ __('clinic.lbl_gender') }} <span class="text-danger">*</span></label>
                                        <div class=" input-group d-flex flex-wrap align-items-center gap-2">
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="gender" value="male" id="genderMale" required>
                                                <label class="form-check-label rounded-pill" for="genderMale">{{ __('messages.male') }}</label>
                                            </div>
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="gender" value="female" id="genderFemale" required>
                                                <label class="form-check-label rounded-pill" for="genderFemale">{{ __('messages.female') }}</label>
                                            </div>
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="gender" value="other" id="genderOther" required>
                                                <label class="form-check-label rounded-pill" for="genderOther">{{ __('messages.lbl_other') }}</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Relationship Selection -->
                                    <!-- Replace the relationship select field with radio buttons -->
                                    <div class="col-lg-12">
                                        <label class="form-label mb-3">{{ __('clinic.relation') }} <span class="text-danger">*</span></label>
                                        <div class="input-group d-flex flex-wrap align-items-center gap-2">
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="relation"
                                                    id="relationParent" value="Parents" required>
                                                <label class="form-check-label rounded-pill" for="relationParent">
                                                    {{ __('clinic.parents') }}
                                                </label>
                                            </div>
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="relation"
                                                    id="relationSibling" value="Siblings" required>
                                                <label class="form-check-label rounded-pill" for="relationSibling">
                                                    {{ __('clinic.sibling') }}
                                                </label>
                                            </div>
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="relation"
                                                    id="relationSpouse" value="Spouse" required>
                                                <label class="form-check-label rounded-pill" for="relationSpouse">
                                                    {{ __('clinic.spouse') }}
                                                </label>
                                            </div>
                                            <div class="form-check custom-radio-btn">
                                                <input class="form-check-input" type="radio" name="relation"
                                                    id="relationOther" value="Other" required>
                                                <label class="form-check-label rounded-pill" for="relationOther">
                                                    {{ __('messages.lbl_other') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap mt-5">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('messages.close') }}</button>
                            <button type="button" class="btn btn-primary" id="savePatient">{{ __('messages.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
    <script type="text/javascript" defer>
        let currentStep = {{ $currentStep }}; // Start with server-provided value

        document.addEventListener('DOMContentLoaded', function() {
            var input = document.querySelector("#mobile");
            var hiddenInput = document.querySelector("#fullPhoneNumber");

            var iti = window.intlTelInput(input, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
            });

            function updateFullPhoneNumber() {
                var fullNumber = iti.getNumber();
                var localNumber = input.value;

                // Save full number in hidden input
                hiddenInput.value = fullNumber;

                // Keep displaying only local number
                input.value = localNumber;
            }

            input.addEventListener("countrychange", updateFullPhoneNumber);
            input.addEventListener("blur", updateFullPhoneNumber);

            flatpickr('#dob', {
                dateFormat: 'Y-m-d',
                maxDate: 'today'
            });
        });

        document.querySelectorAll('.tab-index').forEach(tab => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();

                const index = parseInt(this.getAttribute('data-index'));
                const steps = document.querySelectorAll('.appointments-steps-item');

                // Update current step and modify classes
                steps.forEach((step, idx) => {
                    const stepText = step.querySelector('.step-text');
                    if (idx === index) {
                        step.classList.add('active');
                        step.classList.remove('complete');
                        step.setAttribute('data-check', 'false');
                        stepText.className = 'step-text';
                    } else if (idx < index) {
                        step.classList.add('complete');
                        step.classList.remove('active');
                        step.setAttribute('data-check', 'true');
                        stepText.className = 'step-text';
                    } else {
                        step.classList.remove('complete', 'active');
                        step.setAttribute('data-check', 'false');
                        stepText.className = 'step-text';
                    }
                });
            });
        });

        const initialState = {
            selectedService: @json($serviceId),
            selectedServiceName: @json($selectedService ? $selectedService->name : null),
            selectedClinic: @json($clinicId),
            selectedDoctor: @json($doctorId),
            selectedClinicName: @json($selectedClinic ? $selectedClinic->name : null),
            selectedDate: null,
            selectedTime: null,
            selectedDoctorName: @json($selectedDoctor ? $selectedDoctor->user->first_name . ' ' . $selectedDoctor->user->last_name : null),
            uploadedFiles: [],
            selectedPaymentMethod: @json($selectedService && $selectedService->is_enable_advance_payment == 1 ? 'Wallet' : 'cash'),
            status: "pending",
            user_id: @json(optional(auth()->user())->id) ?? '',
            previousUrl: @json($previousUrl),
            totalAmount: 0,
            payment: {
                totalAmount: @json($totalAmount ?? 0),
                advance_payment_status: @json($advancePaymentStatus ?? 0),
                remaining_payment_amount: @json($remainingPaymentAmount ?? 0),
                payment_status: @json($paymentStatus ?? 1),
                transaction_type: @json($transactionType ?? 'cash'),
                advance_payment_amount: @json($advancePaymentAmount ?? 0),
                is_enable_advance_payment: 0,
            }
        };

        const checkWalletBalanceUrl = "{{ route('check.wallet.balance') }}";
        const initialStep = {{ $currentStep }};
        const tabs = @json($tabs);
        const routes = {
            clinicIndex: '{{ route('clinic.index_data') }}',
            doctorIndex: '{{ route('doctor.index_data') }}',
            paymentData: '{{ route('payment.data') }}',
            slotTimeList: '{{ route('slot_time_list') }}',
            saveAppointment: '{{ route('saveAppointment') }}',
            appointmentList: '{{ route('appointment-list') }}',
            appointmentDetails: '{{ route('appointment-details', '') }}',
            otherPatient:'{{ route("other-patients.store") }}',
            otherPatientList:'{{ route("other-patients.list") }}?patient_id={{ auth()->id() }}'

        };
        const paymentDetails = @json($paymentDetails ?? '');
        const baseUrl = document.querySelector('meta[name="base-url"]').getAttribute('content')
        const paymentDetail = "{{ __('frontend.payment_details') }}"
        const price = "{{ __('frontend.price') }}"
        const Discount = "{{ __('frontend.discount') }}"
        const Subtotal = "{{ __('frontend.subtotal') }}"
        const Tax = "{{ __('frontend.tax') }}"
        const InclusiveTax = "{{ __('service.inclusive_tax') }}"
        const Total = "{{ __('frontend.total') }}"
        const ChooseClinic = "{{ __('frontend.choose_clinic') }}"
        const ChooseDoctor = "{{ __('frontend.choose_doctors') }}"
        const AdvancePayableAmount = "{{ __('frontend.advance_payable_amount') }}"
        const Submit = "{{ __('frontend.submit') }}"
        const clinicTitle = "{{ __('frontend.clinic') }}"
        const doctorTitle = "{{ __('frontend.doctor') }}"
        const withInclusivetax = "{{ __('messages.lbl_with_inclusive_tax') }}"
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        document.addEventListener('DOMContentLoaded', () => {
            const paymentRadios = document.querySelectorAll('.payment-radio');
            const selectedPaymentMethod = document.getElementById('selected-payment-method');

            const initialChecked = document.querySelector('.payment-radio:checked');
            if (initialChecked) {
                selectedPaymentMethod.textContent = initialChecked.value.charAt(0).toUpperCase() + initialChecked.value.slice(1);
            }

            paymentRadios.forEach((radio) => {
    radio.addEventListener('change', (event) => {
        selectedPaymentMethod.textContent = event.target.value.charAt(0).toUpperCase() + event.target.value.slice(1);
    });
});
        });

    document.addEventListener('DOMContentLoaded', () => {
    // Date picker functionality
    const appointmentDateInput = document.getElementById('appointment_date');
    const calendarIcon = document.getElementById('calendar-icon');

    const picker = flatpickr(appointmentDateInput, {
        dateFormat: "Y-m-d",  // Display date in YYYY-MM-DD format
        placeholder: "Select appointment date",
        clickOpens: false,     // Prevent date picker from opening when input is clicked
        minDate: "today",      // Disable past dates
        defaultDate: "today"   // Set today as default date
    });

    // Show the date picker when the calendar icon is clicked
    appointmentDateInput.addEventListener('click', function() {
        picker.open();  // Open the date picker when icon is clicked
    });
    calendarIcon.addEventListener('click', function() {
        picker.open();  // Open the date picker when icon is clicked
    });

    // File input functionality for mini logo
    const minilogoInput = document.getElementById('mini_logo');
    const miniLogoViewer = document.getElementById('miniLogoViewer');

    minilogoInput.addEventListener('change', function() {
        const minilogofile = this.files[0];
        console.log(minilogofile);
        if (minilogofile) {
            const reader = new FileReader();
            reader.onload = function(e) {
                miniLogoViewer.src = e.target.result;
                console.log(e.target.result);
            }
            reader.readAsDataURL(minilogofile);
        }
    });
});

    </script>
    <script>
        window.patientFormMessages = {
            first_name_required: "{{ __('messages.first_name_required') }}",
            last_name_required: "{{ __('messages.last_name_required') }}",
            contact_number_required: "{{ __('messages.contact_number_required') }}",
            dob_required: "{{ __('messages.dob_required') }}",
            dob_past: "{{ __('messages.dob_past') }}",
            gender_required: "{{ __('messages.gender_required') }}",
            relation_required: "{{ __('messages.relation_required') }}"
        };
    </script>
    <script type="text/javascript" src="{{ asset('js/appointment.min.js') }}" defer></script>

@endpush
