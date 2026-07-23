<?php 
/**
 * Logs APJ errors<br>
 * Registros de errores de APJ
 * Version: 2.0.2501
 * Author: Ricardo Seiffert
 */
namespace APJ;
use \Exception;
use \DateTime;

class APJLog 
{
    private ?string $_path = null;
    private string $_logFile = '';
    private string $_DateTimeFormat = 'Y-m-d H:i:s';

    public function __construct(?string $logFile = null)
    {
        $this->setLogPath(APJLOGS);
        if ($logFile) {
            $this->setLogFile($logFile);
        } else {
            $this->_logFile = date('Y-m-d') . '.txt';
        }
        $this->_setFormats();
    }

    /**
    * Writes errors logs for APJ<br>
    * Escribe los logs de errores para APJ
    * @param string $msg
    * @return void
    */
    public function errorLog(string $msg): void 
    {
        try {
            $date = new DateTime();
            $backtrace = debug_backtrace(); 
            $caller = $backtrace[1];
            $callerInfo = isset($caller['class']) ? "{$caller['class']}::{$caller['function']}" : "{$caller['function']}"; 
            $logMessage = "Time: " . $date->format($this->_DateTimeFormat) . " | Caller: " . $callerInfo . " | Message: " . $msg . PHP_EOL;
        } catch (Exception $e) {
            $this->_throwException($e->getMessage());
            return;
        };
        try { 
            error_log($logMessage, 3, $this->_path . $this->_logFile); 
        } catch (Exception $e) { 
            $msg="Error in APJLog::errorLog: " . $e->getMessage();
            error_log($msg); 
            $this->_throwException($msg);
        };    
    }

    /**
    * Sets de error log path<br>
    * Define la ruta del log de errores
    * @param mixed $path
    * @return void
    */
    public function setLogPath(string $path): void
    {
        $this->_path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
    * Sets the errors log filename<br>
    * Define el archivo de logs de errores
    * @param string $logFile
    * @return void
    */
    public function setLogFile(string $logFile): void 
    {
        if ($this->isValidFileName($logFile)) { 
            $this->_logFile = $logFile; 
        } else { 
            $this->_throwException("Invalid log file name: " . $logFile); 
        }    
    }
    
    /** 
    * Sets the format for date and time based on environment configuration 
    * 
    * @return void 
    */    
    private function _setFormats(): void
    {
        if (defined('FORMATS')) {
            try {
                $fmt=unserialize(FORMATS);
                $this->_DateTimeFormat = $fmt['datetime'];
            } catch (Exception $e) {
                $this->_throwException('Environment misconfiguration: FORMATS constants bad defined '. $e->getMessage());
            }
        } else {
            $this->_DateTimeFormat = 'Y-m-d H:i:s';
        }
    }
    
    /**
    * Common method for throwing Exceptions 
    * 
    * @param mixed $msg
    * @return void
    */
    private function _throwException(string $msg): void
    {
        throw new Exception(
            $msg
        );
    }
    
    /**
    * Validate log filename
    * 
    * @param string $fileName
    * @return bool
    */
    private function isValidFileName(string $fileName): bool 
    { 
        return !empty($fileName) && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fileName); 
    }
}
