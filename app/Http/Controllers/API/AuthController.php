<?php

namespace App\Http\Controllers\API;

use DB;
use Str;
use Auth;
use Hash;
use Mail;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Mail\ForgotPasswordMail;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\AppBaseController;

class AuthController extends AppBaseController
{

    public function login(Request $request): JsonResponse
    {
        $email = $request->get('email');
        $password = $request->get('password');

        if (empty($email) or empty($password)) {
            return $this->sendError(__('messages.common.username_and_password_required'), 422);
        }
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $user) {
            return $this->sendError(__('messages.common.we_cant_find_user'),422);
        }

        if($user->email_verified_at == null){
            return $this->sendError('Please verify your email', 422);
        }

        if (empty($user)) {
            return $this->sendError(__('messages.common.invalid_username_password'), 422);
        }

        if (! Hash::check($password, $user->password)) {
            return $this->sendError(__('messages.common.invalid_username_password'), 422);
        }

        $token = $user->createToken('token')->plainTextToken;
        $user->last_name = $user->last_name ?? '';

        if ($user->hasRole('Doctor')) {
            $data = [
                'token' => $token,
                'is_doctor' => true,
                'role' => 'Doctor',
                'user' => $user->prepareData(),
            ];
        }   elseif ($user->hasRole('Patient')) {
            $data = [
                'token' => $token,
                'is_doctor' => false,
                'role' => 'Patient',
                'user' => $user->prepareData(),
            ];
        } elseif ($user->hasRole('Super Admin')){
            $data = [
                'token' => $token,
                'is_doctor' => false,
                'role' => 'Super Admin',
                'user' => $user->prepareData(),
            ];
        }elseif ($user->hasRole('Admin')){
            $data = [
               'token' => $token,
               'is_doctor' => false,
               'role' => 'Admin',
               'user' => $user->prepareData(),
           ];
        } else {
            return $this->sendError(__('messages.common.invalid_username_password'), 422);
        }

        return $this->sendResponse($data, __('messages.common.logged_in_successfully'));
    }

    public function logout(): JsonResponse
    {
        auth()->user()->tokens()->where('id', Auth::user()->currentAccessToken()->id)->delete();

        return $this->sendSuccess(__('messages.common.logout_successfully'));
    }

    /**
     * @throws ValidationException
     */
    public function sendPasswordResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $data['user'] = User::whereRaw('lower(email) = ?', [strtolower($request->email)])->first();

        if (! $data['user']) {
            return $this->sendError(__('messages.common.we_cant_find_user'));
        }

        // Keep the token URL-safe and only persist its hash. Encrypted payloads can
        // contain characters such as "+" which are changed while parsing a query string.
        $data['token'] = Str::random(64);
        $storedToken = hash('sha256', $data['token']);
        $query = http_build_query([
            'token' => $data['token'],
            'email' => $data['user']->email,
            'apn' => 'com.example.infyhms_flutter',
        ], '', '&', PHP_QUERY_RFC3986);
        $data['link'] = rtrim(config('app.url'), '/').'/createNewPassword?'.$query;

        $user = DB::table('password_reset_tokens')->where('email', $data['user']->email)->first();

        if ($user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->update([
                'email' => $data['user']->email,
                'token' => $storedToken,
                'created_at' => Carbon::now(),
            ]);
        } else {
            DB::table('password_reset_tokens')->insert([
                'email' => $data['user']->email,
                'token' => $storedToken,
                'created_at' => Carbon::now(),
            ]);
        }

        Mail::to($data['user']->email)
            ->send(new ForgotPasswordMail('emails.forgot_password',
                'Reset Password Notification',
                $data));

        return $this->sendSuccess(__('messages.common.we_have_your_password_resetk_link'));
    }

    /**
     * @throws ValidationException
     */
    public function resetPassword(Request $request): JsonResponse
    {
        // $decrypttoken = decrypt($request->token);
        // $getEmail =  explode(' ', $decrypttoken);

        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where(function ($query) use ($request) {
                $query->where('token', hash('sha256', $request->token))
                    // Allow links issued before tokens started being hashed.
                    ->orWhere('token', $request->token);
            })->first();

        $expiresAt = $tokenData
            ? Carbon::parse($tokenData->created_at)->addMinutes(config('auth.passwords.users.expire', 60))
            : null;

        if (! $tokenData || Carbon::now()->greaterThan($expiresAt)) {
            if ($tokenData) {
                DB::table('password_reset_tokens')->where('email', $tokenData->email)->delete();
            }

            return $this->sendError(__('messages.common.this_password_reset_token_is_invalid'));
        }

        $user = User::whereRaw('lower(email) = ?', [strtolower($request->email)])->first();

        if (! $user) {
            return $this->sendError(__('messages.common.we_cant_find_user'));
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')
            ->where('email', $tokenData->email)->delete();

        return $this->sendSuccess(__('messages.common.password_reset_successfully'));

    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! Hash::check($request->old_password, $user->password)) {
            return $this->sendError(__('messages.common.please_enter_correct_old_password'));
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return $this->sendSuccess(__('messages.common.password_updated'));
    }


}
