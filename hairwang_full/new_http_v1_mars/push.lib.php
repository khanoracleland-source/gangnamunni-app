<?php
/***********************************************************************************
+ Page Name		: /lib/push.lib.php
+ Description	: APP PUSH 라이브러리
***********************************************************************************/
if( !defined('_DCSolution_') )	EXIT;

function reload(){
	echo "<a href='javascript:location.reload()'>refresh</a>";
}


// 푸시 발송 paik http v1 방식으로 변경 2023_09_10
// jwt 사용
function send_push_v1_jwt($app, $user_token, $title, $memo, $url2)
{
    global $dc, $Dcs, $member;

    $arg1 = '$arg1';
    $arg2 = '$arg2';
    $arg3 = '$arg3';

    // $url2 값 확인 및 기본 값 지정
	if ($url2 === null || $url2 == "" || $url2 == "http://" || $url2 == "https://") {
		$url2 = "https://edumars.net";
	}


    // FCM 서버 URL
    $url = 'https://fcm.googleapis.com/v1/projects/mars-38372/messages:send';

    // 서비스 계정 키 파일의 경로
    $serviceAccountPath = $_SERVER['DOCUMENT_ROOT'].'/_process/vendor/mars-38372-firebase-adminsdk-60l5a-23f211e854.json';

    // 디버깅: 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        die("Service account file not found: $serviceAccountPath");
    }

    // 액세스 토큰 생성
    $accessToken = getAccessToken($serviceAccountPath);

    // 안드로이드 푸시 발송
    if ($app == 'android') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'data' => [
                    'title' => $title,
                    'message' => $memo,
                    'arg1' => $arg1,
                    'arg2' => '1',
                    // 팝업 방식을 원하면 1 아니면 0
                    'arg3' => 'MARS',
                    // 보낸 사람
                    'url' => $url2,
                ]
            ]
        ];
    }
    // 애플 푸시 발송
    else if ($app == 'ios') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'notification' => [
                    'title' => $title,
                    'body' => $memo,
                    // 'sound' 필드는 iOS의 경우 FCM에서 따로 설정할 필요 없음
                ],
                'data' => [
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => 'MARS',
                    'url' => $url2,
                    // 'sound' => 'default' // 필요한 경우, 여기 추가 가능
                ],
                'apns' => [
                    // APNs 설정을 통해 iOS에서 사운드를 관리
                    'payload' => [
                        'aps' => [
                            'sound' => 'default' // iOS 푸시 사운드를 여기서 설정
                        ]
                    ]
                ]
            ]
        ];
    }


    // HTTP 요청 헤더 설정
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushdata));

    // FCM 서버로 푸시 요청 전송
    $response = curl_exec($ch);

    // cURL 에러 처리
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        echo "<script>alert('cURL Error: $error_msg');</script>";
    }

    curl_close($ch);

    // 푸시 전송 결과 반환
    $obj = json_decode($response);

    if (!$obj || !isset($obj->{"name"})) {
        echo "<script>alert('FCM Response Error: " . htmlspecialchars($response) . "');</script>";
        return 0;
    }

    //return 1;

    // 푸시 전송 결과 반환 : 성공 수량 반환
    //$obj = json_decode($response);
    $cnt = $obj->{"success"};

    return $cnt;
}



// 액세스 토큰 생성 함수 (Google 서비스 계정 JSON 파일 사용)
function getAccessToken($serviceAccountPath)
{
    // 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        echo "<script>alert('Service account file not found at: $serviceAccountPath');</script>";
        return '';
    }

    // JSON 파일 읽기 및 파싱
    $jsonContent = file_get_contents($serviceAccountPath);
    if ($jsonContent === false) {
        echo "<script>alert('Failed to read service account JSON file.');</script>";
        return '';
    }



    $jwt = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<script>alert('Failed to decode JSON: " . json_last_error_msg() . "');</script>";
        return '';
    }


    // JWT 구성
    $now = time();
    $token = [
        'iss' => $jwt['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $jwt['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now
    ];

    // 서명 생성 준비
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

    //echo $jwt['private_key'];

    $payload = base64_encode(json_encode($token));
    $signature = '';

    $privateKey = $jwt['private_key']; //?? '';

    //echo $privateKey;

    // 서명 생성 시도
    //if (!openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
    //    echo "<script>alert('Failed to create JWT signature with openssl_sign.');</script>";
    //    return '';
    //}


    // 서명 생성 시도
    if (!openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        $opensslError = openssl_error_string();
        echo "<script>alert('Failed to create JWT signature with openssl_sign. Error: $opensslError');</script>";
        return '';
    }


    $jwtToken = "$header.$payload." . base64_encode($signature);

    // 액세스 토큰 요청
    $ch = curl_init($jwt['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwtToken
    ]));

    $response = curl_exec($ch);

    // cURL 에러 처리
    if (curl_errno($ch)) {
        echo "<script>alert('cURL Error during Access Token Request: " . curl_error($ch) . "');</script>";
        curl_close($ch);
        return '';
    }

    curl_close($ch);
    $data = json_decode($response, true);

    // 액세스 토큰 확인
    if (!isset($data['access_token'])) {
        echo "<script>alert('Error retrieving access token: " . htmlspecialchars($response) . "');</script>";
        return '';
    }

    //echo $data['access_token'];

    return $data['access_token'];


}



