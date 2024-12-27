<?php
/**
 * Class that defines the jQuery selector for jQ
 * Clase que define el selector jQuery para jQ
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;
use Libs\APJ\jQ;

class jQSelector
{
    public $selector;
    public $method = [];
    public $arguments = [];

    /**
     * jQSelector constructor<br>
     * Constructor de jQselector
     * @param string $selector
     */
    public function __construct(string $selector)
    {
        jQ::setSelector($this);
        $this->selector = $selector;
    }

    /**
     * Calls to inexistent methods (Overloading)<br>
     * Invoca métodos inexistentes (Sobrecarga)
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return jQSelector
     */
    public function __call(string $method, array $arguments): self
    {
        $this->method[] = $method;
        $this->arguments[] = $arguments;
        return $this;
    }

    /**
     * Returns a new instance of jQSelector<br>
     * Devuelve una nueva instancia de jQSelector
     * @return jQSelector new jQSelector from $this->selector
     */
    public function newSelector(): self
    {
        return new self($this->selector);
    }
}
