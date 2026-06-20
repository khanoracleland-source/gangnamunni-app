<?php
/**
 * PDO DB 연결 클래스
 * API 엔드포인트에서 사용
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // dbconfig.php 로드
        $config_path = dirname(__DIR__, 2) . '/dbconfig.php';
        if (!file_exists($config_path)) {
            $config_path = dirname(__DIR__) . '/dbconfig.php';
        }

        if (file_exists($config_path)) {
            require_once $config_path;
        }

        $host = defined('G5_MYSQL_HOST') ? G5_MYSQL_HOST : 'localhost';
        $port = defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : '3306';
        $user = defined('G5_MYSQL_USER') ? G5_MYSQL_USER : 'root';
        $pass = defined('G5_MYSQL_PASSWORD') ? G5_MYSQL_PASSWORD : '';
        $db   = defined('G5_MYSQL_DB') ? G5_MYSQL_DB : 'gangnamunni';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    /**
     * SELECT 쿼리 실행 후 전체 결과 반환
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * SELECT 쿼리 실행 후 단일 행 반환
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * SELECT 쿼리 실행 후 단일 값 반환
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * INSERT/UPDATE/DELETE 실행
     */
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * 마지막 INSERT ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
