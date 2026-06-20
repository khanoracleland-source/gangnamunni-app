@if (
    $appointment->status == 'cancelled' &&
        optional($appointment->appointmenttransaction)->payment_status != 0 &&
        optional($appointment->appointmenttransaction)->transaction_type != 'cash')
    @php
        $refundAmount = $appointment->getRefundAmount();
    @endphp

    <div class="d-flex justify-content-between align-items-center px-4 py-2 rounded"
        style="background-color: {{ $refundAmount >= 0 ? '#e6f4ea' : '#fdecea' }};">

        <span class="fw-semibold {{ $refundAmount >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $refundAmount >= 0 ? __('frontend.refund_completed') : __('frontend.wallet_deducted') }}
        </span>

        <span class="fw-semibold heading-color">
            {{ \Currency::format(abs($refundAmount)) }}
        </span>
    </div>
@endif
<li class="appointments-card section-bg rounded p-5">
    <div class="d-flex justify-content-between align-items-center gap-5 flex-wrap">
        <div class="appointments-badge d-flex column-gap-5 row-gap-2 flex-wrap rounded-pill bg-primary-subtle">
            <span class="appointments-detail">{{ DateFormate($appointment->appointment_date) }}</span>
            <span class="appointments-detail">
                {{ \Carbon\Carbon::parse($appointment->appointment_time)->format(setting('time_formate') ?? 'h:i A') }}</span>
        </div>
        <ul class="list-inline m-0 appointments-meta d-flex column-gap-4 row-gap-3 align-items-center flex-wrap">
            <li>
                <div class="d-flex flex-wrap align-items-center gap-3 ">
                    <p class="mb-0">Appointment ID:</p>
                    <p class="mb-0 font-size-14 text-primary">#{{ $appointment->id }}</p>
                </div>
            </li>
            @if (optional($appointment->clinicservice)->is_video_consultancy)
                <li>
                    <a class="appointments-videocall"
                        href="{{ $appointment->join_video_link ?? $appointment->meet_link }}">
                        <i class="ph ph-video-camera align-middle"></i></a>
                </li>
            @endif
        </ul>
    </div>
    <div class="mt-3">
        @php
            $serviceId = optional($appointment->clinicservice)->id;
        @endphp

        @if ($serviceId)
            <a href="{{ route('service-details', ['id' => $serviceId]) }}">
                <!-- Link content here -->
            </a>
        @endif
        <h5 class="mb-0">{{ optional($appointment->clinicservice)->name }}</h5></a>
    </div>
    <div class="appointments-card-content border-top border-bottom">
        <div class="row gy-3">
            <div class="col-lg-4 col-12">
                <div class="row gy-2 gx-3">
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.appointment_type') }}
                        </p>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-0">
                            {{ \Illuminate\Support\Str::title(str_replace('_', ' ', optional($appointment->clinicservice)->type)) }}
                        </h6>

                    </div>
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.clinic_name') }}
                        </p>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-0">{{ optional($appointment->cliniccenter)->name }}</h6>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="row gy-2 gx-3">
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.booking_status') }}
                        </p>
                    </div>
                    <div class="col-md-7">
                        <h6
                            class="mb-0
                            @if ($appointment->status === 'cancelled') text-danger
                            @elseif($appointment->status === 'checkout')
                                text-success
                            @else
                                text-muted @endif
                        ">
                            {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $appointment->status === 'checkout' ? 'Complete' : $appointment->status)) }}
                        </h6>
                    </div>
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.doctor_name') }}
                        </p>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-0">

                            {{ getDisplayName($appointment->doctor) }}

                            <!-- {{ optional($appointment->doctor)->first_name . ' ' . optional($appointment->doctor)->last_name }} -->
                        </h6>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="row gy-2 gx-3">
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.price') }}
                        </p>
                    </div>

                    @php
                        // Calculate final total amount matching appointment details page logic
                        $total_amount = 0;
                        $debug_data = [];
                        
                        if ($appointment->patientEncounter != null) {
                            // For appointments with encounter - calculate same as appointment details page
                            $billingRecord = optional($appointment->patientEncounter)->billingrecord;
                            
                            if ($billingRecord) {
                                // Fetch bed allocations to calculate bed charges - ONLY by encounter_id
                                // Only show beds allocated to this specific encounter, NOT by patient_id
                                $bedAllocations = collect();
                                $hasBedAllocations = false;
                                
                                if ($appointment->patientEncounter->id) {
                                    // Fetch ONLY by encounter_id - NO fallback to patient_id
                                    $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $appointment->patientEncounter->id)
                                        ->whereNotNull('encounter_id') // Ensure encounter_id is not null
                                        ->whereNull('deleted_at')
                                        ->get();
                                    
                                    if ($bedAllocations->isNotEmpty()) {
                                        $hasBedAllocations = true;
                                    }
                                }
                                
                                // Calculate bed charges from bed allocations (only if bed allocations exist)
                                $bed_charges = 0;
                                if ($hasBedAllocations && $bedAllocations->isNotEmpty()) {
                                    $bed_charges = $bedAllocations->sum('charge') ?? 0;
                                    
                                    // If still 0, try from billing record (only if bed allocations exist)
                                    if ($bed_charges == 0 && $billingRecord && isset($billingRecord->bed_charges)) {
                                        $bed_charges = $billingRecord->bed_charges ?? 0;
                                    }
                                } else {
                                    // No bed allocations exist - ensure bed charges is 0
                                    $bed_charges = 0;
                                }
                                
                                $debug_data['bed_charges_from_allocations'] = $bed_charges;
                                $debug_data['bed_charges_from_billing'] = $billingRecord->bed_charges ?? 0;
                                $debug_data['bed_allocations_count'] = $bedAllocations->count();
                                $debug_data['has_bed_allocations'] = $hasBedAllocations;
                                $debug_data['final_total_amount'] = $billingRecord->final_total_amount ?? null;
                                $debug_data['total_amount'] = $billingRecord->total_amount ?? null;
                                $debug_data['service_amount'] = $billingRecord->service_amount ?? null;
                                $debug_data['final_tax_amount'] = $billingRecord->final_tax_amount ?? null;
                                $debug_data['final_discount'] = $billingRecord->final_discount ?? null;
                                $debug_data['final_discount_value'] = $billingRecord->final_discount_value ?? null;
                                $debug_data['final_discount_type'] = $billingRecord->final_discount_type ?? null;
                                
                                // Calculate service total from billing items - BASE PRICE ONLY (matching appointment_detail.blade.php)
                                $totalServiceAmount = 0;
                                $totalServiceDiscount = 0;
                                
                                if ($billingRecord->billingItem && $billingRecord->billingItem->isNotEmpty()) {
                                    foreach ($billingRecord->billingItem as $item) {
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
                                    // Fallback: use billing record service_amount
                                    $totalServiceAmount = $billingRecord->service_amount ?? 0;
                                    $totalServiceDiscount = 0;
                                }
                                
                                // Overall discount (final_discount) - Apply only to base service amount (matching appointment_detail)
                                $overallDiscountAmount = 0;
                                if (($billingRecord->final_discount ?? null) == 1) {
                                    $discount_type = $billingRecord->final_discount_type ?? 'percentage';
                                    $discount_value = $billingRecord->final_discount_value ?? 0;
                                    if ($discount_type === 'percentage') {
                                        $overallDiscountAmount = ($totalServiceAmount * $discount_value) / 100;
                                    } else {
                                        $overallDiscountAmount = $discount_value;
                                    }
                                }
                                
                                // Tax calculation logic (matching appointment_detail):
                                // - If there's NO overall discount, calculate tax on BASE service amount (ignoring service-level discounts)
                                // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount)
                                $amountForTaxCalculation = $totalServiceAmount;
                                if ($overallDiscountAmount > 0) {
                                    $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                                }
                                
                                // Get tax_data from billing record if available
                                $taxData = null;
                                if ($billingRecord && isset($billingRecord->tax_data)) {
                                    $taxData = $billingRecord->tax_data;
                                }
                                
                                // Tax (Exclusive) is calculated using getBookingTaxamount function
                                $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
                                $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                                
                                // Calculate total payable amount: (Base Service Amount - Overall Discount) + Tax (WITHOUT bed charges)
                                $totalPayableAmount = $amountForTaxCalculation + $taxAmount;
                                
                                // For debug and display
                                $service_total = $totalServiceAmount;
                                $encounter_discount = $overallDiscountAmount;
                                $amount_after_discount = $amountForTaxCalculation;
                                $base_total = $totalPayableAmount; // Total Payable Amount (without bed charges)
                                
                                $debug_data['service_total'] = $service_total;
                                $debug_data['encounter_discount'] = $encounter_discount;
                                $debug_data['amount_after_discount'] = $amount_after_discount;
                                $debug_data['tax_amount'] = $taxAmount;
                                $debug_data['base_total'] = $base_total;
                                
                                // Final Total: Total Payable Amount + Bed Charges (only if bed allocations exist)
                                $total_amount = $base_total;
                                if ($hasBedAllocations && $bed_charges > 0) {
                                    $total_amount = $base_total + $bed_charges;
                                }
                                $debug_data['calculated_total'] = $total_amount;
                                $debug_data['appointment_id'] = $appointment->id;
                                
                                // Log to Laravel log
                                \Log::info('Appointment Card Price Calculation', $debug_data);
                            } else {
                                $debug_data['error'] = 'No billing record found';
                                $debug_data['appointment_id'] = $appointment->id;
                                \Log::warning('Appointment Card - No Billing Record', $debug_data);
                            }
                        } else {
                            // For direct appointment without encounter
                            $total_amount = $appointment->total_amount;
                            $debug_data['appointment_id'] = $appointment->id;
                            $debug_data['using'] = 'appointment->total_amount';
                            $debug_data['calculated_total'] = $total_amount;
                            \Log::info('Appointment Card Price Calculation (No Encounter)', $debug_data);
                        }
                    @endphp
                    
                    <script>
                        console.log('Appointment Card Price Calculation Debug', @json($debug_data));
                    </script>

                    <div class="col-md-7">
                        <h6 class="mb-0">{{ Currency::format($total_amount) ?? '--' }}</h6>
                    </div>
                    <div class="col-md-5">
                        <p class="mb-0">{{ __('frontend.payment_status') }}
                        </p>
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-0 text-danger">
                            @php
                                $transaction = optional($appointment->appointmenttransaction);
                                $isCancelled = $appointment->status == 'cancelled';
                                $isFullyPaid = $transaction->payment_status == 1;
                                $isAdvancePaid =
                                    $appointment->advance_paid_amount > 0 && $transaction->advance_payment_status == 1;
                            @endphp

                            {{-- Cancelled Cases --}}
                            @if ($isCancelled)
                                @if ($isFullyPaid)
                                    <span class="text-success">{{ __('frontend.payment_refunded') }}</span>
                                @elseif($isAdvancePaid)
                                    <span class="text-warning">{{ __('frontend.advance_refunded') }}</span>
                                @else
                                    <span class="text-danger">{{ __('frontend.cancelled') }}</span>
                                @endif

                                {{-- Active or Completed Cases --}}
                            @else
                                @if ($isFullyPaid)
                                    <span class="text-success">{{ __('frontend.paid') }}</span>
                                @elseif($isAdvancePaid)
                                    <span class="text-info">{{ __('frontend.advance_paid') }}</span>
                                @else
                                    <span class="text-danger">{{ __('frontend.pending') }}</span>
                                @endif
                            @endif
                        </h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">
        @if ($appointment->otherPatient)
            <div class="d-flex align-items-center gap-2">
                <span class="font-size-14">{{ __('frontend.booked_for') }}</span>
                <div class="d-flex align-items-center">
                    <img src="{{ optional($appointment->otherPatient)->profile_image ?: asset('images/default-avatar.png') }}"
                        class="rounded-circle me-2" alt="{{ optional($appointment->otherPatient)->first_name }}"
                        style="width: 32px; height: 32px; object-fit: cover;">
                    <span class="fw-medium">{{ optional($appointment->otherPatient)->first_name }}
                        {{ optional($appointment->otherPatient)->last_name }}</span>
                </div>
            </div>
        @endif
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-5 mt-5">
        <div class="d-flex align-items-center flex-wrap gap-4">
            @if ($appointment->status == 'pending' && optional($appointment->appointmenttransaction)->transaction_type == 'cash')
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#cancel-appointment"
                    data-appointment-id="{{ $appointment->id }}" data-charge="0">{{ __('frontend.cancel') }}
                </button>
            @elseif($appointment->status == 'pending' || $appointment->status == 'confirmed')
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#cancel-appointment"
                    data-appointment-id="{{ $appointment->id }}"
                    data-charge="{{ $appointment->getCancellationCharges() }}">{{ __('frontend.cancel') }}
                </button>
            @endif
            @if ($appointment->status == 'checkout' ?? $appointment->status == 'check_in')
                <button data-bs-toggle="modal" data-bs-target="#encounter-details-view-{{ $appointment->id }}"
                    class="btn btn-secondary"><i
                        class="ph ph-gauge align-middle me-2"></i>{{ __('frontend.encounter') }}
                </button>
            @endif
        </div>
        <div class="d-flex align-items-center flex-wrap gap-4">
            <a href="{{ route('appointment-details', ['id' => $appointment->id]) }}"
                class="btn-link text-secondary fw-semibold font-size-14">{{ __('frontend.view_detail') }}
            </a>
            @php
                $serviceRating = optional($appointment->clinicservice)->serviceRating;
            @endphp

            @if (!is_null($serviceRating) && $serviceRating->isEmpty() && $appointment->status == 'checkout')
                <button class="btn btn-light" data-bs-toggle="modal"
                    data-service-id="{{ optional($appointment->clinicservice)->id }}"
                    data-doctor-id="{{ optional($appointment->doctor)->id }}" data-bs-target="#review-service">
                    <i class="ph-fill ph-star text-warning me-2"></i>{{ __('frontend.rate_us') }}
                </button>
            @endif
        </div>
    </div>
