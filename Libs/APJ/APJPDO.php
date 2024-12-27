<?php
/**
 * APJPDO Class for PDO management<br>
 * Clase para la gestión de PDO
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;
use Libs\APJ\APJLog;
use Libs\APJ\APJPDOConnection;

class APJPDO 
{
    private $_Pdo;
    private $_Qry;      
    private $_Settings;
    private $_Connected = false;
    private $_Log;
    private $_Parameters;
    private $_ACTION = ["insert", "replace", "update", "delete", "truncate"];
    private $_SELECTION = ["select", "show", "describe"];
    public $trans = false; 
    public $result = false;
    public $error = false; 
    public $errormsg = null; 
    public $errornum = 0; 
    public $affected = 0;
    public $numrows = 0;
  
    /**
     * Constructor
     * @param string|null $dsn (optional)
     * @param string|null $user (optional)
     * @param string|null $password (optional)
     * @param string|null $charset (optional)
     */
    public function __construct(string $dsn = null, string $user = null, string $password = null, string $charset = null)
    {           
        $this->_Log = new APJLog(); 
        $this->connect($dsn, $user, $password, $charset);
        $this->clearBinding();
    }
  
    public function __destruct() {
        $this->disconnect();
    }
    
    public function connect(string $dsn = null, string $user = null, string $password = null, string $charset = null): void
    {
        if ($dsn === null) {
            $inifile = APJ . DIRECTORY_SEPARATOR . "APJPDO.ini.php";
            if (is_readable($inifile)) {
                $this->_Settings = parse_ini_file($inifile);
                if ($this->_Settings) {
                    $dsn = 'mysql:dbname=' . $this->_Settings["dbname"] . ';host=' . $this->_Settings["host"] . ';charset=' . $this->_Settings['charset'];
                } else {
                    die($this->_errorLog("El archivo de DSN {$inifile} no se pudo leer."));
                }
            } else {
                die($this->_errorLog("El archivo de DSN {$inifile} no está disponible. "));
            }
        } else {
            $this->_Settings["user"] = $user;
            $this->_Settings["password"] = $password;
            $this->_Settings["charset"] = $charset;
        }
        try {
            if (class_exists('PDO')) {
                $this->_Pdo = APJPDOConnection::instance($dsn, $this->_Settings);
                $this->_Connected = true;
            } else {
                die($this->_errorLog("The PDO object is not available."));
            }
        } catch (PDOException $e) {
            die($this->_errorLog($e->getMessage()));
        }
    }
  
    /**
     * Disconnect from database<br>
     * Desconecta de la base de datos
     */
    public function disconnect(): void
    {
        $this->_Qry = null;
        $this->_Pdo = null;
    }
    
    /**
     * Prepares the query and data binding<br>
     * Prepara la consulta y enlace de datos
     * @param string $query Query string
     * @param array $parameters Parameters array
     */
    private function _prepare(string $query, array $parameters = []): void
    {
        if (!$this->_Connected) {
            $this->connect();
        }
        try {
            $this->_Qry = $this->_Pdo->prepare($query);
            $this->bindArray($parameters);
            if (!empty($this->_Parameters)) {
                foreach ($this->_Parameters as $param) {
                    $param['value'] = (strlen($param['value']) == 0) ? null : $param['value'];
                    $this->_Qry->bindParam($param['param'], $param['value']);
                }
            }
            $this->result = $this->_Qry->execute();     
            $this->_setNoError();
        } catch (PDOException $e) {
            $this->_errorLog($e->getMessage(), $query);
            $this->_setError();
        }
        $this->clearBinding();
    }

    /**
     * Binds data to parameters<br>
     * Enlaza datos a los parametros
     * @param string $param Query parameter name
     * @param mixed $value Query parameter value
     * @param string $type Value data type (default 'none')
     * @param bool $trim Data value must be trimmed (default false)
     * @return mixed Binded new value
     */
    public function bind(string $param, $value, string $type = 'none', bool $trim = false)
    {
        if ($type !== 'none') {
            $value = $this->getValue($value, $type, $trim);
        }
        $this->_Parameters[]['param'] = ":" . $param;
        end($this->_Parameters);
        $key = key($this->_Parameters);
        $this->_Parameters[$key]['value'] = $value;
        return $value;
    }

    /**
     * Bind an array of data to parameters of a query<br>
     * Enlaza un array de datos a parámetros de una consulta
     * @param array $paramarray Array of parameters to be binded
     */
    public function bindArray(array $paramarray): void
    {
        if (is_array($paramarray)) {
            $this->clearBinding();
            $columns = array_keys($paramarray);
            foreach ($columns as $column) {
                $this->bind($column, $paramarray[$column]);
            }
        }
    }

    /**
     * Clears data binding<br>
     * Limpia los enlaces de datos
     */
    public function clearBinding(): void
    {
        $this->_Parameters = [];
    }

    /**
     * Returns all rows from query into array<br>
     * Devuelve todas las filas en un array
     * @param string $query Query string
     * @param array|null $params Binding parameters (optional)
     * @param int $fetchmode Fetchmode, can be PDO::FETCH_ASSOC (default), PDO::FETCH_BOTH, PDO::FETCH_CLASS, PDO::FETCH_NUM
     * @return array|false Array or false
     */
    public function rows(string $query, ?array $params = null, int $fetchmode = PDO::FETCH_ASSOC)
    {
        $rows = [];
        $query = trim($query);
        if (in_array($this->_first($query), $this->_SELECTION, true)) {
            $this->_prepare($query, $params);
            if ($this->error === false) {
                try {
                    $rows = $this->_Qry->fetchAll($fetchmode);
                    $this->numrows = count($rows);
                    return $rows;
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                    $this->numrows = 0;
                }
            }
        } 
        return false;
    }

    /**
     * Returns all rows from query into array<br>
     * Devuelve todas las filas en un array
     * @param string $query Query string
     * @param array|null $params Binding parameters (optional)
     * @param int $fetchmode Fetchmode, can be PDO::FETCH_ASSOC (default), PDO::FETCH_BOTH, PDO::FETCH_CLASS, PDO::FETCH_NUM
     * @return array|false Array or false
     */
    public function rows(string $query, $params = null, int $fetchmode = PDO::FETCH_ASSOC)
    {
        $rows = [];
        $query = trim($query);
        $firstWord = strtolower(strtok($query, " "));
        if (in_array($firstWord, $this->_SELECTION, true)) {
            $this->_prepare($query, $params);
            if ($this->error === false) {
                try {
                    $rows = $this->_Qry->fetchAll($fetchmode);
                    $this->numrows = count($rows);
                    return $rows;
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                    $this->numrows = 0;
                }
            }
        }
        return false;
    }

    /**
     * Returns a row from query into array<br>
     * Devuelve todas las filas en un array
     * @param string $query Query string
     * @param array|null $params Binding parameters
     * @param int $fetchMode Fetch mode, PDO::FETCH_ASSOC (default), PDO::FETCH_BOTH, PDO::FETCH_CLASS, PDO::FETCH_NUM
     * @return array|false Array or false
     */
    public function row(string $query, $params = null, int $fetchMode = PDO::FETCH_ASSOC)
    {        
        $query = trim($query);
        $firstWord = strtolower(strtok($query, " "));
        if (in_array($firstWord, $this->_SELECTION, true)) {
            $this->_prepare($query, $params);
            if ($this->error === false) {
                try {
                    return $this->_Qry->fetch($fetchMode);
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                }
            }
        }
        return false;      
    }

    /**
     * Execute a query binding parameters<br>
     * Ejecuta una consulta enlazando parámetros
     * @param string $query Query string
     * @param array|null $params Binding parameters (optional)
     * @return int|false Number of affected rows or false if not an ACTION query
     */
    public function execute(string $query, $params = null)
    {
        $query = trim($query);
        $firstWord = strtolower(strtok($query, " "));
        if (in_array($firstWord, $this->_ACTION, true)) {
            $this->_prepare($query, $params);
            if ($this->error === false) {
                try {
                    return $this->affected = $this->_Qry->rowCount();
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                }
            }
        }
        return false;
    }

    /**
     * Returns next row from query into array<br>
     * Devuelve la siguiente fila de una consulta en un array
     * @param int $fetchmode Fetch mode, PDO::FETCH_ASSOC (default), PDO::FETCH_BOTH, PDO::FETCH_CLASS, PDO::FETCH_NUM
     * @return array|false Array or false
     */
    public function nextRow(int $fetchmode = PDO::FETCH_ASSOC)
    {        
        if ($this->error === false) {
            try {
                return $this->_Qry->fetch($fetchmode);      
            } catch (PDOException $e) {
                $this->_errorLog($e->getMessage());
                $this->_setError();
            }
        }
        return false;      
    }

    /**
     * Execute a query without binding parameters<br>
     * Ejecuta una consulta sin enlazar parámetros
     * @param string $query Query string
     * @return mixed Number of result rows or affected rows or false if error
     */
    public function query(string $query)
    {
        $query = trim($query);
        $this->_Qry = $this->_Pdo->query($query);
        if ($this->error === false) {
            $firstWord = strtolower(strtok($query, " "));
            if (in_array($firstWord, $this->_ACTION, true)) {
                try {
                    return $this->affected = $this->_Qry->rowCount();
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                }
            } else {
                try {
                    return $this->numrows = $this->_Qry->rowCount();
                } catch (PDOException $e) {
                    $this->_errorLog($e->getMessage());
                    $this->_setError();
                }
            }
        }
        return false;
    }
      
    /**
     * Return last inserted primary key value<br>
     * Devuelve la última clave promaria insertada
     * @return mixed Last id
     */
    public function lastId()
    {
        try {
            return $this->_Pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->_errorLog($e->getMessage());
            $this->_setError();
        }
        return false;      
    }  

    /**
     * Begins Transaction<br>
     * Comienza el control de transacción
     * @return bool
     */
    public function beginTrans(): bool
    {
        try {
            $this->_Pdo->beginTransaction();
            $this->trans = true;
            return true;
        } catch (PDOException $e) {
            $this->_errorLog($e->getMessage());
            $this->_setError();
        }
        return false;
    }  
      
    /**
     * Commits a transaction<br>
     * Confirma la transacción
     * @return bool
     */
    public function commitTrans(): bool
    {
        if ($this->trans) {
            try {
                $this->_Pdo->commit();
                $this->trans = false;
                return true;
            } catch (PDOException $e) {
                $this->_errorLog($e->getMessage());
                $this->_setError();
            }
        }
        return false;
    }  
      
    /**
     * Rolls back a transaction<br>
     * Deshace la transacción
     * @return bool
     */
    public function rollBack(): bool
    {
        if ($this->trans) {
            try {
                $this->_Pdo->rollBack();
                $this->trans = false;
                return true;
            } catch (PDOException $e) {
                $this->_errorLog($e->getMessage());
                $this->_setError();
            }
        }
        return false;
    }  
      
    /**
     * Ends transaction<br>
     * Termina la transacción
     * @return bool
     */
    public function endTrans(): bool
    {
        if ($this->trans) {
            try {
                $this->_Pdo->commit();
            } catch (PDOException $e) {
                $this->_Pdo->rollBack();
            }
            $this->trans = false;
            return true;
        }
        return false;
    }

    /**
     * Error logging<br>
     * Registro de Errores
     * @param string $message Message
     * @param string $sql Query string (optional)
     * @return string Exception message
     */
    private function _errorLog(string $message, string $sql = ""): string
    {
        $exception = 'Excepción no manejada<br>';
        $exception .= $message;
        $exception .= "<br>Puede encontrar el error en el Log";
        if (!empty($sql)) {
            $message .= "\r\nSQL : " . $sql;
        }
        $this->_Log->write($message);
        return $exception;
    }

    /**
     * Returns the first query instruction<br>
     * Devuelve la primera instrucción de la consulta
     * @param string $query Query string
     * @return string First Query instruction
     */
    private function _first(string $query): string
    {
        $query = str_replace("\r\n", ' ', $query);
        $array = explode(" ", $query);
        return strtolower($array[0]);
    }

    /**
     * Returns the database server information<br>
     * Devuelve la información del servidor de base de datos
     * @return string Database server info
     */
    public function server_info(): string
    {
        return $this->_Pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    }

    /**
     * Returns the database server version<br>
     * Devuelve la version del servidor de base de datos
     * @return string Database server version
     */
    public function server_version(): string
    {
        $row = $this->row('SELECT version()');
        return $row['version()'];
    }

    /**
     * Returns the database name<br>
     * Retorna el nombre de la base de datos
     * @return string Database name
     */
    public function getDataBaseName(): string
    {
        return $this->_Settings['dbname'];
    }
}
