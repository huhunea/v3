<?php
/**
 * Cấu hình Database
 * Tạo bởi: MiniMax Agent
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'khoahoc_share');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;

    public function connect() {
        if ($this->pdo == null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch(PDOException $e) {
                echo "Connection Error: " . $e->getMessage();
            }
        }
        return $this->pdo;
    }
}
?>
