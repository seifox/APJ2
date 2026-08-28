/*!
 * APJ - Async PHP jQuery Plugin
 * Version 2.0.3
 * Requiere jQuery 3.x | PHP 8
 *
 * Cambios principales respecto a 1.9:
 * - Un único namespace APJ: configuración, ajax, dialogs, form utilities
 * - url configurable via APJ.init() en lugar de variable global
 * - $.ajax reemplazado por $.ajax con Promises nativas (compatible jQuery 3)
 * - eval() eliminado: Script ahora llama funciones registradas con APJ.register()
 * - Compatibilidad con jQuery 3 (.bind/.unbind eliminados → .on/.off)
 * - ES5 estricto, sin variables globales implícitas
 * - Helpers globales opcionales via APJ.exposeGlobals()
 *
 * Cambios en 2.0.1:
 * - Corregido botón "No" de jConfirm: ya no reinvoca el callback de "Sí" con
 *   (false). Antes, si el callback era 'APJSubmit', se terminaba llamando
 *   APJSubmit(false), que caía al action por defecto 'main' y producía
 *   "El método main no existe!" en el servidor. Ahora "No" solo cierra el diálogo.
 *
 * Cambios en 2.0.2:
 * - Nuevas acciones APJSubmit/APJCall en _runAction: el controlador PHP
 *   (via $this->APJSubmit()/$this->APJCall(), ver APJController.php) puede
 *   disparar un APJSubmit/APJCall real del lado del cliente de inmediato al
 *   procesar la respuesta, sin diálogo de por medio y sin depender de
 *   exposeGlobals() (llaman APJ.APJSubmit/APJ.APJCall directo, no
 *   window[...]).
 *
 * Cambios en 2.0.3:
 * - $.fieldValue(): un radio SIN marcar ya no envía '0' - se excluye del
 *   formulario (return null), igual que un campo cualquiera sin valor. Antes,
 *   al compartir la misma rama que checkbox, un grupo de radios con el mismo
 *   name mandaba dos entradas (la marcada con su value real, la NO marcada
 *   con '0'), y como PHP se queda con el último valor repetido, el radio que
 *   apareciera después en el HTML siempre ganaba sin importar cuál estuviera
 *   realmente marcado - el selector "Nivel" de QueryBuilder.php nunca
 *   cambiaba de Detalle a Solo Totales por esto. El comportamiento de
 *   checkbox (marcado→value o '1', sin marcar→'0') no cambia.
 */
