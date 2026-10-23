<?php
/**
 * APJ's parent Controller
 * Controlador padre de APJ
 * Version: 2.0.2611
 * Author: Ricardo Seiffert
 * Cambios en 2.0.2611 (2): absoluteUrl() usa $this->isHttps() (método
 * protegido de APJCommon, este controlador ya usa ese trait) en vez de la
 * función global isHttps() de init.php, que se retiró - decisión
 * explícita del usuario, prefiere la detección de HTTPS duplicada
 * localmente en cada clase que la necesita antes que una función suelta
 * fuera del namespace APJ\.
 * Cambios en 2.0.2611: eliminada la constante intermedia que normalizaba
 * ROOTURL para concatenar en URLs (equivalía exactamente a
 * rtrim(ROOTURL,'/') - "" cuando el sitio corre en la raíz del dominio,
 * sin cambios en cualquier otro caso). render() y absoluteUrl() ahora
 * calculan rtrim(ROOTURL,'/') directo, sin una segunda constante casi-
 * anagrama de ROOTURL que se prestaba a confusión.
 * Cambios en 2.0.2610: nuevo método absoluteUrl($path) - reemplaza el
 * patrón "{$protocolo}://".DOMAIN."{root}"."/ruta" que estaba duplicado en
 * cada controlador que arma un link absoluto (email/QR/verificación de
 * ticket), usando la nueva función global isHttps() (ver init.php) en vez
 * de repetir la detección de protocolo a mano en cada lugar.
 * Cambios en 2.0.2610 (2): absoluteUrl() ahora normaliza la unión
 * DOMAIN+root+$path (ltrim+"/" de $path) en vez de confiar en que quien
 * llama siempre pase $path con "/" inicial - evita doble barra o barra
 * faltante sin importar cómo se invoque.
 * Cambios en 2.0.2609: render() armaba el <base href="..."> con
 * (ROOTURL=='/')?'':ROOTURL - eso omite el DOMAIN configurado y rompe los
 * links generados cuando el sitio corre detrás del dominio de producción
 * real. Ver project_gestion3_futuro_dominio_produccion.
 * Cambios en 2.0.2608: getParameters() usaba str_replace+explode manual en
 * vez de json_decode() real - perdía comillas y cortaba en cada coma de
 * cualquier parámetro con HTML/texto con comas (mismo bug encontrado y
 * corregido primero en APJ v1, ver Libs/APJ/APJController.class.php v1.9.260812).
 * Cambios en 2.0.2608 (2): _ApjReplace()/_APJCall() - los tags APJ:{...}
 * nunca le quitaban las comillas a un argumento string (APJ:{m('PESOS')}
 * pasaba el literal 'PESOS' con comillas, nunca calzaba contra datos
 * reales), y sin argumentos se pasaba null explícito en vez de omitir el
 * parámetro, así que tampoco se disparaba nunca un valor por defecto de
 * PHP. Encontrado depurando com_gastos.php (gestion3).
 * Cambios en 2.0.2608 (3): nuevos métodos APJSubmit($form,$action,...$args)
 * y APJCall($method,...$args) - disparan un APJSubmit/APJCall real del
 * lado del cliente desde el controlador, encolando una acción nativa de
 * jQ (misma familia que jInfo/jConfirm, ver jQ.php) en vez de armar un
 * jScript() a mano. Idea del usuario, motivada por com_creavuelo.php
 * (gestion3), que hacía unset($this->Form) para forzar "sin filtro" en
 * vez de simplemente reenviar el formulario de filtros tal cual estaba.
 */
namespace APJ;
use APJ\jQSelector;
use APJ\jQ;
use APJ\APJLog;
use \stdClass;
use \ReflectionClass;
use \Exception;

class APJController
{
    
    /**
     * Can render<br>
     * Puede renderizar
     * @var bool
     */
    public $canRender = true;

    /**
     * Has the controller rendered? 
     * Ha renderizado el controlador
     * @var bool
     */
    protected $hasRendered = false;

    /**
     * Controller filename<br>
     * Archivo del Controlador
     * @var string
     */
    private $_self = null;

