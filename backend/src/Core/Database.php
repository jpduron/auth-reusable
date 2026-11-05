<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use App\Utils\Env;

class Database 
{
    private static ?Database $instance = null;
    private PDO $conn;

    // 1. Make constructor private so no one can do `new Database()`
    private function __construct() {
        $host    = Env::get('DB_HOST', 'localhost');
        $port    = Env::getInt('DB_PORT', 3306);
        $dbname  = Env::get('DB_NAME');
        $user    = Env::get('DB_USER');
        $pass    = Env::get('DB_PASS', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if (!$dbname || !$user) {
            throw new \RuntimeException('Variables DB_NAME y DB_USER son obligatorias en .env');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Crucial for security
        ];

        try {
            $this->conn = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión a BD: " . $e->getMessage());
            throw new \Exception("Error de conexión a la base de datos");
        }
    }

    // 2. Prevent cloning of the instance
    private function __clone() {}

    // 3. Prevent unserializing of the instance
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    // 4. The single point of access
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }
}