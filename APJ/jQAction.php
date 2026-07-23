<?php
/**
 * Class that sets parameters for jQAction object<br>
 * Clase que define los parámetros del objeto jQAction
 * Version: 2.0.2502
 * Author: Ricardo Seiffert
 */
namespace APJ;

class jQAction implements \JsonSerializable
{
    private array $_data = [];

    /**
     * Sets parameters and value to jQAction object<br>
     * Define los parámetros y valores del objeto jQAction
     * @param string $param Action parameter name
     * @param mixed $value Action parameter value
     * @return jQAction
     */
    public function set(string $param, $value): self
    {
        $this->_data[$param] = $value;
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return empty($this->_data) ? new \stdClass() : (object) $this->_data;
    }
}