    /**
     * Form Object<br>
     * Objeto Form (formulario)
     * @var stdClass
     */
    public $Form;

    /**
     * Ajax default timeout<br>
     * Timeout por defecto de Ajax
     * @var int
     */
    protected $TimeOut = 10000;

    /**
     * User Id in session<br>
     * Id del usuario en la sesión
     * @var int
     */
    protected $userId = null;

    /**
     * Array of form fields types, used in setFormValues<br>
     * Arreglo de tipo de campos del formulario, usado en setFormValues
     * @var array
     */
    protected $fieldTypes = [];

    /**
     * Array paging properties<br>
     * Propiedades de paginación de arreglos
     * @var int
     */
    protected $lastPage = 0;
    protected $currentPage = 0;
    protected $previousPage = 0;
    protected $nextPage = 0;

    /**
     * Defines whether APJCall passes the parameters as an array or as independent arguments<br> 
     * Define si APJCall pasa los parámetros como un arreglo o como argumentos independientes
     * @var bool
     */
    protected $useParametersAsArray = false;
    
    /**
    * Error logging object
    * 
    * @var object
    */
    private $_Log = null;
    
    // Common methods Trait
    use APJCommon;

    /**
     * Constructor
     * @param string $page Rendered view
     */
    public function __construct(?string $page = '')
    {
        ob_start();
        $this->_initializeLog();
        $method = $_POST['action'] ?? null;
        $data = $_POST['data'] ?? $_POST;
        $this->_unsetAction();
        if ($method) {
            if (method_exists($this, $method)) {
                $data = $this->_isJson($data);
                $this->getForm();
                try {
                    $this->{$method}($data);
                } catch (\Throwable $e) {
                    $this->_ErrorLog("Excepción no manejada en {$method}: " . $e->getMessage());
                    $this->jError("Error interno del servidor.");
                }
                $this->getResponse();
            } else {
                $this->jError("El método {$method} no existe!");
                $this->getResponse();
            }
        } elseif ($page) {
            $this->render($page, false);
        }
    }

    /**
     * Renders the view</br>
     * Renderiza la vista
     * @param string $page View name
     * @param bool $return true=Returns the rendered view, false=displays the view (optional)
     */
    protected function render(string $page, bool $return = false)
    {
        if ($this->canRender) {
            $url = VIEWS . DIRECTORY_SEPARATOR . $page;
            $html = $this->_getContent($url);
            if ($html) {
                $root = rtrim(ROOTURL, '/');
                $replace = '<head><base href="' . $root . '/">';
                // Solo la primera ocurrencia (el <head> real de la vista, siempre
                // estático y al principio del archivo) - un str_replace() normal
                // reemplazaba CUALQUIER "<head>" en toda la página ya renderizada,
                // incluyendo uno que apareciera como texto dentro de un string JS
                // generado por un controlador (ej. com_ingresomensual.php armando el
                // HTML de una exportación a Excel), corrompiendo ese string.
                $posHead = strpos($html, '<head>');
                if ($posHead !== false) {
                    $html = substr_replace($html, $replace, $posHead, strlen('<head>'));
                }
                if ($return) {
                    return $html;
                } else {
                    echo $html;
                }
                $this->hasRendered = true;
            } else {
                $html = "Can't open view " . $url;
                if ($return) {
                    return $html;
                } else {
                    echo $html;
                }
            }
        }
    }

    /**
     * Builds an absolute URL for the current environment (protocol +
     * DOMAIN + normalized root), from a site-relative path. Replaces the
     * "{$protocol}://" . DOMAIN . ROOTURL . "/path" pattern that used to
     * be duplicated in every controller building an absolute link (email,
     * QR, ticket verification).<br>
     * Arma una URL absoluta para el entorno actual (protocolo + DOMAIN +
     * raíz normalizada), a partir de una ruta relativa al sitio. Reemplaza
     * el patrón "{$protocolo}://" . DOMAIN . ROOTURL . "/ruta" que estaba
     * duplicado en cada controlador que arma un link absoluto (email, QR,
     * verificación de ticket). No usar DOMAIN/ROOTURL directo para esto,
     * usar este método.
     * @param string $path Ruta relativa al sitio, con "/" inicial (ej. "/images/logo.gif")
     * @return string URL absoluta completa
     */
    protected function absoluteUrl(string $path = ''): string
    {
        $protocol = $this->isHttps() ? 'https' : 'http';
        // Normaliza ambos lados de la unión (nunca doble barra ni barra
        // faltante): ROOTURL vale "/" en la raíz del dominio (correcto
        // como path de cookie, ver login.php), rtrim lo deja en "" para
        // concatenar sin duplicar la barra; $path se normaliza igual sin
        // importar si quien llama la incluyó o no.
        $root = rtrim(ROOTURL, '/');
        $path = $path !== '' ? '/' . ltrim($path, '/') : '';
        return "{$protocol}://" . DOMAIN . $root . $path;
    }

