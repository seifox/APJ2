<?php
// Constantes de Sistema y Autocarga
define("DEVELOPMENT", true);
define("APPNAME", "Nombre de la aplicación");
define("ROOT", __DIR__);
define("APP", ROOT);
define("APJ", APP . "/APJ");
define("APJLOGS", APJ . "/Logs");
define("CONTROLLERS", APP);
define("MODELS", APP . "/Models");
define("VIEWS", APP . "/Views");
define("HELPERS", APP . "/Helpers");
define("VENDORS", APP . "/Vendor");
define("IMAGES", APP . "/images");

if (DEVELOPMENT) {
    error_reporting(E_ALL);
    $domain = "localhost";
    $rootUrl = "/MiAplicacion";
    $es = "ES";
} else {
    error_reporting(0);
    $domain = "miservidor.com";
    $rootUrl = "/MiAplicacion";
    $es = "es_ES";
}
define("ROOTURL", $rootUrl);
define("DOMAIN", $domain);
define("LOGIN", "login.php");
define("LOGIN_ATTEMPTS", 3);
define("SESSION_LIMIT", 1800);
define("PERSISTENT_CONNECTION", FALSE);
define("EMULATE_PREPARES", TRUE);

define("FORMATS", serialize([
    "int" => [0, ',', '.'],
    "decimal" => [2, ',', '.'],
    "date" => 'd-m-Y',
    'datetime' => 'd-m-Y H:i:s',
    'time' => 'H:i',
    'timestamp' => 'd-m-Y H:i:s',
    'booleanTrue' => 'Si',
    'booleanFalse' => 'No'
]));

define("TIMEZONE", 'America/Santiago');
date_default_timezone_set(TIMEZONE);
define("LOCALTIMESYMBOL", $es);

if (!is_dir(APJLOGS)) {
    mkdir(APJLOGS, 0755, true);
}

require_once APJ . "/APJAutoload.php";
APJ\Autoload::register();

$vendorAutoload = VENDORS . '/autoload.php';
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
} else {
    throw new \Exception("El archivo {$vendorAutoload} no está disponible.");
}

function isEntryPoint(string $file): bool
{
    if (!isset($_SERVER['SCRIPT_FILENAME'])) {
        return false;
    }
    $resolved = realpath($file);
    $script   = realpath($_SERVER['SCRIPT_FILENAME']);
    return $resolved !== false && $script !== false && $resolved === $script;
}
