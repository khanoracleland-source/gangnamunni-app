<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Get payment list for user
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->input('user_id');

            if (!$userId) {
                return $this->sendError('user_id is required', [], 400);
            }

            $perPage = $request->input('per_page', 20);
            $status = $request->input('status');

            $query = DB::table('payments')
                ->select('payments.*')
                ->where('user_id', $userId);

            if ($status) {
                $query->where('status', $status);
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->sendResponse($payments, '결제 목록을 불러왔습니다.');
        } catch (\Exception $e) {
            return $this->sendError('결제 목록 조회 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment detail
     */
    public function show($id)
    {
        try {
            $payment = DB::table('payments')->where('id', $id)->first();

            if (!$payment) {
                return $this->sendError('결제 정보를 찾을 수 없습니다.', [], 404);
            }

            // Get payment logs
            $logs = DB::table('payment_logs')
                ->where('payment_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            $payment->logs = $logs;

            return $this->sendResponse($payment, '결제 정보를 불러왔습니다.');
        } catch (\Exception $e) {
            return $this->sendError('결제 조회 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Prepare payment (generate merchant_uid)
     */
    public function prepare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'booking_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:100',
            'item_name' => 'required|string|max:200',
            'buyer_name' => 'required|string|max:100',
            'buyer_tel' => 'required|string|max:20',
            'buyer_email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            $merchantUid = 'PAYMENT_' . date('YmdHis') . '_' . Str::random(8);
            $vat = round($request->amount * 0.1, 2);
            $totalAmount = $request->amount + $vat;

            $paymentId = DB::table('payments')->insertGetId([
                'user_id' => $request->user_id,
                'booking_id' => $request->booking_id,
                'payment_method' => $request->payment_method ?? 'card',
                'pg_provider' => 'portone',
                'merchant_uid' => $merchantUid,
                'amount' => $request->amount,
                'vat' => $vat,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'buyer_name' => $request->buyer_name,
                'buyer_email' => $request->buyer_email,
                'buyer_tel' => $request->buyer_tel,
                'buyer_addr' => $request->buyer_addr,
                'buyer_postcode' => $request->buyer_postcode,
                'item_name' => $request->item_name,
                'item_description' => $request->item_description,
                'metadata' => json_encode($request->metadata ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log
            DB::table('payment_logs')->insert([
                'payment_id' => $paymentId,
                'action' => 'prepare',
                'request_data' => json_encode($request->all()),
                'status' => 'success',
                'message' => 'Payment prepared',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $payment = DB::table('payments')->where('id', $paymentId)->first();

            return $this->sendResponse($payment, '결제가 준비되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('결제 준비 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Payment callback (PortOne webhook)
     */
    public function callback(Request $request)
    {
        try {
            $impUid = $request->input('imp_uid');
            $merchantUid = $request->input('merchant_uid');

            if (!$impUid || !$merchantUid) {
                return $this->sendError('imp_uid and merchant_uid are required', [], 400);
            }

            // Get payment from DB
            $payment = DB::table('payments')->where('merchant_uid', $merchantUid)->first();

            if (!$payment) {
                return $this->sendError('Payment not found', [], 404);
            }

            // Verify payment with PortOne API
            $portoneData = $this->verifyPortOnePayment($impUid);

            // Log callback
            DB::table('payment_logs')->insert([
                'payment_id' => $payment->id,
                'action' => 'callback',
                'request_data' => json_encode($request->all()),
                'response_data' => json_encode($portoneData),
                'status' => $portoneData['status'] === 'paid' ? 'success' : 'fail',
                'message' => 'PortOne callback received',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            // Verify amount matches
            if ($portoneData['amount'] != $payment->total_amount) {
                DB::table('payments')->where('id', $payment->id)->update([
                    'status' => 'failed',
                    'fail_reason' => 'Amount mismatch',
                    'updated_at' => now(),
                ]);

                return $this->sendError('결제 금액이 일치하지 않습니다.', [], 400);
            }

            // Update payment status
            DB::table('payments')->where('id', $payment->id)->update([
                'imp_uid' => $impUid,
                'status' => $portoneData['status'],
                'paid_at' => $portoneData['status'] === 'paid' ? now() : null,
                'card_name' => $portoneData['card_name'] ?? null,
                'card_number' => $portoneData['card_number'] ?? null,
                'card_quota' => $portoneData['card_quota'] ?? 0,
                'receipt_url' => $portoneData['receipt_url'] ?? null,
                'fail_reason' => $portoneData['fail_reason'] ?? null,
                'updated_at' => now(),
            ]);

            return $this->sendResponse([
                'payment_id' => $payment->id,
                'status' => $portoneData['status']
            ], '결제가 완료되었습니다.');

        } catch (\Exception $e) {
            return $this->sendError('결제 처리 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel payment
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            $payment = DB::table('payments')->where('id', $id)->first();

            if (!$payment) {
                return $this->sendError('결제를 찾을 수 없습니다.', [], 404);
            }

            if ($payment->user_id != $request->user_id) {
                return $this->sendError('권한이 없습니다.', [], 403);
            }

            if ($payment->status !== 'paid') {
                return $this->sendError('취소할 수 없는 상태입니다.', [], 400);
            }

            // Cancel via PortOne API
            $cancelResult = $this->cancelPortOnePayment($payment->imp_uid, $request->reason);

            if (!$cancelResult['success']) {
                return $this->sendError('결제 취소 실패', ['error' => $cancelResult['message']], 500);
            }

            // Update payment
            DB::table('payments')->where('id', $id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $request->reason,
                'updated_at' => now(),
            ]);

            // Log
            DB::table('payment_logs')->insert([
                'payment_id' => $id,
                'action' => 'cancel',
                'request_data' => json_encode($request->all()),
                'response_data' => json_encode($cancelResult),
                'status' => 'success',
                'message' => 'Payment cancelled',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            return $this->sendResponse([], '결제가 취소되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('결제 취소 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Refund payment
     */
    public function refund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'amount' => 'nullable|numeric',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            $payment = DB::table('payments')->where('id', $id)->first();

            if (!$payment) {
                return $this->sendError('결제를 찾을 수 없습니다.', [], 404);
            }

            if ($payment->status !== 'paid') {
                return $this->sendError('환불할 수 없는 상태입니다.', [], 400);
            }

            $refundAmount = $request->amount ?? $payment->total_amount;

            // Refund via PortOne API
            $refundResult = $this->refundPortOnePayment($payment->imp_uid, $refundAmount, $request->reason);

            if (!$refundResult['success']) {
                return $this->sendError('환불 실패', ['error' => $refundResult['message']], 500);
            }

            // Update payment
            DB::table('payments')->where('id', $id)->update([
                'status' => 'refunded',
                'cancelled_at' => now(),
                'cancel_reason' => 'Refund: ' . $request->reason,
                'updated_at' => now(),
            ]);

            // Log
            DB::table('payment_logs')->insert([
                'payment_id' => $id,
                'action' => 'refund',
                'request_data' => json_encode($request->all()),
                'response_data' => json_encode($refundResult),
                'status' => 'success',
                'message' => 'Payment refunded',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            return $this->sendResponse([], '환불이 완료되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('환불 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify payment with PortOne
     */
    private function verifyPortOnePayment($impUid)
    {
        // Get PortOne API access token
        $token = $this->getPortOneToken();

        // Call PortOne payments API
        $response = Http::withToken($token)
            ->get("https://api.iamport.kr/payments/{$impUid}");

        if (!$response->successful()) {
            throw new \Exception('Failed to verify payment with PortOne');
        }

        $data = $response->json();

        return [
            'imp_uid' => $data['response']['imp_uid'],
            'merchant_uid' => $data['response']['merchant_uid'],
            'amount' => $data['response']['amount'],
            'status' => $data['response']['status'],
            'paid_at' => $data['response']['paid_at'],
            'card_name' => $data['response']['card_name'] ?? null,
            'card_number' => $data['response']['card_number'] ?? null,
            'card_quota' => $data['response']['card_quota'] ?? 0,
            'receipt_url' => $data['response']['receipt_url'] ?? null,
            'fail_reason' => $data['response']['fail_reason'] ?? null,
        ];
    }

    /**
     * Cancel payment via PortOne
     */
    private function cancelPortOnePayment($impUid, $reason)
    {
        $token = $this->getPortOneToken();

        $response = Http::withToken($token)
            ->post('https://api.iamport.kr/payments/cancel', [
                'imp_uid' => $impUid,
                'reason' => $reason,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'PortOne API error: ' . $response->body()
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }

    /**
     * Refund payment via PortOne
     */
    private function refundPortOnePayment($impUid, $amount, $reason)
    {
        $token = $this->getPortOneToken();

        $response = Http::withToken($token)
            ->post('https://api.iamport.kr/payments/cancel', [
                'imp_uid' => $impUid,
                'amount' => $amount,
                'reason' => $reason,
                'checksum' => $amount,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'PortOne API error: ' . $response->body()
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }

    /**
     * Get PortOne API access token
     */
    private function getPortOneToken()
    {
        // TODO: Store these in .env
        $impKey = env('PORTONE_API_KEY', 'YOUR_API_KEY');
        $impSecret = env('PORTONE_API_SECRET', 'YOUR_API_SECRET');

        $response = Http::post('https://api.iamport.kr/users/getToken', [
            'imp_key' => $impKey,
            'imp_secret' => $impSecret,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get PortOne token');
        }

        $data = $response->json();
        return $data['response']['access_token'];
    }
}
