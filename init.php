<?php
// Constantes de Sistema y Autocarga
define("DEVELOPMENT", true);
define("APPNAME", "Nombre de la aplicación");
define("ROOT", __DIR__);
define("APP", ROOT);
define("CONTROLLERS", APP);
define("LIBS", APP . "/Libs");
define("MODELS", APP . "/Models");
define("VIEWS", APP . "/Views");
define("HELPERS", APP . "/Helpers");
define("VENDORS", APP . "/Vendor");
define("APJ", LIBS . "/APJ");
define("IMAGES", APP . "/images");

if (DEVELOPMENT) {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    $domain = "localhost";
    $rootUrl = "/MiAplicacion";
    $es = "ES";
} else {
    error_reporting(0);
    $domain = "miservidor.com";
    $rootUrl = ROOT;
    $es = "es_ES";
}

define("ROOTURL", $rootUrl);
define("DOMAIN", $domain);
define("LOGIN", "login.php");
define("LOGIN_ATTEMPTS", 3);
define("SESSION_LIMIT", 1800);
define("PERSISTENT_CONNECTION", FALSE);
define("EMULATE_PREPARES", FALSE);

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

require_once APJ . "/Autoload.php";
Libs\APJ\Autoload::register();

$vendorAutoload = VENDORS . '/autoload.php';
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
} else {
    throw new \Exception("El archivo {$vendorAutoload} no está disponible.");
}