;(function ($) {
  'use strict';

  // ─── Configuración por defecto ───────────────────────────────────────────────

  var _config = {
    url:     '',          // Requerido: definir en APJ.init()
    timeout: 10000,
    images:  'APJ/images/'
  };

  // ─── Registro de funciones para reemplazar eval() ───────────────────────────
  // Uso: APJ.register('miFuncion', function(params) { ... })
  // Llamada desde PHP: { "action": { "Script": [{ "fn": "miFuncion", "params": {} }] } }

  var _registry = {};

  // ─── Polling silencioso ──────────────────────────────────────────────────────

  var _polls = {};

  function _pollTick(method, params) {
    var data = { action: method };
    if (params !== null && params !== undefined) {
      data.data = JSON.stringify(params);
    }
    var pollOpts = {
      url:      _config.url,
      method:   'POST',
      dataType: 'json',
      data:     data
    };
    if (_config.timeout > 0) { pollOpts.timeout = _config.timeout; }
    $.ajax(pollOpts)
    .done(function (response) {
      if (_polls[method]) { _polls[method].errors = 0; }
      _ajax._success(response);
    })
    .fail(function (jqXHR, textStatus) {
      if (!_polls[method]) { return; }
      _polls[method].errors++;

      if (jqXHR.status === 200) {
        APJ.stopPoll(method);
        if (typeof APJ.onSessionEnd === 'function') { APJ.onSessionEnd(); }
        return;
      }

      var opts = _polls[method].opts;
      if (typeof opts.onError === 'function') {
        opts.onError(jqXHR, textStatus);
      } else if (opts.onError === 'stop') {
        APJ.stopPoll(method);
        return;
      }

      if (opts.maxErrors > 0 && _polls[method].errors >= opts.maxErrors) {
        APJ.stopPoll(method);
      }
    });
  }

  // ─── Constantes ─────────────────────────────────────────────────────────────

  var HTTP_ERRORS = {
    0:   'No conectado o tiempo de espera agotado.<br>Verifique su conexión de red.',
    404: 'El controlador no fue encontrado.',
    408: 'Tiempo de espera agotado.',
    500: 'Error del servidor.'
  };

  var EFFECT_METHODS  = ['show','hide','slideDown','slideUp','slideToggle','fadeIn','fadeOut'];
  var EVENT_METHODS   = [
    'blur','change','click','dblclick','error','focus',
    'keydown','keypress','keyup','load','mousedown',
    'mousemove','mouseout','mouseover','mouseup',
    'resize','scroll','select','submit'
  ];

  // ─── Módulo AJAX ─────────────────────────────────────────────────────────────

  var _ajax = {

    send: function (params) {
      if (!_config.url) {
        console.error('[APJ] url no configurada. Llama APJ.init({ url: "..." }) primero.');
        return;
      }
      _dialogs.busy();
      var ajaxOpts = {
        url:      _config.url,
        async:    true,
        method:   'POST',
        dataType: 'json',
        data:     params
      };
      if (_config.timeout > 0) { ajaxOpts.timeout = _config.timeout; }
      return $.ajax(ajaxOpts)
      .done(function (response) {
        _ajax._success(response);
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        _ajax._error(jqXHR, textStatus, errorThrown);
      })
      .always(function () {
        _ajax._complete();
      });
    },

    _success: function (response) {
      _dialogs.close();

      var queries = response['query'] || [];
      queries.forEach(function (q) {
        var $sel    = $(q['selector']);
        var methods = q['method']    || [];
        var argSets = q['arguments'] || [];

        methods.forEach(function (method, m) {
          if (!method || method === 'undefined') { return; }
          try {
            $sel = _ajax._applyMethod($sel, method, argSets[m] || []);
          } catch (e) {
            _dialogs.error(
              'Selector: $("' + q['selector'] + '").' + method + '()<br>Error: ' + e.message,
              'Error en Respuesta'
            );
          }
        });
      });

      $.each(response['action'] || {}, function (func, paramList) {
        paramList.forEach(function (p) {
          try {
            _ajax._runAction(func, p);
          } catch (e) {
            _dialogs.error('Acción: ' + func + '<br>Error: ' + e.message);
          }
        });
      });
    },

    _applyMethod: function ($sel, method, argSet) {
      var resolveCallback = function (name) {
        return (typeof name === 'string' && typeof window[name] === 'function')
               ? window[name] : name;
      };

      if (['ready', 'map', 'queue'].indexOf(method) > -1) {
        return $sel[method](resolveCallback(argSet[0]));
      }
      // .on() reemplaza .bind() y .one() eliminados en jQuery 3
      if ((method === 'on' || method === 'one') && argSet.length === 3) {
        return $sel[method](argSet[0], argSet[1], resolveCallback(argSet[2]));
      }
      if (method === 'hover' && argSet.length === 2) {
        return $sel[method](resolveCallback(argSet[0]), resolveCallback(argSet[1]));
      }
      if (method === 'filter' && argSet.length === 1) {
        var fn = resolveCallback(argSet[0]);
        return $sel[method](typeof fn === 'function' ? fn : argSet[0]);
      }
      if (method === 'fadeTo' && argSet.length === 3) {
        return $sel[method](argSet[0], argSet[1], resolveCallback(argSet[2]));
      }
      if (method === 'animate' && argSet.length === 4) {
        return $sel[method](argSet[0], argSet[1], argSet[2], resolveCallback(argSet[3]));
      }
      if (EFFECT_METHODS.indexOf(method) > -1 && argSet.length === 2) {
        return $sel[method](argSet[0], resolveCallback(argSet[1]));
      }
      if (EVENT_METHODS.indexOf(method) > -1 && argSet.length === 1) {
        return $sel.on(method, resolveCallback(argSet[0]));
      }
      switch (argSet.length) {
        case 0: return $sel[method]();
        case 1: return $sel[method](argSet[0]);
        case 2: return $sel[method](argSet[0], argSet[1]);
        case 3: return $sel[method](argSet[0], argSet[1], argSet[2]);
        default: return $sel[method].apply($sel, argSet);
      }
    },

    // Despacha acciones nombradas que vienen desde PHP
    _runAction: function (func, params) {
      var actions = {
        jInfo:    function (o) { _dialogs.info(o.msg, o.title, o.callback, o.params); },
        jWarning: function (o) { _dialogs.warning(o.msg, o.title, o.callback, o.params); },
        jError:   function (o) { _dialogs.error(o.msg, o.title, o.callback, o.params); },
        jConfirm: function (o) { _dialogs.confirm(o.msg, o.title, o.callback, o.params); },
        jPrompt:  function (o) { _dialogs.prompt(o.msg, o.title, o.callback, o.params); },
        jProcess: function (o) { _dialogs.process(o.msg, o.title, o.style); },
        jBusy:    function ()  { _dialogs.busy(); },
        jClose:   function ()  { _dialogs.close(); },
        setData:  function (o) {
          var cb = o.callback || 'defaultCallBack';
          if (APJ.data && typeof APJ.data[cb] === 'function') {
            APJ.data[cb](o.key, o.value);
          }
        },
        Script: function (o) {
          if (typeof o.scr === 'string') {
            try { (new Function(o.scr))(); } catch(e) { console.error('[APJ] Script error: ' + e.message); }
            return;
          }
          var fn = _registry[o.fn];
          if (typeof fn === 'function') { fn(o.params || {}); }
          else { console.warn('[APJ] Script "' + o.fn + '" no registrado. Usa APJ.register("' + o.fn + '", fn).'); }
        },
        jRedirect: function (o) {
          if (o.parent) { window.parent.location = o.url; } else { window.location = o.url; }
        },
        APJSubmit: function (o) {
          var args = [o.form, o.action].concat(o.params || []);
          APJ.APJSubmit.apply(APJ, args);
        },
        APJCall: function (o) {
          var args = [o.method].concat(o.params || []);
          APJ.APJCall.apply(APJ, args);
        }
      };
      if (typeof actions[func] === 'function') {
        actions[func](params);
      } else {
        console.warn('[APJ] Acción desconocida: ' + func);
      }
    },

    _error: function (jqXHR, textStatus, errorThrown) {
      var code = jqXHR.status;
      var desc = HTTP_ERRORS[code >= 500 ? 500 : code]
                 || ('Error desconocido.<br>' + errorThrown + '<br>' + textStatus);

      if (code === 200) {
        desc = 'Error de respuesta desde el servidor o sesión finalizada.';
        if (typeof APJ.onSessionEnd === 'function') { APJ.onSessionEnd(); }
      }
      _dialogs.error(desc, 'Error ' + code);
    },

    _complete: function () {
      if (typeof APJ.onComplete === 'function') { APJ.onComplete(); }
    }
  };

  // ─── Módulo de diálogos ───────────────────────────────────────────────────────

  var _dialogs = {
    _blinkId: 0,
    _isOpen:  false,

    info:    function (msg, title, cb, params) { this._render('info',    msg, title || 'Información',      cb, params); },
    warning: function (msg, title, cb, params) { this._render('warning', msg, title || 'Advertencia',      cb, params); },
    error:   function (msg, title, cb, params) { this._render('error',   msg, title || 'Error',            cb, params); },
    confirm: function (msg, title, cb, params) { this._render('confirm', msg, title || 'Confirmar',        cb, params); },
    prompt:  function (msg, title, cb, params) { this._render('prompt',  msg, title || 'Introduzca un valor', cb, params); },
    process: function (msg, title, style)      { this._render('process', msg, title || 'Proceso en curso', null, null, style); },

    busy: function () {
      if (!this._isOpen) { this._showBusy(); }
    },

    _showBusy: function () {
      this.close();
      $(document.body).append(
        '<div id="busyoverlay"></div>' +
        '<div id="busybox"><img src="' + _config.images + 'busy32.gif" alt=""></div>'
      );
      this._isOpen = true;
      $('#busyoverlay, #busybox').show();
    },

    _render: function (type, message, header, callback, params, style) {
      this.close();

      var dialogW = Math.max(window.innerWidth / 4, 300);

      $(document.body).append(
        '<div id="dialogoverlay"></div>' +
        '<div id="dialogbox"><div>' +
          '<div id="dialogboxhead"></div>' +
          '<div id="dialogboxbody"></div>' +
          '<div id="dialogboxfoot"></div>' +
        '</div></div>'
      );

      var cfg = this._typeConfig(type, message, callback, params);

      $('#dialogbox').css({ width: dialogW + 'px' });
      $('#dialogboxhead').css('background-image', 'url(' + _config.images + cfg.image + ')').html(header);
      $('#dialogboxbody').addClass(cfg.clase).html('<div id="dialogboxtext">' + cfg.text + '</div>');
      $('#dialogboxfoot').html(cfg.foot);
      cfg.bindEvents && cfg.bindEvents();

      this._open();
      if (style === 'blink') {
        this._blinkId = setInterval(this._blinker, 1500);
      }
    },

    _typeConfig: function (type, message, callback, params) {
      var self = this;
      var cb   = this._resolve(callback);
      var cfg  = { text: message, foot: '', image: type + '32.png', clase: type + 'Body', bindEvents: null };

      switch (type) {
        case 'info':
        case 'warning':
        case 'error':
          cfg.foot = '<button type="button" id="dialogOk">Aceptar</button>';
          cfg.bindEvents = function () {
            $('#dialogOk').on('click', function () {
              self.close();
              if (cb) { cb.apply(null, params || []); }
            });
          };
          break;

        case 'confirm':
          cfg.image = 'question32.png';
          cfg.clase = 'confirmBody';
          cfg.foot  = '<button type="button" id="dialogOk">Sí</button> ' +
                      '<button type="button" id="dialogCancel">No</button>';
          cfg.bindEvents = function () {
            $('#dialogOk').on('click', function () {
              self.close();
              if (cb) { cb.apply(null, params || []); }
            });
            $('#dialogCancel').on('click', function () {
              self.close();
            });
          };
          break;

        case 'prompt':
          cfg.image = 'question32.png';
          cfg.clase = 'promptBody';
          cfg.text  = message + ': <input id="prompt_value" placeholder="valor">';
          cfg.foot  = '<button type="button" id="dialogOk">Aceptar</button> ' +
                      '<button type="button" id="dialogCancel">Cancelar</button>';
          cfg.bindEvents = function () {
            $('#dialogOk').on('click', function () {
              var val = $('#prompt_value').val();
              self.close();
              if (cb) {
                var p = (params && params.length) ? params.concat([val]) : [val];
                cb.apply(null, p);
              }
            });
            $('#dialogCancel').on('click', function () {
              self.close();
              if (cb) { cb(null); }
            });
          };
          break;

        case 'process':
          cfg.image = 'process32.png';
          cfg.clase = 'processBody';
          break;
      }
      return cfg;
    },

    _open: function () {
      this._isOpen = true;
      $('#dialogoverlay, #dialogbox').show();
    },

    close: function () {
      this._isOpen = false;
      $('#dialogbox, #busybox').hide().remove();
      $('#dialogoverlay, #busyoverlay').hide().remove();
      if (this._blinkId) { clearInterval(this._blinkId); this._blinkId = 0; }
    },

    _blinker: function () {
      $('#dialogboxtext').stop().fadeOut(500).fadeIn(500);
    },

    _resolve: function (func) {
      if (typeof func === 'function') { return func; }
      if (typeof func === 'string' && typeof window[func] === 'function') { return window[func]; }
      return null;
    }
  };

  // ─── Módulo de formularios ($.fn.*) ──────────────────────────────────────────

  $.fn.formToArray = function (semantic) {
    var arr = [];
    if (!this.length) { return arr; }
    var form = this[0];
    var els  = semantic ? form.getElementsByTagName('*') : form.elements;
    if (!els) { return arr; }

    Array.from(els).forEach(function (elem) {
      var nam = elem.name;
      if (!nam) { return; }
      var val = $.fieldValue(elem, true);
      if (Array.isArray(val)) {
        val.forEach(function (v) { arr.push({ name: nam, value: v }); });
      } else if (val !== null && typeof val !== 'undefined') {
        arr.push({ name: nam, value: val });
      }
    });
    return arr;
  };

  $.fn.formSerialize  = function (semantic) { return $.param(this.formToArray(semantic)); };

  $.fn.fieldSerialize = function (successful) {
    var arr = [];
    this.each(function () {
      var nam = this.name;
      if (!nam) { return; }
      var val = $.fieldValue(this, successful);
      if (Array.isArray(val)) {
        val.forEach(function (v) { arr.push({ name: nam, value: v }); });
      } else if (val !== null && typeof val !== 'undefined') {
        arr.push({ name: nam, value: val });
      }
    });
    return $.param(arr);
  };

  $.fieldValue = function (elem, successful) {
    var typ = elem.type, tag = elem.tagName.toLowerCase();
    if (typeof successful === 'undefined') { successful = true; }

    if (successful && typ === 'radio') {
      return elem.checked ? elem.value : null;
    }
    if (successful && typ === 'checkbox') {
      return elem.checked ? (elem.value || '1') : '0';
    }
    if (successful && (!elem.name || elem.disabled || typ === 'reset' || typ === 'button' ||
        ((typ === 'submit' || typ === 'image') && elem.form && elem.form.clk !== elem) ||
        (tag === 'select' && elem.selectedIndex === -1))) {
      return null;
    }
    if (tag === 'select') {
      if (elem.selectedIndex < 0) { return null; }
      var one = typ === 'select-one';
      var selected = Array.from(elem.options).filter(function (op) { return op.selected; });
      var vals = selected.map(function (op) {
        return (op.attributes['value'] && op.attributes['value'].specified) ? op.value : op.text;
      });
      return one ? (vals[0] || null) : vals;
    }
    return elem.value;
  };

  $.fn.clearForm   = function () { return this.each(function () { $('input,select,textarea', this).clearFields(); }); };
  $.fn.clearFields = $.fn.clearInputs = function () {
    return this.each(function () {
      var typ = this.type, tag = this.tagName.toLowerCase();
      if (typ === 'text' || typ === 'password' || tag === 'textarea') { this.value = ''; }
      else if (typ === 'checkbox' || typ === 'radio')                 { this.checked = false; }
      else if (tag === 'select')                                       { this.selectedIndex = -1; }
    });
  };

  $.fn.resetForm = function (hidden) {
    hidden = (hidden !== false);
    return this.each(function () {
      if (typeof this.reset === 'function') { this.reset(); }
      if (hidden) { $(this).find('input[type=hidden]').val(''); }
    });
  };

  $.fn.enable = function (boo) {
    return this.each(function () { this.disabled = !(boo !== false); });
  };

  $.fn.select = function (select) {
    select = (select !== false);
    return this.each(function () {
      var type = this.type;
      if (type === 'checkbox' || type === 'radio') {
        this.checked = select;
      } else if (this.tagName.toLowerCase() === 'option') {
        var $sel = $(this).parent('select');
        if (select && $sel[0] && $sel[0].type === 'select-one') {
          $sel.find('option').select(false);
        }
        this.selected = select;
      }
    });
  };

  // ─── Namespace público APJ ───────────────────────────────────────────────────

  var APJ = {

    // Datos de usuario extensibles desde fuera
    data: {},

    // Hooks de ciclo de vida
    onSessionEnd: null,
    onComplete:   null,

    /**
     * Inicializa el plugin.
     * @param {object} options - { url: string, timeout: number, images: string }
     */
    init: function (options) {
      $.extend(_config, options || {});
      return this;
    },

    /**
     * Registra una función para ser llamada desde PHP via acción "Script".
     * Reemplaza el uso de eval().
     * @param {string}   name - Nombre de la función (debe coincidir con o.fn en PHP)
     * @param {function} fn   - Función a ejecutar, recibe params como objeto
     */
    register: function (name, fn) {
      _registry[name] = fn;
      return this;
    },

    /**
     * Envío AJAX genérico.
     */
    call: function (params) {
      return _ajax.send(params);
    },

    /**
     * Equivalente a APJCall: llama un método PHP con argumentos.
     * @example APJ.APJCall('MiMetodo', param1, param2)
     */
    APJCall: function () {
      var args = Array.prototype.slice.call(arguments);
      return _ajax.send({ action: '_APJCall', data: JSON.stringify(args) });
    },

    /**
     * Equivalente a APJSubmit: envía un formulario por AJAX.
     * @example APJ.APJSubmit('formId', 'action', extraParam1, extraParam2)
     */
    APJSubmit: function (form, action) {
      var args   = Array.prototype.slice.call(arguments);
      action     = action || 'main';
      var params = $('form#' + form).formToArray(true);
      var hasAction = params.some(function (p) { return p.name === 'action'; });
      if (!hasAction) { params.push({ name: 'action', value: action }); }
      if (args.length > 2) {
        params.push({ name: 'parameters', value: JSON.stringify(args.slice(2)) });
      }
      return _ajax.send(params);
    },

    /**
     * Inicia un poll silencioso — llama un método PHP cada `interval` ms sin mostrar jBusy.
     * @param {string}  method    Nombre del método PHP
     * @param {number}  interval  Intervalo en ms (ej: 5000)
     * @param {*}       params    Parámetros opcionales (array o cualquier valor JSON)
     * @param {object}  options   { maxErrors: N, onError: 'silent'|'stop'|fn, immediate: bool }
     * @example APJ.poll('checkMsgs', 5000, [roomId]);
     * @example APJ.poll('keepAlive', 240000);
     */
    poll: function (method, interval, params, options) {
      APJ.stopPoll(method);
      var opts = {
        maxErrors: (options && options.maxErrors) || 0,
        onError:   (options && options.onError)   || 'silent',
        immediate: !!(options && options.immediate)
      };
      _polls[method] = {
        timerId: setInterval(function () { _pollTick(method, params); }, interval),
        errors:  0,
        opts:    opts
      };
      if (opts.immediate) { _pollTick(method, params); }
      return this;
    },

    /**
     * Detiene el poll del método indicado.
     */
    stopPoll: function (method) {
      if (_polls[method]) {
        clearInterval(_polls[method].timerId);
        delete _polls[method];
      }
      return this;
    },

    /**
     * Detiene todos los polls activos.
     */
    stopAllPolls: function () {
      Object.keys(_polls).forEach(function (k) { APJ.stopPoll(k); });
      return this;
    },

    // Acceso directo a diálogos
    dialog: _dialogs,

    /**
     * Expone los helpers globales (jInfo, jError, APJCall, etc.) en window.
     * Útil durante migración desde 1.9, o si se prefiere el estilo global.
     */
    exposeGlobals: function () {
      window.jInfo    = function (m, t, cb, p) { _dialogs.info(m, t, cb, p);    $('#dialogOk').trigger('focus'); };
      window.jWarning = function (m, t, cb, p) { _dialogs.warning(m, t, cb, p); $('#dialogOk').trigger('focus'); };
      window.jError   = function (m, t, cb, p) { _dialogs.error(m, t, cb, p);   $('#dialogOk').trigger('focus'); };
      window.jConfirm = function (m, t, cb, p) { _dialogs.confirm(m, t, cb, p); $('#dialogOk').trigger('focus'); };
      window.jPrompt  = function (m, t, cb, p) { _dialogs.prompt(m, t, cb, p);  $('#prompt_value').trigger('focus'); };
      window.jProcess = function (m, t, s)     { _dialogs.process(m, t, s); };
      window.jBusy    = function ()            { _dialogs.busy(); };
      window.jClose   = function ()            { _dialogs.close(); };
      window.APJCall  = function ()            { APJ.APJCall.apply(APJ, arguments); };
      window.APJSubmit = function ()           { APJ.APJSubmit.apply(APJ, arguments); };
      window.jShowDown = APJ.jShowDown;
      return this;
    },

    jShowDown: function (input, container) {
      var $cont  = $('#' + container);
      var $input = $('#' + input);
      if ($cont.width() < $input.width()) { $cont.width($input.width()); }
      var offset = $input.offset();
      var bottom = offset.top + $input.outerHeight() + 1;
      $cont.is(':visible')
        ? $cont.hide()
        : $cont.show().offset({ top: bottom, left: offset.left });
    }
  };

  // Expone APJ globalmente
  window.APJ = APJ;

})(jQuery);

