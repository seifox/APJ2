<?php
/**
* Class Autoloader
* Autocargador de clases
* Version: 2.0.2412
* Author: Ricardo Seiffert
*/
namespace Libs\APJ;

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
    * @return mixed
    */
    public static function autoload($class): void {
        // Convertir namespace a la ruta del archivo
        $prefixes = [
            'Libs\\' => LIBS . '/',
            'Models\\' => MODELS . '/',
            'Helpers\\' => HELPERS . '/',
            'Controllers\\' => CONTROLLERS . '/'
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) === 0) {
                $relativeClass = substr($class, $len);
                $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require $file;
                    return;
                } else {
                    throw new \Exception("El archivo {$file} no está disponible.");
                }
            }
        }
    }
}