    /**
     * Controls session<br>
     * Control de sesi?n
     * @param bool $controllerControl controls access to the controller (default = true)
     */
    protected function sessionControl(bool $controllerControl = true)
    {
        try {
            if (!APJSession::active(APPNAME)) {
                $this->redirect(LOGIN, true);
            }
            APJSession::start(APPNAME, SESSION_LIMIT);
            if (!isset($_SESSION['id']) || !isset($_SESSION['IPaddress']) || !isset($_SESSION['userAgent'])) {
                $this->redirect(LOGIN, true);
            }
            if ($controllerControl) {
              $app = md5($this->getController());
              if (empty($_SESSION['app']) || !in_array($app, $_SESSION['app'], true)) {
                  $this->redirect(LOGIN, true);
              }
            }
            $this->userId = $_SESSION['id'];
        } catch (Exception $e) {
            $this->_ErrorLog("Error in sessionControl: " . $e->getMessage());
            $this->redirect(LOGIN, true);
        }
    }
  
    /**
     * Extracts into array the (optional) additional parameters from APJSubmit<br>
     * Extrae los parámetros (opcionales) adicionales que envía APJSubmit en un array
     * @param string $params Parameters submitted from APJSubmit
     * @return array Parameters array
     */
    private function getParameters(string $params): array
    {
        $result = json_decode($params, true);
        return is_array($result) ? $result : [];
    }

    /**
     * Create a Form object from submitted form<br>
     * Crea un objeto Form con los campos del formulario enviado
     */
    public function getForm(): void
    {
        $this->createForm();
        foreach ($_REQUEST as $name => $value) {
            if ($name !== 'action' && $name !== 'parameters') {
                $this->Form->$name = $value;
            } elseif ($name === 'parameters') {
                $this->Form->parameters = $this->getParameters($value);
            }
        }
    }
  
