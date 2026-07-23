<?php
/**
 * Controls the user session<br>
 * Controla la sesión de usuarios
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace APJ;

use LogicException;
use PDO;

class APJSession
{
    /**
    * Callback model to validate tokens<br>
    * Modelo o callback para validar tokens
    * @var object
    */
    private static $sessionTokenModel = null;
    /**
    * Model or callback method
    * Método del modelo o callback
    * @var string|null
    */
    private static $sessionTokenMethod = null;
    /**
    * PDO object for direct validation
    * Objeto PDO para validación directa
    * @var mixed
    */
    private static $pdo = null;
    
    /**
     * Verifies that the session is still active<br>
     * Verifica que la sesión está aún activa
     * @param string $name Session name
     * @return bool true if active
     */
    public static function active(string $name): bool
    {
        self::setName($name);
        $limit = defined('SESSION_LIMIT') ? SESSION_LIMIT : 1440;
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $domain = str_contains($serverName, '.') ? $serverName : '';
        $isHttps = isset($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (session_status() != PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => $limit,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            ini_set('session.gc_maxlifetime', $limit);
            ini_set('session.cookie_lifetime', $limit);
            session_start();
        }
        if (!self::same()) {
            return false;
        }
        // Activity-based timeout: expire if idle more than SESSION_LIMIT seconds
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $limit) {
            self::destroy();
            return false;
        }
        // Renew activity timestamp and refresh browser cookie on every request
        $_SESSION['last_activity'] = time();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $limit,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        return true;
    }

    /**
     * Start session<br>
     * Inicia la sesión
     * @param string $name Session name
     * @param int $limit Session time limit (secs)
     * @param string|null $path Path to session data (optional)
     * @param string|null $domain Session domain (optional)
     * @param bool|null $secure Session uses security (optional)
     */
    public static function start(string $name, int $limit = 0, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
    {
        if (session_status() != PHP_SESSION_ACTIVE) {
            if ($limit == 0) {
                $limit = SESSION_LIMIT;
            }
            self::setName($name);
            $serverName = $_SERVER['SERVER_NAME'] ?? '';
            $domain = $domain ?? (str_contains($serverName, '.') ? $serverName : '');
            $isHttps = isset($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $https = $secure ?? $isHttps;
            session_set_cookie_params([
                'lifetime' => $limit,
                'path'     => $path ?? '/',
                'domain'   => $domain,
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            ini_set('session.gc_maxlifetime', $limit);
            ini_set('session.cookie_lifetime', $limit);
            session_start();
        }
        if (self::validate()) {
            if (!self::same()) {
                self::regenerate();
                self::basicData();
            } elseif (rand(1, 100) <= 5) {
                self::regenerate();
            }
        } else {
            self::destroy();
            session_start();
        }
    }

    /**
     * Set hashed session name (md5)<br>
     * Define el nombre de la sesión codificada (md5)
     * @param string $name Starting session name
     */    
    private static function setName(string $name): void
    {
        if (session_status() != PHP_SESSION_ACTIVE) {
            $hashname = md5($name . '_Session');
            session_name($hashname);
        }
    }

    /**
     * Basic session information<br>
     * Información básica de la sesión
     */
    private static function basicData(): void
    {
        $_SESSION['IPaddress'] = md5($_SERVER['REMOTE_ADDR']);
        $_SESSION['userAgent'] = md5($_SERVER['HTTP_USER_AGENT']);
        $_SESSION['last_activity'] = time();
    }

    /**
     * Determines if it is the same IP and machine<br>
     * Determina si es la misma IP y máquina
     * @return bool Same session
     */
    private static function same(): bool
    {
        return isset($_SESSION['IPaddress'], $_SESSION['userAgent']) &&
            $_SESSION['IPaddress'] === md5($_SERVER['REMOTE_ADDR']) &&
            $_SESSION['userAgent'] === md5($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Session validation<br>
     * Validación de sesión
     * @return bool Valid session
     */
    private static function validate(): bool
    {
        return !(isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES'])) &&
            (!isset($_SESSION['EXPIRES']) || $_SESSION['EXPIRES'] >= time());
    }

    /**
     * Regenerates the session<br>
     * Regenera la sesión
     */
    private static function regenerate(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['OBSOLETE'], $_SESSION['EXPIRES']);
    }

    /**
     * Destroys the session and deletes the session cookie<br>
     * Destruye la sesión y elimina la cookie de la sesión
     */
    public static function destroy(): void
    {
        self::destroySession();
        self::deleteSessionCookie();
    }
    
    /**
     * Destroys the session on the server<br>
     * Destruye la sesión en el servidor
     */
    public static function destroySession(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Deletes the session cookie<br>
     * Elimina la cookie de la sesión
     */
    public static function deleteSessionCookie(): void
    {
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
    }
        
    /**
     * Sets a login cookie for a specific system<br>
     * Configura una cookie de inicio de sesión para un sistema específico
     * @param string $systemName Unique name for the system
     * @param string $token Secure token for the user
     * @param int $days Number of days to keep the cookie
     */
    public static function setLoginCookie(string $systemName, string $token, int $days = 30): void
    {
        $cookieName = $systemName . '_login_token';
        setcookie($cookieName, $token, [
            'expires'  => time() + ($days * 86400),
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'],
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Verifies the login cookie for a specific system<br>
     * Verifica la cookie de inicio de sesión para un sistema específico
     * @param string $systemName Unique name for the system
     * @return string|null Returns the token if valid, null otherwise
     */
    public static function verifyLoginCookie(string $systemName): ?string
    {
        $cookieName = $systemName . '_login_token';

        if (!isset($_COOKIE[$cookieName])) {
            return null;
        }

        $token = $_COOKIE[$cookieName];
        $isValid = self::validateToken($token);

        return $isValid ? $token : null;
    }

    /**
     * Clears the login cookie for a specific system<br>
     * Elimina la cookie de inicio de sesión para un sistema específico
     * @param string $systemName Unique name for the system
     */
    public static function deleteRememberMeCookies(string $systemName): void
    {
        $options = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'],
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
        setcookie($systemName . '_login_token', '', $options);
        setcookie($systemName . '_user_id', '', $options);
    }

     /**
     * Set a custom model and method for token validation<br>
     * Define un modelo personalizado y método para la validación de tokens
     *
     * @param object $model Objeto modelo para manejar los tokens
     * @param string $method Nombre del método que se usará para validar
     * @return void
     */
    public static function setSessionTokenModel(object $model, string $method): void
    {
        self::$sessionTokenModel = $model;
        self::$sessionTokenMethod = $method;
    }

    /**
     * Set a PDO instance for direct database access<br>
     * Define una instancia PDO para el acceso directo a la base de datos
     *
     * @param PDO $pdo Instancia de PDO para manejar las consultas
     * @return void
     */
    public static function setSessionPDO(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
        
    /**
     * Validate the token using the provided model or PDO<br>
     * Valida el token usando el modelo o PDO proporcionado
     *
     * @param string $token Token a validar
     * @return bool True si el token es válido, false en caso contrario
     */
    public static function validateToken(string $token): bool
    {
        if (self::$sessionTokenModel && self::$sessionTokenMethod) {
            $model = self::$sessionTokenModel;
            $method = self::$sessionTokenMethod;

            if (method_exists($model, $method)) {
                return $model->$method($token);
            } else {
                throw new LogicException("El método '{$method}' no existe en el modelo proporcionado.");
            }
        }

        if (self::$pdo) {
            $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM session_tokens WHERE token = :token AND expiry > NOW()');
            $stmt->execute([':token' => $token]);
            return $stmt->fetchColumn() > 0;
        }

        throw new LogicException('No se ha configurado un modelo o instancia PDO para la validación de tokens.');
    }
}