</li>


{{-- Encounter modal --}}
<div class="modal  modal-xl fade" id="encounter-details-view-{{ $appointment->id }}">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content section-bg position-relative rounded">
            <div class="close-modal-btn" data-bs-dismiss="modal">
                <i class="ph ph-x align-middle"></i>
            </div>
            <div class="modal-body modal-body-inner modal-enocunter-detail">
                <div class="encounter-info">
                    <h6>{{ __('frontend.basic_information') }}
                    </h6>
                    <div class="encounter-basic-info rounded">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <p class="mb-0 font-size-14">{{ __('frontend.appointment_id') }}
                                    </p>
                                    <span class="text-primary font-size-14 fw-bold">#{{ $appointment->id }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <p class="mb-0 font-size-14">{{ __('frontend.doctor_name') }}
                                    </p>
                                    <span
                                        class="encounter-desc font-size-14 fw-bold">{{ getDisplayName($appointment->doctor) }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <p class="mb-0 font-size-14">{{ __('frontend.clinic_name') }}
                                    </p>
                                    <span
                                        class="encounter-desc font-size-14 fw-bold">{{ optional($appointment->cliniccenter)->name ?? '-' }}</span>
                                </div>
                            </div>
                            <span
                                class="bg-success-subtle badge rounded-pill text-uppercase text-uppercase font-size-10">{{ optional($appointment->patientEncounter)->status ? 'Active' : 'Closed' }}</span>
                        </div>
                        <div class="encounter-descrption border-top">
                            <div class="d-flex gap-2 align-items-center">
                                <span class="font-size-14 flex-shrink-0">{{ __('frontend.description') }}
                                </span>
                                <p class="font-size-14 fw-semibold detail-desc mb-0">
                                    {{ optional($appointment->patientEncounter)->descrtiption ?? 'No records found' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @php
                        $problems = $medical_history->get('encounter_problem', collect());
                        $observations = $medical_history->get('encounter_observations', collect());
                        $notes = $medical_history->get('encounter_notes', collect());
                    @endphp

                    <div class="encounter-box mt-5">
                        <a class="d-flex gap-4 mb-2 encounter-list  justify-content-between"
                            href="#problem-{{ $appointment->id }}" data-bs-toggle="collapse">
                            <p class="mb-0 h6">Problem</p>
                            <i class="ph ph-caret-down"></i>
                        </a>
                        <div id="problem-{{ $appointment->id }}" class="collapse rounded encounter-inner-box">
                            @if ($problems->isNotEmpty())
                                @foreach ($problems as $problem)
                                    <p class="font-size-14">{{ $loop->iteration }}. {{ $problem->title }}</p>
                                @endforeach
                            @else
                                <p class="font-size-12 mb-0 text-danger text-center">No problems found</p>
                            @endif
                        </div>
                    </div>
                    <div class="encounter-box mt-5">
                        <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                            href="#observation-{{ $appointment->id }}" data-bs-toggle="collapse">
                            <p class="mb-0 h6">Observation</p>
                            <i class="ph ph-caret-down"></i>
                        </a>
                        <div id="observation-{{ $appointment->id }}" class="collapse  encounter-inner-box rounded">
                            @if ($observations->isNotEmpty())
                                @foreach ($observations as $observation)
                                    <p class="font-size-14">{{ $loop->iteration }}. {{ $observation->title }}</p>
                                @endforeach
                            @else
                                <p class="font-size-12 mb-0 text-danger text-center">No observation found</p>
                            @endif
                        </div>
                    </div>
                    <div class="encounter-box mt-5">
                        <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                            href="#notes-{{ $appointment->id }}" data-bs-toggle="collapse">
                            <p class="mb-0 h6">Notes</p>
                            <i class="ph ph-caret-down"></i>
                        </a>
                        <div id="notes-{{ $appointment->id }}" class="collapse  encounter-inner-box rounded">
                            @if ($observations->isNotEmpty())
                                @foreach ($notes as $note)
                                    <p class="font-size-14 mb-0">{{ $loop->iteration }}. {{ $note->title }}</p>
                                @endforeach
                            @else
                                <p class="font-size-12 mb-0 text-danger text-center">No note found</p>
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
                            @if ($appointment->media->isNotEmpty())
                                @foreach ($appointment->media as $media)
                                    <a href="{{ asset($media->getUrl()) }}" download class="btn btn-primary">
                                        Download Report
                                    </a>
                                @endforeach
                            @elseif (!$medical_report || !$medical_report->file_url)
                                <p class="font-size-12 mb-0 text-danger text-center">No medical report found</p>
                            @endif

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
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach ($bodychart as $chart)
                                        @foreach ($chart->media as $media)
                                            <!-- Iterate through the media collection -->
                                            <div class="body-chart-content text-center">
                                                <div class="image mb-2">
                                                    <img src="{{ asset($media->getUrl()) }}"
                                                        alt="{{ $media->name }}" class="img-fluid" width="100"
                                                        height="100">
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
                            <a class="d-flex align-items-center gap-2 text-decoration-none medicine-list justify-content-between mb-2 encounter-list"
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
                        <a class="d-flex justify-content-between gap-3 mb-2 encounter-list"
                            href="#prescription-{{ $appointment->id }}" data-bs-toggle="collapse">
                            <p class="mb-0 h6">Prescription</p>
                            <i class="ph ph-caret-down"></i>
                        </a>
                        <div id="prescription-{{ $appointment->id }}" class="collapse  encounter-inner-box rounded">
                            @if ($prescriptions->isNotEmpty())
                                @foreach ($prescriptions as $prescription)
                                    @if (checkPlugin('pharma') !== 'active')
                                        <div class="encounter-prescription-box">
                                            <h6>{{ $prescription->name }}</h6>
                                            @if ($prescription->instruction)
                                                <p class="font-size-14 mb-0">{{ $prescription->instruction }}</p>
                                            @endif

                                            <div class="mt-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <span class="font-size-14 mb-2">Frequency:</span>
                                                        <h6 class="font-size-14 mb-0">{{ $prescription->frequency }}
                                                        </h6>
                                                    </div>
                                                    <div class="col-md-6 mt-md-0 mt-4">
                                                        <span class="font-size-14 mb-2">Days:</span>
                                                        <h6 class="font-size-14 mb-0">{{ $prescription->duration }}
                                                            Days</h6>
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
                                <p class="font-size-12 mb-0 text-danger text-center">No prescription found</p>
                            @endif
                        </div>


                        <div class="encounter-box mt-5">
                            <a class="d-flex justify-content-between gap-3 mb-2 encounter-list" href="#prescription"
                                data-bs-toggle="collapse">
                                <p class="mb-0 h6">{{ __('frontend.soap') }}
                                </p>
                                <i class="ph ph-caret-down"></i>
                            </a>
                            <div id="prescription" class="collapse  encounter-inner-box rounded">
                                @if ($soap)
                                    <div class="border-top mb-3">
                                        <div class="row">
                                            <div class="col-md-6 ">

                                                <h6 class="font-size-14">{{ __('frontend.subjective') }} </h6>

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
</div>
