<?php
/**
 * Html Generator class<br>
 * Clase Generadora de Html
 * Version: 2.0.2412
 * Author: Ricardo Seiffert
 */
namespace Libs\APJ;

class APJHtmlGen
{
    private $content = null;
    private $tags = [];
    private $closed = [];
    // HTML 5 Tags that requires special closing
    private $specialClose = [];

    public function __construct()
    {
        $this->setSpecialClose();
    }

    /**
     * Starts a new content<br>
     * Comienza un nuevo contenido
     */
    public function start(): void
    {
        $this->initialize();
    }

    /**
     * Initializes properties and creates a new HTML Tag<br>
     * Inicializa las porpiedades y crea una nueva Etiqueta HTML
     * @param string $tag
     * @return APJHtmlGen
     */
    public function create(string $tag): APJHtmlGen
    {
        $this->initialize();
        $this->content = '<' . $tag;
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Closes previous Tag and adds a new one
     * Cierra la Etiqueta anterior y añade una nueva
     * @param string $tag
     * @param bool $closeLast
     * @return APJHtmlGen
     */
    public function add(string $tag, bool $closeLast = true): APJHtmlGen
    {
        if ($closeLast) {
            $this->closeLast();
        }
        $this->content .= '<' . $tag;
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Adds a new attribute</br>
     * Agrega un nuevo atributo
     * @param string $attr
     * @param string $value
     * @return APJHtmlGen
     */
    public function attr(string $attr, string $value = ''): APJHtmlGen
    {
        $this->content .= ' ' . $attr;
        if (strlen($value)) {
            $this->content .= '="' . $value . '"';
        }
        return $this;
    }

    /**
     * Adds a style attribute<br>
     * Agrega un atributo style
     * @param string $style
     * @return APJHtmlGen
     */
    public function style(string $style): APJHtmlGen
    {
        $this->content .= ' style="' . $style . '"';
        return $this;
    }

    /**
     * Adds a src attribute<br>
     * Agrega un atributo src
     * @param string $src
     * @return APJHtmlGen
     */
    public function src(string $src): APJHtmlGen
    {
        $this->content .= ' src="' . $src . '"';
        return $this;
    }

    /**
     * Adds an id attribute<br>
     * Agrega un atributo id
     * @param string $value
     * @return APJHtmlGen
     */
    public function id(string $value): APJHtmlGen
    {
        $this->content .= ' id="' . $value . '"';
        return $this;
    }

    /**
     * Adds a class attribute<br>
     * Agrega un atributo class
     * @param string $value
     * @return APJHtmlGen
     */
    public function clas(string $value): APJHtmlGen
    {
        $this->content .= ' class="' . $value . '"';
        return $this;
    }

    /**
     * Adds a value attribute
     * Agrega un atributo value
     * @param string $value
     * @return APJHtmlGen
     */
    public function value(string $value): APJHtmlGen
    {
        $this->content .= ' value="' . $value . '"';
        return $this;
    }

    /**
     * Adds a title attribute
     * Agrega un atributo title
     * @param string $value
     * @return APJHtmlGen
     */
    public function title(string $value): APJHtmlGen
    {
        $this->content .= ' title="' . $value . '"';
        return $this;
    }

    /**
     * Adds a text attribute
     * Agrega un atributo text
     * @param string $value
     * @return APJHtmlGen
     */
    public function text(string $value): APJHtmlGen
    {
        $close = "";
        if ($this->isOpen()) {
            $close = ">";
        }
        $this->content .= $close . $value;
        return $this;
    }

    /**
     * Adds a name attribute
     * Agrega un atributo name
     * @param string $value
     * @return APJHtmlGen
     */
    public function name(string $value): APJHtmlGen
    {
        $this->content .= ' name="' . $value . '"';
        return $this;
    }

    /**
     * Adds a type attribute
     * Agrega un atributo type
     * @param string $value
     * @return APJHtmlGen
     */
    public function type(string $value): APJHtmlGen
    {
        $this->content .= ' type="' . $value . '"';
        return $this;
    }

    /**
     * Adds an onclick event
     * Agrega un evento onclick
     * @param string $value
     * @return APJHtmlGen
     */
    public function onclick(string $value): APJHtmlGen
    {
        $this->content .= ' onclick="' . $value . '"';
        return $this;
    }

    /**
     * Adds an onchange event
     * Agrega un evento onchange
     * @param string $value
     * @return APJHtmlGen
     */
    public function onchange(string $value): APJHtmlGen
    {
        $this->content .= ' onchange="' . $value . '"';
        return $this;
    }

    /**
     * Adds an onmouseover event
     * Agrega un evento onmouseover
     * @param string $value
     * @return APJHtmlGen
     */
    public function onmouseover(string $value): APJHtmlGen
    {
        $this->content .= ' onmouseover="' . $value . '"';
        return $this;
    }

    /**
     * Adds a literal
     * Agrega un literal
     * @param string $value
     * @return APJHtmlGen
     */
    public function literal(string $value): APJHtmlGen
    {
        $this->content .= $value;
        return $this;
    }

    /**
     * Close with ><br>
     * Cierra con >
     * @return APJHtmlGen
     */
    public function preClose(): APJHtmlGen
    {
        $this->content .= ">";
        return $this;
    }

    /**
     * Close last open Tag<br>
     * Cierra la última Etiqueta
     * @return APJHtmlGen
     */
    public function close(): APJHtmlGen
    {
        $this->closeSingle();
        return $this;
    }

    /**
     * Close all open Tags<br>
     * Cierra todas las etiquetas abiertas
     */
    public function closeAll(): void
    {
        while (count($this->closed) < count($this->tags)) {
            $this->closeSingle();
        }
    }

    /**
     * Returns the final content with the option to close the open tags<br>
     * Retorna el contenido final con la opción de cerrar las etiquetas abiertas
     * @param bool $closeAll (default true)
     * @return string Html result
     */
    public function end(bool $closeAll = true): string
    {
        if ($closeAll) {
            $this->closeAll();
        }
        return $this->getContent();
    }

    /**
     * Returns the current content<br>
     * Retorna el contenido actual
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
    * Close the last open tag
    * 
    */
    private function closeLast(): void
    {
        if (strlen($this->content) > 0 && substr($this->content, -1) !== '>' && substr($this->content, -1) !== ';') {
            $this->content .= ">";
        }
    }

    /**
    * Close a single opened tag
    * 
    */
    private function closeSingle(): void
    {
        $tags = array_reverse($this->tags, true);
        foreach ($tags as $key => $tag) {
            if (!array_key_exists($key, $this->closed)) {
                $this->closeTag($key);
                break;
            }
        }
    }

    /**
    * Close a given tag
    * 
    * @param (string) $key
    */
    private function closeTag($key): void
    {
        $tag = $this->tags[$key];
        if ($this->inSpecial($tag)) {
            $this->content .= "</" . $tag . ">";
        } elseif ($this->isOpen()) {
            $this->content .= ">";
        }
        $this->closed[$key] = $tag;
    }

    /**
    * Returns whether a tag is among those that require special closure
    * 
    * @param mixed $tag
    * @return bool
    */
    private function inSpecial(string $tag): bool
    {
        return in_array($tag, $this->specialClose, true);
    }
    
    /**
    * Returns whether the content is open
    * 
    */
    private function isOpen(): bool
    {
        return (substr($this->content, -1) !== ">");
    }

    /**
    * Initializes content, tags and closings
    * 
    */
    private function initialize(): void
    {
        $this->content = null;
        $this->tags = [];
        $this->closed = [];
    }

    /**
    * Specifies which tags require special closure
    * 
    */
    private function setSpecialClose(): void
    {
        $this->specialClose = ['a', 'abr', 'address', 'article', 'aside', 'audio', 'b', 'bdi', 'blockquote', 'body', 'button', 'canvas', 'caption', 'cite', 'code', 'colgroup', 'datalist', 'dd', 'del', 'detail', 'dfn', 'dialog', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'html', 'i', 'iframe', 'ins', 'kbd', 'label', 'legend', 'li', 'main', 'map', 'mark', 'menu', 'menuitem', 'meter', 'nav', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script', 'section', 'select', 'small', 'span', 'strong', 'style', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'u', 'ul', 'var', 'video'];
    }
}