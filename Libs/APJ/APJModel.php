<?php
/*
 * APJ Base Model that extends APJPDO class<br>
 * Modelo de base que extiende la clase APJPDO
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;
use Libs\APJ\APJPDO;

class APJModel extends APJPDO
{
    /**
     * Table name<br>
     * Nombre de la Tabla
     * @var string
     */
    public $table = null;
    /**
     * Primary key<br>
     * Clave primaria
     * @var array
     */
    public $pk = [];
    /**
     * Primary key full name<br>
     * Ruta completa de la clave primaria 
     * @var array
     */
    public $fullPk = [];
    /**
     * Columns values<br>
     * Valores de columnas
     * @var array
     */
    public $variables = [];
    /**
     * Model structure<br>
     * Estructura del modelo
     * @var array
     */
    public $structure = [];
    /**
     * Model columns<br>
     * Columnas del modelo
     * @var array
     */
    public $fields = [];
    /**
     * Defines if columns must be trimmed<br>
     * Define si se recortan los valores de las columnas
     * @var bool
     */
    public $trim = false;
    /**
     * Errors array<br>
     * Array de errores 
     * @var array
     */
    public $errors = [];
    /**
     * Columns Alias/Comment<br>
     * Alias/Comentario de las columnas
     * @var array
     */
    public $alias = [];
    /**
     * Values in associative array for update, insert and others<br>
     * Array asociativo de valores para update, insert y otros
     * @var array|null
     */
    private $values = null;
    
    /**
     * Conditions for update, delete and find (associative array or literal condition)<br>
     * Condicione para update, delete y find
     * @var array|string|null
     */
    private $where = null;

    /**
     * Array with join instructions for query methods<br>
     * Arreglo con instrucciones join para los métodos de consultas
     * @var array
     */
    private $joins = [];
    
    /**
     * Limit of rows for query methods<br>
     * Límite de filas para los métodos de consultas
     * @var int|null
     */
    private $limit = null;
    
    /**
     * Query order<br>
     * Orden de la consulta
     * @var string|null
     */
    private $order = null;
    
    /**
     * Define charset to use<br>
     * Define el juego de caracteres a usar
     * @var string
     */
    private $charset = 'utf-8';

    /**
     * Array of columns to be stored in lowercase<br>
     * Array asociativo de columnas que deben guardarse en minúsculas
     * @var array
     */
    public $toLower = [];

    /**
     * Array of columns to be stored in uppercase<br>
     * Array asociativo de columnas que deben guardarse en mayúsculas
     * @var array
     */
    public $toUpper = [];
    
    /**
     * Paging properties<br>
     * Propiedades de paginación
     * @var int
     */
    public $lastPage = 0;
    public $currentPage = 0;
    public $previousPage = 0;
    public $nextPage = 0;
    
    private $quotes = ["'", '"'];
    
    // Common methods Trait
    use APJCommon;
    
    /**
     * Constructor (connects to database)<br>
     * Constructor (conecta con la base de datos)
     * @param string|null $dsn Data Source Name
     * @param string|null $user Username
     * @param string|null $password Password
     * @param string|null $charset Character Set
     */
    public function __construct(string $dsn = null, string $user = null, string $password = null, string $charset = null)
    {
        parent::__construct($dsn, $user, $password, $charset);
        $this->_clearError();
    }

    /**
     * Defines the table name and reads model<br>
     * Define el nombre de la tabla y lee el modelo
     * @param string $table Table name
     */
    public function setTable(string $table): void
    {
        $this->_clearError();
        $this->table = $table;
        $this->_defineModel();
    }

    /**
     * Set the columns Alias<br>
     * Define los Alias de las columnas
     * @param array $names Array of alias names ('name' => 'Alias', ...)
     */
    public function setAlias(array $names): void
    {
        $i = 0;
        foreach ($this->fields as $fld) {
            $this->alias[$fld] = $names[$i];
            $i++;
        }
    }

    /**
     * Extracts and define the model structure<br>
     * Extrae y define la estructura del modelo
     */
    private function _defineModel(): void
    {
        $sql = "SHOW FULL COLUMNS FROM `{$this->table}`";
        if ($struc = $this->rows($sql)) {
            foreach ($struc as $str) {
                $type = $this->_type($str['Type']);
                $size = $this->_size($type, $str['Type']);
                $this->structure[$str['Field']]['Type'] = $type;
                $this->structure[$str['Field']]['Size'] = $size[0];
                $this->structure[$str['Field']]['Decimals'] = $size[1];
                $this->structure[$str['Field']]['Null'] = $str['Null'];
                $this->structure[$str['Field']]['Key'] = $str['Key'];
                $this->structure[$str['Field']]['Default'] = $str['Default'];
                $this->structure[$str['Field']]['Extra'] = $str['Extra'];
                $this->structure[$str['Field']]['Comment'] = $str['Comment'];
                $this->alias[$str['Field']] = $str['Comment'];
                if ($str['Key'] === "PRI") {
                    $this->pk[] = $str['Field'];
                    $this->fullPk[] = "`{$this->table}`." . $str['Field'];
                }
            }
            $this->fields = array_keys($this->structure);
        }
    }

    /**
     * Extracts a foreign table structure<br>
     * Extrae la estructura de una tabla foránea
     * @param string $table Table name
     * @return array|false Structure array or false on failure
     */
    private function _foreignModel(string $table)
    {
        $sql = "SHOW FULL COLUMNS FROM " . $table;
        if ($structure = $this->rows($sql)) {
            return $structure;
        }
        return false;
    }

    /**
     * Show table structure<br>
     * Muestra la estructura de la tabla
     */
    public function showStructure(): void
    {
        $out = '<textarea rows="30" cols="120">';
        $out .= '$this->table = "' . $this->table . '";' . PHP_EOL;
        $out .= '$this->structure = array(';
        foreach ($this->structure as $fld => $infos) {
            $out .= "'{$fld}' => array(";
            foreach ($infos as $info => $value) {
                $sep = '';
                if ($value === null || is_numeric(trim((string)$value))) {
                    if ($value === null) {
                        $value = 'NULL';
                    }
                } else {
                    $sep = "'";
                }
                $out .= "'{$info}' => " . $sep . $value . $sep . ',';
            }
            $out = substr($out, 0, -1);
            $out .= "),";
        }
        $out = substr($out, 0, -1);
        $out .= ');' . PHP_EOL;
        if ($this->alias) {
            $out .= '$this->alias = array(';
            foreach ($this->alias as $fld => $comment) {
                $out .= "'{$fld}' => '{$comment}',";
            }
            $out = substr($out, 0, -1);
            $out .= ');' . PHP_EOL;
        }
        if ($this->pk) {
            $out .= '$this->pk = array(';
            foreach ($this->pk as $pk) {
                $out .= "'{$pk}',";
            }
            $out = substr($out, 0, -1);
            $out .= ');' . PHP_EOL;
        }
        if ($this->fullPk) {
            $out .= '$this->fullPk = array(';
            foreach ($this->fullPk as $pk) {
                $out .= "'{$pk}',";
            }
            $out = substr($out, 0, -1);
            $out .= ');' . PHP_EOL;
        }
        $out .= '$this->fields = array_keys($this->structure);';
        $out .= '</textarea>';
        echo $out;
        exit();
    }
  
    /**
     * Show table model in a textarea (for documentation)<br>
     * Muestra el modelo de la tabla en un textarea (para documentación)
     * @param bool $short Short description (default false)
     */
    public function showModel(bool $short = false): void
    {
        $this->_clearError();
        $arr = [];
        $tab = [];
        $tr = '';
        $sizes = [];
        $arr[0]['Field'] = ' Field ';
        foreach ($this->structure as $fld => $str) {
            foreach ($str as $key => $value) {
                if ($short && ($key === "Comment" || $key === "Extra")) {
                    break; 
                } else {
                    $arr[0][$key] = " {$key} ";
                }
            }
            break;
        }
        $inx = 1;
        foreach ($this->structure as $fld => $str) {
            $arr[$inx]['Field'] = " {$fld} ";
            foreach ($str as $key => $value) {
                if ($short && ($key === "Comment" || $key === "Extra")) {
                    break; 
                } else {
                    $arr[$inx][$key] = " {$value} ";
                }
            }
            $inx++;
        }
        foreach ($arr as $ar) {
            foreach ($ar as $k => $a) {
                $size = strlen($a);
                if (!isset($sizes[$k]) || $sizes[$k] < $size) {
                    $sizes[$k] = $size;
                }
            }
        }
        foreach ($sizes as $size) {
            $tr .= '+' . str_repeat('-', $size);
        }
        $tr .= '+';
        foreach ($arr as $inx => $fld) {
            foreach ($fld as $key => $value) {
                if ($inx == 0) {
                    $align = STR_PAD_BOTH;
                } elseif (is_numeric(trim($value))) {
                    $align = STR_PAD_LEFT;
                } else {
                    $align = STR_PAD_RIGHT;
                }
                $tab[$inx] .= '|' . str_pad($value, $sizes[$key], " ", $align);
            }
            $tab[$inx] .= '|';
        }
        $out = '<textarea rows="30" cols="120">';
        $out .= '/*' . PHP_EOL;
        $out .= 'Table structure of [' . $this->table . ']' . PHP_EOL;
        $out .= $tr . PHP_EOL;
        foreach ($tab as $k => $row) {
            $out .= $row . PHP_EOL;
            if ($k == 0) {
                $out .= $tr . PHP_EOL;
            }
        }
        $out .= $tr . PHP_EOL;
        $out .= '*/' . PHP_EOL;
        $out .= '</textarea>';
        echo $out;
        exit();
    }

    /**
     * Set model column properties and value, if exist in structure (overloading)<br>
     * Crea la columna del modelo con su valor, si existe en la estructura (sobrecarga) 
     * @param string $name Column name
     * @param mixed $value Column value
     */
    public function __set(string $name, $value): void
    {
        if (isset($this->structure[$name]['Type'])) {
            $value = $this->_upperLower($name, $value);
            $this->variables[$name] = $this->getValue($value, $this->structure[$name]['Type'], $this->trim);
            return;
        }
        $trace = debug_backtrace();
        trigger_error(
            'Propiedad indefinida mediante __set(): ' . $name .
            ' en ' . $trace[0]['file'] .
            ' en la línea ' . $trace[0]['line'],
            E_USER_NOTICE
        );
    }

    /**
     * Converts the values of the specified columns to lowercase or uppercase<br>
     * Convierte a minúsculas o mayúsculas los valores de las columnas especificadas
     * @param string $name Column name
     * @param mixed $value Column value
     * @return mixed New value
     */
    private function _upperLower(string $name, $value)
    {
        if (in_array($name, $this->toUpper, true)) {
            return mb_strtoupper($value, $this->charset);
        }
        if (in_array($name, $this->toLower, true)) {
            return mb_strtolower($value, $this->charset);
        }
        return $value;
    }
  
    /**
     * Get model columns values (overloading)<br>
     * Obtiene los valores de las columnas del modelo (sobrecarga)
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (is_array($this->variables)) {
            if (array_key_exists($name, $this->variables)) {
                return $this->variables[$name];
            }
        }
        $trace = debug_backtrace();
        trigger_error(
            'Propiedad indefinida mediante __get(): ' . $name .
            ' en ' . $trace[0]['file'] .
            ' en la línea ' . $trace[0]['line'],
            E_USER_NOTICE
        );
        return null;
    }

    /**
     * Basic columns validation by model and set errors property<br>
     * Validación básica según modelo y rellena la propiedad errors
     * @return bool true = has errors, false = no errors
     */
    public function basicValidation(): bool
    {
        $this->_clearError();
        if (is_array($this->structure)) {
            foreach ($this->fields as $fld) {
                $var = $this->variables[$fld];
                $type = $this->structure[$fld]['Type'];
                $size = $this->structure[$fld]['Size'];
                $decimals = $this->structure[$fld]['Decimals'];
                $null = $this->structure[$fld]['Null'];
                $default = $this->structure[$fld]['Default'];
                $extra = $this->structure[$fld]['Extra'];
                $comment = $this->structure[$fld]['Comment']; 
                if (!in_array($fld, $this->pk, true) && $null === 'NO' && strlen($var) == 0 && strlen($default) > 0) {
                    $var = $default;
                    $this->variables[$fld] = $default;
                }
                switch (true) {
                    case (in_array($fld, $this->pk, true) && $extra !== 'auto_increment' && (is_null($var) || strlen($var) == 0)):
                    case (!in_array($fld, $this->pk, true) && $null === 'NO' && strlen($var) == 0 && strlen($default) == 0):
                        $this->errors[$fld] = "no puede estar vacío";
                        break;
                    case ($size && iconv_strlen($var) > $size):
                        $this->errors[$fld] = "excede el tamaño definido ({$size}).";
                        break;
                }
                if (!in_array($fld, $this->pk, true) && strlen($var) > 0) {
                    switch ($type) {
                        case 'int':
                        case 'bigint':
                        case 'tinyint':
                        case 'smallint':
                        case 'boolean':
                            if (!is_numeric($var)) {
                                $this->errors[$fld] = "no es un número entero.";
                            }
                            break;
                        case 'date':
                            if (!$this->verifyDate($var, 'Y-m-d', true)) {
                                $this->errors[$fld] = "no es una fecha válida";
                            }
                            break;
                        case 'datetime':
                            if (!$this->verifyDate($var, 'Y-m-d H:i:s', false)) {
                                $this->errors[$fld] = "no es una fecha/hora válida";
                            }
                            break;
                        case 'timestamp':
                            if (!$this->verifyDate($var, 'timestamp', true)) {
                                $this->errors[$fld] = "no es una fecha/hora válida";
                            }
                            break;
                        case 'time':
                            if (!$this->verifyDate($var, 'H:i:s', false)) {
                                $this->errors[$fld] = "no es una hora válida";
                            }
                            break;
                        case 'year':
                            if (!$this->verifyDate($var, 'Y', true)) {
                                $this->errors[$fld] = "no es un año válido";
                            }
                            break;
                    }
                }
            }
        }
        if (count($this->errors) > 0) {
            $this->error = true;
        }
        return (count($this->errors) > 0);
    }

    /**
     * Assigns object/array values to model matching columns<br>
     * Asigna los valores de un objeto/arreglo a las columnas del modelo
     * @param mixed $object
     */
    protected function objectToModel($object): void
    {
        if (is_array($object) || is_object($object)) {
            foreach ($object as $name => $value) {
                if (in_array($name, $this->fields, true)) {
                    $this->$name = $value;
                }
            }
        }
    }

    /**
     * Formats columns values according to data type defined in init.php file<br>
     * Formatea los valores de las columnas según los tipos de datos definidos en el archivo init.php
     * @param array $row Row array
     * @return array Formatted row array
     */
    public function setFormat(array $row): array
    {
        $rowf = [];
        if (is_array($row)) {
            foreach ($row as $fld => $value) {
                $this->$fld = $value;
                $type = $this->structure[$fld]['Type'];
                $rowf[$fld] = $this->format($value, $type);
            }
        }
        return $rowf;
    }

    /**
     * Update table<br>
     * Actualiza tabla
     * @param mixed $where Where condition as array or string (optional if primary key is defined)
     * @param array $values Columns values in associative array (optional if values are defined)
     * @return mixed Number of affected rows or false if any error
     */
    public function update($where = '', $values = ''): int|bool
    {
        $this->_clearError();
        $fieldsvals = '';
        if ($this->_values($values)) {
            foreach ($this->values as $column => $val) {
                $fieldsvals .= $column . " = :" . $column . ",";
            }
            $fieldsvals = substr($fieldsvals, 0, -1);
            if ($this->_condition($where, true)) {
                $sql = "UPDATE " . $this->table .  " SET " . $fieldsvals . $this->_queryWhere();
                return $this->execute($sql, $this->values);
            }
        }
        return false;
    }

    /**
     * Insert into table<br>
     * Insertar en la tabla
     * @param bool $ignore Ignore duplicate rows (default false)
     * @param array|null $values Associative array of values (optional if values are set)
     * @return mixed Number of affected rows or false if any error
     */
    public function insert(bool $ignore = false, ?array $values = null): int|bool
    {
        $this->_clearError();
        if ($this->_values($values)) {
            $fields = '';
            $fieldsvals = '';
            foreach ($this->values as $column => $val) {
                $fields .= "{$column},";
                $fieldsvals .= ",:{$column}";
            }
            $fields = substr($fields, 0, -1);
            $fieldsvals = substr($fieldsvals, 1);
            $ignsql = ($ignore) ? " IGNORE " : " ";
            $sql = "INSERT{$ignsql}INTO `{$this->table}` ({$fields}) VALUES ({$fieldsvals})";
            return $this->execute($sql, $this->values);
        }
        return false;
    }

    /**
     * Replace into table<br>
     * Reemplaza en la tabla
     * @param array|string $values Columns values in associative array or string
     * @return mixed Number of affected rows or false if any error
     */
    public function replace($values = ''): int|bool
    {
        $this->_clearError();
        if ($this->_values($values, true)) {
            $fields = '';
            $fieldsvals = '';
            foreach ($this->values as $column => $val) {
                $fields .= "{$column},";
                $fieldsvals .= ",:{$column}";
            }
            $fields = substr($fields, 0, -1);
            $fieldsvals = substr($fieldsvals, 1);
            $sql = "REPLACE INTO `{$this->table}` ({$fields}) VALUES ({$fieldsvals})";
            return $this->execute($sql, $this->values);
        }
        return false;
    }

    /**
     * Deletes a row<br>
     * Elimina una fila
     * @param mixed $where Array/int or WHERE string condition (optional if primary key value is set)
     * @return mixed Number of affected rows or false if any error
     */
    public function delete($where = '')
    {
        $this->_clearError();
        if ($this->_condition($where, true)) {
            $sql = "DELETE FROM `{$this->table}` {$this->_queryWhere()}";
            return $this->execute($sql, $this->values);
        }
        return false;
    }

    /**
     * Truncate a table<br>
     * Trunca una tabla
     * @param string|null $table
     * @return mixed
     */
    public function truncate(?string $table = null)
    {
        $this->_clearError();
        $table = $table ? $table : $this->table;
        return $this->query("TRUNCATE TABLE `{$table}`");
    }  

    /**
     * Drop a table<br>
     * Elimina una tabla
     * @param bool $temporary Is a temporary table (optional)
     * @param string|null $table Name of the table, if not specified it will be the current table (optional) 
     * @return mixed
     */
    public function drop(bool $temporary = false, ?string $table = null)
    {
        $this->_clearError();
        $table = $table ? $table : $this->table;
        $temp = $temporary ? 'TEMPORARY' : '';
        return $this->query("DROP {$temp} TABLE IF EXISTS `{$table}`");
    }

    /**
     * Find a row and assigns values<br>
     * Encuentra una fila y asigna los valores
     * @param mixed $where Array or string where condition (optional if primary key value is set)
     * @return array|false Associative row array, false if error
     */
    public function find($where = '')
    {
        $this->_clearError();
        if ($this->_condition($where, true)) {
            $sql = $this->_querySelect() . $this->_queryWhere() . $this->_queryOrder() . $this->_queryLimit();
            return $this->variables = $this->row($sql, $this->values);
        }
        return false;
    }

    /**
     * Returns all rows that meet the condition in LIKE<br>
     * Devuelve todas las filas que cumplen la condición LIKE
     * @param string $field Column
     * @param string $search Search condition
     * @return array Result array
     */
    public function like(string $field, string $search): array
    {
        $this->_clearError();
        if (in_array($field, $this->fields, true) && $search) {
            return $this->rows($this->_querySelect() . " WHERE {$field} LIKE '{$search}'" . $this->_queryLimit());
        }
        return [];
    }
  
    /**
     * Returns all table rows by given order<br>
     * Devuelve todas las filas de la tabla según orden dado
     * @param string $order Comma separated order columns (optional)
     * @return mixed Associative rows array or false if any error
     */
    public function all(string $order = '')
    {
        $this->_clearError();
        $order = ($order) ? " ORDER BY {$order}" : $this->_queryOrder();
        return $this->rows($this->_querySelect() . $order . $this->_queryLimit());
    }

    /**
     * Returns all rows for given condition<br>
     * Devuelve todas las filas para la condición dada
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @param string $order Comma separated order columns (optional)
     * @return mixed Associative rows array or false if any error
     */
    public function select($condition = '', string $order = '')
    {
        $this->_clearError();
        $order = ($order) ? " ORDER BY {$order}" : $this->_queryOrder();
        if ($this->_condition($condition, false)) {
            return $this->rows($this->_querySelect() . $this->_queryWhere() . $order . $this->_queryLimit(), $this->values);
        }
        return false;
    }

    /**
     * Joins query statements
     * @return string
     */
    private function _queryJoin(): string
    {
        $joins = "";
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $joins .= $join . " ";
            }
        }
        return $joins;
    }

    /**
     * Create a SELECT statement
     * @return string
     */
    private function _querySelect(): string
    {
        return "SELECT {$this->_queryColumns()} FROM `{$this->table}`" . $this->_queryJoin();
    }

    /**
     * Returns the columns of a query
     * @return string
     */
    private function _queryColumns(): string
    {
        $columns = "*";
        if (count($this->joins) > 0) {
            $columns = "";
            foreach ($this->fields as $field) {
                $columns .= "`{$this->table}`.{$field}, ";
            }
            $fieldArray = $this->fields;
            $cumul = [];
            $as = [];
            foreach ($this->joins as $table => $join) {
                if ($fields = $this->_foreignModel($table)) {
                    foreach ($fields as $field) {
                        $col = $field['Field'];
                        $columns .= "`{$table}`.{$col}";
                        if (in_array($col, $fieldArray, true)) {
                            $cumul[$col]++;
                            $as = $col . $cumul[$col];
                            $columns .= " AS {$as}";
                        }
                        $columns .= ", ";
                        $fieldArray[] = $col;
                    }
                }
            }
            $columns = substr($columns, 0, -2);
        }
        return $columns;
    }

    /**
     * Completes the WHERE statement
     * @return string
     */
    private function _queryWhere(): string
    {
        return $this->where ? " WHERE " . $this->where : "";
    }

    /**
     * Completes the ORDER BY statement
     * @return string
     */
    private function _queryOrder(): string
    {
        return $this->order ? " ORDER BY " . $this->order : "";
    }
  
    /**
     * Sets a foreign table Join for query methods<br>
     * Define un Join con tablas foráneas en los métodos de consultas
     * @param string $table Foreign table name
     * @param string $column Local column 
     * @param string $comparator Column comparator
     * @param string $foreignKey Foreign Key column to compare with
     * @param string $type Type of join (INNER, LEFT, RIGHT) Default: INNER
     * @return bool
     */
    public function join(string $table, string $column, string $comparator, string $foreignKey, string $type = "INNER"): bool
    {
        $types = ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER'];
        $type = strtoupper($type);
        if ($table && in_array($type, $types, true)) {
            $join = " {$type} JOIN `{$table}` ON (";
            if ($column) {
                $column = (strpos($column, '.') > 0) ? $column : "`{$this->table}`.{$column}";
                $join .= $column;
                if ($comparator) {
                    $join .= " {$comparator} ";
                    if ($foreignKey) {
                        $column = (strpos($foreignKey, '.') > 0) ? $foreignKey : "`{$table}`.{$foreignKey}";
                        $join .= "{$column})";
                        $this->joins[$table] = $join;
                        return true;
                    }
                }
            }
        }
        $this->error = true;
        $this->errormsg = "Error defining the join";
        return false;
    }  

    /**
     * Sets query limits<br>
     * Define los límites de consultas
     * @return string
     */
    private function _queryLimit(): string
    {
        return $this->limit > 0 ? " LIMIT {$this->limit}" : "";
    }

    /**
     * Sets query limit for query methods<br>
     * Define el límite para los métodos de consulta
     * @param mixed $limit
     */
    public function limit($limit): void
    {
        $this->limit = $limit;
    }  
     
    /**
     * Clear columns values<br>
     * Limpia los valores de las columnas
     */
    public function clearValues(): void
    {
        $this->variables = [];
    }

    /**
     * Clear foreign joins<br>
     * Limpia los joins foráneos
     */
    public function clearJoins(): void
    {
        $this->joins = [];
    }

    /**
     * Returns the minimum value of a column<br>
     * Devuelve el valor mínimo de una columna
     * @param string $field Column name
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @return mixed Min column value
     */
    public function min(string $field, $condition = null)
    {
        $this->_clearError();
        $params = "";
        if ($this->_condition($condition, false)) {
            $params = $this->values;
        }
        if ($row = $this->row("SELECT MIN({$field}) FROM `{$this->table}` {$this->_queryWhere()}", $params, PDO::FETCH_NUM)) {
            return $row[0];
        }
        return false;
    }

    /**
     * Returns the maximum value of a field<br>
     * Devuelve el valor máximo de un campo
     * @param string $field Column name
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @return mixed Max column value
     */
    public function max(string $field, $condition = null)
    {
        $this->_clearError();
        $params = "";
        if ($this->_condition($condition, false)) {
            $params = $this->values;
        }
        if ($row = $this->row("SELECT MAX({$field}) FROM `{$this->table}` {$this->_queryWhere()}", $params, PDO::FETCH_NUM)) {
            return $row[0];
        }
        return false;
    }

    /**
     * Returns the average value of a field<br>
     * Devuelve el valor promedio de un campo
     * @param string $field Column name
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @return mixed Average column value
     */
    public function avg(string $field, $condition = null)
    {
        $this->_clearError();
        $params = "";
        if ($this->_condition($condition, false)) {
            $params = $this->values;
        }
        if ($row = $this->row("SELECT AVG({$field}) FROM `{$this->table}` {$this->_queryWhere()}", $params, PDO::FETCH_NUM)) {
            return $row[0];
        }
        return false;
    }

    /**
     * Returns the sum of a field<br>
     * Devuelve la suma de un campo
     * @param string $field Column name
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @return mixed Sum of the column values
     */
    public function sum(string $field, $condition = null)
    {
        $this->_clearError();
        $params = "";
        if ($this->_condition($condition, false)) {
            $params = $this->values;
        }
        if ($row = $this->row("SELECT SUM({$field}) FROM `{$this->table}` {$this->_queryWhere()}", $params, PDO::FETCH_NUM)) {
            return $row[0];
        }
        return false;
    }

    /**
     * Returns the count of a field<br>
     * Devuelve la cuenta de un campo
     * @param string $field Column name
     * @param mixed $condition Array or string where condition (optional if values are set)
     * @return int Count of column values
     */
    public function count(string $field, $condition = null): int
    {
        $this->_clearError();
        $params = "";
        if ($this->_condition($condition, false)) {
            $params = $this->values;
        }
        if ($row = $this->row("SELECT COUNT({$field}) FROM `{$this->table}` {$this->_queryWhere()}", $params, PDO::FETCH_NUM)) {
            return (int) $row[0];
        }
        return 0;
    }

    /**
     * Returns an array of paged results<br>
     * Devuelve un array con resultado paginados
     * @param string $query SQL query
     * @param int $limit Limit of returned rows (default 20)
     * @param int $page Page to return (default 1)
     * @return mixed Paged result array or false if any error
     */
    public function paging(string $query, int $limit = 20, int $page = 1)
    {
        if (empty($query)) {
            $query = "SELECT {$this->_queryColumns()} FROM `{$this->table}`" . $this->_queryJoin() . $this->_queryWhere() . $this->_queryOrder(); 
        }
        if ($all = $this->query($query)) {
            $this->lastPage = (int) ceil($all / $limit);
            $this->currentPage = $page;
            $this->previousPage = ($page > 1) ? $page - 1 : 1;
            $this->nextPage = ($page < $this->lastPage) ? $page + 1 : $this->lastPage;
            $offset = ($page - 1) * $limit;
            $pagedQuery = $query . " LIMIT {$limit} OFFSET {$offset}";
            return $this->rows($pagedQuery);
        }
        return false;
    }

    /**
     * Assign values to values array if in field structure<br>
     * Asigna valores al array values con columnas coincidentes de la estructura
     * @param array $values Array of values (optional)
     * @param bool $incId Include id
     * @return bool True if values are assigned and false if values are missing
     */
    private function _values($values, bool $incId = false): bool
    {
        $this->values = [];
        if (empty($values) && $this->variables) {
            foreach ($this->variables as $column => $val) {
                if (in_array($column, $this->fields, true)) {
                    if ($this->structure[$column]['Extra'] !== "auto_increment" || $incId) {
                        $this->values[$column] = $val;
                    }
                }
            }
            return true;
        } elseif (is_array($values)) {
            foreach ($values as $column => $val) {
                if (in_array($column, $this->fields, true)) {
                    if ($this->structure[$column]['Extra'] !== "auto_increment" || $incId) {
                        $this->values[$column] = $val;
                    }
                }
            }
            return true;
        }
        $this->error = true;
        $this->errormsg = "The values are missing";
        return false;
    }

    /**
     * Sets query order<br>
     * Establece el orden de la consulta
     * @param string $order
     */
    public function order(string $order): void
    {
        $this->order = $order;
    }

    /**
     * Sets query condition<br>
     * Establece la condición de la consulta
     * @param mixed $condition Array or string condition
     * @return bool
     */
    public function where($condition): bool
    {
        return $this->_condition($condition, true);
    }

    /**
     * Creates the WHERE condition<br>
     * Crea la condición WHERE
     * @param mixed $condition Condicion
     * @param bool $mandatory
     * @return bool True if condition could be created
     */
    private function _condition($condition, bool $mandatory = true): bool
    {
        $where = '';
        if (empty($condition) && !empty($this->where) && $mandatory) {
            return true;
        } elseif (empty($condition) && !$mandatory) {      
            $this->where = null;
            return true;
        } elseif (empty($condition) && $this->variables) {
            foreach ($this->pk as $inx => $fld) {
                $fldinx = $fld . $inx;
                $where .= "{$this->fullPk[$inx]} = :{$fldinx} AND ";
                $this->values[$fldinx] = $this->variables[$fld];
            }
            $this->where = (strlen($where) > 5) ? substr($where, 0, -5) : $where;
            return true;
        } elseif (is_array($condition)) {
            $count = 0;
            foreach ($condition as $fld => $val) {
                $fldinx = $fld . $count;
                $where .= "{$fld} = :{$fldinx} AND ";
                $this->values[$fldinx] = $val;
                $count++;
            }
            $this->where = (strlen($where) > 5) ? substr($where, 0, -5) : $where;
            return true;
        } elseif (strlen($condition) > 0 && is_numeric($condition)) {
            $this->where = "{$this->fullPk[0]} = {$condition}";      
            return true;
        } elseif (strlen($condition) > 0) {
            $this->where = $condition;
            return true;
        }
        $this->where = null;
        $this->error = true;
        $this->errormsg = "There are no defined conditions";
        return false;
    }

    /**
     * Extracts the column type from structure<br>
     * Extrae el tipo de columna de la estructura
     * @param string $type Structure column type
     * @return string Column type
     */
    private function _type(string $type): string
    {
        if (($len = strpos($type, "(", 0)) > 0) {
            return substr($type, 0, $len);
        }
        return $type;
    }

    /**
     * Extracts column size from structure<br>
     * Extrae el tamaño de la columna de la estructura
     * @param string $btype Structure type
     * @param string $type Simple type
     * @return array Size (integers, decimals)
     */
    private function _size(string $btype, string $type): array
    {
        $type = str_replace($this->quotes, "", $type);
        if ($size = $this->getStringBetween($type, '(', ')', 1)) {
            if ($btype === 'enum' && strpos($size, ",")) {
                $enumValues = explode(',', $size);
                $size = 1;
                foreach ($enumValues as $value) {
                    $esize = iconv_strlen($value);
                    $size = ($esize > $size) ? $esize : $size;
                }
                $result = [$size, null];
            } elseif (strpos($size, ",") > 0) {
                $result = explode(",", $size);
            } else {
                $result = [$size, null];
            }
        } else {
            switch ($btype) {
                case "date":
                    $result = [10, null];
                    break;
                case "datetime":
                    $result = [19, null];
                    break;
                case "time":
                    $result = [8, null];
                    break;
                default:
                    $result = [null, null];
            }
        }
        return $result;
    }

    /**
     * Clear all error properties<br>
     * Limpia todas las propiedades de errores
     */
    private function _clearError(): void
    {
        $this->error = false;
        $this->errormsg = null;
        $this->errors = [];
        $this->values = null;
        $this->where = null;
    }

    /**
     * Set the Charset<br>
     * Define el charset
     * @param string $charset
     */
    public function setCharset(string $charset = "utf-8"): void
    {
        $this->charset = $charset;
    }
  
}
