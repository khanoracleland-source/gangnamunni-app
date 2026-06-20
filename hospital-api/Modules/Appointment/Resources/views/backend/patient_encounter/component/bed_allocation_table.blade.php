<div class="table-responsive rounded mb-0">
    <table class="table table-lg m-0" id="bed_allocation_table">
        <thead>
            <tr class="text-white">
                <th scope="col">Patient Name</th>
                <th scope="col">Bed Type</th>
                <th scope="col">Room/Bed</th>
                <th scope="col">Assign Date</th>
                <th scope="col">Discharge Date</th>
                <th scope="col">Per Day Charge</th>
                <th scope="col">Total Charge</th>
                <th scope="col">Payment Status</th>
                @if (!isset($hideActions) || !$hideActions)
                    <th scope="col">Action</th>
                @endif
            </tr>
        </thead>
        <tbody>

            @php
                // Debug: Log what we're receiving
\Log::info('Bed Allocation Table Component', [
    'bedAllocations_type' => gettype($bedAllocations),
    'bedAllocations_count' => is_countable($bedAllocations) ? count($bedAllocations) : 'N/A',
    'bedAllocations_is_collection' => $bedAllocations instanceof \Illuminate\Support\Collection,
    'bedAllocations_is_array' => is_array($bedAllocations),
                ]);
            @endphp

            @if (isset($bedAllocations['id']))
                @php
                    $bedAllocations = [$bedAllocations];

                    // Normalize nested objects if needed
                    $bedAllocations[0]['bedType'] = $bedAllocations[0]['bed_type'] ?? null;
                    $bedAllocations[0]['bedMaster'] = $bedAllocations[0]['bed_master'] ?? null;
                @endphp
            @endif

            @if (isset($bedAllocations) && is_countable($bedAllocations) && count($bedAllocations) > 0)
                @foreach ($bedAllocations as $index => $allocation)
                    @php
                        // Handle both array and object access
                        $assignDate = is_array($allocation)
                            ? $allocation['assign_date'] ?? null
                            : $allocation->assign_date ?? null;
                        $dischargeDate = is_array($allocation)
                            ? $allocation['discharge_date'] ?? null
                            : $allocation->discharge_date ?? null;
                        $allocationId = is_array($allocation) ? $allocation['id'] ?? null : $allocation->id ?? null;

                        // Calculate days
                        if ($assignDate && $dischargeDate) {
                            $days = \Carbon\Carbon::parse($assignDate)->diffInDays(
                                \Carbon\Carbon::parse($dischargeDate),
                            );
                            $days = $days > 0 ? $days : 1;
                        } else {
                            $days = 1;
                        }

                        // Get patient name
                        $patient = is_array($allocation)
                            ? $allocation['patient'] ?? null
                            : $allocation->patient ?? null;
                        $patientName = '--';
                        if ($patient) {
                            if (is_array($patient)) {
                                $patientName =
                                    $patient['full_name'] ??
                                    ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '');
                            } else {
                                $patientName =
                                    $patient->full_name ??
                                    ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '');
                            }
                        }

                        // Get bed type
                        $bedType = is_array($allocation)
                            ? $allocation['bedType'] ?? ($allocation['bed_type'] ?? null)
                            : $allocation->bedType ?? null;
                        $bedTypeName = '--';
                        if ($bedType) {
                            if (is_array($bedType)) {
                                $bedTypeName = $bedType['type'] ?? '--';
                            } else {
                                $bedTypeName = $bedType->type ?? '--';
                            }
                        }

                        // Get bed master
                        $bedMaster = is_array($allocation)
                            ? $allocation['bedMaster'] ?? ($allocation['bed_master'] ?? null)
                            : $allocation->bedMaster ?? null;
                        $bedName = '--';
                        $bedCharges = null;
                        if ($bedMaster) {
                            if (is_array($bedMaster)) {
                                $bedName = $bedMaster['bed'] ?? '--';
                                $bedCharges = $bedMaster['charges'] ?? null;
                            } else {
                                $bedName = $bedMaster->bed ?? '--';
                                $bedCharges = $bedMaster->charges ?? null;
                            }
                        }

                        // Get charge
                        $charge = is_array($allocation) ? $allocation['charge'] ?? null : $allocation->charge ?? null;

                        // Get data status
                        $dataStatus = is_array($data) ? $data['status'] ?? 0 : $data->status ?? 0;

                        // Get payment status - check if appointment is closed
                        $paymentStatusText = '--';
                        $encounterClosed = false;
                        $paymentStatus = 0;
                        $appointmentPaymentStatus = 0;

                        // Get patient encounter
                        $patientEncounter = is_array($allocation)
                            ? $allocation['patientEncounter'] ?? null
                            : $allocation->patientEncounter ?? null;

                        // Get appointment payment status from data (appointment object or billing record)
                        $appointmentTransactionType = null;
                        $appointmentStatus = null;
                        $billingRecordPaymentStatus = 0;
                        $encounterFromBilling = null;

                        if ($data) {
                            if (is_array($data)) {
                                // Check if data is a billing record (has payment_status directly)
                                if (isset($data['payment_status'])) {
                                    $billingRecordPaymentStatus = $data['payment_status'] ?? 0;
                                    $appointmentPaymentStatus = $billingRecordPaymentStatus;
                                }
                                // Check if data has patientencounter (billing record -> encounter)
                                if (isset($data['patientencounter'])) {
                                    $encounterFromBilling = $data['patientencounter'];
                                } elseif (isset($data['patient_encounter'])) {
                                    $encounterFromBilling = $data['patient_encounter'];
                                }
                                // Check if data has appointmenttransaction
                                $appointmentTransaction = $data['appointmenttransaction'] ?? null;
                                if ($appointmentTransaction) {
                                    $appointmentPaymentStatus = is_array($appointmentTransaction)
                                        ? $appointmentTransaction['payment_status'] ?? 0
                                        : $appointmentTransaction->payment_status ?? 0;
                                    $appointmentTransactionType = is_array($appointmentTransaction)
                                        ? $appointmentTransaction['transaction_type'] ?? null
                                        : $appointmentTransaction->transaction_type ?? null;
                                }
                                // Also check if data is appointment with transaction
                                if (isset($data['appointmenttransaction'])) {
                                    $appointmentPaymentStatus = is_array($data['appointmenttransaction'])
                                        ? $data['appointmenttransaction']['payment_status'] ?? 0
                                        : $data['appointmenttransaction']->payment_status ?? 0;
                                    $appointmentTransactionType = is_array($data['appointmenttransaction'])
                                        ? $data['appointmenttransaction']['transaction_type'] ?? null
                                        : $data['appointmenttransaction']->transaction_type ?? null;
                                }
                                // Get appointment status from encounter if available
                                if ($encounterFromBilling) {
                                    $appointmentDetail = is_array($encounterFromBilling)
                                        ? $encounterFromBilling['appointmentdetail'] ?? null
                                        : $encounterFromBilling->appointmentdetail ?? null;
                                    if ($appointmentDetail) {
                                        $appointmentStatus = is_array($appointmentDetail)
                                            ? $appointmentDetail['status'] ?? null
                                            : $appointmentDetail->status ?? null;
                                    }
                                } else {
                                    $appointmentStatus = $data['status'] ?? null;
                                }
                            } else {
                                // Data is an object - check if it's a BillingRecord
        if (method_exists($data, 'payment_status')) {
            $billingRecordPaymentStatus = $data->payment_status ?? 0;
            $appointmentPaymentStatus = $billingRecordPaymentStatus;
        }
        // Check if data has patientencounter relationship
        if (method_exists($data, 'patientencounter') && $data->patientencounter) {
            $encounterFromBilling = $data->patientencounter;
        } elseif (method_exists($data, 'patientEncounter') && $data->patientEncounter) {
            $encounterFromBilling = $data->patientEncounter;
        }
        // Data is an object (Appointment model)
        if (method_exists($data, 'appointmenttransaction') && $data->appointmenttransaction) {
            $appointmentPaymentStatus = $data->appointmenttransaction->payment_status ?? 0;
            $appointmentTransactionType =
                $data->appointmenttransaction->transaction_type ?? null;
        }
        // Get appointment status
        if (
            $encounterFromBilling &&
            method_exists($encounterFromBilling, 'appointmentdetail') &&
            $encounterFromBilling->appointmentdetail
        ) {
            $appointmentStatus = $encounterFromBilling->appointmentdetail->status ?? null;
        } elseif (method_exists($data, 'status')) {
            $appointmentStatus = $data->status;
        }
    }
}

