<?php
/**
 * APJModelTool — Development-to-production model promotion tool for APJ2
 * Promotes model files from dev mode (setTable) to production mode (defineStructure).
 * Version: 2.0
 * Author: Ricardo Seiffert
 */
namespace APJ;

class APJModelTool
{
    /**
     * Scans a Models directory and returns status info for each PHP file.
     * @param string $modelsDir Absolute path to Models/ directory
     * @return array Each entry: ['file'=>filename, 'path'=>fullpath, 'class'=>classname, 'table'=>tablename|null, 'mode'=>'dev'|'prod'|'unknown']
     */
    public static function scan(string $modelsDir): array
    {
        $result = [];
        $files = glob(rtrim($modelsDir, '/\\') . DIRECTORY_SEPARATOR . '*.php');
        if (!$files) {
            return $result;
        }
        foreach ($files as $path) {
            $content = file_get_contents($path);
            $mode = 'unknown';
            if (self::_isDevModeContent($content)) {
                $mode = 'dev';
            } elseif (self::_isProdModeContent($content)) {
                $mode = 'prod';
            }
            $result[] = [
                'file'  => basename($path),
                'path'  => $path,
                'class' => self::parseClassName($content),
                'table' => self::_getTableNameFromContent($content),
                'mode'  => $mode,
            ];
        }
        usort($result, fn($a, $b) => strcmp($a['file'], $b['file']));
        return $result;
    }

    /**
     * Returns true if the file has an uncommented setTable() call.
     * Matches lines where only whitespace precedes $this->setTable( (not a // comment line).
     */
    public static function isDevMode(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        return (bool) preg_match('/^\s*\$this->setTable\s*\(/m', $content);
    }

    /**
     * Returns true if the file has an uncommented defineStructure(), defineEstructura(), or estructura() call.
     */
    public static function isProdMode(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        return (bool) preg_match('/^\s*\$this->(defineStructure|defineEstructura|estructura)\s*\(/m', $content);
    }