// 푸시 발송 paik http v1 방식으로 변경 2023_09_10
function send_push_v1($app, $user_token, $title, $memo, $url)
{
    global $dc, $Dcs, $member;

    $arg1 = '$arg1';
    $arg2 = '$arg2';
    $arg3 = '$arg3';


    // FCM 서버 URL
    $url = 'https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send';

    // 서비스 계정 키 파일의 경로
    $serviceAccountPath = '/vendor/mars-38372-firebase-adminsdk-60l5a-23f211e854.json';

    // 서비스 계정 키 파일을 읽어서 인증 토큰 생성
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $serviceAccountPath);


    // Google_Client 클래스 불러오기
    require_once __DIR__ . '/vendor/autoload.php';

    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

    $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

    // 안드로이드 푸시 발송
    if ($app == 'android') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'data' => [
                    'title' => $title,
                    'message' => $memo,
                    'arg1' => $arg1,
                    'arg2' => '1',
                    // 팝업 방식을 원하면 1 아니면 0
                    'arg3' => 'MARS',
                    // 보낸 사람
                    'url' => $url,
                ]
            ]
        ];
    }
    // 애플 푸시 발송
    else if ($app == 'ios') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'notification' => [
                    'title' => $title,
                    'body' => $memo,
                    'sound' => 'default',
                ],
                'data' => [
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => $arg3,
                    'url' => $url,
                ]
            ]
        ];
    }

    // HTTP 요청 헤더 설정
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushdata));

    // FCM 서버로 푸시 요청 전송
    $response = curl_exec($ch);
    curl_close($ch);

    // 푸시 전송 결과 반환
    $obj = json_decode($response);
    $cnt = $obj->{"name"} ? 1 : 0; // 성공 여부를 확인하여 결과 반환
    return $cnt;
}

// 푸시발송 paik 이전 방식
function send_push($app,$user_token,$title,$memo,$url){
    global $dc, $Dcs, $member;

    // 안드로이드 푸시발송 ------------------------>
    if( $app == 'android' ){
        $headers = array('Content-Type:application/json','Authorization:key=AAAA8IS5g4s:APA91bHaEpokOJkrzti_OrGuCCiGDS906xayRGoc9xvMORDImRjcDd3WIDuqfmrh39NBC8cDukOqSRUd2PY1POyN1XtcuFs-l0Wd5ZIxI1D3IYJfJyKikyN7hUxKFxiUM6QlTeS7dScN');

        $pushdata	= array();
        $pushdata['data']	= array();
        $pushdata['data']['title']		= $title;
        $pushdata['data']['message']	= $memo;
        $pushdata['data']['arg1']		= $arg1;
        $pushdata['data']['arg2']		= 1; //팝업 방식을 원하면 1 아니면 0
        $pushdata['data']['arg3']		= 'MARS'; //보낸사람
        $pushdata['data']['url']		= $url;
        $pushdata['registration_ids'][0]	= $user_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLPROTO_HTTPS, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt ($ch, CURLOPT_SSLVERSION,0); // SSL 버젼 (https 접속시에 필요)
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($pushdata));
        $response = curl_exec($ch);
        curl_close($ch);

        // 푸시 전송 결과 반환 : 성공 수량 반환
        $obj = json_decode($response);
        $cnt = $obj->{"success"};
        $failure = $obj->{"failure"};

        return $cnt;
    }
    // 애플 푸시발송 ------------------------>
    else if( $app == 'ios' ){
        $headers = array('Content-Type:application/json','Authorization:key=AAAA8IS5g4s:APA91bHaEpokOJkrzti_OrGuCCiGDS906xayRGoc9xvMORDImRjcDd3WIDuqfmrh39NBC8cDukOqSRUd2PY1POyN1XtcuFs-l0Wd5ZIxI1D3IYJfJyKikyN7hUxKFxiUM6QlTeS7dScN');

        $pushdata	= array();
        $pushdata['notification']	= array();
        $pushdata['notification']['title']	= $title;
        $pushdata['notification']['body']	= $memo;
        $pushdata['notification']['sound']	= "default";

        $pushdata['data'] = array();
        if( !empty($file_name) )	$pushdata['data']['attachment'] = $file_name; //서버에 파일이 꼭 존재해야 푸시메시지에 사진이 도착한다.
        if( !empty($file_name) )	$pushdata['data']['media_type'] ='image';
        $pushdata['data']['arg1']	= $arg1;
        $pushdata['data']['arg2']	= $arg2;
        $pushdata['data']['arg3']	= $arg3; //개인간 푸시 메시지
        $pushdata['data']['url']	= $url;

        $pushdata['registration_ids'][0] = $user_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($pushdata));

        if( !empty($memo) )	$response = curl_exec($ch);
        curl_close($ch);

        // 푸시 전송 결과 반환 : 성공 수량 반환
        $obj = json_decode($response);
        $cnt = $obj->{"success"};

        return $cnt;
    }
}

