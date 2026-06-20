<?php

return [

    /*
     *
     * Shared translations.
     *
     */
    'title' => '라라벨 설치 프로그램',
    'next' => '다음 단계',
    'back' => '이전',
    'finish' => '설치',
    'forms' => [
        'errorTitle' => '다음 오류가 발생했습니다:',
    ],

    /*
     *
     * Home page translations.
     *
     */
    'welcome' => [
        'templateTitle' => '환영합니다',
        'title'   => '라라벨 설치 프로그램',
        'message' => '간편한 설치 및 설정 마법사입니다.',
        'next'    => '요구사항 확인',
    ],

    /*
     *
     * Requirements page translations.
     *
     */
    'requirements' => [
        'templateTitle' => '1단계 | 서버 요구사항',
        'title' => '서버 요구사항',
        'next'    => '권한 확인',
    ],

    /*
     *
     * Permissions page translations.
     *
     */
    'permissions' => [
        'templateTitle' => '2단계 | 권한',
        'title' => '권한',
        'next' => '환경 설정',
    ],

    /*
     *
     * Environment page translations.
     *
     */
    'environment' => [
        'menu' => [
            'templateTitle' => '3단계 | 환경 설정',
            'title' => '환경 설정',
            'desc' => '앱의 <code>.env</code> 파일을 어떻게 구성할지 선택해주세요.',
            'wizard-button' => '폼 마법사 설정',
            'classic-button' => '클래식 텍스트 편집기',
        ],
        'wizard' => [
            'templateTitle' => '3단계 | 환경 설정 | 가이드 마법사',
            'title' => '가이드 <code>.env</code> 마법사',
            'tabs' => [
                'environment' => '환경',
                'database' => '데이터베이스',
                'application' => '애플리케이션',
            ],
            'form' => [
                'name_required' => '환경 이름이 필요합니다.',
                'app_name_label' => '앱 이름',
                'app_name_placeholder' => '앱 이름',
                'app_environment_label' => '앱 환경',
                'app_environment_label_local' => '로컬',
                'app_environment_label_developement' => '개발',
                'app_environment_label_qa' => 'QA',
                'app_environment_label_production' => '프로덕션',
                'app_environment_label_other' => '기타',
                'app_environment_placeholder_other' => '환경을 입력하세요...',
                'app_debug_label' => '앱 디버그',
                'app_debug_label_true' => '참',
                'app_debug_label_false' => '거짓',
                'app_log_level_label' => '앱 로그 레벨',
                'app_log_level_label_debug' => '디버그',
                'app_log_level_label_info' => '정보',
                'app_log_level_label_notice' => '알림',
                'app_log_level_label_warning' => '경고',
                'app_log_level_label_error' => '에러',
                'app_log_level_label_critical' => '심각',
                'app_log_level_label_alert' => '경보',
                'app_log_level_label_emergency' => '긴급',
                'app_url_label' => '앱 URL',
                'app_url_placeholder' => '앱 URL',
                'db_connection_failed' => '데이터베이스에 연결할 수 없습니다.',
                'db_connection_label' => '데이터베이스 연결',
                'db_connection_label_mysql' => 'mysql',
                'db_connection_label_sqlite' => 'sqlite',
                'db_connection_label_pgsql' => 'pgsql',
                'db_connection_label_sqlsrv' => 'sqlsrv',
                'db_host_label' => '데이터베이스 호스트',
                'db_host_placeholder' => '데이터베이스 호스트',
                'db_port_label' => '데이터베이스 포트',
                'db_port_placeholder' => '데이터베이스 포트',
                'db_name_label' => '데이터베이스 이름',
                'db_name_placeholder' => '데이터베이스 이름',
                'db_username_label' => '데이터베이스 사용자 이름',
                'db_username_placeholder' => '데이터베이스 사용자 이름',
                'db_password_label' => '데이터베이스 비밀번호',
                'db_password_placeholder' => '데이터베이스 비밀번호',

                'app_tabs' => [
                    'more_info' => '추가 정보',
                    'broadcasting_title' => '브로드캐스팅, 캐싱, 세션 및 큐',
                    'broadcasting_label' => '브로드캐스트 드라이버',
                    'broadcasting_placeholder' => '브로드캐스트 드라이버',
                    'cache_label' => '캐시 드라이버',
                    'cache_placeholder' => '캐시 드라이버',
                    'session_label' => '세션 드라이버',
                    'session_placeholder' => '세션 드라이버',
                    'queue_label' => '큐 드라이버',
                    'queue_placeholder' => '큐 드라이버',
                    'redis_label' => 'Redis 드라이버',
                    'redis_host' => 'Redis 호스트',
                    'redis_password' => 'Redis 비밀번호',
                    'redis_port' => 'Redis 포트',

                    'mail_label' => '메일',
                    'mail_driver_label' => '메일 드라이버',
                    'mail_driver_placeholder' => '메일 드라이버',
                    'mail_host_label' => '메일 호스트',
                    'mail_host_placeholder' => '메일 호스트',
                    'mail_port_label' => '메일 포트',
                    'mail_port_placeholder' => '메일 포트',
                    'mail_username_label' => '메일 사용자명',
                    'mail_username_placeholder' => '메일 사용자명',
                    'mail_password_label' => '메일 비밀번호',
                    'mail_password_placeholder' => '메일 비밀번호',
                    'mail_encryption_label' => '메일 암호화',
                    'mail_encryption_placeholder' => '메일 암호화',

                    'pusher_label' => 'Pusher',
                    'pusher_app_id_label' => 'Pusher 앱 ID',
                    'pusher_app_id_palceholder' => 'Pusher 앱 ID',
                    'pusher_app_key_label' => 'Pusher 앱 키',
                    'pusher_app_key_palceholder' => 'Pusher 앱 키',
                    'pusher_app_secret_label' => 'Pusher 앱 시크릿',
                    'pusher_app_secret_palceholder' => 'Pusher 앱 시크릿',
                ],
                'buttons' => [
                    'setup_database' => '데이터베이스 설정',
                    'setup_application' => '애플리케이션 설정',
                    'install' => '설치',
                ],
            ],
        ],
        'classic' => [
            'templateTitle' => '3단계 | 환경 설정 | 클래식 편집기',
            'title' => '클래식 환경 편집기',
            'save' => '.env 저장',
            'back' => '폼 마법사 사용',
            'install' => '저장 후 설치',
        ],
        'success' => '.env 파일 설정이 저장되었습니다.',
        'errors' => '.env 파일을 저장할 수 없습니다. 수동으로 생성해주세요.',
    ],

    'install' => '설치',

    /*
     *
     * Installed Log translations.
     *
     */
    'installed' => [
        'success_log_message' => '라라벨 설치 프로그램이 성공적으로 설치되었습니다: ',
    ],

    /*
     *
     * Final page translations.
     *
     */
    'final' => [
        'title' => '설치 완료',
        'templateTitle' => '설치 완료',
        'finished' => '애플리케이션이 성공적으로 설치되었습니다.',
        'migration' => '마이그레이션 및 시드 콘솔 출력:',
        'console' => '애플리케이션 콘솔 출력:',
        'log' => '설치 로그 항목:',
        'env' => '최종 .env 파일:',
        'exit' => '종료하려면 여기를 클릭하세요',
        'user_website'=>'사용자 웹사이트',
        'admin_panel' =>'관리자 패널'

    ],

    /*
     *
     * Update specific translations
     *
     */
    'updater' => [
        /*
         *
         * Shared translations.
         *
         */
        'title' => '라라벨 업데이터',

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'welcome' => [
            'title'   => '업데이터에 오신 것을 환영합니다',
            'message' => '업데이트 마법사에 오신 것을 환영합니다.',
        ],

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'overview' => [
            'title'   => '개요',
            'message' => '업데이트가 1개 있습니다.|업데이트가 :number개 있습니다.',
            'install_updates' => '업데이트 설치',
        ],

        /*
         *
         * Final page translations.
         *
         */
        'final' => [
            'title' => '완료',
            'finished' => '애플리케이션의 데이터베이스가 성공적으로 업데이트되었습니다.',
            'exit' => '종료하려면 여기를 클릭하세요',
        ],

        'log' => [
            'success_message' => '라라벨 설치 프로그램이 성공적으로 업데이트되었습니다: ',
        ],
    ],
];
