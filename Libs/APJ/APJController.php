<?php
/**
 * APJ's parent Controller
 * Controlador padre de APJ
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;
use Libs\APJ\jQSelector;
use Libs\APJ\jQ;

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

    // Common methods Trait
    use APJCommon;

    /**
     * Constructor
     * @param string $page Rendered view
     */
    public function __construct(string $page = '')
    {
        $method = $_POST['action'] ?? null;
        $data = $_POST['data'] ?? $_POST;
        $this->_unsetAction();
        if ($method) {
            if (method_exists($this, $method)) {
                $data = $this->_isJson($data);
                $this->getForm();
                $this->{$method}($data);
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
                $replace = '<head><base href="' . ROOTURL . '/">';
                $html = str_replace('<head>', $replace, $html);
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
     * Controls session<br>
     * Control de sesión
     */
    protected function sessionControl()
    {
        try {
            if (!APJSession::active(APPNAME)) {
                $this->redirect(LOGIN, true);
            }
            APJSession::start(APPNAME, SESSION_LIMIT);
            if (!isset($_SESSION['id']) || !isset($_SESSION['IPaddress']) || !isset($_SESSION['userAgent'])) {
                $this->redirect(LOGIN, true);
            }
            $app = hash('sha256', $this->getController());
            if (in_array($app, $_SESSION['app'], true) === false) {
                $this->redirect(LOGIN, true);
            }
            $this->userId = $_SESSION['id'];
        } catch (Exception $e) {
            error_log("Error in sessionControl: " . $e->getMessage());
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
        $string = trim($params, '[');
        $string = trim($string, ']');
        $string = str_replace(['"', "'"], '', $string);
        return explode(',', $string);
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
            error_log("Error in formToModel: " . $e->getMessage());
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
            error_log("Error in formObjectToModel: " . $e->getMessage());
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
            error_log("Error in modelToForm: " . $e->getMessage());
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
            error_log("Error in arrayToForm: " . $e->getMessage());
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
            error_log("Error in clearForm: " . $e->getMessage());
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
            error_log("Error in createForm: " . $e->getMessage());
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
        if ($form) {
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
                    if (!$this->useParametersAsArray && is_array($data)) {
                        return call_user_func_array([$this, $func], $data);
                    } else {
                        return $this->{$func}($data);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error in _APJCall: " . $e->getMessage());
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
        } catch (Exception $e) {
            error_log("Error in _getContent: " . $e->getMessage());
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
            error_log("Error in getLocalContent: " . $e->getMessage());
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
            error_log("Error in getContent: " . $e->getMessage());
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
                        $paramArray = explode(',', $params);
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
        } catch (Exception $e) {
            error_log("Error in _ApjReplace: " . $e->getMessage());
        }
        return $html;
    }

    /**
     * Generates the <option> elements of a <select> from a given array<br>
     * Genera los <option> de un <select> según array dado
     * @param array $array Array of elements
     * @param string $valueIndex Value index name
     * @param string $textIndex Item index name
     * @param string $selected Default selected item value (optional)
     * @return string <options>
     */
    protected function options(array $array, string $valueIndex, string $textIndex, string $selected = ''): string
    {
        $options = '';
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
            error_log("Error in options: " . $e->getMessage());
        }
        return $options;
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
            error_log("Error in self: " . $e->getMessage());
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
            error_log("Error in getController: " . $e->getMessage());
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
            error_log("Error in paging: " . $e->getMessage());
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
     * Get response for Ajax<br>
     * Obtiene respuesta para Ajax
     */
    protected function getResponse(): void
    {
        try {
            jQ::getResponse();
        } catch (Exception $e) {
            error_log("Error in getResponse: " . $e->getMessage());
        }
    }

    /**
     * Creates a jQuery object selector<br>
     * Crea un objeto selector jQuery
     * @param string $selector
     * @return jQSelector
     */
    protected function jQ(string $selector): jQSelector
    {
        try {
            return jQ::setQuery($selector);
        } catch (Exception $e) {
            error_log("Error in jQ: " . $e->getMessage());
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
            error_log("Error in jScript: " . $e->getMessage());
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
            error_log("Error in jShowDown: " . $e->getMessage());
        }
    }
  
    /**
     * Displays an information alert<br>
     * Despliega una alerta de información
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param string $params (optional)
     * @return jInfo
     */
    protected function jInfo(string $message, string $title = '', string $callback = '', string $params = ''): jInfo
    {
        try {
            return jQ::jInfo($message, $title, $callback, $params);
        } catch (Exception $e) {
            error_log("Error in jInfo: " . $e->getMessage());
            return jQ::jInfo("An error occurred", "Error");
        }
    }

    /**
     * Displays a warning alert<br>
     * Despliega una alerta de advertencia
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param string $params (optional)
     * @return jWarning
     */
    protected function jWarning(string $message, string $title = '', string $callback = '', string $params = ''): jWarning
    {
        try {
            return jQ::jWarning($message, $title, $callback, $params);
        } catch (Exception $e) {
            error_log("Error in jWarning: " . $e->getMessage());
            return jQ::jWarning("An error occurred", "Warning");
        }
    }

    /**
     * Displays an error alert<br>
     * Despliega una alerta de error
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param string $params (optional)
     * @return jError
     */
    protected function jError(string $message, string $title = '', string $callback = '', string $params = ''): jError
    {
        try {
            return jQ::jError($message, $title, $callback, $params);
        } catch (Exception $e) {
            error_log("Error in jError: " . $e->getMessage());
            return jQ::jError("An error occurred", "Error");
        }
    }

    /**
     * Displays a confirmation alert<br>
     * Despliega una confirmación
     * @param string $message
     * @param string $title (optional)
     * @param string $callback (optional)
     * @param string $params (optional)
     * @return jConfirm
     */
    protected function jConfirm(string $message, string $title = '', string $callback = '', string $params = ''): jConfirm
    {
        try {
            return jQ::jConfirm($message, $title, $callback, $params);
        } catch (Exception $e) {
            error_log("Error in jConfirm: " . $e->getMessage());
            return jQ::jConfirm("An error occurred", "Confirmation");
        }
    }

    /**
     * Displays a value prompt<br>
     * Despliega una captura de valor
     * @param string $message Message
     * @param string $title Title (optional)
     * @param string $callback Callback function/method (optional)
     * @param string $params Javascript array format of callback parameters (optional)
     * @return jQ
     */
    protected function jPrompt(string $message, string $title = '', string $callback = '', string $params = ''): jQ
    {
        return jQ::jPrompt($message, $title, $callback, $params);
    }

    /**
     * Displays a processing alert<br>
     * Despliega una alerta de procesamiento
     * @param string $message Message
     * @param string $title Title (optional)
     * @param string $style Style, can be 'blink' (default none)
     * @return jQ
     */
    protected function jProcess(string $message, string $title = '', string $style = ''): jQ
    {
        return jQ::jProcess($message, $title, $style);
    }

    /**
     * Closes any alert window<br>
     * Cierra cualquier ventana de alerta
     * @return jQ
     */
    protected function jClose(): jQ
    {
        return jQ::jClose();
    }

  /**
  * Displays an array of Errors, Warnings or Information<br>
  * Despliega un array de Errores, Advertencias o Información
  * @param (array) Arrays with messages
  * @param (string) Title of message
  * @param (string) Can be 'Error', 'Warning' or 'Info'
  */
  protected function showMessages($messages,$title,$type) {
    $func=array("Error"=>"jError","Info"=>"jInfo","Warning"=>"jWarning");
    $msg="";
    foreach ($messages as $message) {
      $msg.=$message.'<br>';
    }
    $this->$func[$type]($msg,$title);
  }

  /**
  * Displays an array of errors in a Error alert (jError)<br>
  * Despliega un array de errores en una alerta de Error (jError)
  * @param (array) Errors messages
  * @param (string) Title
  * @param (array) Fields alias (optional)
  */
  protected function showErrors($errors,$title,$alias) {
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
  * @param (array) Warning messages
  * @param (string) Title
  * @param (array) Fields alias (optional)
  */
  protected function showWarnings($warnings,$title,$alias=NULL) {
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
    private function _getAlias(string $fld, array $alias): string
    {
        return $alias[$fld] ?? $fld;
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
            } else {
                if ($parent) {
                    echo "<script language=javascript> parent.location = '{$url}';</script>";
                } else {
                    header("Location: {$url}");
                }
                die();
            }
        } catch (Exception $e) {
            error_log("Error in redirect: " . $e->getMessage());
        }
    }
}
