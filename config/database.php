<?php
/**
 * NOXARA - Database Configuration
 * PHP 8.2 + MySQLi
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'noxara_db');
define('DB_USER', 'noxara_user');
define('DB_PASS', 'YOUR_DB_PASSWORD_HERE');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $this->connection->set_charset(DB_CHARSET);
            $this->connection->query("SET time_zone = '+07:00'");
        } catch (mysqli_sql_exception $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli {
        // Reconnect if connection lost
        if (!$this->connection->ping()) {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $this->connection->set_charset(DB_CHARSET);
        }
        return $this->connection;
    }

    public function query(string $sql): mysqli_result|bool {
        return $this->connection->query($sql);
    }

    public function prepare(string $sql): mysqli_stmt|false {
        return $this->connection->prepare($sql);
    }

    public function escape(string $value): string {
        return $this->connection->real_escape_string($value);
    }

    public function lastInsertId(): int {
        return (int)$this->connection->insert_id;
    }

    public function affectedRows(): int {
        return $this->connection->affected_rows;
    }

    public function beginTransaction(): void {
        $this->connection->begin_transaction();
    }

    public function commit(): void {
        $this->connection->commit();
    }

    public function rollback(): void {
        $this->connection->rollback();
    }

    // Prevent clone & unserialize
    private function __clone() {}
    public function __wakeup() {}
}

// Helper shortcut
function db(): mysqli {
    return Database::getInstance()->getConnection();
}
