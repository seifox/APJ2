<?php 
/**
 * Logs PDO errors<br>
 * Registros de errores de PDO
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;

class APJLog 
{
    /**
     * APJPDO Log file path folder<br>
     * Ruta de la carpeta del Log de APJPDO
     * @var string
     */
    private $path = null;
    
    /**
     * Constructor, defines the APJPDO log file path<br>
     * Constructor, define la ruta del archivo Log de APJPDO
     */
    public function __construct() {
        $this->setLogPath(APJ . DIRECTORY_SEPARATOR . 'APJPDO_logs' . DIRECTORY_SEPARATOR);
    }

    /**
     * Creates Log file<br>
     * Crea el archivo Log
     * @param string $msg Log message
     */
    public function write(string $msg): void {
        $date = new DateTime();
        $log = $this->path . $date->format('Y-m-d') . ".txt";

        try {
            if (is_dir($this->path)) {
                if (!file_exists($log)) {
                    $fileHandler = fopen($log, 'a+') or die("Fatal Error!");
                    $logcontent = "Time: " . $date->format('H:i:s') . "\r\n" . $msg . "\r\n";
                    fwrite($fileHandler, $logcontent);
                    fclose($fileHandler);
                } else {
                    $this->_append($log, $date, $msg);
                }
            } elseif (mkdir($this->path, 0777) === true) {
                $this->write($msg);
            }
        } catch (Exception $e) {
            error_log("Error in APJLog::write: " . $e->getMessage());
        }
    }

    /**
     * Sets the log path<br>
     * Define la ruta del log
     * @param string $path Log path
     */
    public function setLogPath(string $path): void {
        $this->path = $path;
    }
    
    /**
     * Appends content to the log file<br>
     * Agrega contenido al archivo de log
     * @param string $log Log file path
     * @param DateTime $date Current date and time
     * @param string $msg Log message
     */
    private function _append(string $log, DateTime $date, string $msg): void {
        try {
            $logcontent = "Time: " . $date->format('H:i:s') . "\r\n" . $msg . "\r\n\r\n";
            $logcontent = $logcontent . file_get_contents($log);
            file_put_contents($log, $logcontent);
        } catch (Exception $e) {
            error_log("Error in APJLog::_append: " . $e->getMessage());
        }
    }
}
