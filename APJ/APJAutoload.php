<?php
/**
* Class Autoloader
* Autocargador de clases
* Version: 2.0.2609
* Author: Ricardo Seiffert
*
* Cambios en 2.0.2609: fallback a minúsculas para Controllers\ (bug de
* case-sensitivity Windows/Linux: com_CotizacionPdf.php no existía en
* servidor Linux, el archivo real es com_cotizacionpdf.php).
*/
namespace APJ;
use Exception;

class Autoload {
    /**
    * Register the autoload function<br>
    * Registra la función de autocarga
    * 
    */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload'], true, true);
    }

    /**
    * Class autoload function<br>
    * función de autocarga de clases
    * 
    * @param mixed $class Clase a cargar
    */
    public static function autoload($class): void {
        // Convertir namespace a la ruta del archivo
        $prefixes = [
            'APJ\\' => APJ . '/',
            'Models\\' => MODELS . '/',
            'Helpers\\' => HELPERS . '/',
            'Controllers\\' => CONTROLLERS . '/'
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) === 0) {
                $relativeClass = substr($class, $len);
                $relativePath = str_replace('\\', '/', $relativeClass);
                $filePath = $baseDir . $relativePath . '.php';
                $file = realpath($filePath);
                if (!$file && $prefix === 'Controllers\\') {
                    // Los archivos de controladores se nombran en minúsculas
                    // (convención de URL) aunque la clase use mayúsculas -
                    // en Windows (case-insensitive) nunca se notaba la
                    // diferencia, pero en Linux sí.
                    $file = realpath($baseDir . strtolower($relativePath) . '.php');
                }
                if ($file && file_exists($file)) {
                    require_once $file;
                    return;
                } else {
                    throw new Exception("El archivo {$filePath} no está disponible.");
                }
            }
        }
    }
}