    /**
     * Extracts the table name from the first uncommented setTable() call in the file.
     */
    public static function getTableName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/^\s*\$this->setTable\s*\(\s*[\'"](\w+)[\'"]\s*\)/m', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Promotes a single model file from dev mode to production mode.
     * Replaces setTable() with defineStructure(), generates the method body,
     * optionally adds createTableIfNotExists(), and updates the doc comment.
     *
     * @param string $filePath    Absolute path to the model PHP file
     * @param bool   $updateDoc   Whether to insert/replace the documentation comment (default true)
     * @param bool   $withCreate  Whether to add createTableIfNotExists() method (default false)
     * @return array ['success'=>bool, 'error'=>string|null, 'table'=>string|null]
     */
    public static function promote(string $filePath, bool $updateDoc = true, bool $withCreate = false): array
    {
        $table = self::getTableName($filePath);
        if (!$table) {
            return ['success' => false, 'error' => 'No se encontró setTable() sin comentar', 'table' => null];
        }

        // Create a temporary anonymous APJModel instance to load the schema from DB
        try {
            $tmp = new class extends APJModel {
                public function __construct() { parent::__construct(); }
            };
            $tmp->setTable($table);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error al cargar estructura: ' . $e->getMessage(), 'table' => $table];
        }

        $structureCode = $tmp->getStructureCode();
        $docComment    = $updateDoc ? $tmp->getDocComment() : null;
        $createCode    = $withCreate ? $tmp->getCreateTableCode() : null;

        $content = file_get_contents($filePath);

        // 1. Replace setTable(...) with $this->defineStructure();
        $content = preg_replace('/\$this->setTable\s*\([^)]+\)\s*;/', '$this->defineStructure();', $content);

        // 2. Remove any line (commented or not) that contains a show* call
        $content = preg_replace('/^[^\n]*\$this->(showStructure|showModel|showCreateTable)\s*\([^)]*\)\s*;[^\n]*\n?/m', '', $content);

        // 3. Remove existing defineStructure() and createTableIfNotExists() methods if present
        //    (bodies contain only assignments/SQL — no nested braces — so [^}]* is safe)
        $content = preg_replace('/\n?[ \t]*private\s+function\s+defineStructure\s*\(\s*\)[^}]*\}/s', '', $content);
        $content = preg_replace('/\n?[ \t]*public\s+function\s+createTableIfNotExists\s*\(\s*\)(?:\s*:\s*void)?[^}]*\}/s', '', $content);

        // 4. Build new methods
        $newMethods = PHP_EOL . self::buildMethod('private', 'defineStructure', '', $structureCode);
        if ($withCreate && $createCode) {
            // Heredoc closing delimiter must be at column 0 — build method manually to avoid indentation by buildMethod()
            $newMethods .= PHP_EOL . PHP_EOL
                . "    public function createTableIfNotExists(): void" . PHP_EOL
                . "    {" . PHP_EOL
                . "        \$sql = <<<'SQL'" . PHP_EOL
                . $createCode . PHP_EOL
                . "SQL;" . PHP_EOL
                . "        \$this->query(\$sql);" . PHP_EOL
                . "    }";
        }

        // 5. Append new methods before the last closing brace of the class
        $lastBrace = strrpos($content, '}');
        $content = substr($content, 0, $lastBrace)
            . $newMethods . PHP_EOL
            . '}' . PHP_EOL;

        // 6. Insert/replace doc comment before the class declaration
        if ($updateDoc) {
            if (preg_match('/\/\*[\s\S]*?Table structure of[\s\S]*?\*\//s', $content)) {
                $content = preg_replace('/\/\*[\s\S]*?Table structure of[\s\S]*?\*\//s', $docComment, $content);
            } else {
                $content = preg_replace('/(class\s+\w+)/', $docComment . PHP_EOL . '$1', $content, 1);
            }
        }

        // 7. Normalize: no more than 2 consecutive blank lines
        $content = preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $content);

        if (file_put_contents($filePath, $content) === false) {
            return ['success' => false, 'error' => 'No se pudo escribir el archivo', 'table' => $table];
        }

        return ['success' => true, 'error' => null, 'table' => $table];
    }

    /**
     * Promotes all dev-mode models in the given directory.
     * @param string $modelsDir  Absolute path to Models/ directory
     * @param bool   $updateDoc  Whether to insert/replace doc comments
     * @param bool   $withCreate Whether to add createTableIfNotExists()
     * @return array Each entry: ['file'=>filename, 'success'=>bool, 'error'=>string|null, 'table'=>string|null]
     */
    public static function promoteAll(string $modelsDir, bool $updateDoc = true, bool $withCreate = false): array
    {
        $results = [];
        $models = self::scan($modelsDir);
        foreach ($models as $m) {
            if ($m['mode'] === 'dev') {
                $r = self::promote($m['path'], $updateDoc, $withCreate);
                $results[] = array_merge(['file' => $m['file']], $r);
            }
        }
        return $results;
    }

    /**
     * Reverts a single prod-mode model back to dev mode.
     * Replaces defineStructure() call with setTable(), removes the method and any createTableIfNotExists(),
     * and strips the auto-generated doc comment.
     *
     * @param string $filePath Absolute path to the model PHP file
     * @return array ['success'=>bool, 'error'=>string|null, 'table'=>string|null]
     */
    public static function demote(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $table = self::_getProdTableNameFromContent($content);
        if (!$table) {
            return ['success' => false, 'error' => 'Could not find table name in structure method', 'table' => null];
        }

        // 1. Replace $this->defineStructure/defineEstructura/estructura() with $this->setTable('table');
        $content = preg_replace(
            '/^(\s*)\$this->(defineStructure|defineEstructura|estructura)\s*\(\s*\)\s*;/m',
            '$1$this->setTable(\'' . $table . '\');',
            $content
        );

        // 2. Remove defineStructure / defineEstructura / estructura methods
        $content = preg_replace(
            '/\n?[ \t]*private\s+function\s+(defineStructure|defineEstructura|estructura)\s*\(\s*\)[^}]*\}/s',
            '',
            $content
        );

        // 3. Remove createTableIfNotExists method if present
        $content = preg_replace(
            '/\n?[ \t]*public\s+function\s+createTableIfNotExists\s*\(\s*\)(?:\s*:\s*void)?[^}]*\}/s',
            '',
            $content
        );

        // 4. Remove auto-generated doc comment (Table structure of [...])
        $content = preg_replace('/\/\*[\s\S]*?Table structure of[\s\S]*?\*\/[ \t]*\n?/s', '', $content);

        // 5. Normalize blank lines
        $content = preg_replace('/\n{3,}/', PHP_EOL . PHP_EOL, $content);

        if (file_put_contents($filePath, $content) === false) {
            return ['success' => false, 'error' => 'Could not write file', 'table' => $table];
        }

        return ['success' => true, 'error' => null, 'table' => $table];
    }

    /**
     * Reverts all prod-mode models in the given directory back to dev mode.
     * @param string $modelsDir Absolute path to Models/ directory
     * @return array Each entry: ['file'=>filename, 'success'=>bool, 'error'=>string|null, 'table'=>string|null]
     */
    public static function demoteAll(string $modelsDir): array
    {
        $results = [];
        $models = self::scan($modelsDir);
        foreach ($models as $m) {
            if ($m['mode'] === 'prod') {
                $r = self::demote($m['path']);
                $results[] = array_merge(['file' => $m['file']], $r);
            }
        }
        return $results;
    }

    /**
     * Returns a preview of the defineStructure() code for a dev-mode model file.
     */
    public static function previewStructureCode(string $filePath): ?string
    {
        $table = self::getTableName($filePath);
        if (!$table) {
            return null;
        }
        try {
            $tmp = new class extends APJModel {
                public function __construct() { parent::__construct(); }
            };
            $tmp->setTable($table);
            return $tmp->getStructureCode();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------

    private static function _getProdTableNameFromContent(string $content): ?string
    {
        // Find $this->table = "name"; inside defineStructure/defineEstructura/estructura method body
        if (preg_match('/private\s+function\s+(?:defineStructure|defineEstructura|estructura)\s*\(\s*\).*?\$this->table\s*=\s*[\'"](\w+)[\'"]/s', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function _isDevModeContent(string $content): bool
    {
        return (bool) preg_match('/^\s*\$this->setTable\s*\(/m', $content);
    }

    private static function _isProdModeContent(string $content): bool
    {
        return (bool) preg_match('/^\s*\$this->(defineStructure|defineEstructura|estructura)\s*\(/m', $content);
    }

    private static function _getTableNameFromContent(string $content): ?string
    {
        // Dev mode: from setTable()
        if (preg_match('/^\s*\$this->setTable\s*\(\s*[\'"](\w+)[\'"]\s*\)/m', $content, $m)) {
            return $m[1];
        }
        // Prod mode: from $this->table = "name" inside defineStructure/defineEstructura/estructura
        return self::_getProdTableNameFromContent($content);
    }

    private static function parseClassName(string $content): ?string
    {
        if (preg_match('/class\s+(\w+)\s+extends\s+\w+/', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function buildMethod(string $visibility, string $name, string $returnType, string $body): string
    {
        $indent = '        ';
        $lines  = explode(PHP_EOL, trim($body));
        $indentedBody = implode(PHP_EOL, array_map(fn($l) => $indent . $l, $lines));
        return "    {$visibility} function {$name}(){$returnType}" . PHP_EOL
             . '    {' . PHP_EOL
             . $indentedBody . PHP_EOL
             . '    }';
    }
}
