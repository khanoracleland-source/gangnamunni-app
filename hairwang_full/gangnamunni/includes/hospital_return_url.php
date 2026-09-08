<?php

// Only a relative hospital-list destination is accepted, never an external URL.
function hospital_return_url($value) {
    if (!is_string($value) || preg_match('/[\x00-\x20\x7f\\\\#]/', $value)) return '';
    return preg_match('/\Ahospitals\.php(?:\?[^#]*)?\z/', $value) ? $value : '';
}