    /**
     * Assign matching form fields to the Model<br>
     * Asigna los campos coincidentes del formulario al Modelo
     * @param object $model Model object
     */
    protected function formToModel($model): void
    {
        try {
            if (isset($_REQUEST['action'])) {
                foreach ($_REQUEST as $name => $value) {
                    if ($name !== 'action' && in_array($name, $model->fields, true)) {
                        $model->$name = $value;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in formToModel: " . $e->getMessage());
        }
    }

    /**
     * Assign matching Form object fields to the Model<br>
     * Asigna los campos coincidentes del objeto Form al Modelo
     * @param object $model Model object
     */
    protected function formObjectToModel($model): void
    {
        try {
            $this->createForm();
            foreach ($this->Form as $name => $value) {
                if (in_array($name, $model->fields, true)) {
                    $model->$name = $value;
                }
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in formObjectToModel: " . $e->getMessage());
        }
    }

    /**
     * Assigns Model values to Form object<br>
     * Asigna valores del Modelo al objeto Form
     * @param object $model Model object
     */
    protected function modelToForm($model): void
    {
        try {
            $this->createForm();
            $fields = $model->fields;
            foreach ($fields as $name) {
                $this->Form->$name = $model->$name;
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in modelToForm: " . $e->getMessage());
        }
    }
  
    /**
     * Assigns the array values to the Form object<br>
     * Asigna los valores de un arreglo al objeto Form
     * @param array $array Associative array
     */
    protected function arrayToForm(array $array): void
    {
        try {
            $this->createForm();
            foreach ($array as $name => $value) {
                $this->Form->$name = $value;
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in arrayToForm: " . $e->getMessage());
        }
    }

    /**
     * Clear Form Object<br>
     * Limpia el objeto Form
     */
    protected function clearForm(): void
    {
        try {
            unset($this->Form);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in clearForm: " . $e->getMessage());
        }
    }

    /**
     * Create the Form object<br>
     * Crea el objeto Form
     */
    public function createForm(): void
    {
        try {
            if (!isset($this->Form)) {
                $this->Form = new stdClass();
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in createForm: " . $e->getMessage());
        }
    }
  
    /**
     * Sets the html form values from Form object or array<br>
     * Asigna los valores del formulario html desde objeto Form o Array
     * @param mixed $data Array or object (optional)
     * @param string|null $form Form ID (optional)
     */
    protected function setFormValues($data = '', ?string $form = null): void
    {
        $this->createForm();
        if (empty($data) && !empty($this->Form)) {
            $data = $this->Form;
        }
        foreach ($data as $field => $value) {
            $this->Form->$field = $value;
            $selector = $this->selector($field, $form);
            if (isset($this->fieldTypes[$field])) {
                if ($this->fieldTypes[$field] === "checkbox" || $this->fieldTypes[$field] === "radio") {
                    $this->jQ($selector)->prop('checked', (bool) $value);
                } elseif ($this->fieldTypes[$field] === 'datetime-local') {
                    $value = date('Y-m-d\TH:i:s', strtotime($value));
                    $this->jQ($selector)->val($value);
                } elseif ($this->fieldTypes[$field] !== 'file') {
                    $this->jQ($selector)->val($value);
                }
            } else {
                $this->jQ($selector)->val($value);
            }
        }
    }

    /**
     * Sets form special input types like checkbox or radio<br>
     * Define los campos del formulario de tipo especial como checkbox o radio
     * @param string $field Input field ID/name
     * @param string $type Type (checkbox or radio)
     */
    protected function setFieldType(string $field, string $type = 'checkbox'): void
    {
        $this->fieldTypes[$field] = $type;
    }

    /**
     * Returns a jQ selector by ID or Form :input name
     * Retorna un selector de jQ por ID o por Formulario :input
     * @param string $field Input ID or name
     * @param string|null $form Form ID (optional)
     * @return string
     */
    protected function selector(string $field, ?string $form = null): string
    {
        if (isset($form)) {
            $sel = "#{$form} :input[name={$field}]";
        } else {
            $sel = "#{$field}";
        }
        return $sel;
    }
      
    /**
     * Checks if a string is a valid JSON and decodes it<br>
     * Verifica si una cadena es un JSON válido y lo decodifica
     * @param mixed $json
     * @return mixed
     */
    private function _isJson($json)
    {
        if (is_string($json) && (is_object(json_decode($json)) || is_array(json_decode($json)))) {
            return json_decode($json);
        } else {
            return $json;
        }
    }

    /**
     * Calls a method with parameters<br>
     * Llama a un método con parámetros
     * @param array $params
     * @return mixed
     */
    private function _APJCall(array $params)
    {
        try {
            $data = null;
            if (is_array($params)) {
                $func = $params[0];
                if (count($params) > 1) {
                    $data = array_slice($params, 1);
                }
                if (method_exists($this, $func)) {
                    if ($data === null) {
                        // Sin parámetros: se omite el argumento por completo
                        // para que el valor por defecto de PHP del método sí
                        // se dispare (antes se pasaba null explícito, que
                        // nunca activa un default sin type hint nullable).
                        return $this->{$func}();
                    }
                    if (!$this->useParametersAsArray) {
                        return call_user_func_array([$this, $func], $data);
                    }
                    return $this->{$func}($data);
                }
            }
        } catch (\Throwable $e) {
            $this->_ErrorLog("Error in _APJCall: " . $e->getMessage());
            return null;
        }
        return null;
    }

    /**
     * Gets the content of a page<br>
     * Obtiene el contenido de una página
     * @param string $page
     * @return string|null
     */
    private function _getContent(string $page): ?string
    {
        try {
            $html = $this->getLocalContent($page);
            if ($html) {
                $html = $this->_ApjReplace($html);
            }
            return $html;
        } catch (\Throwable $e) {
            $this->_ErrorLog("Error in _getContent: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets local file content<br>
     * Obtiene el contenido de un archivo local
     * @param string $url File URL
     * @return string|null File content
     */
    protected function getLocalContent(string $url): ?string
    {
        try {
            if (file_exists($url) && is_readable($url)) {
                return $this->getContent($url);
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in getLocalContent: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets a file content<br>
     * Obtiene el contenido de un archivo
     * @param string $url File URL
     * @return string|null File content
     */
    protected function getContent(string $url): ?string
    {
        if (ini_get('allow_url_fopen') != 1) {
            ini_set('allow_url_fopen', '1');
        }
        try {
            return file_get_contents($url);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in getContent: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Unsets the action and data values<br>
     * Limpia las acciones y valores de los datos
     */
    private function _unsetAction(): void
    {
        unset($_POST['action']);
        unset($_POST['data']);
    }

    /**
     * Removes one matching pair of surrounding quotes (' or ") from a raw
     * APJ:{...} template argument.<br>
     * Quita un par de comillas envolventes (' o ") de un argumento crudo de
     * APJ:{...}, para que APJ:{metodo('PESOS')} pase el string limpio PESOS
     * en vez del literal 'PESOS' (comillas incluidas) - el parser nunca las
     * sacaba, así que un argumento con comillas jamás calzaba contra datos
     * reales. Argumentos sin comillas (el estilo ya soportado, ej.
     * APJ:{estimaciones(fondo A)}) pasan intactos.
     * @param string $value
     * @return string
     */
    private function _stripTagQuotes(string $value): string
    {
        $value = trim($value);
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                return substr($value, 1, -1);
            }
        }
        return $value;
    }

    /**
     * Replaces the APJ: tags in the view with the data returned by the given method and returns the new html<br>
     * Reemplaza las etiquetas APJ: en la vista por los datos devueltos por el método dado y devuelve el nuevo html
     * @param string $html
     * @return string new HTML
     */
    private function _ApjReplace(string $html): string
    {
        try {
            $sNeedle = "APJ:{";
            $eNeedle = "}";
            $startPos = 0;
            while (($startPos = strpos($html, $sNeedle, $startPos)) !== false) {
                $len = strlen($sNeedle);
                $allFunc = $this->getStringBetween($html, $sNeedle, $eNeedle, $startPos);
                if ($allFunc) {
                    $allNeedle = $sNeedle . $allFunc . $eNeedle;
                    $func = substr($allFunc, 0, strpos($allFunc, '('));
                    $params = $this->getStringBetween($allFunc, '(', ')', 0);
                    if ($params) {
                        $paramArray = array_map([$this, '_stripTagQuotes'], explode(',', $params));
                        if (count($paramArray)) {
                            array_unshift($paramArray, $func);
                        } else {
                            $paramArray = [$func, $params];
                        }
                    } else {
                        $paramArray = [$func];
                    }
                    $replace = $this->_APJCall($paramArray);
                    if (is_null($replace)) {
                        $replace = '';
                    }
                    $html = str_replace($allNeedle, $replace, $html);
                    $len = strlen($replace);
                }
                $startPos += $len;
            }
        } catch (\Throwable $e) {
            $this->_ErrorLog("Error in _ApjReplace: " . $e->getMessage());
        }
        return $html;
    }

    /**
     * Generates the <option> elements of a <select> from a given array<br>
     * Genera los <option> de un <select> según array dado
     * @param array $array Array of elements
     * @param string $valueIndex Value index name
     * @param string $textIndex Item index name
     * @param string|array $selected Default selected item value, or array of values for multi-select (optional)
     * @return string <options>
     */
    protected function options(array $array, string $valueIndex, string $textIndex, string|array|null $selected = ''): string
    {
        $options = '';
        $selected = $selected ?? '';
        try {
            if (is_array($array) && strlen($valueIndex) > 0 && strlen($textIndex) > 0) {
                foreach ($array as $row) {
                    $sel = "";
                    if (is_array($selected)) {
                        if (in_array($row[$valueIndex], $selected, true)) {
                            $sel = "selected";
                        }
                    } elseif ($row[$valueIndex] == $selected) {
                        $sel = "selected";
                    }
                    $options .= '<option value="' . $row[$valueIndex] . '" ' . $sel . '>' . $row[$textIndex] . '</option>';
                }
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in options: " . $e->getMessage());
        }
        return $options;
    }
    
    /**
    * Check if the current file is the one to be executed directly
    * Comprueba si el archivo actual es el que se ejecuta directamente
    * @param string $file
    * @return bool
    */
    public static function executeLocally(string $file): bool
    {
        return isEntryPoint($file);
    }
    
    /**
     * Return current filename to _self (used with url = "APJ:{self()}")<br>
     * Retorna el nombre del archivo actual a _self (para uso en url = "APJ:{self()}")
     * @return string Controller filename
     */
    protected function self(): string
    {
        try {
            $obj = new ReflectionClass($this);
            return $this->_self = basename($obj->getFileName());
        } catch (Exception $e) {
            $this->_ErrorLog("Error in self: " . $e->getMessage());
            return '';
        }
    }

    protected function classBasename(): string
    {
        try {
            $obj = new ReflectionClass($this);
            return basename($obj->getFileName());
        } catch (Exception $e) {
            $this->_ErrorLog("Error in classBasename: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Return current controller name<br>
     * Retorna el nombre del controlador actual
     * @return string Controller name
     */
    protected function getController(): string
    {
        try {
            $file = $this->self();
            return substr($file, 0, -4);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in getController: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Returns an array with paged results from a data array<br>
     * Retorna un arreglo con resultados paginados de un arreglo de datos
     * @param array $data Data array
     * @param int $limit Elements per page
     * @param int $page Page number
     * @return array|false Result array or false on failure
     */
    protected function paging(array $data, int $limit = 20, int $page = 1)
    {
        try {
            if (is_array($data)) {
                $this->lastPage = (int) ceil(count($data) / $limit);
                $this->currentPage = $page;
                $this->previousPage = ($page > 1) ? $page - 1 : 1;
                $this->nextPage = ($page < $this->lastPage) ? $page + 1 : $this->lastPage;
                $pagedArray = array_chunk($data, $limit, true);
                return $pagedArray[$page - 1] ?? [];
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in paging: " . $e->getMessage());
            return false;
        }
        return false;
    }
  
  
    /**
     * Return the timeout parameter for the view<br>
     * Retorna el parámetro timeout para la vista
     * @return int Timeout value
     */
    protected function timeout(): int
    {
        return $this->TimeOut;
    }

    /**
     * Returns the APJ plugin initialization object as JSON for use in views<br>
     * Retorna el objeto de inicialización del plugin APJ como JSON para usar en las vistas
     * Usage in view: APJ.init(APJ:{APJInit()});
     * @return string JSON config object
     */
    protected function APJInit(): string
    {
        return json_encode([
            'url'     => $this->self(),
            'timeout' => $this->TimeOut,
        ]);
    }

    /**
     * Get response for Ajax<br>
     * Obtiene respuesta para Ajax
     */
    protected function getResponse(): void
    {
        try {
            jQ::getResponse();
        } catch (Exception $e) {
            $this->_ErrorLog("Error in getResponse: " . $e->getMessage());
        }
    }

    /**
     * Creates a jQuery object selector<br>
     * Crea un objeto selector jQuery
     * @param string $selector
     * @return jQSelector
     */
    protected function jQ(string $selector)
    {
        try {
            return jQ::setQuery($selector);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jQ: " . $e->getMessage());
            return new jQSelector($selector);
        }
    }

    /**
     * Creates a jQuery/javascript script<br>
     * Crea un script de jQuery/javascript
     * @param string $script
     */
    protected function jScript(string $script): void
    {
        try {
            jQ::Script($script);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jScript: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta un APJSubmit(form, action, ...args) real del lado del
     * cliente, encolando una acción nativa de jQ (misma familia que
     * jInfo/jConfirm) en vez de armar un string para jScript().<br>
     * Runs a real client-side APJSubmit(form, action, ...args) by queueing
     * a native jQ action (same family as jInfo/jConfirm), instead of
     * hand-building a jScript() string.
     * Útil, por ejemplo, para reaplicar los filtros que el usuario ya
     * tiene seleccionados en un formulario de consulta, en vez de
     * recalcular la lista del lado del servidor con el Form actual (o
     * forzarlo a null a mano).
     * @example $this->APJSubmit('filtros', 'filtraVuelos');
     * @param string $form Id del formulario a enviar
     * @param string $action Método del controlador a invocar
     * @param mixed ...$args Argumentos adicionales opcionales
     */
    protected function APJSubmit(string $form, string $action, ...$args)
    {
        try {
            return jQ::APJSubmit($form, $action, $args);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in APJSubmit: " . $e->getMessage());
            return jQ::$jQ;
        }
    }

    /**
     * Ejecuta un APJCall(method, ...args) real del lado del cliente,
     * encolando una acción nativa de jQ - sin formulario, solo el método
     * y argumentos.<br>
     * Runs a real client-side APJCall(method, ...args) by queueing a
     * native jQ action - no form, just the method and arguments.
     * @example $this->APJCall('refrescar', $vuelo);
     * @param string $method Método del controlador a invocar
     * @param mixed ...$args Argumentos adicionales opcionales
     */
    protected function APJCall(string $method, ...$args)
    {
        try {
            return jQ::APJCall($method, $args);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in APJCall: " . $e->getMessage());
            return jQ::$jQ;
        }
    }

    /**
     * Displays a showdown list with result elements<br>
     * Despliega el resultado de una búsqueda en una lista desplegable
     * @param string $input Element to locate the list under
     * @param string $container Container element to display/toggle
     * @param string|null $html Optional HTML content
     */
    protected function jShowDown(string $input, string $container, ?string $html = null): void
    {
        try {
            if ($html) {
                $this->jQ("#{$container}")->html($html);
            }
            jQ::Script("jShowDown('{$input}','{$container}')");
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jShowDown: " . $e->getMessage());
        }
    }
  
    /**
     * Displays an information alert<br>
     * Despliega una alerta de información
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param array $params (optional)
     * @return jQ jInfo
     */
    protected function jInfo(string $message, ?string $title = '', ?string $callback = '', ?array $params = [])
    {
        try {
            return jQ::jInfo($message, $title, $callback, $params);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jInfo: " . $e->getMessage());
            return jQ::jInfo("An error occurred", "Error");
        }
    }

    /**
     * Displays a warning alert<br>
     * Despliega una alerta de advertencia
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param array $params (optional)
     * @return jQ jWarning
     */
    protected function jWarning(string $message, ?string $title = '', ?string $callback = '', ?array $params = [])
    {
        try {
            return jQ::jWarning($message, $title, $callback, $params);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jWarning: " . $e->getMessage());
            return jQ::jWarning("An error occurred", "Warning");
        }
    }

    /**
     * Displays an error alert<br>
     * Despliega una alerta de error
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param array $params (optional)
     * @return jQ jError
     */
    protected function jError(string $message, ?string $title = '', ?string $callback = '', ?array $params = [])
    {
        try {
            return jQ::jError($message, $title, $callback, $params);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jError: " . $e->getMessage());
            return jQ::jError("An error occurred", "Error");
        }
    }

    /**
     * Displays a confirmation alert<br>
     * Despliega una confirmación
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param array $params (optional)
     * @return jQ jConfirm
     */
    protected function jConfirm(string $message, ?string $title = '', ?string $callback = '', ?array $params = [])
    {
        try {
            return jQ::jConfirm($message, $title, $callback, $params);
        } catch (Exception $e) {
            $this->_ErrorLog("Error in jConfirm: " . $e->getMessage());
            return jQ::jConfirm("An error occurred", "Confirmation");
        }
    }

    /**
     * Displays a value prompt<br>
     * Despliega una captura de valor
     * @param string $message Message
     * @param string $title Title (optional)
     * @param string $callback Callback function/method (optional)
     * @return jQ
     */
    protected function jPrompt(string $message, string $title = '', string $callback = '', ?array $params = [])
    {
        return jQ::jPrompt($message, $title, $callback);
    }

    /**
     * Displays a processing alert<br>
     * Despliega una alerta de procesamiento
     * @param string $message Message
     * @param string $title Title (optional)
     * @param string $style Style, can be 'blink' (default none)
     * @return jQ
     */
    protected function jProcess(string $message, ?string $title = '', ?string $style = '')
    {
        return jQ::jProcess($message, $title, $style);
    }

    /**
     * Closes any alert window<br>
     * Cierra cualquier ventana de alerta
     * @return jQ
     */
    protected function jClose()
    {
        return jQ::jClose();
    }

  /**
  * Displays an array of Errors, Warnings or Information<br>
  * Despliega un array de Errores, Advertencias o Información
  * @param (array) $messages Array with messages
  * @param (string) $title Title of message
  * @param (string) $type Can be 'Error', 'Warning' or 'Info'
  */
  protected function showMessages(array $messages, string $title, string $type) {
    $func=array("Error"=>"jError","Info"=>"jInfo","Warning"=>"jWarning");
    $msg="";
    foreach ($messages as $message) {
      $msg.=$message.'<br>';
    }
    $this->{$func[$type]}($msg,$title);
  }

  /**
  * Displays an array of errors in a Error alert (jError)<br>
  * Despliega un array de errores en una alerta de Error (jError)
  * @param (array) $errors Errors messages
  * @param (string) $title Title (optional)
  * @param (array) $alias Fields alias (optional)
  */
  protected function showErrors(array $errors,?string $title = '', ?array $alias = []) {
    $msg="";
    foreach ($errors as $fld=>$err) {
      $fld=$this->_getAlias($fld,$alias);
      $msg.=$fld.": ".$err.'<br>';
    }
    if ($msg) {
      $this->jError($msg,$title);
    }
  }

  /**
  * Displays array of warnings in a Warning alert (jWarning)<br>
  * Despliega un array de advertencias de un modelo en una alerta de Advertencia (jWarning)
  * @param (array) $warnings Warning messages
  * @param (string) $title Title (optional)
  * @param (array) $alias Fields alias (optional)
  */
  protected function showWarnings(array $warnings,?string $title = '',?array $alias=[]) {
    $msg="";
    foreach ($warnings as $fld=>$wrn) {
      $fld=$this->_getAlias($fld,$alias);
      $msg.=$fld.": ".$wrn.'<br>';
    }
    if ($msg) {
      $this->jWarning($msg,$title);
    }
  }
  
    /**
     * Get alias description of a given column<br>
     * Obtiene la descripción de una columna dada
     * @param string $fld Column name
     * @param array $alias Array of alias names
     * @return string
     */
    private function _getAlias(string $fld, ?array $alias = []): string
    {
        if (isset($alias[$fld]) && $alias[$fld] !== '') {
            return $alias[$fld];
        }
        return $fld;
    }

    /**
     * Redirects to other controller<br>
     * Redirige a otro controlador
     * @param string $url Controller URL
     * @param bool $parent From parent location (default false)
     */
    protected function redirect(string $url, bool $parent = false): void
    {
        try {
            if (isset($_REQUEST['action'])) {
                if ($parent) {
                    $this->jScript('parent.location = "' . $url . '"');
                } else {
                    $this->jScript('window.location = "' . $url . '"');
                }
                $this->getResponse();
                exit();
            } else {
                if ($parent) {
                    echo "<script language=javascript> parent.location = '{$url}';</script>";
                } else {
                    header("Location: {$url}");
                }
                die();
            }
        } catch (Exception $e) {
            $this->_ErrorLog("Error in redirect: " . $e->getMessage());
        }
    }    
    
    /**
    * Instance of APJLog for local Error Logging
    * 
    */
    private function _initializeLog():void 
    {
        $this->_Log = new APJLog();        
    }
    
    /**
    * Local Error logging method
    * 
    * @param string $msg Error message
    * @return void
    */
    private function _ErrorLog(string $msg): void
    {
        $this->_Log->errorLog($msg);
    }
}
