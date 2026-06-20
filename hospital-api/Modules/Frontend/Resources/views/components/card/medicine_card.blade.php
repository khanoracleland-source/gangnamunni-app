<li class="section-bg rounded p-4">
    @foreach ($prescriptions as $prescription)
        <div class="d-flex flex-wrap align-items-center gap-3">
            <h6 class="mb-0">{{ $prescription->medicine->name ?? '-'}}</h6>
            <span class="text-primary font-size-14 fw-bold">{{ \Currency::format($prescription->total_amount ?? 0) }}</span>
        </div>
        @if ($prescription->instruction)
            <p class="font-size-14 mb-0">{{ $prescription->instruction }}</p>
        @endif
        <div class="border-top mt-3 pt-3">
            <div class="row gy-3">
                <div class="col-md-4">
                    <span class="font-size-14 mb-2">Dosage:</span>
                    <h6 class="font-size-14 mb-0">{{ $prescription->medicine->dosage ?? '-' }}</h6>
                </div>
                <div class="col-md-4">
                    <span class="font-size-14 mb-2">Frequency:</span>
                    <h6 class="font-size-14 mb-0">{{ $prescription->frequency ?? '-' }}</h6>
                </div>
                <div class="col-md-4">
                    <span class="font-size-14 mb-2">Days:</span>
                    <h6 class="font-size-14 mb-0">{{ $prescription->duration ?? '-' }} Days</h6>
                </div>
            </div>
        </div>
    @endforeach
</li>