<?php
namespace Controllers;
require_once("init.php");
use APJ\APJController;
use APJ\APJModelTool;

class APJToolController extends APJController
{
    public function __construct() {
        $this->sessionControl(false);
        if (!defined('DEVELOPMENT') || !DEVELOPMENT) {
            die('APJModelTool is only available in DEVELOPMENT mode.');
        }
        parent::__construct('apjtool.html');
    }

    /** Lists all models with their current status — called from the view template */
    public function listModels(): string
    {
        $models = APJModelTool::scan(MODELS);
        $out = '';
        foreach ($models as $m) {
            $badge = match($m['mode']) {
                'dev'   => '<span class="apjt-dev">DEV</span>',
                'prod'  => '<span class="apjt-prod">PROD</span>',
                default => '<span class="apjt-unk">?</span>',
            };
            $table = htmlspecialchars($m['table'] ?? '—');
            $class = htmlspecialchars($m['class'] ?? '—');
            $file  = htmlspecialchars($m['file']);
            $actions = '';
            $fe = addslashes($m['file']);
            if ($m['mode'] === 'dev') {
                $actions = "<button onclick=\"APJCall('promoteModel','{$fe}',false,false)\">Promote</button> "
                         . "<button onclick=\"APJCall('promoteModel','{$fe}',true,false)\">+ Doc</button> "
                         . "<button onclick=\"APJCall('promoteModel','{$fe}',true,true)\">+ Doc + Create</button>";
            } elseif ($m['mode'] === 'prod') {
                $actions = "<button onclick=\"APJCall('demoteModel','{$fe}')\">Demote</button>";
            }
            $out .= "<tr><td>{$file}</td><td>{$class}</td><td>{$table}</td>"
                  . "<td class=\"apjt-center\">{$badge}</td><td>{$actions}</td></tr>";
        }
        return $out;
    }

    /**
     * Promotes a single model to production (AJAX).
     * @param string $file      Filename only (no path)
     * @param bool   $updateDoc Insert/replace documentation comment
     * @param bool   $withCreate Add createTableIfNotExists() method
     */
    public function promoteModel(string $file, bool $updateDoc = true, bool $withCreate = false): void
    {
        $file = basename($file);
        $path = MODELS . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($path)) {
            $this->jError("File not found: {$file}");
            return;
        }
        $result = APJModelTool::promote($path, $updateDoc, $withCreate);
        if ($result['success']) {
            $table = htmlspecialchars($result['table'] ?? '');
            $extra = $withCreate ? ' createTableIfNotExists() added.' : '';
            $msg = "&#10003; {$file} (table: {$table}) promoted to PROD.{$extra}";
            $this->jQ("#apjt-result")->prepend("<p class=\"apjt-ok\">{$msg}</p>");
        } else {
            $error = htmlspecialchars($result['error'] ?? 'Unknown error');
            $this->jQ("#apjt-result")->prepend("<p class=\"apjt-err\">&#10007; {$file}: {$error}</p>");
        }
        // Refresh the full table after any promotion
        $this->jQ("#apjt-body")->html($this->listModels());
    }

    /**
     * Reverts a single prod-mode model back to dev mode (AJAX).
     * Restores setTable(), removes defineStructure() and related methods.
     * @param string $file Filename only (no path)
     */
    public function demoteModel(string $file): void
    {
        $file = basename($file);
        $path = MODELS . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($path)) {
            $this->jError("File not found: {$file}");
            return;
        }
        $result = APJModelTool::demote($path);
        if ($result['success']) {
            $table = htmlspecialchars($result['table'] ?? '');
            $this->jQ("#apjt-result")->prepend("<p class=\"apjt-ok\">&#10003; {$file} (table: {$table}) reverted to DEV.</p>");
        } else {
            $error = htmlspecialchars($result['error'] ?? 'Unknown error');
            $this->jQ("#apjt-result")->prepend("<p class=\"apjt-err\">&#10007; {$file}: {$error}</p>");
        }
        $this->jQ("#apjt-body")->html($this->listModels());
    }

    /**
     * Reverts all prod-mode models back to dev mode (AJAX).
     */
    public function demoteAll(): void
    {
        $results = APJModelTool::demoteAll(MODELS);
        if (empty($results)) {
            $this->jQ("#apjt-result")->prepend('<p class="apjt-ok">No models in PROD mode.</p>');
            return;
        }
        $ok = $fail = 0;
        $html = '';
        foreach ($results as $r) {
            $file = htmlspecialchars($r['file']);
            if ($r['success']) {
                $table = htmlspecialchars($r['table'] ?? '');
                $html .= "<p class=\"apjt-ok\">&#10003; {$file} (table: {$table}) reverted to DEV.</p>";
                $ok++;
            } else {
                $error = htmlspecialchars($r['error'] ?? '');
                $html .= "<p class=\"apjt-err\">&#10007; {$file}: {$error}</p>";
                $fail++;
            }
        }
        $this->jQ("#apjt-result")->prepend("<p><strong>Result: {$ok} reverted, {$fail} errors.</strong></p>" . $html);
        $this->jQ("#apjt-body")->html($this->listModels());
    }

    /**
     * Promotes all dev-mode models (AJAX).
     * @param bool $updateDoc  Insert/replace documentation comment
     * @param bool $withCreate Add createTableIfNotExists() method
     */
    public function promoteAll(bool $updateDoc = true, bool $withCreate = false): void
    {
        $results = APJModelTool::promoteAll(MODELS, $updateDoc, $withCreate);
        if (empty($results)) {
            $this->jQ("#apjt-result")->prepend('<p class="apjt-ok">No models in DEV mode.</p>');
            return;
        }
        $ok = $fail = 0;
        $html = '';
        foreach ($results as $r) {
            $file = htmlspecialchars($r['file']);
            if ($r['success']) {
                $table = htmlspecialchars($r['table'] ?? '');
                $html .= "<p class=\"apjt-ok\">&#10003; {$file} (table: {$table})</p>";
                $ok++;
            } else {
                $error = htmlspecialchars($r['error'] ?? '');
                $html .= "<p class=\"apjt-err\">&#10007; {$file}: {$error}</p>";
                $fail++;
            }
        }
        $this->jQ("#apjt-result")->prepend("<p><strong>Result: {$ok} promoted, {$fail} errors.</strong></p>" . $html);
        $this->jQ("#apjt-body")->html($this->listModels());
    }
}

$app = new APJToolController();
