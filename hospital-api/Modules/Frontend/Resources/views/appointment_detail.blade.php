@extends('frontend::layouts.master')

@section('title', __('frontend.appointment_detail'))

@section('content')
    @include('frontend::components.section.breadcrumb')
        <div class="list-page section-spacing px-0">
            <div class="page-title" id="page_title">
                <div class="container">
                    <div class="d-flex align-items-center justify-content-md-between gap-3 flex-wrap mb-5">
                        <h6 class="font-size-18 mb-0">{{ __('frontend.appointment_detail') }}
                        </h6>
                        @php
                            $id = $appointment ? $appointment->id : 0;
                            $status = $appointment ? $appointment->status : null;
                            $pay_status = $appointment ? optional($appointment->appointmenttransaction)->payment_status : 0;
                        @endphp
                        <div class="d-flex align-items-center gap-3 flex-md-nowrap flex-wrap">
                            @if ($pay_status == 1 && $status == 'checkout')
                                <a class="btn btn-secondary" href="{{ route('download_invoice', ['id' => $appointment->id]) }}">
                                    <i class="fa-solid fa-download"></i>
                                    {{ __('frontend.lbl_download_invoice') }}
                                </a>
                            @endif
                            @if ($appointment->status == 'checkout' || $appointment->status == 'check_in')
                                @if ($appointment->patientEncounter !== null)
                                    <button data-bs-toggle="modal" data-bs-target="#encounter-details-view"
                                        class="btn btn-primary">
                                        <i class="ph ph-gauge align-middle me-2"></i>{{ __('frontend.encounter') }}
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            @if (empty($appointment->serviceRating) &&
                                    $appointment->status == 'checkout' &&
                                    optional($appointment->appointmenttransaction)->payment_status)
                                <div class="d-flex align-items-center justify-content-between gap-5 flex-wrap mb-5 pb-3">
                                    <h6 class="font-size-18 mb-0">{{ __('frontend.havent_rated') }}
                                    </h6>
                                    <button class="btn btn-secondary d-flex gap-2 align-items-center" data-bs-toggle="modal"
                                        data-service-id="{{ optional($appointment->clinicservice)->id }}"
                                        data-doctor-id="{{ optional($appointment->doctor)->id }}"
                                        data-bs-target="#review-service">
                                        <i class="ph-fill ph-star"></i>{{ __('frontend.rate_us') }}
                                    </button>
                                </div>
                            @endif
                            <div class="section-bg payment-box rounded mb-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="font-size-14">Appointment ID:</span>
                                    <span class="text-primary fw-semibold">#{{ $appointment->id }}</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3">
                                <h6 class="font-size-18">{{ __('frontend.booking_detail') }}
                                </h6>
                                <div class="section-bg payment-box rounded">
                                    <div class="row g-4">
                                        <div class="col-md-4">
                                            <span class="font-size-14">{{ __('frontend.appointment_date_time') }}</span>
                                            <p class="mb-0">
                                                <span class="mb-0 h6">{{ DateFormate($appointment->appointment_date) }}</span>
                                                <span class="mx-1 text-secondary">at</span>
                                                <span class="mb-0 h6 text-uppercase">
                                                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format(setting('time_formate') ?? 'h:i A') }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="font-size-14">{{ __('frontend.service_name') }}</span>
                                            <a href="{{ route('service-details', ['id' => optional($appointment->clinicservice)->id]) }}">
                                                <h6 class="mb-0">{{ optional($appointment->clinicservice)->name ?? '-' }}</h6>
                                            </a>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="font-size-14">{{ __('frontend.doctor') }}</span>
                                            @include('frontend::components.appointment.doctor_info', ['doctor' => $appointment->doctor])
                                        </div>
                                    </div>

                                    <div class="row g-4 mt-4 pt-4 border-top">
                                        <div class="col-md-6">
                                            <span class="font-size-14">{{ __('frontend.clinic_name') }}</span>
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3 mt-2">
                                                <img src="{{ optional($appointment->cliniccenter)->file_url ?? default_file_url() }}"
                                                    alt="clinic" class="avatar avatar-50 rounded-pill">
                                                <div class="text-start">
                                                    <h6 class="mb-0">{{ optional($appointment->cliniccenter)->name ?? '-' }}</h6>
                                                    @if (optional($appointment->cliniccenter)->email)
                                                        <a class="text-break" href="mailto:{{ optional($appointment->cliniccenter)->email }}">
                                                            {{ optional($appointment->cliniccenter)->email }} 
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="font-size-14">{{ __('frontend.booking_status') }}</span>
                                            @php
                                                $status = $appointment->status;
                                                $statusText =
                                                    $status === 'checkout'
                                                        ? 'Complete'
                                                        : \Illuminate\Support\Str::title(str_replace('_', ' ', $status));
                                                $statusClass =
                                                    $status === 'cancelled'
                                                        ? 'text-danger'
                                                        : ($status === 'pending'
                                                            ? 'text-danger'
                                                            : 'text-success');
                                            @endphp
                                            <h6 class="mb-0 {{ $statusClass }}">{{ $statusText }}</h6>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="font-size-14">{{ __('frontend.payment_status') }}</span>
                                            <h6 class="mb-0">
                                                @if ($appointment->appointmenttransaction && $appointment->appointmenttransaction->payment_status)
                                                    @if ($appointment->status == 'cancelled')
                                                        @if ($appointment->advance_paid_amount > 0)
                                                            <span class="text-success">{{ __('frontend.advance_refunded') }}</span>
                                                        @else
                                                            <span class="text-warning">{{ __('frontend.payment_refunded') }}</span>
                                                        @endif
                                                    @else
                                                        @if ($appointment->appointmenttransaction->payment_method == 'cash')
                                                            <span class="text-danger">{{ __('frontend.pending') }}</span>
                                                        @else
                                                            <span class="text-success">{{ __('frontend.paid') }}</span>
                                                        @endif
                                                    @endif
                                                @elseif($advancePaid && optional($appointment->appointmenttransaction)->advance_payment_status == 1)
                                                    @if ($appointment->status == 'cancelled')
                                                        <span class="text-success">{{ __('frontend.advance_refunded') }}</span>
                                                    @else
                                                        <span class="text-success">{{ __('frontend.advance_paid') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-danger">{{ __('frontend.pending') }}</span>
                                                @endif
                                            </h6>
                                        </div>
                                    </div>

                                    <div class="row g-4 mt-4 pt-4 border-top">
                                        <div class="col-md-6">
                                            <span class="font-size-14">{{ __('frontend.booked_for') }}</span>
                                            @if ($appointment->user === null)
                                                <h6 class="m-0">-</h6>
                                            @elseif($appointment->otherPatient)
                                                <div class="d-flex gap-3 align-items-center mt-2">
                                                    <img src="{{ optional($appointment->otherPatient)->profile_image ?? default_user_avatar() }}"
                                                        alt="avatar" class="avatar avatar-50 rounded-pill">
                                                    <div class="text-start">
                                                        <h6 class="m-0">
                                                            {{ optional($appointment->otherPatient)->first_name . ' ' . optional($appointment->otherPatient)->last_name ?? '-' }}
                                                        </h6>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="d-flex gap-3 align-items-center mt-2">
                                                    <img src="{{ optional($appointment->user)->profile_image ?? default_user_avatar() }}"
                                                        alt="avatar" class="avatar avatar-50 rounded-pill">
                                                    <div class="text-start">
                                                        <h6 class="m-0">
                                                            {{ optional($appointment->user)->first_name . ' ' . optional($appointment->user)->last_name ?? '-' }}
                                                        </h6>
                                                        @php
                                                            $userEmail = optional($appointment->user)->email;
                                                        @endphp
                                                        @if ($userEmail)
                                                            <a href="mailto:{{ $userEmail }}">{{ $userEmail }}</a>
                                                        @else
                                                            <span>-</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        @php
                                            // Check if encounter is closed (status == 0 means closed)
                                            $encounterClosed = false;
                                            if ($appointment->patientEncounter) {
                                                $encounterClosed = ($appointment->patientEncounter->status == 0);
                                            }
                                        @endphp
                                        @if ($encounterClosed)
                                        <div class="col-md-6">
                                            <span class="font-size-14">{{ __('frontend.payment_method') }}</span>
                                            <h6 class="mb-0">
                                                    @php
                                                        $paymentMethod = null;
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
                                                            'cash' => 'Cash',
                                                            'wallet' => 'Wallet',
                                                        ];
                                                        
                                                        // Check appointment transaction transaction_type first
                                                        if ($appointment->appointmenttransaction && $appointment->appointmenttransaction->transaction_type) {
                                                            $methodKey = $appointment->appointmenttransaction->transaction_type;
                                                            $paymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                                        }
                                                        // Check appointment transaction payment_method
                                                        if (!$paymentMethod && $appointment->appointmenttransaction && $appointment->appointmenttransaction->payment_method) {
                                                            $methodKey = $appointment->appointmenttransaction->payment_method;
                                                            $paymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                                        }
                                                        // Check billing record payment_method
                                                        if (!$paymentMethod && $appointment->patientEncounter && $appointment->patientEncounter->billingrecord && $appointment->patientEncounter->billingrecord->payment_method) {
                                                            $methodKey = $appointment->patientEncounter->billingrecord->payment_method;
                                                            $paymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                                        }
                                                        // Default to "Cash" if no payment method found
                                                        $paymentMethod = $paymentMethod ?: 'Cash';
                                                    @endphp
                                                    <span class="text-primary">{{ $paymentMethod }}</span>
                                            </h6>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mt-lg-0 mt-5">
                            <h6 class="pb-1">{{ __('frontend.payment_details') }}</h6>
                            @if (
                                $appointment->status == 'cancelled' &&
                                    optional($appointment->appointmenttransaction)->payment_status != 0 &&
                                    optional($appointment->appointmenttransaction)->transaction_type != 'cash')
                                @php
                                    $refundAmount = $appointment->getRefundAmount(); // Assumes this returns positive or negative amount

                                @endphp
                                <div class="payment-box section-bg rounded">
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="text-muted small">{{ formatDate($appointment->updated_at) }}</div>
                                            <span
                                                class="badge {{ $refundAmount >= 0 ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-2">
                                                {{ $refundAmount >= 0 ? __('frontend.refund_completed') : __('frontend.wallet_deducted') }}
                                            </span>
                                        </div>

                                        <h6 class="fw-bold mb-4">
                                            {{ $refundAmount >= 0 ? __('messages.refund_of') . ' ' . \Currency::format($refundAmount) : __('messages.wallet_deduction') . ' ' . \Currency::format(abs($refundAmount)) }}
                                        </h6>

                                        <div class="row mb-2">
                                            <div class="col-6 text-muted">{{ __('earning.lbl_payment_method') }}</div>
                                            <div class="col-6 text-end text-primary">{{ __('messages.wallet') }}</div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-6 text-muted">{{ __('clinic.price') }}</div>
                                            <div class="col-6 text-end">{{ \Currency::format($appointment->total_amount) }}
                                            </div>
                                        </div>
                                        @if ($appointment->advance_paid_amount != 0)
                                            <div class="row mb-2">
                                                <div class="col-6 text-muted">{{ __('messages.advanced_payment') }} </div>
                                                <div class="col-6 text-end">
                                                    {{ \Currency::format($appointment->advance_paid_amount) }}</div>
                                            </div>
                                        @endif

                                        @if ($appointment->cancellation_charge_amount != 0)
                                            <div class="row mb-2">
                                                <div class="col-6 text-muted">
                                                    {{ __('messages.cancellation_fee') }}
                                                    @if ($appointment->cancellation_type === 'percentage')
                                                        ({{ $appointment->cancellation_charge }}%)
                                                    @else
                                                        ({{ Currency::format($appointment->cancellation_charge) }})
                                                    @endif
                                                </div>
                                        @endif
                                        <hr class="my-3">

                                        <div class="row">
                                            <div class="d-flex justify-content-between align-items-center px-4 py-2 rounded"
                                                style="background-color: {{ $refundAmount >= 0 ? '#e6f4ea' : '#fdecea' }};">

                                                <span
                                                    class="fw-semibold {{ $refundAmount >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $refundAmount >= 0 ? __('messages.refund_amount') : __('frontend.wallet_deducted') }}
                                                </span>

                                                <span class="fw-semibold heading-color">
                                                    {{ \Currency::format(abs($refundAmount)) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @php
                                // Get transaction and tax data
                                $transaction = null;
                                if ($appointment->patientEncounter !== null && optional($appointment->patientEncounter)->billingrecord) {
                                    $transaction = optional($appointment->patientEncounter)->billingrecord;
                                } else {
                                    $transaction = $appointment->appointmenttransaction;
                                }

                                // Calculate service total from billing items - BASE PRICE ONLY (without inclusive tax)
                                $totalServiceAmount = 0;
                                $totalServiceDiscount = 0;

                                if (
                                    $appointment->patientEncounter !== null &&
                                    optional($appointment->patientEncounter->billingrecord)->billingItem
                                ) {
                                    // For encounters: Calculate base service price and service-level discounts
                                    foreach ($appointment->patientEncounter->billingrecord->billingItem as $item) {
                                        $quantity = $item->quantity ?? 1;
                                        $unitPrice = $item->service_amount ?? 0; // Base price per unit
                                        
                                        // Service price total (base price only, without inclusive tax)
                                        $itemBasePriceTotal = $unitPrice * $quantity;
                                        
                                        // Get discount information
                                        $itemDiscountValue = $item->discount_value ?? null;
                                        $itemDiscountType = $item->discount_type ?? 'percentage';
                                        $itemDiscountStatus = $item->discount_status ?? null;
                                        
                                        // If billing item has no discount, check service for discount
                                        if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
                                            if (!empty($item->item_id)) {
                                                $service = \Modules\Clinic\Models\ClinicsService::where('id', $item->item_id)->first();
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
                                    // For direct appointment without encounter
                                    $totalServiceAmount = $appointment->service_price ?? 0;
                                }

                                // Overall discount (final_discount) - Apply only to base service amount
                                $overallDiscountAmount = 0;
                                    if (
                                        $appointment->patientEncounter !== null &&
                                        $transaction &&
                                        ($transaction->final_discount ?? null) == 1
                                    ) {
                                    $discountType = $transaction->final_discount_type ?? 'percentage';
                                    $discountValue = $transaction->final_discount_value ?? 0;
                                    
                                    if ($discountType === 'percentage') {
                                        $overallDiscountAmount = ($totalServiceAmount * $discountValue) / 100;
                                        } else {
                                        $overallDiscountAmount = $discountValue;
                                        }
                                    } elseif (optional($appointment->appointmenttransaction)->discount_value > 0) {
                                        // Direct appointment discount
                                    $discountType = optional($appointment->appointmenttransaction)->discount_type ?? 'percentage';
                                    $discountValue = optional($appointment->appointmenttransaction)->discount_value;

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

                                // Get tax_data from billing record if available
                                $taxData = null;
                                if ($appointment->patientEncounter !== null && $transaction) {
                                    $taxData = $transaction->tax_data ?? null;
                                } elseif ($appointment->appointmenttransaction) {
                                    $taxData = optional($appointment->appointmenttransaction)->tax_percentage ?? null;
                                }

                                // Tax calculation logic:
                                // - If there's NO overall discount, calculate tax on BASE service amount (ignoring service-level discounts)
                                // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount)
                                $amountForTaxCalculation = $totalServiceAmount;
                                if ($overallDiscountAmount > 0) {
                                    // When overall discount exists, calculate tax on (Base Service Amount - Overall Discount)
                                    $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                                }
                                // Note: Service-level discounts are NOT included in tax calculation base

                                // Tax (Exclusive) is calculated using getBookingTaxamount function
                                $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
                                $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                                $taxBreakdown = $taxDetails['tax_details'] ?? [];

                                // Calculate total payable amount: (Base Service Amount - Overall Discount) + Tax (WITHOUT bed charges)
                                // Note: Service-level discounts are separate and don't affect tax or total payable calculation
                                $totalPayableAmount = $amountForTaxCalculation + $taxAmount;

                                // Calculate Bed Charges - Only if bed allocations exist for this encounter
                                $bed_charges = 0;
                                $hasBedAllocations = false;
                                
                                if ($appointment->patientEncounter !== null) {
                                    // First, check if bed allocations exist by querying the database
                                    // This ensures we only show bed charges if bed allocations actually exist
                                    $allBedAllocations = collect();
                                    
                                    if ($appointment->patientEncounter->id) {
                                        $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $appointment->patientEncounter->id)
                                            ->whereNotNull('encounter_id') // Ensure encounter_id is not null
                                            ->whereNull('deleted_at')
                                            ->get();
                                        
                                        if ($allBedAllocations->isNotEmpty()) {
                                            $hasBedAllocations = true;
                                        }
                                    }
                                    
                                    // Also check if bed allocations were passed to view
                                    if (!$hasBedAllocations && isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                        $hasBedAllocations = true;
                                        $allBedAllocations = $bedAllocations;
                                    }
                                    
                                    // Only calculate bed charges if bed allocations actually exist
                                    if ($hasBedAllocations) {
                                        // Calculate bed charges from bed allocations (preferred source)
                                        if ($allBedAllocations->isNotEmpty()) {
                                            $bed_charges = $allBedAllocations->sum('charge') ?? 0;
                                        }
                                        
                                        // If still 0, try from billing record (only if bed allocations exist)
                                        if ($bed_charges == 0 && $transaction && isset($transaction->bed_charges)) {
                                            $bed_charges = $transaction->bed_charges ?? 0;
                                        }
                                    } else {
                                        // No bed allocations exist - ensure bed charges is 0
                                        $bed_charges = 0;
                                    }
                                }
                                
                                // Final total including bed charges (only if bed allocations exist)
                                $grand_total = $totalPayableAmount;
                                if ($hasBedAllocations && $bed_charges > 0) {
                                    $grand_total = $totalPayableAmount + $bed_charges;
                                }
                                $final_amount = $grand_total;
                                @endphp
                            <div class="payment-box section-bg rounded">
                                {{-- frontend side appointment details calculation --}}
                                <!-- STEP 1: Service Amount (Base Price Only) -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                    <span>{{ __('appointment.service_amount') }}</span>
                                    <span class="text-primary fw-bold">{{ Currency::format($totalServiceAmount) }}</span>
                                </div>

                                <!-- STEP 2: Discount (if any) -->
                                @if ($overallDiscountAmount > 0)
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                        <span>{{ __('messages.discount') }}(
                                            @if (($transaction->final_discount_type ?? optional($appointment->appointmenttransaction)->discount_type ?? 'percentage') === 'percentage')
                                                <span class="text-success">{{ ($transaction->final_discount_value ?? optional($appointment->appointmenttransaction)->discount_value) ?? '--' }}%</span>
                                            @else
                                                <span class="text-success">{{ Currency::format(($transaction->final_discount_value ?? optional($appointment->appointmenttransaction)->discount_value) ?? 0) }}</span>
                                            @endif
                                            )
                                        </span>
                                        <span class="text-success fw-bold">- {{ Currency::format($overallDiscountAmount) }}</span>
                                    </div>
                                @endif

                                <!-- STEP 3: Tax -->
                                @if ($taxAmount > 0)
                                    <div class="mb-3">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between tax-total-line"
                                            style="cursor: pointer;" onclick="toggleTaxBreakdown()">
                                            <span>{{ __('frontend.total_tax') }}</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ph ph-caret-up text-danger" id="taxArrow"
                                                    style="transition: transform 0.3s ease;"></i>
                                                <span class="text-danger fw-bold"
                                                    id="totalTaxDisplay">{{ Currency::format($taxAmount) }}</span>
                                            </div>
                                        </div>

                                        <!-- Collapsible Tax Breakdown -->
                                        <div id="taxBreakdown" class="tax-breakdown-container"
                                            style="display: none; margin-top: 10px;">
                                            <div class="bg-light rounded p-3" style="border: 1px solid #e9ecef;">
                                                @foreach ($taxBreakdown as $taxItem)
                                                    @php
                                                        $tax_name = $taxItem['tax_name'] ?? ($taxItem['title'] ?? ($taxItem['name'] ?? 'Tax'));
                                                        $tax_value = $taxItem['tax_value'] ?? ($taxItem['value'] ?? 0);
                                                        $tax_type = $taxItem['tax_type'] ?? ($taxItem['type'] ?? 'percent');
                                                        $individual_tax_amount = $taxItem['tax_amount'] ?? 0;
                                                        @endphp
                                                    <div class="d-flex justify-content-between align-items-center mb-2 text-muted">
                                                            <span>
                                                                @if ($tax_type == 'fixed')
                                                                    {{ $tax_name }}
                                                                @else
                                                                    {{ $tax_name }} ({{ $tax_value }}%)
                                                                @endif
                                                            </span>
                                                        <span>{{ Currency::format($individual_tax_amount) }}</span>
                                                        </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @php
                                    // STEP 4: Total Payable Amount (Service Amount - Discount + Tax, WITHOUT bed charges)
                                    $total_payable_amount = $totalPayableAmount;
                                    
                                    // STEP 5: Final Total = Total Payable Amount + Bed Charges
                                    $grand_total = $total_payable_amount + $bed_charges;
                                        $final_amount = $grand_total;
                                @endphp

                                <!-- STEP 4: Total Payable Amount (Service Amount - Discount + Tax, WITHOUT bed charges) -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                    <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                    <span class="text-dark fw-bold">{{ Currency::format($total_payable_amount) }}</span>
                                </div>
                                
                                <hr class="border-top border-gray">

                                <!-- STEP 5: Bed Total (only if bed allocations exist and bed charges > 0) -->
                                @if($hasBedAllocations && $bed_charges > 0)
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                    <span>{{ __('messages.bed_price') }}</span>
                                    <span class="heading-color">{{ Currency::format($bed_charges) ?? '--' }}</span>
                                </div>
                                @endif

                                <!-- STEP 7: Grand Total (Total Payable Amount + Bed Charges) -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                    <span class="fw-bold text-dark">{{ __('messages.grand_total') }}</span>
                                    <span class="text-success fw-bold">{{ Currency::format($final_amount) ?? '--' }}</span>
                                </div>
                                @if (optional($appointment->appointmenttransaction)->advance_payment_status == 1)
                                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                                        <span>{{ __('service.advance_payment_amount') }}({{ $appointment->advance_payment_amount }}%)</span>
                                        <span>{{ Currency::format($appointment->advance_paid_amount) ?? '--' }}</span>
                                    </div>
                                @endif

                                @if (optional($appointment->appointmenttransaction)->advance_payment_status == 1 && $appointment->status == 'checkout')
                                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                        <span>{{ __('service.remaining_amount') }}<span
                                                class="text-capitalize badge bg-success p-2 ms-2">{{ __('appointment.paid') }}</span></span>
                                        <span
                                            class="heading-color">{{ Currency::format($final_amount - $appointment->advance_paid_amount) }}</span>
                                    </div>
                                @elseif (optional($appointment->appointmenttransaction)->advance_payment_status == 1 &&
                                        optional($appointment->appointmenttransaction)->payment_status != 1 &&
                                        $appointment->status != 'cancelled')
                                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                        <span>{{ __('service.remaining_amount') }}<span
                                                class="text-capitalize badge bg-warning p-2 ms-2">{{ __('appointment.pending') }}</span></span>
                                        <span
                                            class="heading-color">{{ Currency::format($final_amount - $appointment->advance_paid_amount) }}</span>
                                    </div>
                                @endif

                                @php
                                    // Check if encounter is closed (status == 0 means closed)
                                    $encounterClosed = false;
                                    if ($appointment->patientEncounter) {
                                        $encounterClosed = ($appointment->patientEncounter->status == 0);
                                    }
                                @endphp
                                @if ($encounterClosed)
                                    @php
                                        // Get payment method from multiple sources
                                        $displayPaymentMethod = null;
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
                                            'cash' => 'Cash',
                                            'wallet' => 'Wallet',
                                        ];
                                        
                                        // Check appointment transaction transaction_type first
                                        if (optional($appointment->appointmenttransaction)->transaction_type) {
                                            $methodKey = optional($appointment->appointmenttransaction)->transaction_type;
                                            $displayPaymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                        }
                                        // Check appointment transaction payment_method
                                        if (!$displayPaymentMethod && optional($appointment->appointmenttransaction)->payment_method) {
                                            $methodKey = optional($appointment->appointmenttransaction)->payment_method;
                                            $displayPaymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                        }
                                        // Check billing record payment_method
                                        if (!$displayPaymentMethod && $appointment->patientEncounter && optional($appointment->patientEncounter->billingrecord)->payment_method) {
                                            $methodKey = optional($appointment->patientEncounter->billingrecord)->payment_method;
                                            $displayPaymentMethod = $paymentMethods[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey));
                                        }
                                        // Default to "Cash" if no payment method found
                                        $displayPaymentMethod = $displayPaymentMethod ?: 'Cash';
                                    @endphp
                                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                        <span class="fw-semibold">{{ __('appointment.lbl_payment_method') }}</span>
                                        <span class="text-primary fw-bold">
                                            {{ $displayPaymentMethod }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                @if (
                                    $advancePaid &&
                                        $appointment->status == 'check_in' &&
                                        optional($appointment->appointmenttransaction)->payment_status == 0 &&
                                        optional($appointment->patientEncounter)->status == 1)
                                    <a href="#" class="btn btn-secondary" data-bs-toggle="modal"
                                        data-bs-target="#paymentModal">{{ __('frontend.pay_now') }}
                                        {{ Currency::format($final_amount - $appointment->advance_paid_amount) }}</a>
                                @elseif(
                                    $appointment->status == 'check_in' &&
                                        optional($appointment->appointmenttransaction)->payment_status == 0 &&
                                        optional($appointment->patientEncounter)->status == 1)
                                    <a href="#" class="btn btn-secondary" data-bs-toggle="modal"
                                        data-bs-target="#paymentModal">{{ __('frontend.pay_now') }}
                                        {{ Currency::format($final_amount) }}</a>
                                @endif
                            </div>
                        </div>


                        

                        <div class="mt-5 pt-3">
                            <h6 class="font-size-18">{{ __('frontend.service_detail') }}
                            </h6>
                            <div class="section-bg payment-box rounded">

                                @php
                                    $isEncounter = $appointment->patientEncounter !== null;
                                @endphp

                                @if (! $isEncounter)
                                    <div
                                        class="d-flex align-items-md-center bg-body p-4 rounded flex-md-row flex-column gap-3 payment-box-info">
                                        <div class="detail-box">
                                            <img src="{{ optional($appointment->clinicservice)->file_url ?? default_file_url() }}"
                                                alt="service" class="avatar avatar-80 rounded-pill">
                                        </div>

                                        <div class="row">
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <a
                                                        href="{{ route('service-details', ['id' => optional($appointment->clinicservice)->id]) }}">
                                                        <b>{{ optional($appointment->clinicservice)->name ?? '-' }}</b>
                                                    </a>
                                                    <div class="mt-2">
                                                        {{ optional($appointment->clinicservice)->description ?? ' ' }}
                                                    </div>
                                                </div>
                                            </div>
                                            @php
                                                $payableAmount = $appointment->service_price ?? 0;
                                                $inclusiveTaxPrice =
                                                    optional($appointment->appointmenttransaction)
                                                        ->inclusive_tax_price ?? 0;
                                                $originalPrice = $payableAmount + $inclusiveTaxPrice;

                                                $finalPrice = $originalPrice;
                                                if (optional($appointment->appointmenttransaction)->discount_value > 0) {
                                                    if (
                                                        optional($appointment->appointmenttransaction)->discount_type ===
                                                        'percentage'
                                                    ) {
                                                        $discountAmount =
                                                            $originalPrice *
                                                            (optional($appointment->appointmenttransaction)
                                                                ->discount_value /
                                                                100);
                                                    } else {
                                                        $discountAmount = optional(
                                                            $appointment->appointmenttransaction,
                                                        )->discount_value;
                                                    }
                                                    $finalPrice = max(0, $originalPrice - $discountAmount);
                                                }
                                            @endphp
                                            @if (optional($appointment->appointmenttransaction)->discount_value > 0)
                                                <div class="d-flex align-items-center gap-2">
                                                    <h6 class="mb-0">
                                                        {{ Currency::format($finalPrice) }}
                                                        <span class="text-success ms-2">
                                                            ({{ optional($appointment->appointmenttransaction)->discount_value }}{{ optional($appointment->appointmenttransaction)->discount_type === 'percentage' ? '%' : '' }}
                                                            {{ __('frontend.off') }})
                                                        </span>
                                                    </h6>
                                                    <del class="text-muted">{{ Currency::format($originalPrice) }}</del>
                                                </div>
                                            @else
                                                <h6 class="mb-0">
                                                    {{ Currency::format($finalPrice) }}
                                                </h6>
                                            @endif
                                            @if ($inclusiveTaxPrice > 0)
                                                <small class="text-secondary"><i>{{ __('messages.lbl_with_inclusive_tax') }}</i></small>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $billingRecord = optional(optional($appointment->patientEncounter)->billingrecord);
                                        $billingItems = collect(optional($billingRecord)->billingItem ?? []);
                                    @endphp

                                    @forelse ($billingItems as $item)
                                        @php
                                            $service = optional($item->clinicservice);
                        $serviceImage = $service->file_url ?? default_file_url();
                        $serviceName = $service->name ?? ($item->item_name ?? '-');
                        $serviceDescription = $service->description ?? '';
                        $quantity = $item->quantity ?? 1;
                        
                        // Get base service price from service charges (actual base price, not calculated amount)
                        $baseAmount = $item->service_amount ?? 0; // Default fallback
                        $serviceModel = null;
                        if (!empty($item->item_id)) {
                            $serviceModel = \Modules\Clinic\Models\ClinicsService::where('id', $item->item_id)->first();
                            if ($serviceModel) {
                                // Get base price from service charges (this is the actual base price)
                                $baseAmount = $serviceModel->charges ?? $baseAmount;
                            }
                        }
                        
                        // Calculate: Base Service - Discount + Inclusive Tax (on discounted amount)
                        // Get discount - check billing item first, then service
                        $discountValue = $item->discount_value ?? 0;
                        $discountType = $item->discount_type ?? 'percentage';
                        $discountStatus = $item->discount_status ?? null;
                        
                        // If billing item doesn't have discount, check the service for discount
                        if (empty($discountValue) || $discountValue == 0) {
                            if ($serviceModel && !empty($serviceModel->discount_value) && $serviceModel->discount_value > 0) {
                                $discountValue = $serviceModel->discount_value;
                                $discountType = $serviceModel->discount_type ?? 'percentage';
                                $discountStatus = 1;
                            }
                        }
                        
                        $hasDiscount = $discountValue > 0 && ($discountStatus === null || $discountStatus == 1);
                        $discountAmount = 0;
                        $discountLabel = '';
                        
                        if ($hasDiscount) {
                            if ($discountType === 'percentage') {
                                // Discount is applied to base price only
                                $discountAmount = ($baseAmount * $discountValue) / 100;
                                $discountLabel = $discountValue . '%';
                            } else {
                                $discountAmount = $discountValue;
                                $discountLabel = Currency::format($discountValue);
                            }
                        }
                        
                        // Calculate discounted amount: Base - Discount
                        $discountedAmount = $baseAmount - $discountAmount;
                        
                        // Calculate inclusive tax on discounted amount (not on base price)
                        $inclusiveTax = 0;
                        if ($serviceModel && $serviceModel->is_inclusive_tax == 1 && !empty($serviceModel->inclusive_tax)) {
                            $inclusiveTaxJson = json_decode($serviceModel->inclusive_tax, true);
                            if (is_array($inclusiveTaxJson)) {
                                foreach ($inclusiveTaxJson as $tax) {
                                    if (isset($tax['status']) && $tax['status'] == 1) {
                                        if ($tax['type'] == 'percent') {
                                            // Calculate inclusive tax on discounted amount (per unit)
                                            $inclusiveTax += ($discountedAmount * $tax['value']) / 100;
                                        } elseif ($tax['type'] == 'fixed') {
                                            $inclusiveTax += $tax['value'];
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Only use stored value as fallback if we couldn't recalculate (service not found or no inclusive tax enabled)
                        if ($inclusiveTax == 0 && (empty($item->item_id) || !isset($serviceModel) || ($serviceModel && $serviceModel->is_inclusive_tax != 1))) {
                            $inclusiveTax = $item->inclusive_tax_amount ?? 0;
                        }
                        
                        // Calculate original inclusive tax on base price (for display of original price)
                        $originalInclusiveTax = 0;
                        if ($serviceModel && $serviceModel->is_inclusive_tax == 1 && !empty($serviceModel->inclusive_tax)) {
                            $inclusiveTaxJson = json_decode($serviceModel->inclusive_tax, true);
                            if (is_array($inclusiveTaxJson)) {
                                foreach ($inclusiveTaxJson as $tax) {
                                    if (isset($tax['status']) && $tax['status'] == 1) {
                                        if ($tax['type'] == 'percent') {
                                            // Calculate inclusive tax on base price (for original price display)
                                            $originalInclusiveTax += ($baseAmount * $tax['value']) / 100;
                                        } elseif ($tax['type'] == 'fixed') {
                                            $originalInclusiveTax += $tax['value'];
                                        }
                                    }
                                }
                            }
                        } else {
                            $originalInclusiveTax = $item->inclusive_tax_amount ?? 0;
                        }
                        
                        // Original price: Base + Inclusive Tax (on base, before discount)
                        $originalPrice = $baseAmount + $originalInclusiveTax;
                        
                        // Final price: (Base - Discount) + Inclusive Tax (calculated on discounted amount)
                        $finalPrice = $discountedAmount + $inclusiveTax;
                                        @endphp

                                        <div
                                            class="d-flex align-items-md-center bg-body p-4 rounded flex-md-row flex-column gap-3 payment-box-info mb-3">
                                            <div class="detail-box">
                                                <img src="{{ $serviceImage }}" alt="service"
                                                    class="avatar avatar-80 rounded-pill">
                                            </div>

                                            <div class="row flex-grow-1">
                                                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                                                    <div>
                                                        @if ($service->id ?? false)
                                                            <a href="{{ route('service-details', ['id' => $service->id]) }}">
                                                                <b>{{ $serviceName }}</b>
                                                            </a>
                                                        @else
                                                            <b>{{ $serviceName }}</b>
                                                        @endif
                                                        @if ($serviceDescription)
                                                            <div class="mt-2">
                                                                {{ $serviceDescription }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="text-md-end">
                                                        <span class="badge bg-light text-dark">{{ __('product.quantity') }}:
                                                            {{ $quantity }}</span>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    @if ($hasDiscount)
                                                        <div class="d-flex align-items-center gap-2">
                                                            <h6 class="mb-0">
                                                                {{ Currency::format($finalPrice) }}
                                                                <span class="text-success ms-2">
                                                                    ({{ $discountLabel }}
                                                                    {{ __('frontend.off') }})
                                                                </span>
                                                            </h6>
                                                            @if ($originalPrice > 0)
                                                                <del class="text-muted">{{ Currency::format($originalPrice) }}</del>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <h6 class="mb-0">
                                                            {{ Currency::format($finalPrice) }}
                                                        </h6>
                                                    @endif

                                                    @if ($inclusiveTax > 0)
                                                        <small class="text-secondary d-block"><i>{{ __('messages.lbl_with_inclusive_tax') }}</i></small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4 text-danger">
                                            {{ __('appointment.no_service_found') }}
                                        </div>
                                    @endforelse
                                @endif
                            </div>
                        </div>
                    </div>
                    {{-- Bed Allocation Details --}}
                        @php
                            // Use bedAllocations passed from controller, or fetch if not set
                            if (!isset($bedAllocations) || $bedAllocations === null) {
                                $bedAllocations = collect();
                                
                                // Try to get from patientEncounter relationship
                                if ($appointment->patientEncounter) {
                                    // First, try to fetch directly from BedAllocation model by encounter_id ONLY
                                    // NO fallback to patient_id - only show beds allocated to this specific encounter
                                    if ($appointment->patientEncounter->id) {
                                        $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $appointment->patientEncounter->id)
                                            ->whereNotNull('encounter_id') // Ensure encounter_id is not null
                                            ->whereNull('deleted_at')
                                            ->with(['patient', 'bedMaster.bedType', 'bedType', 'patientEncounter'])
                                            ->orderBy('assign_date', 'desc')
                                            ->get();
                                    }
                                    
                                    // If still empty, try to get from bedAllocations relationship
                                    if ($bedAllocations->isEmpty()) {
                                        $encounterBedAllocations = $appointment->patientEncounter->bedAllocations;
                                        if ($encounterBedAllocations) {
                                            // bedAllocations is a hasOne relationship, so it returns a single model
                                            if ($encounterBedAllocations instanceof \Illuminate\Database\Eloquent\Collection) {
                                                $bedAllocations = $encounterBedAllocations;
                                            } else {
                                                // It's a single model, wrap it in a collection
                                                $bedAllocations = collect([$encounterBedAllocations]);
                                            }
                                        }
                                    }
                                    
                                    // NO fallback to patient_id - only show beds allocated to this specific encounter
                                }
                            }
                            
                            // Ensure it's always a collection
                            if (!($bedAllocations instanceof \Illuminate\Support\Collection)) {
                                $bedAllocations = collect($bedAllocations);
                            }
                        @endphp
                        @if ($bedAllocations->isNotEmpty())
                            <div class="mt-5 pt-3">
                                <h6 class="font-size-18">Bed Allocation Details</h6>
                                <div class="section-bg payment-box rounded">
                                    @include(
                                        'appointment::backend.patient_encounter.component.bed_allocation_table',
                                        [
                                            'data' => $appointment,
                                            'bedAllocations' => $bedAllocations,
                                            'hideActions' => true,
                                        ]
                                    )
                                </div>
                            </div>
                        @endif

                    

                        </div>
                </div>
            </div>
        </div>
     

        {{-- Encounter modal --}}
        <div class="modal modal-xl fade" id="encounter-details-view">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content section-bg position-relative rounded">
                    <div class="modal-body modal-body-inner modal-enocunter-detail">
                        <div class="close-modal-btn" data-bs-dismiss="modal">
                            <i class="ph ph-x align-middle"></i>
                        </div>

                        @php
                            $problems = $medical_history->get('encounter_problem', collect());
                            $observations = $medical_history->get('encounter_observations', collect());
                            $notes = $medical_history->get('encounter_notes', collect());
                        @endphp

                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#problem"
                                data-bs-toggle="collapse">
                                <p class="mb-0 h6">{{ __('frontend.problem') }}
                                </p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="problem" class="collapse rounded encounter-inner-box">
                                @if ($problems->isNotEmpty())
                                    @foreach ($problems as $problem)
                                        <p class="font-size-14">{{ $loop->iteration }}. {{ $problem->title }}</p>
                                    @endforeach
                                @else
                                    <p class="font-size-12 mb-0 text-danger text-center">
                                        {{ __('frontend.no_problems_found') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#observation"
                                data-bs-toggle="collapse">
                                <p class="mb-0 h6">{{ __('frontend.observation') }}
                                </p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="observation" class="collapse  encounter-inner-box rounded">
                                @if ($observations->isNotEmpty())
                                    @foreach ($observations as $observation)
                                        <p class="font-size-14">{{ $loop->iteration }}. {{ $observation->title }}</p>
                                    @endforeach
                                @else
                                    <p class="font-size-12 mb-0 text-danger text-center">
                                        {{ __('frontend.no_observation_found') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#notes"
                                data-bs-toggle="collapse">
                                <p class="mb-0 h6">{{ __('frontend.notes') }}
                                </p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="notes" class="collapse  encounter-inner-box rounded">
                                @if ($observations->isNotEmpty())
                                    @foreach ($notes as $note)
                                        <p class="font-size-14 mb-0">{{ $loop->iteration }}. {{ $note->title }}</p>
                                    @endforeach
                                @else
                                    <p class="font-size-12 mb-0 text-danger text-center">{{ __('frontend.no_note_found') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                                href="#medical-report-{{ $appointment->id }}" data-bs-toggle="collapse">
                                <p class="mb-0 h6">Medical Report</p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="medical-report-{{ $appointment->id }}" class="collapse encounter-inner-box rounded">
                                @if ($medical_report && $medical_report->file_url)
                                    <a href="{{ asset($medical_report->file_url) }}" download class="btn btn-primary">
                                        Download Report
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                                href="#body_chart-{{ $appointment->id }}" data-bs-toggle="collapse">
                                <p class="mb-0 h6">Body chart</p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="body_chart-{{ $appointment->id }}" class="collapse  encounter-inner-box rounded">
                                @if ($bodychart->isNotEmpty())
                                    <div class="d-flex  flex-wrap gap-3">
                                        @foreach ($bodychart as $chart)
                                            @foreach ($chart->media as $media)
                                                <!-- Iterate through the media collection -->
                                                <div class="body-chart-content text-center">
                                                    <div class="image mb-2">
                                                        <img src="{{ asset($media->getUrl()) }}" alt="{{ $media->name }}"
                                                            class="img-fluid" width="100" height="100">
                                                    </div>
                                                    <a href="{{ asset($media->getUrl()) }}" download>
                                                        Download
                                                    </a>
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </div>
                                @else
                                    <p class="font-size-12 mb-0 text-danger text-center">No report found</p>
                                @endif
                            </div>
                        </div>
                        @if (checkPlugin('pharma') == 'active')
                            <div class="encounter-box mt-5">
                                <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                                    href="#medicine-{{ $appointment->id }}" data-bs-toggle="collapse">
                                    <p class="mb-0 h6">Medicine</p>
                                    <i class="ph ph-caret-down"></i>
                                </a>
                                <div id="medicine-{{ $appointment->id }}" class="collapse encounter-inner-box rounded">
                                    @if ($prescriptions->isNotEmpty())
                                        @foreach ($prescriptions->take(2) as $prescription)
                                            <div class="encounter-prescription-box">
                                                <h6 class="mb-3 fw-semibold text-primary">Medicine {{ $loop->iteration }}</h6>
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <span class="font-size-14 mb-2">name:</span>
                                                            <h6 class="font-size-14 mb-0">{{ $prescription->name }}</h6>
                                                        </div>
                                                        <div class="col-md-6 mt-md-0 mt-4">
                                                            <span class="font-size-14 mb-2">price:</span>
                                                            <h6 class="font-size-14 mb-0">{{ \Currency::format($prescription->total_amount ?? 0) }}
                                                            </h6>
                                                        </div>
                                                       
                                                    </div>
                                                </div>
          
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <span class="font-size-14 mb-2">Frequency:</span>
                                                            <h6 class="font-size-14 mb-0">{{ $prescription->frequency }}</h6>
                                                        </div>
                                                        <div class="col-md-6 mt-md-0 mt-4">
                                                            <span class="font-size-14 mb-2">Days:</span>
                                                            <h6 class="font-size-14 mb-0">{{ $prescription->duration }} Days
                                                            </h6>
                                                        </div>
                                                       
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="row">
                                                        @if ($prescription->instruction)
                                                            <div class="col-md-6 mt-md-0 mt-4">
                                                                <span class="font-size-14 mb-2">prescription:</span>
                                                                <h6 class="font-size-14 mb-0">{{ $prescription->instruction }}
                                                                </h6>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="font-size-12 mb-0 text-danger text-center">No prescription found</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#prescription"
                                data-bs-toggle="collapse">
                                <p class="mb-0 h6">{{ __('frontend.prescription') }}</p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="prescription" class="collapse encounter-inner-box rounded">
                                @if ($prescriptions->isNotEmpty())
                                    @foreach ($prescriptions as $prescription)
                                        @if (checkPlugin('pharma') !== 'active')
                                            <div class="encounter-prescription-box">
                                                <h6>{{ $prescription->name }}</h6>
                                                @if ($prescription->instruction)
                                                    <p class="font-size-14 mb-0">{{ $prescription->instruction }}</p>
                                                @endif

                                                <div class="mt-3 pt-3 border-top mb-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <span class="font-size-14 mb-2">{{ __('frontend.frequency') }}
                                                            </span>
                                                            <h6 class="font-size-14">{{ $prescription->frequency }}</h6>
                                                        </div>
                                                        <div class="col-md-6 mt-md-0 mt-4">
                                                            <span class="font-size-14 mb-2">{{ __('frontend.days') }}:
                                                            </span>
                                                            <h6 class="font-size-14">{{ $prescription->duration }}
                                                                {{ __('frontend.days') }}
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                    @if (checkPlugin('pharma') == 'active')
                                        @if ($prescriptionBill != null)
                                            <div class="encounter-prescription-box">
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <span class="font-size-14 mb-2">Exclusive Tax:</span><br>
                                                            <span
                                                                class="text-primary">{{ Currency::format($prescriptionBill->exclusive_tax_amount ?? 0) }}</span>
                                                        </div>
                                                        <div class="col-md-6 mt-md-0 mt-4">
                                                            <span class="font-size-14 mb-2">Total Amount:</span><br>
                                                            <span
                                                                class="text-primary">{{ Currency::format($prescriptionBill->total_amount ?? 0) }}</span>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="encounter-prescription-box">
                                            <div class="mt-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <span class="font-size-14 mb-2">Prescription Status:</span><br>
                                                        @if ($prescription->encounter->prescription_status == 1)
                                                            <span class="badge bg-success">Completed</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        @endif
                                                    </div>
                                                    <div class="col-md-6 mt-md-0 mt-4">
                                                        <span class="font-size-14 mb-2">Payment Status:</span><br>
                                                        @if ($prescription->encounter->prescription_payment_status == 1)
                                                            <span class="badge bg-success">Paid</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Unpaid</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <p class="font-size-12 mb-0 text-danger text-center">
                                        {{ __('frontend.no_prescription_found') }}
                                    </p>
                                @endif
                            </div>

                            <div class="encounter-box mt-5">
                                <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#soap"
                                    data-bs-toggle="collapse">
                                    <p class="mb-0 h6">{{ __('frontend.soap') }}
                                    </p>
                                    <i class="ph ph-caret-down"></i>
                                </a>
                                <div id="soap" class="collapse encounter-inner-box rounded">
                                    @if ($soap)

                                        <div class="border-top mb-3">
                                            <div class="row">
                                                <div class="col-md-6 ">

                                                    <h6 class="font-size-14">{{ __('frontend.subjective') }}</h6>

                                                    <span class="font-size-14 mb-2">{{ $soap->subjective }}</span>

                                                </div>
                                                <div class="col-md-6 ">
                                                    <h6 class="font-size-14 mb-2">{{ __('frontend.objective') }}
                                                    </h6>
                                                    <span class="font-size-14">{{ $soap->objective }}</span>

                                                </div>

                                                <div class="col-md-6 ">
                                                    <h6 class="font-size-14">{{ __('frontend.assessment') }}
                                                    </h6>
                                                    <span class="font-size-14 mb-2">
                                                        {{ $soap->assessment }}
                                                    </span>

                                                </div>
                                                <div class="col-md-6 ">
                                                    <h6 class="font-size-14">{{ __('frontend.plan') }}
                                                    </h6>
                                                    <span class="font-size-14 mb-2">
                                                        {{ $soap->plan }}
                                                    </span>

                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <p class="font-size-12 mb-0 text-danger text-center">
                                            {{ __('frontend.no_soap_found') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content section-bg rounded">
                    <div class="close-modal-btn" data-bs-dismiss="modal">
                        <i class="ph ph-x align-middle"></i>
                    </div>
                    <div class="modal-body modal-payemnt-inner">
                        <h6 class="mb-3 font-size-18" id="paymentModalLabel">{{ __('frontend.payment_method') }}</h6>
                        <div class="payment-modal-box rounded">
                            @foreach ($paymentMethods as $method)
                                <div
                                    class="form-check payment-method-items ps-0 d-flex justify-content-between align-items-center gap-3">
                                    <label class="form-check-label d-flex gap-2 align-items-center"
                                        for="method-{{ $method }}">
                                        <img src="{{ asset('dummy-images/payment_icons/' . strtolower($method) . '.svg') }}"
                                            alt="{{ $method }}" style="width: 20px; height: 20px;">
                                        <span class="h6 fw-semibold m-0">{{ $method }}</span>
                                    </label>
                                    <input class="form-check-input" type="radio" name="payment_method"
                                        value="{{ $method }}" id="method-{{ $method }}"
                                        @if ($method === 'cash') checked @endif>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-end mt-5">
                            <button class="btn btn-secondary" id="pay_now"
                                data-bs-dismiss="modal">{{ __('frontend.submit') }}</button>
                    </div>
                </div>
            </div>
        </div>
        </div>

        {{-- Review Service Modal --}}
        @include('frontend::components.section.review')
@endsection


@push('after-scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .tax-total-line {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
        }

        .tax-total-line:hover {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 0 -12px;
        }

        #taxArrow {
            font-size: 14px;
        }

        .tax-breakdown-container {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tax-breakdown-container .bg-light {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
        }

        .tax-breakdown-container .d-flex {
            font-size: 14px;
        }

        .tax-breakdown-container .d-flex:last-child {
            margin-bottom: 0 !important;
        }
    </style>
    <script>
        $(document).ready(function() {
            const paymentModalElement = document.getElementById('paymentModal');
            if (paymentModalElement && paymentModalElement.parentNode !== document.body) {
                document.body.appendChild(paymentModalElement);
            }
            $('.delete-rating-btn').on('click', function() {
                const reviewId = $(this).data('review-id');

                Swal.fire({
                    title: 'Are you sure you want to remove your review?',
                    text: 'Once deleted, your review cannot be recovered',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--bs-secondary)',
                    cancelButtonColor: 'var(--bs-gray-500)',
                    confirmButtonText: 'Delete Review',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('delete-rating') }}',
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                id: reviewId
                            },
                            success: function(data) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: data.message
                                });
                                location.reload();
                            },
                            error: function(xhr, status, error) {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'There was an error deleting the review. Please try again.'
                                });
                            }
                        });
                    }
                });
            });

            document.addEventListener('DOMContentLoaded', function() {
                @if (session('paymentDetails'))
                    const paymentDetails = @json(session('paymentDetails'));
                    Swal.fire({
                        title: 'Payment Success',
                        html: `
                <p>Your appointment with <strong>Dr. ${paymentDetails.doctorName}</strong> at
                <strong>${paymentDetails.clinicName}</strong> has been confirmed on
                <strong>${new Date(paymentDetails.appointmentDate).toLocaleDateString()}</strong> at
                <strong>${new Date('1970-01-01T' + paymentDetails.appointmentTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</strong>.</p>
                <div>
                    <p><strong>Booking ID:</strong> #${paymentDetails.bookingId}</p>
                    <p><strong>Payment via:</strong>${paymentDetails.paymentVia}</p>
                    <p><strong>Total Payment:</strong>${paymentDetails.currency} ${paymentDetails.totalAmount}</p>
                </div>
            `,
                        icon: 'success',
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#FF6F61',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ route('appointment-list') }}";
                        }
                    });
                @endif
            });

            const payNowButton = document.querySelector('#pay_now');

            if (!payNowButton) {
                return;
            }

            payNowButton.addEventListener('click', async function() {
                const appointmentId = "{{ $appointment->id }}";
                const selectedPaymentInput = document.querySelector('input[name="payment_method"]:checked');

                if (!selectedPaymentInput) {
                    console.warn('No payment method selected.');
                    return;
                }

                const selectedPaymentMethod = selectedPaymentInput.value;
                const baseUrl = "{{ url('/') }}";
                const totalAmount = parseFloat("{{ $appointment->total_amount }}");
                const advancePaymentAmount = parseFloat("{{ $appointment->advance_payment_amount }}");
                const advancePaymentStatus = parseInt("{{ $appointment->advance_payment_status }}");

                // Check wallet balance if wallet is selected payment method
                if (selectedPaymentMethod === 'Wallet') {
                    try {
                        const response = await fetch("{{ route('check.wallet.balance') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                totalAmount: advancePaymentStatus === 1 ?
                                    advancePaymentAmount : totalAmount
                            })
                        });

                        const data = await response.json();

                        if (!data.success || (advancePaymentStatus === 1 ? data.balance <
                                advancePaymentAmount :
                                data.balance < totalAmount)) {
                            successSnackbar('Insufficient balance. Please add funds in wallet')
                            return;
                        }
                    } catch (error) {
                        console.error('Error checking wallet balance:', error);
                        return;
                    }
                }

                fetch(`${baseUrl}/pay-now`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            appointment_id: appointmentId,
                            transaction_type: selectedPaymentMethod,
                            totalAmount: advancePaymentStatus === 1 ? advancePaymentAmount :
                                totalAmount
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.status) {
                            if (selectedPaymentMethod === 'Wallet') {
                                const paymentDetails = {
                                    doctorName: "{{ optional($appointment->doctor)->first_name }} {{ optional($appointment->doctor)->last_name }}",
                                    clinicName: "{{ optional($appointment->cliniccenter)->name }}",
                                    appointmentDate: "{{ $appointment->appointment_date }}",
                                    appointmentTime: "{{ $appointment->appointment_time }}",
                                    bookingId: appointmentId,
                                    paymentVia: selectedPaymentMethod,
                                    currency: "{{ $appointment->currency_symbol }}",
                                    totalAmount: advancePaymentStatus === 1 ?
                                        advancePaymentAmount.toFixed(
                                            2) : totalAmount.toFixed(2)
                                };

                                Swal.fire({
                                    title: 'Payment Success',
                                    html: `
                        <p>Your appointment with <strong>Dr. ${paymentDetails.doctorName}</strong> at
                        <strong>${paymentDetails.clinicName}</strong> has been confirmed on
                        <strong>${new Date(paymentDetails.appointmentDate).toLocaleDateString()}</strong> at
                        <strong>${new Date('1970-01-01T' + paymentDetails.appointmentTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</strong>.</p>
                        <div>
                            <p><strong>Booking ID:</strong> #${paymentDetails.bookingId}</p>
                            <p><strong>Payment via:</strong>${paymentDetails.paymentVia}</p>
                            <p><strong>Total Payment:</strong>${paymentDetails.currency} ${paymentDetails.totalAmount}</p>
                        </div>
                    `,
                                    icon: 'success',
                                    confirmButtonText: 'Close',
                                    confirmButtonColor: '#FF6F61',
                                    allowOutsideClick: false
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href =
                                        `${baseUrl}/appointment-list`;
                                    }
                                });
                            } else {
                                window.location.href = `${baseUrl}/appointment-list`;
                            }
                        } else {
                            alert(data.message || 'Payment failed.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred during payment processing.');
                    });
            });
        });

        // Toggle tax breakdown function
        function toggleTaxBreakdown() {
            const breakdown = document.getElementById('taxBreakdown');
            const arrow = document.getElementById('taxArrow');

            if (breakdown.style.display === 'none' || breakdown.style.display === '') {
                // Show breakdown
                breakdown.style.display = 'block';
                arrow.style.transform = 'rotate(0deg)'; // Keep arrow pointing up when expanded
            } else {
                // Hide breakdown
                breakdown.style.display = 'none';
                arrow.style.transform = 'rotate(180deg)'; // Rotate arrow down when collapsed
            }
        }
    </script>
@endpush
