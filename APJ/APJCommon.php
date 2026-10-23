<?php
/**
 * Common methods Trait<br>
 * Rasgo de métodos comunes
 * Version: 2.0.2609
 * Author: Ricardo Seiffert
 * Cambios en 2.0.2609 (2): agregado isHttps() (protegido) - detección de
 * HTTPS duplicada localmente en cada clase que la necesita (APJSession,
 * APJController vía este trait, Helpers\Sendmail donde exista) en vez de
 * una función global en init.php, que se retiró. Decisión explícita del
 * usuario: prefiere la duplicación acotada a una función suelta fuera del
 * namespace APJ\ que además no siempre se usa.
 * Cambios en 2.0.2609: format() con tipo 'date'/'datetime'/'time'/'timestamp'
 * ahora trata un string vacío ('') igual que NULL (retorna null) - antes
 * caía en `new DateTime('')`, que PHP interpreta como "now", mostrando
 * silenciosamente la fecha/hora actual para cualquier columna vacía (bug
 * real: la grilla de ope_cierre.php de aerotac3 mostraba la misma fecha/hora
 * de cierre en todos los vuelos, incluso los no cerrados, porque el SQL
 * devuelve '' en vez de NULL para fecha_cierre cuando no hay cierre).
 */
namespace APJ;
use \stdClass;
use \DateTime;
use \Exception;

trait APJCommon
{

