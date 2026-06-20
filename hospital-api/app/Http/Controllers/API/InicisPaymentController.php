<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InicisPaymentController extends Controller
{
    /**
     * INICIS 결제 요청
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:50',
            'amount' => 'required|numeric|min:100',
            'product_name' => 'required|string|max:100',
            'buyer_name' => 'required|string|max:50',
            'buyer_email' => 'required|email|max:100',
            'buyer_tel' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '입력 정보가 올바르지 않습니다.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderNumber = $request->order_number;
            $amount = $request->amount;

            // INICIS 설정 값 (환경변수에서 가져오기)
            $mid = env('INICIS_MID', '');
            $signKey = env('INICIS_SIGNKEY', '');

            if (empty($mid) || empty($signKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'INICIS 설정이 올바르지 않습니다.'
                ], 500);
            }

            // 타임스탬프 생성
            $timestamp = date('YmdHis');

            // INICIS 거래 고유번호 (oid) 생성
            $oid = $orderNumber;

            // 해시 값 생성 (INICIS 인증)
            $hashString = $signKey . $oid . $amount . $timestamp;
            $hashData = hash('sha256', $hashString);

            // INICIS 결제 URL 생성
            $paymentUrl = $this->generateInicisPaymentUrl([
                'mid' => $mid,
                'oid' => $oid,
                'price' => $amount,
                'goodname' => $request->product_name,
                'buyername' => $request->buyer_name,
                'buyeremail' => $request->buyer_email,
                'buyertel' => $request->buyer_tel,
                'timestamp' => $timestamp,
                'hashdata' => $hashData,
                'returnUrl' => env('APP_URL') . '/api/payment/inicis/return',
                'closeUrl' => env('APP_URL') . '/api/payment/inicis/close',
            ]);

            // 결제 정보 DB에 저장 (선택)
            // PaymentTransaction::create([...]);

            Log::info('INICIS 결제 요청', [
                'order_number' => $orderNumber,
                'amount' => $amount,
                'buyer' => $request->buyer_name
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $paymentUrl,
                'mid' => $mid,
                'oid' => $oid,
                'timestamp' => $timestamp,
                'message' => '결제 요청이 생성되었습니다.'
            ]);

        } catch (\Exception $e) {
            Log::error('INICIS 결제 요청 실패', [
                'error' => $e->getMessage(),
                'order_number' => $request->order_number
            ]);

            return response()->json([
                'success' => false,
                'message' => '결제 요청 처리 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * INICIS 결제 검증
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tid' => 'required|string',
            'order_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '검증 정보가 올바르지 않습니다.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tid = $request->tid;
            $orderNumber = $request->order_number;

            // INICIS API로 결제 상태 확인
            $verificationResult = $this->verifyWithInicis($tid, $orderNumber);

            if ($verificationResult['result_code'] === '00') {
                // 결제 성공
                // DB 업데이트 등 처리

                Log::info('INICIS 결제 검증 성공', [
                    'tid' => $tid,
                    'order_number' => $orderNumber,
                    'amount' => $verificationResult['amount']
                ]);

                return response()->json([
                    'success' => true,
                    'result_code' => '00',
                    'result_msg' => '정상 처리',
                    'tid' => $tid,
                    'amount' => $verificationResult['amount'],
                    'payment_method' => $verificationResult['payment_method'] ?? 'Card',
                    'card_name' => $verificationResult['card_name'] ?? '',
                    'message' => '결제가 정상적으로 처리되었습니다.'
                ]);
            } else {
                // 결제 실패
                Log::warning('INICIS 결제 검증 실패', [
                    'tid' => $tid,
                    'order_number' => $orderNumber,
                    'result_code' => $verificationResult['result_code']
                ]);

                return response()->json([
                    'success' => false,
                    'result_code' => $verificationResult['result_code'],
                    'result_msg' => $verificationResult['result_msg'],
                    'message' => '결제 검증에 실패했습니다.'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('INICIS 결제 검증 오류', [
                'error' => $e->getMessage(),
                'tid' => $request->tid
            ]);

            return response()->json([
                'success' => false,
                'message' => '결제 검증 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * INICIS 결제 취소
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tid' => 'required|string',
            'order_number' => 'required|string',
            'cancel_amount' => 'required|numeric|min:100',
            'cancel_reason' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '취소 정보가 올바르지 않습니다.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tid = $request->tid;
            $orderNumber = $request->order_number;
            $cancelAmount = $request->cancel_amount;
            $cancelReason = $request->cancel_reason;

            // INICIS 설정
            $mid = env('INICIS_MID', '');
            $signKey = env('INICIS_SIGNKEY', '');

            if (empty($mid) || empty($signKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'INICIS 설정이 올바르지 않습니다.'
                ], 500);
            }

            // INICIS API로 취소 요청
            $cancelResult = $this->cancelWithInicis([
                'mid' => $mid,
                'tid' => $tid,
                'cancelAmount' => $cancelAmount,
                'cancelReason' => $cancelReason,
                'signKey' => $signKey
            ]);

            if ($cancelResult['result_code'] === '00') {
                // 취소 성공
                Log::info('INICIS 결제 취소 성공', [
                    'tid' => $tid,
                    'order_number' => $orderNumber,
                    'cancel_amount' => $cancelAmount
                ]);

                return response()->json([
                    'success' => true,
                    'result_code' => '00',
                    'result_msg' => '취소 완료',
                    'cancel_tid' => $cancelResult['cancel_tid'],
                    'message' => '결제가 정상적으로 취소되었습니다.'
                ]);
            } else {
                // 취소 실패
                Log::warning('INICIS 결제 취소 실패', [
                    'tid' => $tid,
                    'result_code' => $cancelResult['result_code']
                ]);

                return response()->json([
                    'success' => false,
                    'result_code' => $cancelResult['result_code'],
                    'result_msg' => $cancelResult['result_msg'],
                    'message' => '결제 취소에 실패했습니다.'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('INICIS 결제 취소 오류', [
                'error' => $e->getMessage(),
                'tid' => $request->tid
            ]);

            return response()->json([
                'success' => false,
                'message' => '결제 취소 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * INICIS 결제 URL 생성
     *
     * @param array $params
     * @return string
     */
    private function generateInicisPaymentUrl($params)
    {
        // 실제 환경에서는 INICIS 모바일 결제 페이지 URL 사용
        $baseUrl = env('INICIS_PAYMENT_URL', 'https://mobile.inicis.com/smart/payment/');

        $queryParams = http_build_query([
            'P_MID' => $params['mid'],
            'P_OID' => $params['oid'],
            'P_AMT' => $params['price'],
            'P_GOODS' => $params['goodname'],
            'P_UNAME' => $params['buyername'],
            'P_EMAIL' => $params['buyeremail'],
            'P_MOBILE' => $params['buyertel'],
            'P_TIMESTAMP' => $params['timestamp'],
            'P_HASH' => $params['hashdata'],
            'P_RETURN_URL' => $params['returnUrl'],
            'P_NOTI_URL' => $params['closeUrl'],
        ]);

        return $baseUrl . '?' . $queryParams;
    }

    /**
     * INICIS API로 결제 검증
     *
     * @param string $tid
     * @param string $orderNumber
     * @return array
     */
    private function verifyWithInicis($tid, $orderNumber)
    {
        // 실제 구현: INICIS API 호출
        // 여기서는 예시 응답 반환

        // TODO: 실제 INICIS API 연동 필요

        return [
            'result_code' => '00',
            'result_msg' => '정상 처리',
            'tid' => $tid,
            'amount' => '50000',
            'payment_method' => 'Card',
            'card_name' => '신한카드'
        ];
    }

    /**
     * INICIS API로 결제 취소
     *
     * @param array $params
     * @return array
     */
    private function cancelWithInicis($params)
    {
        // 실제 구현: INICIS 취소 API 호출
        // 여기서는 예시 응답 반환

        // TODO: 실제 INICIS 취소 API 연동 필요

        return [
            'result_code' => '00',
            'result_msg' => '취소 완료',
            'cancel_tid' => 'CANCEL_' . $params['tid']
        ];
    }

    /**
     * INICIS 결제 완료 콜백 (Return URL)
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function paymentReturn(Request $request)
    {
        $resultCode = $request->input('P_STATUS');
        $tid = $request->input('P_TID');
        $orderNumber = $request->input('P_OID');

        if ($resultCode === '00') {
            // 결제 성공
            $redirectUrl = env('FLUTTER_APP_URL') . '/payment/success?tid=' . $tid;
        } else {
            // 결제 실패
            $redirectUrl = env('FLUTTER_APP_URL') . '/payment/fail?code=' . $resultCode;
        }

        return redirect($redirectUrl);
    }

    /**
     * INICIS 결제 창 닫기 콜백 (Close URL)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentClose(Request $request)
    {
        Log::info('INICIS 결제 창 닫기', $request->all());

        return response()->json([
            'success' => true,
            'message' => '결제 창이 닫혔습니다.'
        ]);
    }
}