// 로그인 유저의 앱토큰을 갱신
function user_app_token_update($os,$app_id,$app_token,$mb_id){
	global $DB, $dc, $Dcs, $member;

	$appIns[app_id]		= $app_id;
	$appIns[app_token]	= $app_token;
	$appIns[app_os]		= $os;
	$DB->SetUpdate($dc['member_table'],$appIns,"mb_id = '{$mb_id}'");
	unset($appIns);
}

/**************************************************************
+ PUSH CLASS
**************************************************************/
class pushClass{
	public $Push;

	/**************************************************************
	+ 푸시발송내역 : 리스트 반환
	**************************************************************/
	function push_submit_list($pn,$mx,$Sch){
		global $DB, $dc, $Dcs, $member;

		$where = '';

		if( $Sch[pu_ex] ){
			$where .= " AND a.pu_ex = '{$Sch[pu_ex]}'";
		}

		// 키워드검색
		if( $Sch[stx] ){
			$where .= "
				AND (
					a.pu_title LIKE '%{$Sch[stx]}%'
					OR a.pu_content LIKE '%{$Sch[stx]}%'
					OR b.mb_name LIKE '%{$Sch[stx]}%'
				)
			";
		}

		// TotalCnt ------------------->
		$query = "
			SELECT COUNT(*) Cnt
			FROM {$dc['push_table']} a
			INNER JOIN {$dc['member_table']} b ON a.pu_mb_no = b.mb_no
			WHERE a.pu_no <> ''
			{$where}
			ORDER BY a.pu_no DESC
		";
		$row = $DB->OneRow($query);
		$Arr[Cnt] = $row[Cnt];

		// list ----------------------->
		$query = "
			SELECT a.*, b.mb_id, b.mb_name,
				CASE
					WHEN a.pu_ex = 'mat' THEN '멘토매칭'
					ELSE '개별'
				END pu_ex_name
			FROM {$dc['push_table']} a
			INNER JOIN {$dc['member_table']} b ON a.pu_mb_no = b.mb_no
			WHERE a.pu_no <> ''
			{$where}
			ORDER BY a.pu_no DESC
		";
		//=> LIMIT 설정
		$StartNo = ($pn-1)*$mx;
		$Su = $mx;
		$query .= " limit ".$StartNo.",".$Su;
		$Arr[Rows] = $DB->FetchResult($query);

		return $Arr;
	}

	/**************************************************************
	+ 푸시발송내역 : 리스트 반환
	**************************************************************/
	function push_member_list($Sch){
		global $DB, $dc, $Dcs, $member;

		$where = "";
		if( $Sch[mb_level] ){
			$where .= " AND a.mb_level = '{$Sch[mb_level]}'";
		}

		if( $Sch[men_level] ){
			$where .= " AND b.men_level = '{$Sch[men_level]}'";
		}

		if( $Sch[mb_name] ){
			$where .= " AND a.mb_name LIKE '%{$Sch[mb_name]}%'";
		}

		$query = "
			SELECT a.*, b.men_level
			FROM {$dc['member_table']} a
			LEFT OUTER JOIN {$dc[mentor_table]} b ON a.mb_no = b.mb_no
			WHERE a.mb_level < 90 AND a.mb_status = '1' AND a.app_token != '' AND a.app_token is not null
			{$where}
			ORDER BY a.mb_name ASC
		";
		$rows = $DB->FetchResult($query);

		return $rows;
	}
}
?>