<!--begin::Authentication - Sign-in -->
@php
    $style = 'style=background-image:url(' . asset('assets/img/progress-hd.png') . ')';
    $settingValue = getSuperAdminSettingValue();
    App::setLocale(checkLanguageSession());

@endphp
@extends('layouts.auth_app')

@section('title')
    {{ __('auth.login.login') }}
@endsection
@section('content')


    {{--    <ul class="nav nav-pills" style="justify-content: flex-end; cursor: pointer"> --}}
    {{--        <li class="nav-item dropdown"> --}}
    {{--            <a class="btn btn-primary w-150px mb-5 indicator m-3" --}}
    {{--               data-bs-toggle="dropdown" href="javascript:void(0)" role="button" --}}
    {{--               aria-expanded="false">{{ getCurrentLanguageName() }}</a> --}}
    {{--            <ul class="dropdown-menu w-150px"> --}}
    {{--                @foreach (getLanguages() as $key => $value) --}}
    {{--                    <li class="{{(checkLanguageSession() == $key) ? 'active' : '' }}"><a --}}
    {{--                                class="dropdown-item  px-5 language-select {{(checkLanguageSession() == $key) ? 'bg-primary text-white' : 'text-dark' }}" --}}
    {{--                                data-id="{{$key}}">{{$value}}</a> --}}
    {{--                    </li> --}}
    {{--                @endforeach --}}
    {{--            </ul> --}}
    {{--        </li> --}}
    {{--    </ul> --}}

    <ul class="nav nav-pills language-option" style="justify-content: flex-end; cursor: pointer">
        <li class="nav-item dropdown m-5">
            <a class="btn btn-primary w-150px mb-5 indicator m-3 dropdown-toggle" data-bs-toggle="dropdown"
                href="javascript:void(0)" role="button" aria-expanded="false">{{ getCurrentLanguageName() }}</a>
            <ul class="dropdown-menu w-150px">
                @foreach (getLanguages() as $key => $value)
                    <li class="{{ checkLanguageSession() == $key ? 'active' : '' }}"><a
                            class="dropdown-item  px-5 language-select {{ checkLanguageSession() == $key ? 'bg-primary text-white' : 'text-dark' }}"
                            data-id="{{ $key }}">{{ $value }}</a>
                    </li>
                @endforeach
            </ul>
        </li>
    </ul>

    <div class="d-flex flex-column flex-column-fluid align-items-center justify-content-center p-4">
        <div class="col-12 text-center">
            <a href="{{ route('landing-home') }}" class="image mb-7 mb-sm-10">
                <img alt="Logo" src="{{ asset($settingValue['app_logo']['value']) }}" class="img-fluid logo-fix-size">
            </a>
        </div>
        <div class="bg-theme-white rounded-15 shadow-md width-540 px-5 px-sm-7 py-10 mx-auto">
            @include('flash::message')
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            
    @endif

            <h1 class="text-center mb-7">{{ __('auth.sign_in') }}</h1>
            <form method="post" action="{{ url('/login') }}">
                @csrf
                <input type="hidden" name="route_name"
                    value="{{ app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName() }}">
                <div class="mb-sm-7 mb-4">
                    <label for="formInputEmail" class="form-label">
                        {{ __('auth.email') }} <span class="required"></span>
                    </label>
                    <input type="email" class="form-control" name="email"
                        value="{{ Cookie::get('email') !== null ? Cookie::get('email') : old('email') }}" required
                        placeholder="{{ __('auth.login.enter_email') }}" id="formInputEmail">
                </div>
                <div class="mb-sm-7 mb-4">
                    <div class="d-flex justify-content-between">
                        <label for="formInputPassword" class="form-label">{{ __('auth.password') }}:
                            <span class="required"></span>
                        </label>
                        <a href="{{ url('/password/reset') }}" class="link-info fs-6 text-decoration-none">
                            {{ __('auth.login.forgot_password') }} ?
                        </a>
                        <input type="hidden"
                            value="{{ session('languageName') == null ? session('languageName') : checkLanguageSession() }}"
                            name="se_lang">
                    </div>
                    <div class="position-relative">
                        <input type="password" class="form-control pe-5" name="password" id="formInputPassword"
                            placeholder="{{ __('auth.login.enter_password') }}"
                            value="{{ Cookie::get('password') !== null ? Cookie::get('password') : null }}" required>
                        <span class="position-absolute top-50 end-0 translate-middle-y me-3" 
                              style="cursor: pointer; z-index: 5;" id="togglePassword">
                            <i class="fas fa-eye-slash text-muted" id="togglePasswordIcon"></i>
                        </span>
                    </div>
                </div>
                <div class="mb-sm-7 mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="formCheck" name="remember" checked>
                    <label class="form-check-label" for="formCheck">{{ __('auth.remember_me') }}</label>
                </div>
                <div class="d-grid" data-turbo="false">
                    <button type="submit" class="btn btn-primary">{{ __('auth.login.login') }}</button>
                </div>
                <div class="d-flex align-items-center mt-4">
                    <span class="text-gray-700 me-2"> {{ __('messages.new_change.not_have_account') }}?</span>
                    <a href="{{ route('register') }}" class="link-info fs-6 text-decoration-none">
                        {{ __('messages.web_home.sign_up') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Demo Login Section --}}
    @if(config('app.demo_mode'))
    @php
        // DB settings에서 PC/모바일 데모 표시 설정 로딩
        $demoPC = '1';
        $demoMobile = '0';
        try {
            $pcSetting = \App\Models\Setting::where('key', 'show_demo_login_pc')->first();
            $mobileSetting = \App\Models\Setting::where('key', 'show_demo_login_mobile')->first();
            if ($pcSetting) $demoPC = $pcSetting->value;
            if ($mobileSetting) $demoMobile = $mobileSetting->value;
        } catch (\Exception $e) {}
    @endphp
    <style>
        @media (max-width: 767px) {
            #demo-login-section[data-show-mobile="0"] { display: none !important; }
        }
        @media (min-width: 768px) {
            #demo-login-section[data-show-pc="0"] { display: none !important; }
        }
    </style>
    <div class="width-840 mx-auto mt-5 px-3" id="demo-login-section"
         data-show-pc="{{ $demoPC }}" data-show-mobile="{{ $demoMobile }}">
        <div class="text-center mb-4">
            <hr class="my-4">
            <h4 class="fw-bold text-dark">
                <i class="fas fa-users-cog me-2"></i>데모 로그인
            </h4>
            <p class="text-muted">역할을 선택하면 자동으로 로그인됩니다</p>
        </div>
        <div class="row g-3">
            {{-- Super Admin --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="admin@hms.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #6f42c1, #9b59b6);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M6.5 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zM11 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                <path d="M4.5 0A2.5 2.5 0 0 0 2 2.5V14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2.5A2.5 2.5 0 0 0 11.5 0h-7zM3 2.5A1.5 1.5 0 0 1 4.5 1h7A1.5 1.5 0 0 1 13 2.5v10.795a4.2 4.2 0 0 0-2-.654 6.5 6.5 0 0 0-1.76.233A3.99 3.99 0 0 0 8 12a3.99 3.99 0 0 0-1.24.374 6.5 6.5 0 0 0-1.76-.233 4.2 4.2 0 0 0-2 .654V2.5z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Super Admin</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">전체 SaaS 플랫폼 관리<br>(병원, 구독, 설정)</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Admin --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="srunolfsson@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #0d6efd, #3d8bfd);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 5.996V14H3s-1 0-1-1 1-4 6-4c.564 0 1.077.038 1.544.107a4.524 4.524 0 0 0-.803.918A10.46 10.46 0 0 0 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h5z"/>
                                <path d="M12 6.5a.5.5 0 0 1 .5.5v1.5H14a.5.5 0 0 1 0 1h-1.5V11a.5.5 0 0 1-1 0V9.5H10a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 .5-.5z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Admin</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">병원 일상 운영 관리<br>(거의 모든 기능 접근)</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Doctor --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="alvena57@example.net" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #198754, #28a745);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M7 1a1 1 0 0 1 2 0v1h1a1 1 0 0 1 0 2H9v1a1 1 0 0 1-2 0V4H6a1 1 0 0 1 0-2h1V1z"/>
                                <path d="M3.5 13a3.5 3.5 0 0 0 3.5-3.5V7.035a2.5 2.5 0 0 1-1 .215 2.5 2.5 0 0 1-2.236-1.382L2.276 8.839A3.5 3.5 0 0 0 3.5 13zm9 0a3.5 3.5 0 0 0 1.224-4.161l-1.488-2.971A2.5 2.5 0 0 1 10 7.25V9.5A3.5 3.5 0 0 0 12.5 13z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Doctor</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">환자 진료 관리<br>(외래/입원/수술)</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Patient --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="ruthie.carroll@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #dc3545, #e74c3c);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Patient</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">의료 기록 조회<br>및 예약 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Nurse --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="keshawn.ortiz@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #e83e8c, #d63384);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Nurse</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">간호 기록 및<br>환자 케어 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Receptionist --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="bertram79@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #fd7e14, #e67e22);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Receptionist</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">외래 예약, 방문자<br>우편 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Pharmacist --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="khessel@example.net" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #20c997, #1abc9c);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M4.887 7.2l2.3-2.3a.5.5 0 0 1 .707 0l4.243 4.243a.5.5 0 0 1 0 .707l-2.3 2.3L4.886 7.2zm.708-.707l4.95 4.95 1.593-1.593-4.95-4.95-1.593 1.593zM2.034 11.09l1.5 1.5a.5.5 0 0 0 .708-.708l-1.5-1.5a.5.5 0 0 0-.708.708zm.707-2.121l2.5 2.5a.5.5 0 1 0 .707-.708l-2.5-2.5a.5.5 0 0 0-.707.708z"/>
                                <path d="M9.293.293a1 1 0 0 1 1.414 0l2 2a1 1 0 0 1 0 1.414L11.414 5 8 1.586 9.293.293z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Pharmacist</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">약국 판매 및<br>재고 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Accountant --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="korbin.dach@example.org" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #ffc107, #f39c12);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M4 10.781c.148 1.667 1.513 2.85 3.591 3.003V15h1.043v-1.216c2.27-.179 3.678-1.438 3.678-3.3 0-1.59-.947-2.51-2.956-3.028l-.722-.187V3.467c1.122.11 1.879.714 2.07 1.616h1.47c-.166-1.6-1.54-2.748-3.54-2.875V1H7.591v1.233c-1.939.23-3.27 1.472-3.27 3.156 0 1.454.966 2.483 2.661 2.917l.61.162v4.031c-1.149-.17-1.94-.8-2.131-1.718H4zm3.391-3.836c-1.043-.263-1.6-.825-1.6-1.616 0-.944.704-1.641 1.8-1.828v3.495l-.2-.05zm1.591 1.872c1.287.323 1.852.859 1.852 1.769 0 1.097-.826 1.828-2.2 1.939V8.73l.348.086z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Accountant</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">청구, 결제<br>급여 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Case Manager --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="carolina.mante@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #6610f2, #7c3aed);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1h-4z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Case Manager</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">환자 케이스<br>관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
            {{-- Lab Technician --}}
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card demo-role-card h-100 border-0 shadow-sm demo-active" data-email="lourdes50@example.com" data-password="123456">
                    <div class="card-body text-center p-3">
                        <div class="demo-icon-wrap mx-auto mb-2" style="background: linear-gradient(135deg, #0dcaf0, #17a2b8);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 16 16">
                                <path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0z"/>
                                <path d="M2 6.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-5a.5.5 0 0 1-.5-.5v-7zm1 .5v6h4V7H3z"/>
                                <path d="M10.5 6a.5.5 0 0 1 .5.5V8h1.5a.5.5 0 0 1 0 1H11v1.5a.5.5 0 0 1-1 0V9H8.5a.5.5 0 0 1 0-1H10V6.5a.5.5 0 0 1 .5-.5z"/>
                            </svg>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark">Lab Technician</h6>
                        <small class="text-muted d-block" style="font-size: 0.72rem; line-height: 1.3;">검사실 검사 및<br>보고서 관리</small>
                        <button class="btn btn-sm btn-primary mt-2 w-100 demo-login-btn">로그인</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center mt-3 mb-4">
            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>모든 데모 계정의 비밀번호는 123456 입니다</small>
        </div>
    </div>

    <style>
        .width-840 { max-width: 840px; }
        .demo-role-card { cursor: pointer; transition: all 0.3s ease; border-radius: 12px !important; overflow: hidden; }
        .demo-role-card.demo-active:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
        .demo-role-card.demo-inactive { opacity: 0.55; cursor: default; }
        .demo-icon-wrap { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .demo-login-btn { border-radius: 8px; font-weight: 600; font-size: 0.8rem; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.demo-role-card.demo-active').forEach(function(card) {
                card.addEventListener('click', function() {
                    var email = this.getAttribute('data-email');
                    var password = this.getAttribute('data-password');
                    document.getElementById('formInputEmail').value = email;
                    document.getElementById('formInputPassword').value = password;
                    document.querySelector('form[action*="login"]').submit();
                });
            });
        });
    </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggle = document.getElementById('togglePassword');
            var input = document.getElementById('formInputPassword');
            var icon = document.getElementById('togglePasswordIcon');
            if (toggle && input && icon) {
                toggle.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                });
            }
        });
    </script>
    <!--end::Authentication - Sign-in -->
@endsection
