<?php
/**
 * Html Generator class<br>
 * Clase Generadora de Html
 * Version: 2.0.2601
 * Author: Ricardo Seiffert
 *
 * $gen es un recurso OPCIONAL - el controlador que lo use debe declararlo
 * como propiedad propia antes de instanciarlo, por ejemplo:
 *   private ?APJHtmlGen $gen = null;
 * No declararlo (y solo hacer $this->gen = new APJHtmlGen()) crea una
 * "dynamic property", lo que en PHP 8.2+ emite un Deprecated warning. Si esa
 * asignación ocurre en el constructor del controlador ANTES de llamar a
 * parent::__construct() (que es donde arranca ob_start()), el warning se
 * emite crudo y corrompe la respuesta JSON de cualquier acción AJAX.
 */
namespace APJ;

class APJHtmlGen
{
    private ?string $content = null;
    private array $tagStack = [];
    private bool $isCurrentTagOpen = false;
    private array $simpleVoidElements = ['br', 'hr'];

    /**
     * Resets the generator to start a new, clean HTML content.<br>
     * Reinicia el generador para comenzar un nuevo contenido HTML limpio.
     */
    public function start(): void
    {
        $this->initialize();
    }

    /**
     * Initializes the generator and creates the first HTML element.<br>
     * Inicializa el generador y crea el primer elemento HTML.
     * @param string $tag The root HTML tag to create (e.g., 'html', 'div').
     * @return APJHtmlGen
     */
    public function create(string $tag): APJHtmlGen
    {
        $this->initialize();
        $this->add($tag);
        return $this;
    }

    /**
     * Adds a new HTML tag. Void elements ('br', 'hr') are self-closed and not pushed to the stack.<br>
     * Agrega una nueva etiqueta HTML. Los elementos vacíos ('br', 'hr') se auto-cierran y no se apilan.
     * @param string $tag The HTML tag to add.
     * @param bool $closeLast If true, closes the opening part of the previous tag with '>'. Default true.
     * @return APJHtmlGen
     */
    public function add(string $tag, bool $closeLast = true): APJHtmlGen
    {
        if ($closeLast) {
            $this->ensureOpeningTagIsClosed();
        }
        $this->content .= '<' . $tag;
        $this->isCurrentTagOpen = true;
        if (!in_array(strtolower($tag), $this->simpleVoidElements)) {
            array_push($this->tagStack, $tag);
        }
        return $this;
    }

    /**
     * Adds an attribute to the current open tag.<br>
     * Agrega un atributo a la etiqueta actual abierta.
     * @param string $attr The attribute name.
     * @param string $value The attribute value.
     * @return APJHtmlGen
     */
    public function attr(string $attr, string $value = ''): APJHtmlGen
    {
        if (!$this->isCurrentTagOpen) {
            return $this;
        }
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
        return $this->attr('style', $style);
    }

    /**
     * Adds a src attribute<br>
     * Agrega un atributo src
     * @param string $src
     * @return APJHtmlGen
     */
    public function src(string $src): APJHtmlGen
    {
        return $this->attr('src', $src);
    }

    /**
     * Adds an id attribute<br>
     * Agrega un atributo id
     * @param string $value
     * @return APJHtmlGen
     */
    public function id(string $value): APJHtmlGen
    {
        return $this->attr('id', $value);
    }

    /**
     * Adds a class attribute<br>
     * Agrega un atributo class
     * @param string $value
     * @return APJHtmlGen
     */
    public function clas(string $value): APJHtmlGen
    {
        return $this->attr('class', $value);
    }

    /**
     * Adds a value attribute<br>
     * Agrega un atributo value
     * @param string|null $value
     * @return APJHtmlGen
     */
    public function value(?string $value): APJHtmlGen
    {
        return $this->attr('value', $value ?? '');
    }

    /**
     * Adds a title attribute<br>
     * Agrega un atributo title
     * @param string $value
     * @return APJHtmlGen
     */
    public function title(string $value): APJHtmlGen
    {
        return $this->attr('title', $value);
    }