    /**
     * Return formatted current Date and Time<br>
     * Retorna la Fecha y Hora actual con formato
     * @param string $format Datetime format
     * @return string formatted Datetime
     */
    protected function currentDateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }

    /**
     * Formats a value depending on data type<br>
     * Formatea un valor dependiendo del tipo de dato
     * @param mixed $value The value
     * @param string $type Data type
     * @return mixed Formatted value
     */
    protected function format($value, string $type)
    {
        $type = strtolower($type);
        $fmt = unserialize(FORMATS);
        switch ($type) {
            case 'float':
            case 'real':
            case 'double':
            case 'double precision':
            case 'fixed':
            case 'dec':
            case 'decimal':
                if (is_numeric($value)) {
                    return number_format($value, $fmt['decimal'][0], $fmt['decimal'][1], $fmt['decimal'][2]);
                }
                break;
            case 'date':
                return ($value === '0000-00-00' || $value === null || $value === '') ? null : (new DateTime($value))->format($fmt['date']);
            case 'datetime':
                return ($value === '0000-00-00 00:00:00' || $value === null || $value === '') ? null : (new DateTime($value))->format($fmt['datetime']);
            case 'time':
                return ($value === null || $value === '') ? null : (new DateTime($value))->format($fmt['time']);
            case 'timestamp':
                return ($value == 0 || $value === null || $value === '') ? null : (new DateTime($value))->format($fmt['timestamp']);
            case 'smallint':
            case 'mediumint':
            case 'integer':
            case 'int':
            case 'bigint':
            case 'bit':
                if (is_numeric($value)) {
                    return number_format($value, $fmt['int'][0], $fmt['int'][1], $fmt['int'][2]);
                }
                break;
            case 'boolean':
            case 'bool':
            case 'tinyint':
                if (is_numeric($value)) {
                    return $value ? $fmt['booleanTrue'] : $fmt['booleanFalse'];
                }
                break;
            default:
                return $value;
        }
        return $value;
    }

    /**
     * Validate dates by format
     * Validates dates according to a format
     * 
     * @param mixed $date Date to validate
     * @param string $format Date format (or 'timestamp')
     * @param bool $strict Strict validation
     * @return bool True if valid, false if invalid
     */
    public function verifyDate($date, string $format, bool $strict = true): bool
    {
        try {
            if ($format === 'timestamp') {
                // Validate as a UNIX timestamp
                return is_numeric($date) && (new DateTime('@' . $date)) !== false;
            }

            // Validate using the specified format
            $dateTime = DateTime::createFromFormat($format, $date);
            if ($dateTime === false) {
                return false; // Invalid
            }
            $errors = DateTime::getLastErrors();
            if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
                return false; // Invalid
            }

            if ($strict) {
                // Ensure strict match of input and parsed date
                $dateComp = $dateTime->format($format);
                if ($date !== $dateComp) {
                    return false; // Invalid
                }
            }

            return true; // Valid
        } catch (Exception $e) {
            error_log("Error verifying date: " . $e->getMessage());
            return false; // Invalid
        }
    }

    /**
    * Detects whether the current request arrived over HTTPS, accounting
    * for the production reverse proxy (cPanel/Nginx) that terminates TLS
    * and forwards to the app server in plain HTTP - $_SERVER['HTTPS'] is
    * only set when the app server itself sees the TLS connection directly.
    * Duplicated locally in each class that needs it (APJSession,
    * APJController via this trait, Helpers\Sendmail where it exists)
    * instead of a global init.php function, since it's only needed in a
    * handful of narrowly-scoped places.<br>
    * Detecta si el request actual llegó por HTTPS, contemplando el proxy
    * reverso de producción (cPanel/Nginx) que termina TLS y reenvía en
    * HTTP plano hacia el servidor de la app - $_SERVER['HTTPS'] solo queda
    * seteado cuando el servidor ve la conexión TLS directamente. Duplicado
    * localmente en cada clase que lo necesita (APJSession, APJController
    * vía este trait, Helpers\Sendmail donde exista) en vez de una función
    * global en init.php, ya que solo hace falta en unos pocos lugares
    * puntuales.
    * @return bool
    */
    protected function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Converts Date and Time by format<br>
     * Convierte Fecha y Hora según formato
     * @param string $dateTime Date and Time to convert
     * @param string $format Format
     * @return string Formatted datetime
     */
    protected function convertDateTime(string $dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        $arraymonth = [];
        $months = [
            ['ninguno', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            ['ninguno', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
            ['none', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
        ];
        $separator = ' ';
        $strDate = $dateTime;
        foreach ($months as $month) {
            foreach ([' ', '-', '/','.'] as $delimiter) {
                if ($this->arrayInString($month, strtolower($strDate), $delimiter)) {
                    $arraymonth = $month;
                    $separator = $delimiter;
                    break;
                }
            }
        }
        if (count($arraymonth)) {
            $fa = explode($separator, strtolower($strDate));
            $dd = '';
            $mm = '';
            $yy = '';
            foreach ($fa as $part) {
                $part = str_replace(',', '', $part);
                if (is_numeric($part)) {
                    if (strlen($part) <= 2) {
                        $dd = $part;
                    }
                    if (strlen($part) === 4) {
                        $yy = $part;
                    }
                } elseif (in_array($part, $arraymonth, true)) {
                    $mm = array_search($part, $arraymonth, true);
                }
            }
            if ($dd && $mm && $yy) {
                $strDate = $mm . '/' . $dd . '/' . $yy;
            }
        } elseif (strpos($strDate, '/')) {
            $strDate = str_replace('/', '-', $strDate);
        }
        try {
            $newDate = new DateTime($strDate);
            return $newDate->format($format);
        } catch (Exception $e) {
            error_log("Error converting date: " . $e->getMessage());
            return "Error";
        }
    }

    /**
     * Searches array elements in a string<br>
     * Busca elementos de un array en un string
     * @param array $array Elements to be searched
     * @param string $string Where to search
     * @param string $delim Delimiter (default ' ')
     * @return bool True or False
     */
    protected function arrayInString(array $array, string $string, string $delim = ' '): bool
    {
        $stringAsArray = explode($delim, $string);
        return count(array_intersect($array, $stringAsArray)) > 0;
    }

    /**
     * Returns a substring delimited by 2 strings<br>
     * Retorna un substring delimitado por 2 strings
     * @param string $string Entire string
     * @param string $start Starting search string
     * @param string $end Ending search string
     * @param int $pos Starting position (default 0)
     * @return string Result
     */
    protected function getStringBetween(string $string, string $start, string $end, int $pos = 0): string
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start, $pos);
        if ($ini === false) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    /**
     * Returns an object from an array<br>
     * Devuelve un objeto a partir de una matriz
     * @param array $array Elements to be converted
     * @return stdClass Object
     */
    protected function arrayToObject(array $array): stdClass
    {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    /**
     * Returns an associative array of an object<br>
     * Devuelve un arreglo asociativo de un objeto
     * @param object $object
     * @return array
     */
    protected function objectToArray($object): array
    {
        return (array)$object;
    }

    /**
     * Returns current Unix timestamp<br>
     * Retorna la fecha Unix actual
     * @return int Unix timestamp
     */
    protected function timeStamp(): int
    {
        return time();
    }
    
    /**
    * Common method for throwing Exceptions 
    * 
    * @param mixed $msg
    * @return void
    */
    protected function throwException(string $msg): void
    {
        throw new Exception(
            $msg
        );
    }

}
