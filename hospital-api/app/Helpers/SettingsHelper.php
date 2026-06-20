<?php

namespace App\Helpers;

/**
 * Settings Helper Class
 *
 * 사용자 설정 읽기 및 Kivicare 사용 여부 확인
 */
class SettingsHelper
{
    private static $db = null;

    /**
     * 데이터베이스 연결 초기화
     */
    private static function getDB()
    {
        if (self::$db === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $database = getenv('DB_DATABASE') ?: 'kivicare_laravel';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '<REMOVED_DB_PASSWORD>';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            try {
                self::$db = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } catch (\PDOException $e) {
                throw new \Exception("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$db;
    }

    /**
     * Kivicare 사용 여부 확인
     *
     * @param int|null $userId 사용자 ID (null이면 기본 설정 사용)
     * @return bool
     */
    public static function useKivicare($userId = null)
    {
        try {
            $db = self::getDB();

            if ($userId) {
                // 사용자별 설정 확인
                $stmt = $db->prepare("
                    SELECT use_kivicare
                    FROM user_settings
                    WHERE user_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();

                if ($result) {
                    return (bool) $result['use_kivicare'];
                }
            }

            // 기본값: false (새 시스템 사용)
            return false;

        } catch (\Exception $e) {
            // 에러 발생 시 기본값 사용
            error_log("SettingsHelper Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Request에서 사용자 ID 추출 및 Kivicare 사용 여부 확인
     *
     * @param array $request Request 데이터
     * @return bool
     */
    public static function useKivicareFromRequest($request)
    {
        $userId = null;

        // Authorization 헤더에서 토큰 추출
        if (isset($request['headers']['Authorization'])) {
            $token = str_replace('Bearer ', '', $request['headers']['Authorization']);
            $userId = self::getUserIdFromToken($token);
        }

        // Request body에서 user_id 추출
        if (!$userId && isset($request['user_id'])) {
            $userId = $request['user_id'];
        }

        return self::useKivicare($userId);
    }

    /**
     * 토큰에서 사용자 ID 추출
     *
     * @param string $token
     * @return int|null
     */
    private static function getUserIdFromToken($token)
    {
        try {
            $db = self::getDB();

            $stmt = $db->prepare("
                SELECT user_id
                FROM user_sessions
                WHERE token = ?
                AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            return $result ? (int) $result['user_id'] : null;

        } catch (\Exception $e) {
            error_log("Token lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 사용자 설정 전체 조회
     *
     * @param int $userId
     * @return array
     */
    public static function getUserSettings($userId)
    {
        try {
            $db = self::getDB();

            $stmt = $db->prepare("
                SELECT *
                FROM user_settings
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();

            if ($result) {
                return [
                    'user_id' => (int) $result['user_id'],
                    'use_kivicare' => (bool) $result['use_kivicare'],
                    'loading_indicator_type' => $result['loading_indicator_type'] ?? 'busy',
                    'theme' => $result['theme'] ?? 'light',
                    'language' => $result['language'] ?? 'ko',
                    'notifications_enabled' => (bool) ($result['notifications_enabled'] ?? true),
                    'created_at' => $result['created_at'] ?? null,
                    'updated_at' => $result['updated_at'] ?? null,
                ];
            }

            // 기본 설정 반환
            return self::getDefaultSettings($userId);

        } catch (\Exception $e) {
            error_log("Get user settings error: " . $e->getMessage());
            return self::getDefaultSettings($userId);
        }
    }

    /**
     * 사용자 설정 저장
     *
     * @param int $userId
     * @param array $settings
     * @return bool
     */
    public static function saveUserSettings($userId, $settings)
    {
        try {
            $db = self::getDB();

            // 기존 설정 확인
            $stmt = $db->prepare("SELECT id FROM user_settings WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $exists = $stmt->fetch();

            if ($exists) {
                // 업데이트
                $stmt = $db->prepare("
                    UPDATE user_settings
                    SET
                        use_kivicare = ?,
                        loading_indicator_type = ?,
                        theme = ?,
                        language = ?,
                        notifications_enabled = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    $settings['use_kivicare'] ?? false,
                    $settings['loading_indicator_type'] ?? 'busy',
                    $settings['theme'] ?? 'light',
                    $settings['language'] ?? 'ko',
                    $settings['notifications_enabled'] ?? true,
                    $userId,
                ]);
            } else {
                // 삽입
                $stmt = $db->prepare("
                    INSERT INTO user_settings
                    (user_id, use_kivicare, loading_indicator_type, theme, language, notifications_enabled, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $stmt->execute([
                    $userId,
                    $settings['use_kivicare'] ?? false,
                    $settings['loading_indicator_type'] ?? 'busy',
                    $settings['theme'] ?? 'light',
                    $settings['language'] ?? 'ko',
                    $settings['notifications_enabled'] ?? true,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Save user settings error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 기본 설정 반환
     *
     * @param int $userId
     * @return array
     */
    private static function getDefaultSettings($userId)
    {
        return [
            'user_id' => $userId,
            'use_kivicare' => false,
            'loading_indicator_type' => 'busy',
            'theme' => 'light',
            'language' => 'ko',
            'notifications_enabled' => true,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * Kivicare API Base URL 가져오기
     *
     * @return string
     */
    public static function getKivicareApiUrl()
    {
        return getenv('KIVICARE_API_URL') ?: 'http://localhost:8000/api';
    }

    /**
     * Kivicare 사용 가능 여부 확인 (서버 연결 테스트)
     *
     * @return bool
     */
    public static function isKivicareAvailable()
    {
        try {
            $url = self::getKivicareApiUrl() . '/health';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 400;

        } catch (\Exception $e) {
            return false;
        }
    }
}