    /**
     * Adds text content inside the current tag.<br>
     * Agrega contenido de texto dentro de la etiqueta actual.
     * @param string|null $value
     * @return APJHtmlGen
     */
    public function text(?string $value): APJHtmlGen
    {
        $this->ensureOpeningTagIsClosed();
        $this->content .= $value;
        return $this;
    }

    /**
     * Adds a name attribute<br>
     * Agrega un atributo name
     * @param string $value
     * @return APJHtmlGen
     */
    public function name(string $value): APJHtmlGen
    {
        return $this->attr('name', $value);
    }

    /**
     * Adds a type attribute<br>
     * Agrega un atributo type
     * @param string $value
     * @return APJHtmlGen
     */
    public function type(string $value): APJHtmlGen
    {
        return $this->attr('type', $value);
    }

    /**
     * Adds an onclick event<br>
     * Agrega un evento onclick
     * @param string $value
     * @return APJHtmlGen
     */
    public function onclick(string $value): APJHtmlGen
    {
        return $this->attr('onclick', $value);
    }

    /**
     * Adds an onchange event<br>
     * Agrega un evento onchange
     * @param string $value
     * @return APJHtmlGen
     */
    public function onchange(string $value): APJHtmlGen
    {
        return $this->attr('onchange', $value);
    }

    /**
     * Adds an onmouseover event<br>
     * Agrega un evento onmouseover
     * @param string $value
     * @return APJHtmlGen
     */
    public function onmouseover(string $value): APJHtmlGen
    {
        return $this->attr('onmouseover', $value);
    }

    /**
     * Adds a literal string to the content.<br>
     * Agrega una cadena de texto literal al contenido.
     * @param string $value
     * @return APJHtmlGen
     */
    public function literal(string $value): APJHtmlGen
    {
        $this->content .= $value;
        return $this;
    }

    /**
     * Explicitly closes the opening part of the current tag with a '>'.<br>
     * Cierra explícitamente la apertura de la etiqueta actual con '>'.
     * @return APJHtmlGen
     */
    public function preClose(): APJHtmlGen
    {
        $this->ensureOpeningTagIsClosed();
        return $this;
    }

    /**
     * Closes the last opened tag from the stack (e.g., adds '</div>').<br>
     * Cierra la última etiqueta abierta en la pila (ej: agrega '</div>').
     * @return APJHtmlGen
     */
    public function close(): APJHtmlGen
    {
        $this->ensureOpeningTagIsClosed();
        if (!empty($this->tagStack)) {
            $tagToClose = array_pop($this->tagStack);
            $this->content .= '</' . $tagToClose . '>';
        }
        $this->isCurrentTagOpen = false;
        return $this;
    }

    /**
     * Closes all currently open tags on the stack.<br>
     * Cierra todas las etiquetas actualmente abiertas en la pila.
     */
    public function closeAll(): void
    {
        while (!empty($this->tagStack)) {
            $this->close();
        }
    }

    /**
     * Finalizes the generation and returns the complete HTML string.<br>
     * Finaliza la generación y retorna el string HTML completo.
     * @param bool $closeAll If true, closes all remaining open tags. Default true.
     * @return string The final generated HTML.
     */
    public function end(bool $closeAll = true): string
    {
        if ($closeAll) {
            $this->closeAll();
        }
        $this->ensureOpeningTagIsClosed();
        return $this->getContent();
    }

    /**
     * Returns the current generated HTML content without closing any tags.<br>
     * Retorna el contenido HTML generado actualmente sin cerrar etiquetas.
     * @return string
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    private function ensureOpeningTagIsClosed(): void
    {
        if ($this->isCurrentTagOpen) {
            $this->content .= '>';
            $this->isCurrentTagOpen = false;
        }
    }

    private function initialize(): void
    {
        $this->content = null;
        $this->tagStack = [];
        $this->isCurrentTagOpen = false;
    }

}