// For cash payments, if appointment status is checkout/complete, consider it paid
if (
    $appointmentTransactionType == 'cash' &&
    in_array($appointmentStatus, ['checkout', 'complete'])
) {
    $appointmentPaymentStatus = 1;
}

// If billing record payment status is set, use it
if ($billingRecordPaymentStatus == 1) {
    $appointmentPaymentStatus = 1;
}

// Use encounter from billing if patientEncounter is not available
if (!$patientEncounter && $encounterFromBilling) {
    $patientEncounter = $encounterFromBilling;
}

if ($patientEncounter) {
    $encounterStatus = is_array($patientEncounter)
        ? $patientEncounter['status'] ?? 1
        : $patientEncounter->status ?? 1;
    // Encounter is closed if status is 0, or check appointment status for completed/checkout
    $encounterClosed = $encounterStatus == 0;

    // Also check if appointment is completed/checkout
    if (!$encounterClosed && $appointmentStatus) {
        $encounterClosed = in_array(strtolower($appointmentStatus), [
            'completed',
            'checkout',
            'closed',
        ]);
    }

    // If encounter is closed/completed, check payment status from billing record first
    if ($encounterClosed) {
        // Priority 1: Check billing record payment status (most reliable)
        $billingRecord = is_array($patientEncounter)
            ? $patientEncounter['billingrecord'] ?? null
            : $patientEncounter->billingrecord ?? null;

        if ($billingRecord) {
            $paymentStatus = is_array($billingRecord)
                ? $billingRecord['payment_status'] ?? 0
                : $billingRecord->payment_status ?? 0;
        } else {
            // Priority 2: Check from data (billing record passed directly)
            if ($data) {
                if (is_array($data) && isset($data['payment_status'])) {
                    $paymentStatus = $data['payment_status'] ?? 0;
                } elseif (is_object($data) && method_exists($data, 'payment_status')) {
                    $paymentStatus = $data->payment_status ?? 0;
                }
            }

            // Priority 3: Check appointment transaction payment status as fallback
            if (!isset($paymentStatus) || $paymentStatus == 0) {
                $appointmentDetail = is_array($patientEncounter)
                    ? $patientEncounter['appointmentdetail'] ?? null
                    : $patientEncounter->appointmentdetail ?? null;
                if ($appointmentDetail) {
                    $appointmentTransaction = is_array($appointmentDetail)
                        ? $appointmentDetail['appointmenttransaction'] ?? null
                        : $appointmentDetail->appointmenttransaction ?? null;
                    if ($appointmentTransaction) {
                        $paymentStatus = is_array($appointmentTransaction)
                            ? $appointmentTransaction['payment_status'] ?? 0
                            : $appointmentTransaction->payment_status ?? 0;
                    }
                }
            }

            // Priority 4: Use appointment payment status from data
            if (
                (!isset($paymentStatus) || $paymentStatus == 0) &&
                $appointmentPaymentStatus == 1
            ) {
                $paymentStatus = 1;
            }
        }

        // If still not set, default to 0
        if (!isset($paymentStatus)) {
            $paymentStatus = 0;
        }

        $paymentStatusText = $paymentStatus == 1 ? 'Paid' : 'Unpaid';
    } else {
        // If encounter is not closed, check both bed payment status and appointment payment status
        $bedPaymentStatus = is_array($allocation)
            ? $allocation['bed_payment_status'] ?? 0
            : $allocation->bed_payment_status ?? 0;

        // If appointment payment is paid, bed allocation should also show as paid
        if ($appointmentPaymentStatus == 1 || $bedPaymentStatus == 1) {
            $paymentStatusText = 'Paid';
        } else {
            $paymentStatusText = 'Unpaid';
        }
    }
} else {
    // No encounter, check both bed payment status and appointment payment status
    // But also check if data is a billing record with payment_status
    if ($data) {
        if (is_array($data) && isset($data['payment_status'])) {
            $billingRecordPaymentStatus = $data['payment_status'] ?? 0;
            if ($billingRecordPaymentStatus == 1) {
                $paymentStatusText = 'Paid';
            }
        } elseif (is_object($data) && method_exists($data, 'payment_status')) {
            $billingRecordPaymentStatus = $data->payment_status ?? 0;
            if ($billingRecordPaymentStatus == 1) {
                $paymentStatusText = 'Paid';
            }
        }
    }

    // If not set from billing record, check bed payment status and appointment payment status
    if (!isset($paymentStatusText) || $paymentStatusText == '--') {
        $bedPaymentStatus = is_array($allocation)
            ? $allocation['bed_payment_status'] ?? 0
            : $allocation->bed_payment_status ?? 0;

        // If appointment payment is paid, bed allocation should also show as paid
        if ($appointmentPaymentStatus == 1 || $bedPaymentStatus == 1) {
            $paymentStatusText = 'Paid';
        } else {
            $paymentStatusText = 'Unpaid';
                                }
                            }
                        }
                    @endphp

                    <tr>
                        <td>{{ $patientName }}</td>
                        <td>{{ $bedTypeName }}</td>
                        <td>{{ $bedName }}</td>
                        <td>{{ $assignDate ? \Carbon\Carbon::parse($assignDate)->format('Y-m-d') : '--' }}</td>
                        <td>{{ $dischargeDate ? \Carbon\Carbon::parse($dischargeDate)->format('Y-m-d') : '--' }}</td>
                        <td>{{ $bedCharges ? Currency::format($bedCharges) . ' * ' . $days : '--' }}</td>
                        <td>{{ $charge ? Currency::format($charge) : '--' }}</td>
                        <td>{{ $paymentStatusText }}</td>

                        @if (!isset($hideActions) || !$hideActions)
                            <td class="action">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($dataStatus == 1)
                                        <a class="btn text-success p-0 fs-5"
                                            href="{{ route('backend.bed-allocation.edit', $allocationId) }}"
                                            title="{{ __('messages.edit') }}" data-bs-toggle="tooltip">
                                            <i class="ph ph-pencil-simple-line"></i>
                                        </a>
                                    @endif
                                    <button type="button" class="btn text-info p-0 fs-5 view-bed-allocation"
                                        data-id="{{ $allocationId }}" data-bs-toggle="tooltip"
                                        title="{{ __('messages.view_details') }}">
                                        <i class="ph ph-eye"></i>
                                    </button>
                                    @if ($dataStatus == 1)
                                        <button type="button" class="btn text-danger p-0 fs-5 delete-bed-allocation"
                                            data-id="{{ $allocationId }}" data-bs-toggle="tooltip" title="Delete">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="{{ isset($hideActions) && $hideActions ? '9' : '9' }}">
                        <div class="my-1 text-danger text-center">No bed allocation found for this encounter</div>
                    </td>
                </tr>
            @endif

        </tbody>
    </table>
</div>
@push('after-scripts')
    <script>
        $(document).ready(function() {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
@endpush
