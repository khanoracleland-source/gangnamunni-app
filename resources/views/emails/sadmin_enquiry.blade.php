<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
:root { color-scheme: light dark; }
body { margin:0; padding:0; font-family: 'Malgun Gothic','Apple SD Gothic Neo','Segoe UI',sans-serif; }
@@media (prefers-color-scheme: dark) {
    .email-bg { background-color: #1a1a2e !important; }
    .email-card { background-color: #16213e !important; border-color: #2a2a4a !important; }
    .email-text { color: #e0e0e0 !important; }
    .email-text-sub { color: #b0b0b0 !important; }
    .email-sender-box { background-color: #0f3460 !important; border-color: #1a5276 !important; }
    .email-table-bg { background-color: #1a2744 !important; }
    .email-table-border { border-color: #2a3a5c !important; }
    .email-footer { background-color: #0d1117 !important; color: #8b949e !important; }
    .email-link { color: #58a6ff !important; }
}
</style>
</head>
<body style="margin:0; padding:0; background-color:#f0f2f5;" class="email-bg">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f2f5;" class="email-bg">
<tr><td align="center" style="padding: 30px 10px;">

<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:12px; overflow:hidden;" class="email-card">

{{-- Header with logo --}}
<tr>
<td style="background: linear-gradient(135deg, #0d6efd, #6610f2); padding: 24px 30px; text-align: center;">
    @if(function_exists('getSuperAdminAppLogoUrl'))
    <img src="{{ asset(getSuperAdminAppLogoUrl()) }}" alt="{{ getSuperAdminAppName() }}" style="max-height:40px; object-fit:contain;">
    @else
    <span style="color:#fff; font-size:20px; font-weight:bold;">Seoul Beauty</span>
    @endif
</td>
</tr>

{{-- Sender info banner --}}
<tr>
<td style="padding: 20px 30px 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#e8f4fd; border:1px solid #bee5eb; border-radius:8px;" class="email-sender-box">
    <tr>
    <td style="padding: 14px 18px;">
        <span style="color:#0c5460; font-size:13px; font-weight:bold;" class="email-text-sub">보낸 사람</span><br>
        <span style="color:#222; font-size:16px; font-weight:bold;" class="email-text">{{ $data['first_name'] . ' ' . $data['last_name'] }}</span>
        <span style="color:#555; font-size:14px;" class="email-text-sub">&lt;<a href="mailto:{{ $data['email'] }}" style="color:#0d6efd; text-decoration:none;" class="email-link">{{ $data['email'] }}</a>&gt;</span>
    </td>
    </tr>
    </table>
</td>
</tr>

{{-- Body --}}
<tr>
<td style="padding: 24px 30px;">

    @php($superAdmin = App\Models\User::orderBy('created_at')->first())
    <p style="color:#222; font-size:15px; margin:0 0 18px;" class="email-text">
        <b>{{ $superAdmin->first_name . ' ' . $superAdmin->last_name }}</b>님, 새로운 문의가 도착했습니다.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background-color:#f8f9fa; border-radius:8px; overflow:hidden;" class="email-table-bg">
    <tr>
        <td width="90" style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#666; font-size:13px; font-weight:bold; vertical-align:top;" class="email-text-sub email-table-border">이름</td>
        <td style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#222; font-size:14px;" class="email-text email-table-border">{{ $data['first_name'] . ' ' . $data['last_name'] }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#666; font-size:13px; font-weight:bold; vertical-align:top;" class="email-text-sub email-table-border">이메일</td>
        <td style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#222; font-size:14px;" class="email-text email-table-border">
            <a href="mailto:{{ $data['email'] }}" style="color:#0d6efd; text-decoration:none;" class="email-link">{{ $data['email'] }}</a>
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#666; font-size:13px; font-weight:bold; vertical-align:top;" class="email-text-sub email-table-border">전화번호</td>
        <td style="padding:12px 16px; border-bottom:1px solid #e9ecef; color:#222; font-size:14px;" class="email-text email-table-border">
            <a href="tel:{{ $data['phone'] }}" style="color:#0d6efd; text-decoration:none;" class="email-link">{{ $data['phone'] }}</a>
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px; color:#666; font-size:13px; font-weight:bold; vertical-align:top;" class="email-text-sub">메시지</td>
        <td style="padding:12px 16px; color:#222; font-size:14px; white-space:pre-wrap;" class="email-text">{{ $data['message'] }}</td>
    </tr>
    </table>

    <p style="color:#888; font-size:12px; margin:20px 0 0;" class="email-text-sub">
        이 메일에 답장하면 <b>{{ $data['email'] }}</b>로 발송됩니다.
    </p>

</td>
</tr>

{{-- Footer --}}
<tr>
<td style="background-color:#f8f9fa; padding:16px 30px; text-align:center; border-top:1px solid #e9ecef;" class="email-footer">
    <span style="color:#999; font-size:12px;">&copy; {{ date('Y') }} {{ function_exists('getSuperAdminAppName') ? getSuperAdminAppName() : 'Seoul Beauty' }}</span>
</td>
</tr>

</table>

</td></tr>
</table>

</body>
</html>
