<?php
/**
 * APJPDOConnection is a singleton implementation for returning a PDO instance<br>
 * APJPDOConnection es una implementación singleton para devolver una instancia de PDO
 * Usage: $db = APJPDOConnection::instance('dsn', 'username', 'password');
 * If you assign different arguments, it will return a new connection.
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;

class APJPDOConnection
{
    private static $_instance = null;
    private static $_dsn = null;
    private static $_settings = [];

    public function __construct() {}

    private function __destruct(){}

    public function __clone()
    {
        return false;
    }

    public function __wakeup()
    {
        return false;
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
            $conn = new PDO($dsn, $settings["user"], $settings["password"], [PDO::ATTR_PERSISTENT => PERSISTENT_CONNECTION]);
            if (isset($settings['charset'])) {
                $conn->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES ' . $settings['charset']);
            }
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, EMULATE_PREPARES);
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
