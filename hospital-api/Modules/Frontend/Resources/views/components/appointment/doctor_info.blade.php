@php
    $doctor = $doctor ?? null;
    $doctorEmail = optional($doctor)->email;
@endphp

@if ($doctor === null)
    <h6 class="m-0">-</h6>
@else
    <div class="d-flex gap-3 align-items-center">
        <img src="{{ optional($doctor)->profile_image ?? default_user_avatar() }}" alt="avatar"
            class="avatar avatar-50 rounded-pill">
        <div class="text-start">
            <h6 class="m-0">{{ getDisplayName($doctor) }}</h6>
            @if ($doctorEmail)
                <a href="mailto:{{ $doctorEmail }}">{{ $doctorEmail }}</a>
            @else
                <span>-</span>
            @endif
        </div>
    </div>
@endif

