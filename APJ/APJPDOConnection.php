<?php
/**
 * APJPDOConnection is a singleton implementation for returning a PDO instance<br>
 * APJPDOConnection es una implementación singleton para devolver una instancia de PDO
 * Usage: $db = APJPDOConnection::instance('dsn', 'username', 'password');
 * If you assign different arguments, it will return a new connection.
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace APJ;
use \PDO;
use \PDOException;
use \Exception;

class APJPDOConnection
{
    private static PDO|null $_instance = null;
    private static string|null $_dsn = null;
    private static array $_settings = [];

    private function __construct() {}

    private function __destruct() {}

    private function __clone(): void {}

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }  

    /**
     * Returns a instance of the database connection<br>
     * Devuelve una instancia de la conexión de la base de datos.
     * @param string $dsn
     * @param array $settings
     * @return PDO
     */
    public static function instance(string $dsn, array $settings): PDO
    {
        if (self::sameConnection($dsn, $settings)) {
            return self::$_instance;
        } else {
            if (empty(self::$_instance)) {
                self::$_instance = self::getConnection($dsn, $settings);
                self::$_dsn = $dsn;
                self::$_settings = $settings;
                return self::$_instance;
            } else {
                return self::getConnection($dsn, $settings);
            }
        }
    }

    private static function getConnection(string $dsn, array $settings): PDO
    {
        try {
            $conn = new PDO($dsn, $settings["user"], $settings["password"], [
                PDO::ATTR_PERSISTENT      => PERSISTENT_CONNECTION,
                PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => EMULATE_PREPARES,
            ]);
            return $conn;
        } catch (PDOException $e) {
            throw new Exception("PDOException: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Exception: " . $e->getMessage());
        }
    }

    private static function sameConnection(string $dsn, array $settings): bool
    {
        return isset(self::$_instance) && self::$_dsn === $dsn && self::$_settings === $settings;
    }
}