/*!
 * ─── Guía de migración 1.9 → 2.0 ───────────────────────────────────────────
 *
 * 1. INICIALIZACIÓN
 *    Antes:  var url = 'controller.php';   (variable global suelta)
 *    Ahora:  APJ.init({ url: 'controller.php', timeout: 15000 });
 *
 * 2. LLAMADAS DESDE HTML
 *    Opción A - usar el namespace directamente (recomendado):
 *      onclick="APJ.APJCall('MiMetodo', param1); return false;"
 *      onclick="APJ.APJSubmit('miForm', 'guardar'); return false;"
 *
 *    Opción B - exponer globales para migración gradual:
 *      APJ.exposeGlobals();
 *      onclick="APJCall('MiMetodo', param1); return false;"   // igual que antes
 *
 * 3. DIÁLOGOS DESDE JS
 *    Antes:  jInfo('mensaje', 'título');
 *    Ahora:  APJ.dialog.info('mensaje', 'título');
 *    Con globals: APJ.exposeGlobals() → jInfo('mensaje', 'título');
 *
 * 4. SCRIPTS DESDE PHP (reemplaza eval)
 *    Antes PHP devolvía:  { "action": { "Script": [{ "scr": "alert('ok')" }] } }
 *    Ahora PHP devuelve:  { "action": { "Script": [{ "fn": "miAccion", "params": {} }] } }
 *
 *    En JS registrar antes de recibir la respuesta:
 *      APJ.register('miAccion', function(params) { console.log(params); });
 *
 * 5. EVENTOS jQuery
 *    .bind() fue eliminado en jQuery 3. El plugin lo reemplaza automáticamente
 *    con .on() en la capa _applyMethod. No requiere cambios en PHP.
 */
