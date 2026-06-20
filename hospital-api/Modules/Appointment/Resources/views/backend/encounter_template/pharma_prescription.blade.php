<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Inter;
        }

        .column {
            float: left;
            width: 48%;
            padding: 0 10px;
        }

        .row {
            margin: 0 -5px;
        }

        .row:after {
            content: "";
            display: table;
            clear: both;
        }

        .card {
            padding: 16px;
            text-align: center;
            background-color: #F6F7F9;
        }

        table tr td {
            font-size: 14px;
        }

        table thead th {
            font-size: 14px;
        }

        .fs-14 {
            font-size: 14px;
        }
    </style>
</head>

<body>
    @php
    use Carbon\Carbon;
    $setting = App\Models\Setting::where('name', 'date_formate')->first();
    $dateformate = $setting ? $setting->val : 'Y-m-d';
    $setting = App\Models\Setting::where('name', 'time_formate')->first();
    $timeformate = $setting ? $setting->val : 'h:i A';
    $encounter_date = isset($data['encounter_date']) ? date($dateformate, strtotime($data['encounter_date'] ?? '--' )) : '--';
    @endphp
    <div>
        <div>
            <div style="float:left; width: 25%;">
                <img class="logo-mini img-fluid" src="{{ $data['logo'] }}" height="35" alt="logo">
            </div>
            <div style="float: left; width: 75%; display: inline-block;  text-align:right;">
                <p style="font-size: 14px;">Invoice Date - <span class="text-black fs-14">23/09/2024</span></p>
                <p style="font-size: 14px; margin-top: 12px; margin-bottom: 0;">Invoice ID - <span class="text-black fs-14">#456</span></p>
            </div>
        </div>
        
        <div style="clear: both;"></div>
        <div style="margin-bottom: 16px; padding-bottom: 24px; border-bottom:  1px solid #ccc;"></div>
        <div>
            <p style="color: #6C757D; margin-bottom: 16px;">Thanks, you have already completed the payment for this
                invoice</p>
        </div>
        <div style="clear: both;"></div>
        <div style="margin-bottom: 16px;">
            <div style="float: left; width: 75%; display: inline-block;">
                <h5 style="color: #1C1F34; margin: 0;">Organization information:</h5>
                <p style="color: #6C757D;  margin-top: 12px; margin-bottom: 0;">For any questions or support
                    regarding this invoice or our services, please contact us via phone or email</p>
            </div>
            <div style="float:left; width: 25%; text-align:right;">
                <span style="color: #1C1F34; margin-bottom: 12px;">{{ setting('helpline_number') }}</span>
                <p style="color: #1C1F34;  margin-top: 12px; margin-bottom: 0;">{{ setting('inquriy_email') }}</p>
            </div>
        </div>
        <div style="clear: both;"></div>
        <div>
            <h5 style="color: #1C1F34; margin-top: 0;">Payment Information:</h5>
            <div style="background: #F6F7F9; padding:8px 24px;">
                <div style="display: inline-block;">
                    <span style="color: #1C1F34;">Payment Status::</span>
                    @if ($data['prescription_payment_status'] == 1)
                    <span style="color: #219653; margin-left: 16px;">PAID</span>
                    @elseif($data['prescription_payment_status'] == 2)
                    <span style="color: #EB5757; margin-left: 16px;">FAILED</span>
                    @else
                    <span style="color: #EB5757; margin-left: 16px;">UNPAID</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="padding: 16px 0;">
            <div class="row">
                <div class="column">
                    <h5 style="margin: 8px 0;">Clinic Information:</h5>
                    <div class="card" style="text-align: start;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody style="background: #F6F7F9;">
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34">Name:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['clinic']['name'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Phone:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['clinic']['contact_number'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Email:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['clinic']['email'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Address:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['clinic']['address'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="column">
                    <h5 style="margin: 8px 0;">Doctor Information:</h5>
                    <div class="card">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody style="background: #F6F7F9;">
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34">Name:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['doctor']['first_name'].' '.$data['doctor']['last_name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Phone:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['doctor']['mobile'] ?? '-'}}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Email:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['doctor']['email'] ?? '-'}}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Address:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['doctor']['address'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="row" style="margin-top: 16px;">
                @if (!empty($data['pharma']))
                <div class="column">
                    <h5 style="margin: 8px 0;">Pharma Information:</h5>
                    <div class="card">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody style="background: #F6F7F9;">
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Name test:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['pharma']['first_name'].' '.$data['pharma']['last_name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Phone:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['pharma']['mobile'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Email:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['pharma']['email'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Address:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['pharma']['address'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
                <div class="column">
                    <h5 style="margin: 8px 0;">Patient Information:</h5>
                    <div class="card">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody style="background: #F6F7F9;">
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Name test:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['user']['first_name'].' '.$data['user']['last_name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Phone:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['user']['mobile'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34;">Email:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['user']['email'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px; text-align: start; color: #1C1F34; width:50%;">Address:</td>
                                    <td style="padding:4px; text-align: start; color: #6B6B6B;">{{ $data['user']['address'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        @if (checkPlugin('pharma') == 'active')
        @php
        $prescriptionsList = $data['prescriptions'];
        $prescriptions = $prescriptionsList ?? collect();
        $hasMedicine = $prescriptions
        ->filter(function ($item) {
        return !empty($item->medicine);
        })
        ->isNotEmpty();
        @endphp

        @if ($hasMedicine)
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <thead style="background: #F6F7F9;">
                <th style="padding:12px 30px; text-align: start;">{{ __('pharma::messages.medicine_name') }}</th>
                <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.form') }}</th>
                <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.dosage') }}</th>
                <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.frequency') }}</th>
                <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.days') }}</th>
                <!-- <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.expiry_date') }}</th> -->
                <th style="padding:12px 30px; text-align: end;">{{ __('pharma::messages.price') }}</th>
            </thead>
            <tbody>
                <tr>
                    @foreach ($prescriptions as $item)
                    @if (!empty($item->medicine))
                <tr>
                    <td style="padding:12px 30px; text-align: start;">{{ $item->name ?? '--' }}</td>
                    <td style="padding:12px 30px; text-align: start;">{{ $item->medicine->form->name ?? '--' }}</td>
                    <td style="padding:12px 30px; text-align: start;">{{ $item->medicine->dosage ?? '--' }}</td>
                    <td style="padding:12px 30px; text-align: start;">{{ $item->frequency ?? '--' }}</td>
                    <td style="padding:12px 30px; text-align: start;">{{ $item->duration ?? '--' }}</td>
                    <!-- <td style="padding:12px 30px; text-align: start;">{{ \Carbon\Carbon::parse($item->medicine->expiry_date)->timezone($data['timezone'])->format($data['dateformate']) ?? '--' }}</td> -->
                    <td style="padding:12px 30px; text-align: start;">{{ Currency::format($item->total_amount) ?? '--' }}</td>
                </tr>
                @endif
                @endforeach
                </tr>
            </tbody>
        </table>
        @endif

        @php
        $totalMedicinePrice = $data['prescriptions']->sum('total_amount');
        // dd($totalMedicinePrice);
        $inclusiveTaxes = [];
        $exclusiveTaxes = [];
        $totalTaxAmount = 0;
        $totalAmount = 0;

        if ($data['prescriptions']->isNotEmpty() && $data['prescriptions']->first()->inclusive_tax) {
        $inclusiveTaxes = json_decode($data['prescriptions']->first()->inclusive_tax, true);
        if (!is_array($inclusiveTaxes)) {
            $inclusiveTaxes = [];
        }
        }

        $billingDetail = optional($data['prescriptions']->first()->billingDetail);

        if ($billingDetail && $billingDetail->exclusive_tax) {
        $exclusiveTaxes = json_decode($billingDetail->exclusive_tax, true);
        if (!is_array($exclusiveTaxes)) {
            $exclusiveTaxes = [];
        }
        $totalTaxAmount = $billingDetail->exclusive_tax_amount;
        $totalAmount = is_numeric($billingDetail->total_amount) ? \Currency::format($billingDetail->total_amount) : \Currency::format(0);
        }

        @endphp

        <table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
            <tbody style="background: #F6F7F9;">
                <tr>
                    <td style="padding:12px 30px; text-align: start;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: start; color: #6B6B6B;">{{ __('pharma::messages.medicine_total') }}</td>
                    <td style="padding:12px 30px; text-align: end; color: #1C1F34;">{{ Currency::format($totalMedicinePrice) }}</td>
                </tr>

                @if (!empty($exclusiveTaxes))
                @foreach ($exclusiveTaxes as $tax)
                @php
                $amount = $tax['type'] === 'percent'
                ? \Currency::format(($totalMedicinePrice * $tax['value'] / 100))
                : \Currency::format($tax['value']);
                @endphp
                <tr>
                    <td style="padding:12px 30px; text-align: start;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: start; color: #6B6B6B;">
                        {{ $tax['title'] ?? __('pharma::messages.exclusive_tax') }}
                        ({{ $tax['type'] === 'fixed' ? getCurrencySymbol() : '' }}{{ $tax['value'] }}{{ $tax['type'] === 'percent' ? '%' : '' }})
                    </td>
                    <td style="padding:12px 30px; text-align: end; color: #1C1F34;">
                        {{ $tax['type'] === 'fixed' ? '' : '' }}{{ $amount }}
                    </td>
                </tr>
                @endforeach
                @endif

                <tr>
                    <td style="padding:12px 30px; text-align: start;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: end;"></td>
                    <td style="padding:12px 30px; text-align: start; color: #1C1F34; border-top:1px solid #ccc;">Grand Total</td>
                    <td style="padding:12px 30px; text-align: end; color: #1C1F34; border-top:1px solid #ccc;">{{ $totalAmount }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        <div class="bottom-section">
            <h4 style="margin-bottom: 8px;">Terms & Condition</h4>
            <p style="margin:8px 0; font-size: 14px;">Payment is due upon receipt. By making a booking, you agree to our service terms, including payment
                policies, warranties, and liability limitations. Cancellations within 24 hours of the service may
                incur
                a fee. Any issues with workmanship are covered under our 30-day warranty. Contact us for details at
                <a href="mailto:{{ setting('inquriy_email') }}" style="text-decoration: none; color: #5F60B9;">{{ setting('inquriy_email') }}.</a>
            </p>
        </div>
        <footer style="margin-top: 8px;">
            <div style="display: inline; vertical-align: middle; margin-right: 10px;">
                <h5 style="display: inline;">For more information:</h5>
                <a href="{{ env('APP_URL') }}" style="color: #5F60B9;">{{ setting('app_name') }}</a>
            </div>
        </footer>
    </div>
</body>

</html>