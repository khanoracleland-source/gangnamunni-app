@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
    <div class="row">
        <x-backend.section-header>
            <x-slot name="toolbar">
                <a href="{{ route('backend.appointments.index') }}" class="btn btn-primary ms-auto" data-type="ajax"
                    data-bs-toggle="tooltip">
                    {{ __('appointment.back') }}
                </a>
                @php
                    $id = $appointment ? $appointment->id : 0;
                    $status = $appointment ? $appointment->status : null;
                    $pay_status = $appointment ? optional($appointment->appointmenttransaction)->payment_status : 0;
                @endphp
                @if ($appointment->patientEncounter && $appointment->patientEncounter->status == 1)
                    <a href="{{ route('backend.bed-allocation.create') }}?encounter_id={{ $appointment->patientEncounter->id }}" 
                        class="btn btn-secondary ms-2" 
                        data-bs-toggle="tooltip"
                        title="{{ __('messages.bed_allocation') }}">
                        <i class="ph ph-bed me-1"></i>
                        {{ __('messages.bed_allocation') }}
                    </a>
                @endif
                @if ($pay_status == 1 && $status == 'checkout')
                    <div class="d-flex justify-content-end align-items-center ">

                        <a class="btn btn-primary"
                            href="{{ route('backend.appointments.download_invoice', ['id' => $appointment->id]) }}">
                            <i class="fa-solid fa-download"></i>
                            {{ __('appointment.lbl_download') }}
                        </a>
                    </div>
                @endif
            </x-slot>
        </x-backend.section-header>
        <div class="col-lg-12">
            <div class="card card-block card-stretch card-height">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_patient_name') }}</span>
                            <div class="d-flex gap-3 align-items-center">
                                <img src="{{ optional($appointment->user)->profile_image ?? default_user_avatar() }}"
                                    alt="avatar" class="avatar avatar-70 rounded-pill">
                                <div class="text-start">
                                    <h5 class="m-0">{{ optional($appointment->user)->full_name ?? default_user_name() }}
                                    </h5>
                                    <span>{{ optional($appointment->user)->email ?? '--' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div>
                                <span class="d-block mb-2">{{ __('clinic.lbl_clinic_name') }}</span>
                                <img src="{{ optional($appointment->cliniccenter)->file_url ?? 'default_file_url()' }}"
                                    alt="avatar" class="avatar avatar-30 rounded-pill me-2">
                                <h6 class="m-0">
                                    {{ $appointment->cliniccenter ? optional($appointment->cliniccenter)->name : '--' }}
                                </h6>
                            </div>

                            <div>
                                <span class="d-block mb-2">{{ __('appointment.lbl_status') }}</span>
                                @php
                                    $statusKey = $appointment->status;
                                    $statusLabel = $statusKey ? __('appointment.' . $statusKey) : null;

                                    if ($statusLabel === 'appointment.' . $statusKey) {
                                        $statusLabel = $statusKey
                                            ? ucwords(str_replace('_', ' ', $statusKey))
                                            : '--';
                                    }
                                @endphp
                                <h6 class="m-0">
                                    {{ $statusLabel ?: '--' }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @if ($appointment->status === 'cancelled' && $appointment->reason)
            <div class="col-lg-12">
                <div class="card card-block card-stretch card-height">
                    <div class="card-body">
                        <h5 class="mb-2">{{ __('messages.lbl_reason_for_cancellation') }}</h5>
                        <p class="mb-0">{{ $appointment->reason }}</p>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-lg-12">
            <div class="card card-block card-stretch card-height">
                <div class="card-body">
                    <div class="row gy-3 mb-5">
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_appointment_date') }}</span>
                            <h6 class="m-0">{{ date($dateformate, strtotime($appointment->appointment_date ?? '--')) }}
                            </h6>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_appointment_time') }}</span>
                            <h6 class="m-0">{{ __('appointment.at') }}
                                {{ $appointment->appointment_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->appointment_time)->format('h:i A') : '--' }}
                        </div>
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_doctor') }}
                                {{ __('appointment.lbl_name') }}</span>

                            @if ($appointment->doctor === null)
                                <h6 class="m-0">--</h6>
                            @else
                                <div class="d-flex gap-3 align-items-center">
                                    <img src="{{ optional($appointment->doctor)->profile_image ?? default_user_avatar() }}"
                                        alt="avatar" class="avatar avatar-50 rounded-pill">
                                    <div class="text-start">
                                        <h6 class="m-0">
                                            {{ optional($appointment->doctor)->first_name . ' ' . optional($appointment->doctor)->last_name }}
                                        </h6>
                                        <span>{{ optional($appointment->doctor)->email ?? '--' }}</span>
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>

                    <div class="border-top"></div>
                    <div class="row gy-3 pt-5">
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_payment_status') }}</span>
                            @if (isset($appointment->appointmenttransaction->payment_status))
                                @if ($appointment->status === 'cancelled' && optional($appointment->appointmenttransaction)->payment_status == 1)
                                    <h6 class="m-0 mb-2 text-success">{{ getLocalizedPaymentStatus(3) }}</h6>
                                @elseif (optional($appointment->appointmenttransaction)->payment_status == 1)
                                    <h6 class="m-0 mb-2 text-success">{{ getLocalizedPaymentStatus(1) }}</h6>
                                @elseif($appointment->status == 'cancelled' && $appointment->advance_paid_amount != 0)
                                    <h6 class="m-0 mb-2 text-success">{{ getLocalizedPaymentStatus(3) }}</h6>
                                @elseif(optional($appointment->appointmenttransaction)->payment_status == 0 &&
                                        optional($appointment->appointmenttransaction)->advance_payment_status == 1)
                                    <h6 class="m-0 mb-2 text-success">{{ getLocalizedPaymentStatus(5) }}</h6>
                                @else
                                    <h6 class="m-0 mb-2 text-secondary">{{ getLocalizedPaymentStatus(0) }}</h6>

                                    {{--  <span class="d-block mb-1">{{ __('appointment.lbl_payment_method') }}</span>
                                    <div class="col-md-3">
                                        <h6 class="m-0  mb-2">Paid with
                                            {{ ucfirst(optional($appointment->appointmenttransaction)->transaction_type) }}
                                        </h6>
                                    </div> --}}
                                @endif
                            @else
                                <h6 class="m-0 text-danger">{{ __('appointment.failed') }}</h6>
                            @endif
                        </div>
                        @if (
                            (optional($appointment->appointmenttransaction)->payment_status == 0 ||
                                optional($appointment->appointmenttransaction)->payment_status == 1) &&
                                optional($appointment->appointmenttransaction)->advance_payment_status == 0)
                            <div class="col-md-3">
                                @if (isset($appointment->appointmenttransaction->payment_status))
                                    @php
                                        $paymentMethods = [
                                            'razor_payment_method' => 'Razorpay',
                                            'str_payment_method' => 'Stripe',
                                            'paystack_payment_method' => 'Paystack',
                                            'paypal_payment_method' => 'Paypal',
                                            'flutterwave_payment_method' => 'Flutterwave',
                                            'airtel_payment_method' => 'Airtel',
                                            'phonepay_payment_method' => 'PhonePe',
                                            'midtrans_payment_method' => 'Midtrans',
                                            'cinet_payment_method' => 'Cinet',
                                            'sadad_payment_method' => 'Sadad',
                                        ];

                                        $methodKey = optional($appointment->appointmenttransaction)->transaction_type;
                                        $methodName =
                                            $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                    @endphp

                                    <span class="d-block mb-1">{{ __('appointment.lbl_payment_method') }}</span>
                                    <div>
                                        <h6 class="m-0 mb-2">{{ $methodName }}</h6>
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_contact_number') }}</span>
                            <h6 class="m-0">{{ optional($appointment->user)->mobile ?? '--' }}</h6>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block mb-1">{{ __('appointment.lbl_duration') }}</span>
                            <h6 class="m-0">{{ $appointment->duration ?? '--' }} {{ __('appointment.min') }}</h6>
                        </div>

                        <div class="col-md-3">
                            @if ($appointment->otherPatient)
                                <span class="d-block mb-1">{{ __('appointment.booked_for') }}</span>
                                <h6 class="m-0">
                                    <span> <img src={{ $appointment->otherPatient->profile_image }}
                                            class="img-fluid rounded-circle me-2 avatar-40" /></span>
                                    {{ $appointment->otherPatient->first_name }}
                                    {{ $appointment->otherPatient->last_name }}
                                </h6>
                            @endif
                        </div>

                        @if ($appointment->media->isNotEmpty())
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_medical_report') }}</span>
                                <ul>
                                    @foreach ($appointment->media as $media)
                                        <li>
                                            <a href="{{ asset($media->getUrl()) }}" target="_blank">
                                                {{ __('appointment.view_medical_report') }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if ($appointment->appointment_extra_info != '')
            <div class="col-md-12">
                <div class="card card-block card-stretch card-height">
                    <div class="card-body">
                        <div class="flex-column">
                            <h5>{{ __('appointment.lbl_medical_history') }}</h5>
                            <span class="m-0">{{ $appointment->appointment_extra_info }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-lg-12">
            <div class="row gy-3">
                <div class="col-md-12 col-lg-12">
                    <h5 class="mb-3 mt-3">{{ __('messages.service') }}</h5>
                    <div class="card card-block card-stretch card-height mb-0">
                <div class="card-body">
                    @if ($appointment->patientEncounter == null)
                                <div class="content-detail">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                        <span>{{ __('messages.item_name') }}</span>
                                        <span class="heading-color">{{ optional($appointment->clinicservice)->name ?? '--' }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                        <span>{{ __('messages.price') }}</span>
                                        <span class="heading-color">{{ Currency::format($appointment->service_price) ?? '--' }}</span>
                            </div>
                        </div>
                    @endif

                    @if (
                        $appointment->patientEncounter !== null &&
                            optional(optional($appointment->patientEncounter)->billingrecord)->billingItem != null)
                        @foreach (optional(optional($appointment->patientEncounter)->billingrecord)->billingItem as $billingItem)
                                    <div class="d-flex align-items-center bg-body p-4 rounded">
                                        <div class="detail-box bg-white rounded">
                                    <img src="{{ optional($billingItem->clinicservice)->file_url ?? default_file_url() }}"
                                        alt="avatar" class="avatar avatar-80 rounded-pill">
                                </div>

                                        <div class="ms-3 w-100 d-flex align-items-center justify-content-between flex-wrap">
                                            <div class="d-flex flex-column flex-grow-1">
                                                <div class="fs-5">
                                                    <b>{{ optional($billingItem->clinicservice)->name ?? 'N/A' }}</b>
                                                </div>
                                                @if(!empty(optional($billingItem->clinicservice)->description))
                                                    <div class="text-muted mt-1" style="font-size: 1rem;">
                                                        {{ optional($billingItem->clinicservice)->description }}
                                            </div>
                                                @endif
                                        </div>                      

                                        @php
                                            // Show service price, add inclusive tax if present
                                            $service_amount = $billingItem->service_amount ?? 0;
                                            $inclusive_tax = $billingItem->inclusive_tax_amount ?? 0;
                                            $quantity = $billingItem->quantity ?? 1;
                                            $service_price = $billingItem->service_amount;
                                            $inclusive_tax = $billingItem->inclusive_tax_amount ?? 0;

                                            // Service amount with inclusive tax per unit
                                            $service_amount_with_inclusive = $service_price + $inclusive_tax;

                                            // Original service total with tax per unit (no discount)
                                            $price_per_unit = $service_price + $inclusive_tax;

                                            // Original total for this item (quantity × price per unit)
                                            $item_original_total = $price_per_unit * $quantity;

                                            // Apply discount if any (item-level discount)
                                            $discount = 0;
                                            $payable_Amount_per_unit = $service_amount_with_inclusive;
                                            if ($billingItem->discount_value > 0) {
                                                if ($billingItem->discount_type === 'percentage') {
                                                    $discount = $item_original_total * ($billingItem->discount_value / 100);
                                                } else {
                                                    $payable_Amount_per_unit = $service_amount_with_inclusive - ($billingItem->discount_value ?? 0);
                                                }
                                            }
                                            
                                            // Total amount with quantity
                                            $payable_Amount = $payable_Amount_per_unit * $quantity;
                                            $service_amount_with_inclusive_total = $service_amount_with_inclusive * $quantity;
                                        @endphp

                                            <div class="d-flex flex-column align-items-end">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                    @endif
                        </div>
                    </div>
                </div>
            </div>
            
            @if (isset($bedAllocations) && $bedAllocations->isNotEmpty())
                <div class="row gy-3 mt-4">
                    <div class="col-sm-12">
                        <h5 class="mb-3">Bed Allocation</h5>
                        <div class="card">
                            <div class="card-body">
                                @include('appointment::backend.patient_encounter.component.bed_allocation_table', [
                                    'data' => $appointment->patientEncounter->billingrecord ?? ($appointment->patientEncounter ?? ['status' => 1]),
                                    'bedAllocations' => $bedAllocations ?? collect(),
                                    'hideActions' => true
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            @endif
                    @php
                        /*
                    ================================================================================
                    APPOINTMENT BILLING CALCULATION SYSTEM
                    ================================================================================

                    This system handles two calculation methods:
                    1. NEW METHOD: Enhanced encounter service pricing with base/additional service distinction
                    2. OLD METHOD: Traditional calculation for backward compatibility

                    CALCULATION FLOW:
                    ==================
                    1. Calculate Service Totals (with/without discounts)
                    2. Apply Service-Level Discounts (individual service discounts)
                    3. Apply Encounter-Level Discounts (overall encounter discounts)
                    4. Calculate Sub Total and Final Amount
                    5. Display Results with proper formatting

                    DISCOUNT TYPES:
                    ===============
                    - Service Discount: Applied to individual services (base service or additional services)
                    - Encounter Discount: Applied to total service amount after service discounts

                    DISPLAY LOGIC:
                    ==============
                    - When encounter discount exists: Service Total shows amount after service discount, encounter discount shown separately
                    - When only service discount exists: Service Total shows original amount, service discount shown separately
                    - When no discounts: Service Total shows original amount, no discount line
                    */
                    
                    // ============================================================================
                    // STEP 1: INITIALIZE CALCULATION VARIABLES
                    // ============================================================================
                    
                    // NEW CALCULATION METHOD VARIABLES
                    $service_total_amount = 0;           // Total of all services (base + additional)
                    $base_service_total = 0;             // Base service amount (first item in encounter)
                    $additional_services_total = 0;      // Additional services amount (encounter-added services)
                    $base_service_discount = 0;          // Discount applied to base service
                    
                    // OLD CALCULATION METHOD VARIABLES (for backward compatibility)
                    $old_service_total_amount = 0;       // Traditional total calculation
                    
                    // ============================================================================
                    // STEP 2: CALCULATE SERVICE TOTALS - Matching billing_detail.blade.php logic
                    // ============================================================================
                    
                    // Calculate Service Total from billing items - Matching frontend appointment_detail.blade.php logic
                    // Service Amount = BASE PRICE ONLY (without inclusive tax, without discounts)
                    $totalServiceAmount = 0; // Base service price only (for display and tax calculation)
                    $totalServiceDiscount = 0; // Service-level discounts (tracked separately)
                    
                    if ($appointment->patientEncounter !== null && optional($appointment->patientEncounter->billingrecord)->billingItem && optional($appointment->patientEncounter->billingrecord)->billingItem->isNotEmpty()) {
                        foreach ($appointment->patientEncounter->billingrecord->billingItem as $item) {
                            $quantity = $item->quantity ?? 1;
                            $item_id = $item->item_id ?? null;
                            
                            // Get base service price - matching frontend: use service_amount directly
                            $unitPrice = $item->service_amount ?? 0; // Base price per unit (matching frontend)
                            
                            // Service price total (base price only, without inclusive tax)
                            $itemBasePriceTotal = $unitPrice * $quantity;
                            
                            // Get discount information
                            $itemDiscountValue = $item->discount_value ?? null;
                            $itemDiscountType = $item->discount_type ?? 'percentage';
                            $itemDiscountStatus = $item->discount_status ?? null;
                            
                            // If billing item has no discount, check service for discount
                            if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
                                if (!empty($item_id)) {
                                    $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                    if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                        $itemDiscountValue = $service->discount_value;
                                        $itemDiscountType = $service->discount_type ?? 'percentage';
                                        $itemDiscountStatus = 1;
                                    }
                                }
                            }
                            
                            // Calculate service discount amount (applied to base price only)
                            $itemDiscountAmount = 0;
                            if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
                                if ($itemDiscountStatus === null) {
                                    $itemDiscountStatus = 1;
                                }
                                
                                if ($itemDiscountStatus == 1) {
                                    if ($itemDiscountType == 'percentage') {
                                        $itemDiscountAmount = ($itemBasePriceTotal * $itemDiscountValue) / 100;
                                    } else {
                                        $itemDiscountAmount = $itemDiscountValue * $quantity;
                                    }
                                }
                            }
                            
                            // Add to total service amount (base price only)
                            $totalServiceAmount += $itemBasePriceTotal;
                            
                            // Add to total service discount
                            $totalServiceDiscount += $itemDiscountAmount;
                        }
                    } else {
                        // Fallback: For appointments without encounter, use service_price (base price only)
                        $totalServiceAmount = $appointment->service_price ?? 0;
                        $totalServiceDiscount = 0;
                    }
                    @endphp
                   
                    @php
                            $transaction = $appointment->appointmenttransaction ? $appointment->appointmenttransaction : null;
                        $bedcharges = 0;
                        
                            if ($appointment->patientEncounter !== null) {
                            $transaction = optional($appointment->patientEncounter)->billingrecord ? optional($appointment->patientEncounter)->billingrecord : null;
                            
                            // Calculate bed charges from multiple sources
                            // First try from billing record
                            $bedcharges = $appointment->patientEncounter->billingrecord->bed_charges ?? 0;
                            
                            // If still 0, try from bed allocations passed to view
                            if ($bedcharges == 0 && isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                $bedcharges = $bedAllocations->sum('charge') ?? 0;
                            }
                            
                            // If still 0, query directly by encounter_id ONLY (no fallback to patient_id)
                            if ($bedcharges == 0 && $appointment->patientEncounter->id) {
                                $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $appointment->patientEncounter->id)
                                    ->whereNotNull('encounter_id') // Ensure encounter_id is not null
                                    ->whereNull('deleted_at')
                                    ->get();
                                if ($allBedAllocations->isNotEmpty()) {
                                    $bedcharges = $allBedAllocations->sum('charge') ?? 0;
                                }
                                // NO fallback to patient_id - only use encounter_id
                            }
                            
                            // Overall discount (final_discount) - Apply only to base service amount (matching frontend logic)
                            $overallDiscountAmount = 0;
                            if ($transaction != null && isset($transaction->final_discount) && $transaction->final_discount == 1) {
                                $discountType = $transaction->final_discount_type ?? 'percentage';
                                $discountValue = $transaction->final_discount_value ?? 0;
                                
                                if ($discountType === 'percentage') {
                                    $overallDiscountAmount = ($totalServiceAmount * $discountValue) / 100;
                                } else {
                                    $overallDiscountAmount = $discountValue;
                                }
                            }
                            
                            // Total discount = Service discounts + Overall discount
                            $totalDiscountAmount = $totalServiceDiscount + $overallDiscountAmount;
                            
                            // Calculate amount after discount: Service Amount - Total Discount
                            $amountAfterDiscount = $totalServiceAmount - $totalDiscountAmount;
                            
                            // Tax calculation logic (matching frontend):
                            // - If there's NO overall discount, calculate tax on BASE service amount (ignoring service-level discounts)
                            // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount)
                            $amountForTaxCalculation = $totalServiceAmount;
                            if ($overallDiscountAmount > 0) {
                                // When overall discount exists, calculate tax on (Base Service Amount - Overall Discount)
                                $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                            }
                            // Note: Service-level discounts are NOT included in tax calculation base
                            
                            // Get tax_data from transaction if available
                            $taxData = null;
                            if ($transaction && isset($transaction->tax_data)) {
                                $taxData = $transaction->tax_data;
                            }
                            
                            // Tax (Exclusive) is calculated using getBookingTaxamount function
                            $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
                            $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                            $taxBreakdown = $taxDetails['tax_details'] ?? [];
                            
                            // Calculate total payable amount: (Base Service Amount - Overall Discount) + Tax (WITHOUT bed charges)
                            // Note: Service-level discounts are separate and don't affect tax or total payable calculation
                            $totalPayableAmount = $amountForTaxCalculation + $taxAmount;
                            
                            // Final Total: Total Payable Amount + Bed Charges
                            $final_total_amount = $totalPayableAmount + $bedcharges;
                            
                            // For display: Use totalServiceAmount as service_total_amount
                            $service_total_amount = $totalServiceAmount;
                            $encounter_discount_amount = $overallDiscountAmount;
                            } else {
                                // For appointments without patientEncounter - use direct appointment transaction
                                $overallDiscountAmount = 0;
                                if ($transaction && isset($transaction->discount_value) && $transaction->discount_value > 0) {
                                    $discountType = $transaction->discount_type ?? 'percentage';
                                    $discountValue = $transaction->discount_value;
                                    
                                    if ($discountType === 'percentage') {
                                        $overallDiscountAmount = ($totalServiceAmount * $discountValue) / 100;
                                    } else {
                                        $overallDiscountAmount = $discountValue;
                                    }
                                }
                                
                                // Tax calculation logic (matching frontend)
                                $amountForTaxCalculation = $totalServiceAmount;
                                if ($overallDiscountAmount > 0) {
                                    $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                                }
                                
                                // Get tax_data from transaction if available
                                $taxData = null;
                                if ($transaction && isset($transaction->tax_percentage)) {
                                    $taxData = $transaction->tax_percentage;
                                }
                                
                                // Tax (Exclusive) is calculated using getBookingTaxamount function
                                $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
                                $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                                $taxBreakdown = $taxDetails['tax_details'] ?? [];
                                
                                // Calculate total payable amount
                                $totalPayableAmount = $amountForTaxCalculation + $taxAmount;
                                
                                // Final Total: Total Payable Amount + Bed Charges
                                $final_total_amount = $totalPayableAmount + $bedcharges;
                                
                                // For display
                                $service_total_amount = $totalServiceAmount;
                                $encounter_discount_amount = $overallDiscountAmount;
                            }

                            @endphp

                    <div class="row gy-3 mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">{{ __('report.lbl_taxes') }}</h5>
                            <div class="card">
                                <div class="card-body">
                        @if ($transaction !== null)
                                @if ($appointment->patientEncounter !== null)
                                            <!-- STEP 1: Service Amount -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.service_amount') }}</span>
                                                <span class="heading-color">{{ Currency::format($service_total_amount) ?? '--' }}</span>
                                            </div>

                                            <!-- STEP 2: Discount Amount (if any) -->
                                            @if($encounter_discount_amount > 0)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.discount_amount') }}</span>
                                                <span class="heading-color">{{ Currency::format($encounter_discount_amount) ?? '--' }}</span>
                                            </div>
                                            @endif

                                            <!-- STEP 3: Tax -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.tax') }}</span>
                                                <span class="heading-color">{{ Currency::format($taxAmount) ?? '--' }}</span>
                                            </div>

                                            <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                                <span class="text-dark">{{ Currency::format($totalPayableAmount) ?? '--' }}</span>
                                            </div>
                                            <hr class="border-top border-gray">

                                            <!-- STEP 5: Bed Total (if bed charges exist) -->
                                            @if($bedcharges > 0)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>Bed Total</span>
                                                <span class="heading-color">{{ Currency::format($bedcharges) ?? '--' }}</span>
                                            </div>
                                            @endif

                                            <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span class="heading-color fw-bold">Final Total Amount</span>
                                                <span class="text-dark fw-bold">{{ Currency::format($final_total_amount) ?? '--' }}</span>
                                            </div>
                                            
                                            <!-- Payment Method - Only show when encounter is closed/complete AND payment is complete -->
                                            @php
                                                $showPaymentMethod = false;
                                                $encounterClosed = false;
                                                $paymentComplete = false;
                                                
                                                if ($appointment->patientEncounter) {
                                                    // Check if encounter is closed (status = 0) or appointment is complete (checkout)
                                                    $encounterClosed = ($appointment->patientEncounter->status == 0) || ($appointment->status == 'checkout');
                                                    
                                                    // Check if payment is complete
                                                    $billingRecord = $appointment->patientEncounter->billingrecord ?? null;
                                                    if ($billingRecord && $billingRecord->payment_status == 1) {
                                                        $paymentComplete = true;
                                                    } elseif (optional(optional($appointment->patientEncounter->appointmentdetail)->appointmenttransaction)->payment_status == 1) {
                                                        $paymentComplete = true;
                                                    }
                                                    
                                                    $showPaymentMethod = $encounterClosed && $paymentComplete;
                                                }
                                            @endphp
                                            @if($showPaymentMethod && optional(optional(optional($appointment->patientEncounter)->appointmentdetail)->appointmenttransaction)->transaction_type)
                                                @php
                                                    $paymentMethods = [
                                                        'razor_payment_method' => 'Razorpay',
                                                        'str_payment_method' => 'Stripe',
                                                        'paystack_payment_method' => 'Paystack',
                                                        'paypal_payment_method' => 'Paypal',
                                                        'flutterwave_payment_method' => 'Flutterwave',
                                                        'airtel_payment_method' => 'Airtel',
                                                        'phonepay_payment_method' => 'PhonePe',
                                                        'midtrans_payment_method' => 'Midtrans',
                                                        'cinet_payment_method' => 'Cinet',
                                                        'sadad_payment_method' => 'Sadad',
                                                    ];
                                                    $methodKey = optional(optional(optional($appointment->patientEncounter)->appointmentdetail)->appointmenttransaction)->transaction_type;
                                                    $methodName = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                                @endphp
                                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                    <span class="fw-semibold">{{ __('appointment.lbl_payment_method') }}</span>
                                                    <span class="text-primary fw-bold">
                                                        {{ $methodName }}
                                                    </span>
                                                </div>
                                            @endif

                                        @else
                                            <!-- For direct appointments without encounter -->
                                @php
                                    // Service Total = Service Price + Inclusive Tax
                                    $directServiceTotal = ($appointment->service_amount ?? 0) + (optional($transaction)->inclusive_tax_price ?? 0);
                                    
                                    // Encounter-Level Discount (final_discount) - Apply to Service Amount ONLY
                                    $directDiscountAmount = 0;
                                    if ($transaction && isset($transaction->final_discount) && $transaction->final_discount == 1) {
                                        $directDiscountType = $transaction->final_discount_type ?? 'percentage';
                                        $directDiscountValue = $transaction->final_discount_value ?? 0;
                                        
                                        if ($directDiscountType === 'percentage') {
                                            $directDiscountAmount = ($directServiceTotal * $directDiscountValue) / 100;
                                        } else {
                                            $directDiscountAmount = $directDiscountValue;
                                        }
                                    }
                                    
                                    // Calculate amount after discount: Service Amount - Discount
                                    $directAmountAfterDiscount = $directServiceTotal - $directDiscountAmount;
                                    
                                    // Tax (Exclusive) is calculated on (Service Amount - Discount)
                                    $directTaxDetails = getBookingTaxamount($directAmountAfterDiscount, null);
                                    $directTaxAmount = $directTaxDetails['total_tax_amount'] ?? 0;
                                    
                                    // Calculate bed charges for appointments without encounter
                                    // Only use encounter_id, NO fallback to patient_id
                                    $bedcharges_direct = 0;
                                    if (isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                        $bedcharges_direct = $bedAllocations->sum('charge') ?? 0;
                                    }
                                    // NO fallback to patient_id - only beds with encounter_id are counted
                                    
                                    // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                                    $directTotalPayableAmount = $directAmountAfterDiscount + $directTaxAmount;
                                    
                                    // Final Total: Total Payable Amount + Bed Charges
                                    $directFinalTotal = $directTotalPayableAmount + $bedcharges_direct;
                                @endphp
                            
                                            <!-- STEP 1: Service Amount -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.service_amount') }}</span>
                                                <span class="heading-color">{{ Currency::format($directServiceTotal) ?? '--' }}</span>
                                            </div>

                                            <!-- STEP 2: Discount Amount (if any) -->
                                            @if($directDiscountAmount > 0)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.discount_amount') }}</span>
                                                <span class="heading-color">{{ Currency::format($directDiscountAmount) ?? '--' }}</span>
                                            </div>
                                            @endif

                                            <!-- STEP 3: Tax -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('appointment.tax') }}</span>
                                                <span class="heading-color">{{ Currency::format($directTaxAmount) ?? '--' }}</span>
                                            </div>

                                            <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                                <span class="text-dark">{{ Currency::format($directTotalPayableAmount) ?? '--' }}</span>
                                            </div>
                                            <hr class="border-top border-gray">

                                            <!-- STEP 5: Bed Total (if bed charges exist) -->
                                            @if($bedcharges_direct > 0)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>Bed Total</span>
                                                <span class="heading-color">{{ Currency::format($bedcharges_direct) ?? '--' }}</span>
                                            </div>
                                            @endif

                                            <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span class="heading-color fw-bold">Final Total Amount</span>
                                                <span class="text-dark fw-bold">{{ Currency::format($directFinalTotal) ?? '--' }}</span>
                                            </div>
                                        @endif
                                    @else
                                        <!-- For appointments without transaction -->
                                        @php
                                            // Service Total is already calculated above from billing items
                                            // (includes item-level discounts in the calculation)
                                            $noTransactionServiceTotal = $service_total_amount;
                                            
                                            // No encounter-level discount for appointments without transaction
                                            $noTransactionDiscountAmount = 0;
                                            
                                            // Calculate amount after discount: Service Amount - Discount
                                            $noTransactionAmountAfterDiscount = $noTransactionServiceTotal - $noTransactionDiscountAmount;
                                            
                                            // Tax (Exclusive) is calculated on (Service Amount - Discount)
                                            $noTransactionTaxDetails = getBookingTaxamount($noTransactionAmountAfterDiscount, null);
                                            $noTransactionTaxAmount = $noTransactionTaxDetails['total_tax_amount'] ?? 0;
                                            
                                            // Calculate bed charges for appointments without transaction
                                            // Only use encounter_id, NO fallback to patient_id
                                            $bedcharges_no_transaction = 0;
                                            if ($appointment->patientEncounter !== null && $appointment->patientEncounter->id) {
                                                $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $appointment->patientEncounter->id)
                                                    ->whereNotNull('encounter_id') // Ensure encounter_id is not null
                                                    ->whereNull('deleted_at')
                                                    ->get();
                                                if ($allBedAllocations->isNotEmpty()) {
                                                    $bedcharges_no_transaction = $allBedAllocations->sum('charge') ?? 0;
                                                }
                                                // NO fallback to patient_id
                                            } elseif (isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                                $bedcharges_no_transaction = $bedAllocations->sum('charge') ?? 0;
                                            }
                                            // NO fallback to patient_id - only beds with encounter_id are counted
                                            
                                            // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                                            $noTransactionTotalPayableAmount = $noTransactionAmountAfterDiscount + $noTransactionTaxAmount;
                                            
                                            // Final Total: Total Payable Amount + Bed Charges
                                            $noTransactionFinalTotal = $noTransactionTotalPayableAmount + $bedcharges_no_transaction;
                                        @endphp
                                        
                                        <!-- STEP 1: Service Amount -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.service_amount') }}</span>
                                            <span class="heading-color">{{ Currency::format($noTransactionServiceTotal) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 2: Discount Amount (if any) -->
                                        @if($noTransactionDiscountAmount > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.discount_amount') }}</span>
                                            <span class="heading-color">{{ Currency::format($noTransactionDiscountAmount) ?? '--' }}</span>
                                        </div>
                                        @endif

                                        <!-- STEP 3: Tax -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.tax') }}</span>
                                            <span class="heading-color">{{ Currency::format($noTransactionTaxAmount) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                            <span class="text-dark">{{ Currency::format($noTransactionTotalPayableAmount) ?? '--' }}</span>
                                        </div>
                                        <hr class="border-top border-gray">

                                        <!-- STEP 5: Bed Total (if bed charges exist) -->
                                        @if($bedcharges_no_transaction > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>Bed Total</span>
                                            <span class="heading-color">{{ Currency::format($bedcharges_no_transaction) ?? '--' }}</span>
                                        </div>
                                        @endif

                                        <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color fw-bold">Final Total Amount</span>
                                            <span class="text-dark fw-bold">{{ Currency::format($noTransactionFinalTotal) ?? '--' }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                        @if($appointment->cancellation_charge_amount != null)
                        <ul class="list-unstyled pt-4 mb-0">
                            <li class="d-flex align-items-center justify-content-between pb-2 mb-2">
                                <span>
                                    {{ __('service.remaining_amount') }}
                                    <span class="text-capitalize badge bg-success p-2">{{ __('appointment.paid') }}</span>
                                </span>
                                <span class="text-dark">{{ Currency::format($remaining_payable_amount) ?? '--' }}</span>
                            </li>
                        </ul>
                        @endif

                        @if ($appointment->cancellation_charge_amount != null)
                        <ul class="list-unstyled pt-4 mb-0">
                            <li class="d-flex align-items-start justify-content-between pb-2 mb-2">
                                <div class="w-100">
                                    <span>
                                        {{ __('messages.cancellation_fee') }}
                                        @if ($appointment->cancellation_type === 'fixed')
                                            ({{ Currency::format($appointment->cancellation_charge) }})
                                        @elseif($appointment->cancellation_type === 'percentage')
                                            ({{ $appointment->cancellation_charge }}%)
                                        @endif
                                    </span>

                                    @if (optional($appointment->appointmenttransaction)->advance_payment_status == 1 && $appointment->status == 'checkout')
                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                            <span>
                                                {{ __('service.remaining_amount') }}
                                                <span class="text-capitalize badge bg-success p-2 ms-2">{{ __('appointment.paid') }}</span>
                                            </span>
                                            <span class="heading-color">{{ Currency::format($remaining_amount) }}</span>
                                        </div>
                                    @elseif(optional($appointment->appointmenttransaction)->advance_payment_status == 1 &&
                                        optional($appointment->appointmenttransaction)->payment_status != 1 &&
                                        $appointment->status != 'cancelled')
                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                            <span>
                                                {{ __('service.remaining_amount') }}
                                                <span class="text-capitalize badge bg-warning p-2 ms-2">{{ __('appointment.pending') }}</span>
                                            </span>
                                            <span class="heading-color">{{ Currency::format($remaining_amount) }}</span>
                                        </div>
                                    @endif

                                    @php
                                        $showPaymentMethodOther = false;
                                        if ($appointment->patientEncounter) {
                                            // Check if encounter is closed or appointment is complete
                                            $encounterClosed = ($appointment->patientEncounter->status == 0) || ($appointment->status == 'checkout');
                                            
                                            // Check if payment is complete
                                            $billingRecord = $appointment->patientEncounter->billingrecord ?? null;
                                            $paymentComplete = false;
                                            if ($billingRecord && $billingRecord->payment_status == 1) {
                                                $paymentComplete = true;
                                            } elseif (optional($appointment->appointmenttransaction)->payment_status == 1) {
                                                $paymentComplete = true;
                                            }
                                            
                                            $showPaymentMethodOther = $encounterClosed && $paymentComplete;
                                        } else {
                                            // For appointments without encounter, show if payment is complete
                                            $showPaymentMethodOther = optional($appointment->appointmenttransaction)->payment_status == 1;
                                        }
                                    @endphp
                                    @if($showPaymentMethodOther && optional($appointment->appointmenttransaction)->transaction_type)
                                        @php
                                            $paymentMethods = [
                                                'razor_payment_method' => 'Razorpay',
                                                'str_payment_method' => 'Stripe',
                                                'paystack_payment_method' => 'Paystack',
                                                'paypal_payment_method' => 'Paypal',
                                                'flutterwave_payment_method' => 'Flutterwave',
                                                'airtel_payment_method' => 'Airtel',
                                                'phonepay_payment_method' => 'PhonePe',
                                                'midtrans_payment_method' => 'Midtrans',
                                                'cinet_payment_method' => 'Cinet',
                                                'sadad_payment_method' => 'Sadad',
                                            ];
                                            $methodKey = optional($appointment->appointmenttransaction)->transaction_type;
                                            $methodName = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                        @endphp
                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                            <span class="fw-semibold">{{ __('appointment.lbl_payment_method') }}</span>
                                            <span class="text-primary fw-bold">
                                                {{ $methodName }}
                                            </span>
                                        </div>
                                    @endif

                                    @if (
                                        $appointment->status === 'cancelled' &&
                                            ($appointment->cancellation_charge_amount != null || $appointment->advance_paid_amount > 0))
                                        @php
                                            $payment_status = optional($appointment->appointmenttransaction)->payment_status;
                                            $advance_paid_amount = $appointment->advance_paid_amount ?? 0;
                                            $total_paid = $appointment->total_amount ?? 0;
                                            $cancellation_charge_amount = $appointment->cancellation_charge_amount ?? 0;

                                            if ($payment_status == 0 || $advance_paid_amount > 0) {
                                                $refundAmount = max(0, $advance_paid_amount - $cancellation_charge_amount);
                                                $paidAmount = $advance_paid_amount;
                                            } else {
                                                $refundAmount = max(0, $total_paid - $cancellation_charge_amount);
                                                $paidAmount = $total_paid;
                                            }

                                            $paymentMethods = [
                                                'razor_payment_method' => 'Razorpay',
                                                'str_payment_method' => 'Stripe',
                                                'paystack_payment_method' => 'Paystack',
                                                'paypal_payment_method' => 'Paypal',
                                                'flutterwave_payment_method' => 'Flutterwave',
                                                'airtel_payment_method' => 'Airtel',
                                                'phonepay_payment_method' => 'PhonePe',
                                                'midtrans_payment_method' => 'Midtrans',
                                                'cinet_payment_method' => 'Cinet',
                                                'sadad_payment_method' => 'Sadad',
                                            ];
                                            $methodKey = optional($appointment->appointmenttransaction)->transaction_type ?? 'cash';
                                            $paymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                        @endphp

                                        <div class="mt-4">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0 fw-bold">{{ __('messages.refund_detail') ?? 'Refund Detail' }}</h6>
                                            </div>

                                            <div class="border rounded p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="mb-0 fw-bold">
                                                        {{ __('messages.refund_of_amount', ['amount' => Currency::format($refundAmount)]) ?? 'Refund of ' . Currency::format($refundAmount) }}
                                                    </h6>
                                                    <span class="badge bg-success ms-3">{{ __('messages.refund_completed') ?? 'Refund Completed' }}</span>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <span class="text-muted">{{ __('appointment.lbl_payment_method') }}:</span>
                                                        <span class="text-primary fw-semibold">{{ $paymentMethod }}</span>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <div class="d-flex justify-content-between py-1">
                                                        <span>{{ __('messages.price') ?? 'Price' }}:</span>
                                                        <span class="fw-semibold">{{ Currency::format($paidAmount) }}</span>
                                                    </div>

                                                    @if ($cancellation_charge_amount > 0)
                                                        <div class="d-flex justify-content-between py-1">
                                                            <span>
                                                                {{ __('messages.cancellation_fee') }}
                                                                @if ($appointment->cancellation_type === 'percentage')
                                                                    ({{ $appointment->cancellation_charge }}%)
                                                                @elseif($appointment->cancellation_type === 'fixed')
                                                                    ({{ Currency::format($appointment->cancellation_charge) }})
                                                                @endif
                                                                :
                                                            </span>
                                                            <span class="fw-semibold">{{ Currency::format($cancellation_charge_amount) }}</span>
                                                        </div>
                                                    @endif

                                                    <hr class="my-2">

                                                    <div class="d-flex justify-content-between py-2 bg-light rounded px-3" style="background-color: #e8f5e8 !important;">
                                                        <span class="fw-bold text-success">{{ __('service.refund_amount') ?? 'Refund Amount' }}:</span>
                                                        <span class="fw-bold text-success">{{ Currency::format($refundAmount) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($appointment->appointmenttransaction == null && $appointment->clinicservice->is_enable_advance_payment == 1)
                                        @php
                                            $pending_amount =
                                                ($appointment->total_amount * $appointment->clinicservice->advance_payment_amount) /
                                                100;
                                        @endphp
                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                            <span>{{ __('appointment.pending_advance_payment_amount') }}</span>
                                            <span class="heading-color">{{ Currency::format($pending_amount) ?? '--' }}</span>
                                        </div>
                                    @endif
                                </span>
                            </li>
                        </ul>
                    @endif
                                </div>
                            </li>
                </div>
            </div>
        </div>
    </div>

@endsection

    @push('after-styles')
        <style>
            .detail-box {
                padding: 0.625rem 0.813rem;
            }
            .footer,
            .footer .footer-body {
                display: none !important;
            }
        </style>
        <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
    @endpush

    @push('after-scripts')
        <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
        <script src="{{ mix('modules/appointment/script.js') }}"></script>
        <script src="{{ asset('js/form-offcanvas/index.js') }}" defer></script>
        <script>
            function toggleTaxBreakdown(id) {
                const breakdown = document.getElementById(id);
                const icon = document.getElementById(id + '-icon');
                
                if (breakdown) {
                    if (breakdown.style.display === 'none' || breakdown.style.display === '') {
                        breakdown.style.display = 'block';
                        if (icon) {
                            icon.classList.remove('ph-caret-down');
                            icon.classList.add('ph-caret-up');
                        }
                    } else {
                        breakdown.style.display = 'none';
                        if (icon) {
                            icon.classList.remove('ph-caret-up');
                            icon.classList.add('ph-caret-down');
                        }
                    }
                }
            }
        </script>
    @endpush

