<?php
/**
 * Class that sets parameters for jQAction object<br>
 * Clase que define los parámetros del objeto jQAction
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;

class jQAction
{
    /**
     * Sets parameters and value to jQAction object<br>
     * Define los parámetros y valores del objeto jQAction
     * @param string $param Action parameter name
     * @param mixed $value Action parameter value
     * @return jQAction
     */
    public function set(string $param, $value): self
    {
        $this->$param = $value;
        return $this;
    }
}
