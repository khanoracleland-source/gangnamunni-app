<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute을(를) 동의해야 합니다.',
    'accepted_if' => ':other이(가) :value일 때 :attribute을(를) 동의해야 합니다.',
    'active_url' => ':attribute은(는) 유효한 URL이 아닙니다.',
    'after' => ':attribute은(는) :date 이후 날짜여야 합니다.',
    'after_or_equal' => ':attribute은(는) :date 이후이거나 같은 날짜여야 합니다.',
    'alpha' => ':attribute은(는) 문자만 포함할 수 있습니다.',
    'alpha_dash' => ':attribute은(는) 문자, 숫자, 대시 및 밑줄만 포함할 수 있습니다.',
    'alpha_num' => ':attribute은(는) 문자와 숫자만 포함할 수 있습니다.',
    'array' => ':attribute은(는) 배열이어야 합니다.',
    'before' => ':attribute은(는) :date 이전 날짜여야 합니다.',
    'before_or_equal' => ':attribute은(는) :date 이전이거나 같은 날짜여야 합니다.',
    'between' => [
        'array' => ':attribute은(는) :min에서 :max 사이의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :min에서 :max 킬로바이트 사이여야 합니다.',
        'numeric' => ':attribute은(는) :min에서 :max 사이여야 합니다.',
        'string' => ':attribute은(는) :min에서 :max 문자 사이여야 합니다.',
    ],
    'boolean' => ':attribute 필드는 참 또는 거짓이어야 합니다.',
    'confirmed' => ':attribute 확인이 일치하지 않습니다.',
    'current_password' => '비밀번호가 올바르지 않습니다.',
    'date' => ':attribute은(는) 유효한 날짜가 아닙니다.',
    'date_equals' => ':attribute은(는) :date와 같은 날짜여야 합니다.',
    'date_format' => ':attribute이(가) :format 형식과 일치하지 않습니다.',
    'declined' => ':attribute을(를) 거부해야 합니다.',
    'declined_if' => ':other이(가) :value일 때 :attribute을(를) 거부해야 합니다.',
    'different' => ':attribute와(과) :other은(는) 달라야 합니다.',
    'digits' => ':attribute은(는) :digits 자리 숫자여야 합니다.',
    'digits_between' => ':attribute은(는) :min에서 :max 자리 숫자 사이여야 합니다.',
    'dimensions' => ':attribute의 이미지 크기가 유효하지 않습니다.',
    'distinct' => ':attribute 필드에 중복된 값이 있습니다.',
    'email' => ':attribute은(는) 유효한 이메일 주소여야 합니다.',
    'ends_with' => ':attribute은(는) 다음 중 하나로 끝나야 합니다: :values.',
    'enum' => '선택된 :attribute이(가) 유효하지 않습니다.',
    'exists' => '선택된 :attribute이(가) 유효하지 않습니다.',
    'file' => ':attribute은(는) 파일이어야 합니다.',
    'filled' => ':attribute 필드는 값이 있어야 합니다.',
    'gt' => [
        'array' => ':attribute은(는) :value개 이상의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :value 킬로바이트보다 커야 합니다.',
        'numeric' => ':attribute은(는) :value보다 커야 합니다.',
        'string' => ':attribute은(는) :value 문자보다 많아야 합니다.',
    ],
    'gte' => [
        'array' => ':attribute은(는) :value개 이상의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :value 킬로바이트 이상이어야 합니다.',
        'numeric' => ':attribute은(는) :value 이상이어야 합니다.',
        'string' => ':attribute은(는) :value 문자 이상이어야 합니다.',
    ],
    'image' => ':attribute은(는) 이미지여야 합니다.',
    'in' => '선택된 :attribute이(가) 유효하지 않습니다.',
    'in_array' => ':attribute 필드가 :other에 존재하지 않습니다.',
    'integer' => ':attribute은(는) 정수여야 합니다.',
    'ip' => ':attribute은(는) 유효한 IP 주소여야 합니다.',
    'ipv4' => ':attribute은(는) 유효한 IPv4 주소여야 합니다.',
    'ipv6' => ':attribute은(는) 유효한 IPv6 주소여야 합니다.',
    'json' => ':attribute은(는) 유효한 JSON 문자열이어야 합니다.',
    'lt' => [
        'array' => ':attribute은(는) :value개 미만의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :value 킬로바이트 미만이어야 합니다.',
        'numeric' => ':attribute은(는) :value 미만이어야 합니다.',
        'string' => ':attribute은(는) :value 문자 미만이어야 합니다.',
    ],
    'lte' => [
        'array' => ':attribute은(는) :value개 이하의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :value 킬로바이트 이하여야 합니다.',
        'numeric' => ':attribute은(는) :value 이하여야 합니다.',
        'string' => ':attribute은(는) :value 문자 이하여야 합니다.',
    ],
    'mac_address' => ':attribute은(는) 유효한 MAC 주소여야 합니다.',
    'max' => [
        'array' => ':attribute은(는) :max개 이하의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :max 킬로바이트를 초과할 수 없습니다.',
        'numeric' => ':attribute은(는) :max를 초과할 수 없습니다.',
        'string' => ':attribute은(는) :max 문자를 초과할 수 없습니다.',
    ],
    'mimes' => ':attribute은(는) 다음 유형의 파일이어야 합니다: :values.',
    'mimetypes' => ':attribute은(는) 다음 유형의 파일이어야 합니다: :values.',
    'min' => [
        'array' => ':attribute은(는) 최소 :min개의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) 최소 :min 킬로바이트여야 합니다.',
        'numeric' => ':attribute은(는) 최소 :min이어야 합니다.',
        'string' => ':attribute은(는) 최소 :min 문자여야 합니다.',
    ],
    'multiple_of' => ':attribute은(는) :value의 배수여야 합니다.',
    'not_in' => '선택된 :attribute이(가) 유효하지 않습니다.',
    'not_regex' => ':attribute 형식이 유효하지 않습니다.',
    'numeric' => ':attribute은(는) 숫자여야 합니다.',
    'present' => ':attribute 필드가 있어야 합니다.',
    'prohibited' => ':attribute 필드는 금지됩니다.',
    'prohibited_if' => ':other이(가) :value일 때 :attribute 필드는 금지됩니다.',
    'prohibited_unless' => ':other이(가) :values에 없으면 :attribute 필드는 금지됩니다.',
    'prohibits' => ':attribute 필드는 :other의 존재를 금지합니다.',
    'regex' => ':attribute 형식이 유효하지 않습니다.',
    'required' => ':attribute 필드는 필수입니다.',
    'required_array_keys' => ':attribute 필드는 다음 항목을 포함해야 합니다: :values.',
    'required_if' => ':other이(가) :value일 때 :attribute 필드는 필수입니다.',
    'required_unless' => ':other이(가) :values에 없으면 :attribute 필드는 필수입니다.',
    'required_with' => ':values이(가) 있을 때 :attribute 필드는 필수입니다.',
    'required_with_all' => ':values이(가) 모두 있을 때 :attribute 필드는 필수입니다.',
    'required_without' => ':values이(가) 없을 때 :attribute 필드는 필수입니다.',
    'required_without_all' => ':values이(가) 모두 없을 때 :attribute 필드는 필수입니다.',
    'same' => ':attribute와(과) :other이(가) 일치해야 합니다.',
    'size' => [
        'array' => ':attribute은(는) :size개의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :size 킬로바이트여야 합니다.',
        'numeric' => ':attribute은(는) :size여야 합니다.',
        'string' => ':attribute은(는) :size 문자여야 합니다.',
    ],
    'starts_with' => ':attribute은(는) 다음 중 하나로 시작해야 합니다: :values.',
    'string' => ':attribute은(는) 문자열이어야 합니다.',
    'timezone' => ':attribute은(는) 유효한 시간대여야 합니다.',
    'unique' => ':attribute은(는) 이미 사용 중입니다.',
    'uploaded' => ':attribute 업로드에 실패했습니다.',
    'url' => ':attribute은(는) 유효한 URL이어야 합니다.',
    'uuid' => ':attribute은(는) 유효한 UUID여야 합니다.',
    'password' => [
        'mixed' => ':attribute은(는) 최소 하나의 대문자와 소문자를 포함해야 합니다.',
        'letters' => ':attribute은(는) 최소 하나의 문자를 포함해야 합니다.',
        'numbers' => ':attribute은(는) 최소 하나의 숫자를 포함해야 합니다.',
        'symbols' => ':attribute은(는) 최소 하나의 특수 문자를 포함해야 합니다.',
        'uncompromised' => '입력하신 :attribute은(는) 데이터 유출에서 발견되었습니다. 다른 :attribute을(를) 선택해주세요.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
