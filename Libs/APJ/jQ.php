<?php
/**
 * Static class that responds to jQuery from PHP<br>
 * Clase Estática que responde acciones jQuery desde PHP
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;
use Libs\APJ\jQAction;

class jQ
{
    /**
     * Static class instance container<br>
     * Contenedor estático de la instancia de la clase
     * @var jQ
     */
    public static $jQ;

    /**
     * Array with query/actions responses<br>
     * Array de respuestas de queries/acciones 
     * @var mixed
     */
    public $response = [
        'action' => [],
        'query' => []
    ];

    /**
     * Initializes the static container with a new instance of the class (Singleton pattern)<br>
     * Inicializa el contenedor estático con una nueva instancia de la clase (patrón Singleton)
     */
    public static function init(): bool
    {
        if (empty(self::$jQ)) {
            self::$jQ = new self();
        }
        return true;
    }

    /**
     * Adds data to the response<br>
     * Agrega un dato a la respuesta
     * @param mixed $key Key Value
     * @param mixed $value Value Key
     * @param string|null $callBack Callback function (optional)
     * @return jQ
     */
    public static function setData($key, $value, ?string $callBack = null): self
    {
        self::init();
        $jQAction = new jQAction();
        $jQAction->set('key', $key);
        $jQAction->set('value', $value);
        if ($callBack) {
            $jQAction->set('callback', $callBack);
        }
        self::setAction(__FUNCTION__, $jQAction);
        return self::$jQ;
    }

    /**
     * Adds a script to response<br>
     * Agrega un script a la respuesta
     * @param mixed $script Script
     * @return jQ
     */
    public static function Script($script): self
    {
        self::init();
        $jQAction = new jQAction();
        $jQAction->set('scr', $script);
        self::setAction(__FUNCTION__, $jQAction);
        return self::$jQ;
    }

    /**
     * Outputs the response in JSON and terminates execution<br>
     * Genera la respuesta en JSON y termina la ejecución
     */
    public static function getResponse(): void
    {
        self::init();
        echo json_encode(self::$jQ->response);
        exit;
    }

    /**
     * Instance a new jQ selector<br>
     * Instancia un nuevo selector
     * @param string $selector jQuery style selector
     * @return jQSelector instance of the selector
     */
    public static function setQuery(string $selector): jQSelector
    {
        self::init();
        return new jQSelector($selector);
    }

    /**
     * Adds a selector by reference to response queue<br>
     * Agrega un selector por referencia a la cola de respuesta
     * @param jQSelector $jQSelector
     */
    public static function setSelector(jQSelector &$jQSelector): void
    {
        self::init();
        self::$jQ->response['query'][] = $jQSelector;
    }

    /**
     * Adds an action by reference to the action queue<br>
     * Agrega una acción por referencia a la cola de acciones
     * @param string $name Method name
     * @param jQAction $jQAction action object
     */
    public static function setAction(string $name, jQAction &$jQAction): void
    {
        self::init();
        self::$jQ->response['action'][$name][] = $jQAction;
    }

    /**
     * Information alert window<br>
     * Ventana de alerta de información
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $callBack Callback method/function (optional)
     * @param array|null $params Array of callback parameters (optional)
     * @return jQ
     */
    public static function jInfo(string $msg, ?string $title = null, ?string $callBack = null, ?array $params = null): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, $callBack, $params, null);
    }

    /**
     * Warning alert window<br>
     * Ventana de alerta de advertencia
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $callBack Callback method/function (optional)
     * @param array|null $params Array of callback parameters (optional)
     * @return jQ
     */
    public static function jWarning(string $msg, ?string $title = null, ?string $callBack = null, ?array $params = null): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, $callBack, $params, null);
    }

    /**
     * Error alert window<br>
     * Ventana de alerta de error
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $callBack Callback method/function (optional)
     * @param array|null $params Array of callback parameters (optional)
     * @return jQ
     */
    public static function jError(string $msg, ?string $title = null, ?string $callBack = null, ?array $params = null): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, $callBack, $params, null);
    }

    /**
     * Confirmation window<br>
     * Ventana de confirmación
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $callBack Callback method/function (optional)
     * @param array|null $params Array of callback parameters (optional)
     * @return jQ
     */
    public static function jConfirm(string $msg, ?string $title = null, ?string $callBack = null, ?array $params = null): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, $callBack, $params, null);
    }

    /**
     * Data prompt window<br>
     * Ventana de petición de datos
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $callBack Callback method/function (optional)
     * @return jQ
     */
    public static function jPrompt(string $msg, ?string $title = null, ?string $callBack = null): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, $callBack, null, null);
    }

    /**
     * Process information window<br>
     * Ventana de información de proceso
     * @param string $msg Message
     * @param string|null $title Title (optional)
     * @param string|null $style 'Blink' for blinking message (optional)
     * @return jQ
     */
    public static function jProcess(string $msg, ?string $title = null, ?string $style = 'blink'): self
    {
        return self::_jAlert(__FUNCTION__, $msg, $title, null, null, $style);
    }

    private static function _jAlert(string $function, string $msg, ?string $title = null, ?string $callBack = null, ?array $params = null, ?string $style = null): self
    {
        self::init();
        $jQAction = new jQAction();
        $jQAction->set('msg', $msg);
        if ($title) {
            $jQAction->set('title', $title);
        }
        if ($callBack) {
            $jQAction->set('callback', $callBack);
        }
        if ($params) {
            $jQAction->set('params', $params);
        }
        if ($style) {
            $jQAction->set('style', $style);
        }
        self::setAction($function, $jQAction);
        return self::$jQ;
    }

    /**
     * Closes alert/information windows<br>
     * Cierra ventanas de alert/información
     * @return jQ
     */
    public static function jClose(): self
    {
        self::setAction(__FUNCTION__, null);
        return self::$jQ;
    }
}
