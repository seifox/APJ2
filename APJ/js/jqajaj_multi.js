/*!
 * APJ Multi-idioma - Async PHP jQuery Plugin
 * Version 2.0.7 (numeración alineada con jqajaj.js 2.0.7)
 * Requiere jQuery 3.x | PHP 8.x
 *
 * Misma funcionalidad que jqajaj.js 2.0.7 + sistema multilingüe. Todo cambio
 * de jqajaj.js debe replicarse aquí (y viceversa), regenerando ambos .min.js.
 *
 * Novedades en 2.0.1:
 * - Sistema multilingüe: idiomas base incluidos (es, en, pt, fr, de)
 * - APJ.init({ lang: 'en' }) para seleccionar idioma base
 * - APJ.init({ translations: {...} }) para sobreescribir desde PHP
 * - Ambos combinables: lang selecciona la base, translations sobreescribe claves
 * - APJ.addLang() para agregar idiomas adicionales en runtime
 *
 * Cambios en 2.0.2:
 * - Corregido botón "No" de jConfirm: ya no reinvoca el callback de "Sí" con
 *   (false). Antes, si el callback era 'APJSubmit', se terminaba llamando
 *   APJSubmit(false), que caía al action por defecto 'main' y producía
 *   "El método main no existe!" en el servidor. Ahora "No" solo cierra el diálogo.
 *
 * Cambios en 2.0.3 (de esta versión multi-idioma; equivale a 2.0.2 de jqajaj.js):
 * - _runAction: nuevas acciones nativas APJSubmit/APJCall (misma familia que
 *   jInfo/Script) - el servidor las encola vía jQ::APJSubmit()/jQ::APJCall()
 *   (ver APJ2/APJ/jQ.php) para disparar un APJSubmit()/APJCall() real del
 *   lado del cliente de inmediato, sin pasar por jScript()/new Function().
 *
 * Cambios en 2.0.7 (22-oct-2026) - puesta al día con jqajaj.js 2.0.3 a 2.0.7:
 * - $.fieldValue(): un radio SIN marcar ya no envía '0' - se excluye del
 *   formulario (jqajaj.js 2.0.3). Antes, en un grupo de radios con el mismo
 *   name, el que apareciera último en el HTML siempre ganaba en PHP.
 * - Nuevas APJSubmitSilent()/APJCallSilent() (+ globals vía exposeGlobals()):
 *   idénticas a APJSubmit()/APJCall() pero sin mostrar el busy
 *   (jqajaj.js 2.0.5). _ajax.send() acepta un 2do parámetro opcional {busy:false}.
 * - Diálogos y busy con DOM propio, creado una sola vez y reutilizado
 *   (jqajaj.js 2.0.6): #dialogbox.<tipo>Dialog > #dialogboxhead (#dialogboxicon
 *   + #dialogboxtitle) > #dialogboxbody > #dialogboxtext > #dialogboxfoot. El
 *   tipo va como clase en #dialogbox (infoDialog, warningDialog, ...) en vez de
 *   las clases infoBody/warningBody/... - REQUIERE el APJ/css/jqajaj.css nuevo.
 * - Nueva opción _config.keyLock (true por defecto): con busy() el teclado queda
 *   bloqueado; con un diálogo abierto, confinado a él (Tab cicla entre sus
 *   botones), y al cerrar el foco vuelve a donde estaba (jqajaj.js 2.0.6).
 * - Con un diálogo abierto, los focusin dentro de #dialogbox no se propagan, para
 *   que el focus trap de una ventana modal de la página no le robe el foco
 *   (jqajaj.js 2.0.7).
 *
 * Inicialización típica generada por el framework PHP:
 *   APJ.init({
 *     url:          "APJ:{url()}",
 *     timeout:       APJ:{timeout()},
 *     lang:         "APJ:{lang()}",
 *     translations:  APJ:{translations()}
 *   });
 */
;(function ($) {
  'use strict';

  // ─── Idiomas base incluidos ──────────────────────────────────────────────────
  // Estructura: titles (títulos de diálogos), buttons, prompt, http (errores)

  var _i18n = {
    es: {
      titles: {
        info:    'Información',
        warning: 'Advertencia',
        error:   'Error',
        confirm: 'Confirmar',
        prompt:  'Introduzca un valor',
        process: 'Proceso en curso'
      },
      buttons: {
        ok:     'Aceptar',
        yes:    'Sí',
        no:     'No',
        cancel: 'Cancelar'
      },
      prompt: {
        placeholder: 'valor'
      },
      http: {
        0:       'No conectado o tiempo de espera agotado.<br>Verifique su conexión de red.',
        200:     'Error de respuesta desde el servidor o sesión finalizada.',
        404:     'El controlador no fue encontrado.',
        408:     'Tiempo de espera agotado.',
        500:     'Error del servidor.',
        unknown: 'Error desconocido.'
      }
    },
    en: {
      titles: {
        info:    'Information',
        warning: 'Warning',
        error:   'Error',
        confirm: 'Confirm',
        prompt:  'Enter a value',
        process: 'Processing'
      },
      buttons: {
        ok:     'OK',
        yes:    'Yes',
        no:     'No',
        cancel: 'Cancel'
      },
      prompt: {
        placeholder: 'value'
      },
      http: {
        0:       'Not connected or request timed out.<br>Please check your network connection.',
        200:     'Server response error or session expired.',
        404:     'Controller not found.',
        408:     'Request timed out.',
        500:     'Server error.',
        unknown: 'Unknown error.'
      }
    },
    pt: {
      titles: {
        info:    'Informação',
        warning: 'Aviso',
        error:   'Erro',
        confirm: 'Confirmar',
        prompt:  'Insira um valor',
        process: 'Processando'
      },
      buttons: {
        ok:     'OK',
        yes:    'Sim',
        no:     'Não',
        cancel: 'Cancelar'
      },
      prompt: {
        placeholder: 'valor'
      },
      http: {
        0:       'Sem conexão ou tempo esgotado.<br>Verifique sua conexão de rede.',
        200:     'Erro de resposta do servidor ou sessão encerrada.',
        404:     'Controlador não encontrado.',
        408:     'Tempo de requisição esgotado.',
        500:     'Erro do servidor.',
        unknown: 'Erro desconhecido.'
      }
    },
    fr: {
      titles: {
        info:    'Information',
        warning: 'Avertissement',
        error:   'Erreur',
        confirm: 'Confirmer',
        prompt:  'Saisir une valeur',
        process: 'Traitement en cours'
      },
      buttons: {
        ok:     'OK',
        yes:    'Oui',
        no:     'Non',
        cancel: 'Annuler'
      },
      prompt: {
        placeholder: 'valeur'
      },
      http: {
        0:       'Non connecté ou délai expiré.<br>Vérifiez votre connexion réseau.',
        200:     'Erreur de réponse du serveur ou session expirée.',
        404:     'Contrôleur introuvable.',
        408:     'Délai de la requête expiré.',
        500:     'Erreur serveur.',
        unknown: 'Erreur inconnue.'
      }
    },
    de: {
      titles: {
        info:    'Information',
        warning: 'Warnung',
        error:   'Fehler',
        confirm: 'Bestätigen',
        prompt:  'Wert eingeben',
        process: 'Wird verarbeitet'
      },
      buttons: {
        ok:     'OK',
        yes:    'Ja',
        no:     'Nein',
        cancel: 'Abbrechen'
      },
      prompt: {
        placeholder: 'Wert'
      },
      http: {
        0:       'Keine Verbindung oder Zeitüberschreitung.<br>Bitte Netzwerkverbindung prüfen.',
        200:     'Serverfehler oder Sitzung abgelaufen.',
        404:     'Controller nicht gefunden.',
        408:     'Zeitüberschreitung der Anfrage.',
        500:     'Serverfehler.',
        unknown: 'Unbekannter Fehler.'
      }
    }
  };

  // Textos activos — se construyen al llamar APJ.init()
  var _t = _i18n['es'];

  // ─── Configuración por defecto ───────────────────────────────────────────────

  var _config = {
    url:          '',          // Requerido: definir en APJ.init()
    timeout:      10000,
    images:       'APJ/images/',
    keyLock:      true,        // busy() bloquea el teclado y un diálogo lo confina (false: no se toca)
    lang:         'es',
    translations: {}
  };

  // ─── Registro de funciones (reemplaza eval) ──────────────────────────────────

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

  var EFFECT_METHODS = ['show','hide','slideDown','slideUp','slideToggle','fadeIn','fadeOut'];
  var EVENT_METHODS  = [
    'blur','change','click','dblclick','error','focus',
    'keydown','keypress','keyup','load','mousedown',
    'mousemove','mouseout','mouseover','mouseup',
    'resize','scroll','select','submit'
  ];

  // ─── Construye _t: idioma base + sobreescritura parcial desde PHP ────────────

  function _buildTranslations(lang, overrides) {
    var base = _i18n[lang] || _i18n['es'];
    _t = {
      titles:  $.extend({}, base.titles,  (overrides.titles  || {})),
      buttons: $.extend({}, base.buttons, (overrides.buttons || {})),
      prompt:  $.extend({}, base.prompt,  (overrides.prompt  || {})),
      http:    $.extend({}, base.http,    (overrides.http    || {}))
    };
  }

  // ─── Módulo AJAX ─────────────────────────────────────────────────────────────

  var _ajax = {

    send: function (params, opts) {
      if (!_config.url) {
        console.error('[APJ] url no configurada. Usa APJ.init({ url: "..." }) primero.');
        return;
      }
      if (!(opts && opts.busy === false)) { _dialogs.busy(); }
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
              _t.titles.error
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
        case 0:  return $sel[method]();
        case 1:  return $sel[method](argSet[0]);
        case 2:  return $sel[method](argSet[0], argSet[1]);
        case 3:  return $sel[method](argSet[0], argSet[1], argSet[2]);
        default: return $sel[method].apply($sel, argSet);
      }
    },

    _runAction: function (func, params) {
      var actions = {
        jInfo:    function (o) { _dialogs.info(o.msg,    o.title, o.callback, o.params); },
        jWarning: function (o) { _dialogs.warning(o.msg, o.title, o.callback, o.params); },
        jError:   function (o) { _dialogs.error(o.msg,   o.title, o.callback, o.params); },
        jConfirm: function (o) { _dialogs.confirm(o.msg, o.title, o.callback, o.params); },
        jPrompt:  function (o) { _dialogs.prompt(o.msg,  o.title, o.callback, o.params); },
        jProcess: function (o) { _dialogs.process(o.msg, o.title, o.style); },
        jBusy:    function ()  { _dialogs.busy(); },
        jClose:   function ()  { _dialogs.close(); },
        setData:  function (o) {
          var cb = o.callback || 'defaultCallBack';
          if (APJ.data && typeof APJ.data[cb] === 'function') { APJ.data[cb](o.key, o.value); }
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
      var desc;
      if (code === 200) {
        desc = _t.http[200];
        if (typeof APJ.onSessionEnd === 'function') { APJ.onSessionEnd(); }
      } else {
        desc = _t.http[code >= 500 ? 500 : code]
               || (_t.http.unknown + '<br>' + errorThrown + '<br>' + textStatus);
      }
      _dialogs.error(desc, _t.titles.error + ' ' + code);
    },

    _complete: function () {
      if (typeof APJ.onComplete === 'function') { APJ.onComplete(); }
    }
  };

  // ─── Módulo de diálogos ───────────────────────────────────────────────────────
  // DOM propio de jqajaj, sin depender de ningún framework CSS: el aspecto sale
  // solo de APJ/css/jqajaj.css y de los íconos de APJ/images/ (<tipo>32.png),
  // que cada sitio modifica a su gusto. Estructura:
  //   #dialogoverlay
  //   #dialogbox.<tipo>Dialog > #dialogboxhead (#dialogboxicon + #dialogboxtitle)
  //                           > #dialogboxbody > #dialogboxtext
  //                           > #dialogboxfoot (#dialogOk / #dialogCancel)
  //   #busyoverlay + #busybox
  // <tipo> = info | warning | error | confirm | prompt | process.
  // Se crean una sola vez y se reutilizan. Mientras hay un diálogo abierto, con
  // _config.keyLock el teclado queda confinado a él (Tab cicla entre sus botones);
  // con el busy, el teclado queda bloqueado. Ninguno de los dos mueve el foco de
  // la página: al cerrar un diálogo, el foco vuelve a donde estaba.

  var _dialogs = {
    _blinkId:   0,
    _isOpen:    false,
    _mode:      '',      // '' | 'busy' | 'dialog'
    _lastFocus: null,

    info:    function (msg, title, cb, params) { this._render('info',    msg, title || _t.titles.info,    cb, params); },
    warning: function (msg, title, cb, params) { this._render('warning', msg, title || _t.titles.warning, cb, params); },
    error:   function (msg, title, cb, params) { this._render('error',   msg, title || _t.titles.error,   cb, params); },
    confirm: function (msg, title, cb, params) { this._render('confirm', msg, title || _t.titles.confirm, cb, params); },
    prompt:  function (msg, title, cb, params) { this._render('prompt',  msg, title || _t.titles.prompt,  cb, params); },
    process: function (msg, title, style)      { this._render('process', msg, title || _t.titles.process, null, null, style); },

    busy: function () {
      if (this._isOpen) { return; }
      this._ensureDom();
      this._open('busy');
      $('#busyoverlay, #busybox').show();
    },

    _ensureDom: function () {
      if (document.getElementById('dialogbox')) { return; }
      $(document.body).append(
        '<div id="dialogoverlay"></div>' +
        '<div id="dialogbox" role="dialog" aria-modal="true" aria-labelledby="dialogboxtitle">' +
          '<div id="dialogboxhead">' +
            '<img id="dialogboxicon" src="" alt="">' +
            '<span id="dialogboxtitle"></span>' +
          '</div>' +
          '<div id="dialogboxbody"><div id="dialogboxtext"></div></div>' +
          '<div id="dialogboxfoot"></div>' +
        '</div>' +
        '<div id="busyoverlay"></div>' +
        '<div id="busybox"><img src="' + _config.images + 'busy32.gif" alt=""></div>'
      );
    },

    _render: function (type, message, header, callback, params, style) {
      this.close();
      this._ensureDom();

      var cfg = this._typeConfig(type, message, callback, params);

      $('#dialogbox').attr('class', type + 'Dialog');
      $('#dialogboxicon').attr('src', _config.images + cfg.image);
      $('#dialogboxtitle').text(header);
      $('#dialogboxtext').html(cfg.text);
      $('#dialogboxfoot').html(cfg.foot);
      cfg.bindEvents && cfg.bindEvents();

      this._lastFocus = document.activeElement;
      this._open('dialog');
      $('#dialogoverlay, #dialogbox').show();
      $('#dialogbox').find('input, button').first().trigger('focus');
      if (style === 'blink') {
        this._blinkId = setInterval(this._blinker, 1500);
      }
    },

    _typeConfig: function (type, message, callback, params) {
      var self = this;
      var cb   = this._resolve(callback);
      var cfg  = { text: message, foot: '', image: type + '32.png', bindEvents: null };

      switch (type) {
        case 'info':
        case 'warning':
        case 'error':
          cfg.foot = '<button type="button" id="dialogOk">' + _t.buttons.ok + '</button>';
          cfg.bindEvents = function () {
            $('#dialogOk').on('click', function () {
              self.close();
              if (cb) { cb.apply(null, params || []); }
            });
          };
          break;

        case 'confirm':
          cfg.image = 'question32.png';
          cfg.foot  = '<button type="button" id="dialogOk">'     + _t.buttons.yes + '</button> ' +
                      '<button type="button" id="dialogCancel">' + _t.buttons.no  + '</button>';
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
          cfg.text  = message + ': <input id="prompt_value" placeholder="' + _t.prompt.placeholder + '">';
          cfg.foot  = '<button type="button" id="dialogOk">'     + _t.buttons.ok     + '</button> ' +
                      '<button type="button" id="dialogCancel">' + _t.buttons.cancel + '</button>';
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
          break;
      }
      return cfg;
    },

    _open: function (mode) {
      this._isOpen = true;
      this._mode   = mode;
      if (_config.keyLock) { document.addEventListener('keydown', this._keyGuard, true); }
      if (mode === 'dialog') { document.addEventListener('focusin', this._focusGuard, true); }
    },

    close: function () {
      var mode = this._mode;
      this._isOpen = false;
      this._mode   = '';
      document.removeEventListener('keydown', this._keyGuard, true);
      document.removeEventListener('focusin', this._focusGuard, true);
      $('#dialogoverlay, #dialogbox, #busyoverlay, #busybox').hide();
      if (this._blinkId) { clearInterval(this._blinkId); this._blinkId = 0; }
      if (mode === 'dialog' && this._lastFocus && document.body.contains(this._lastFocus)) {
        $(this._lastFocus).trigger('focus');
      }
      this._lastFocus = null;
    },

    // Busy: bloquea toda tecla. Diálogo: deja pasar las teclas dentro del cuadro,
    // Tab/Shift+Tab ciclan entre sus campos y botones, y bloquea el resto.
    _keyGuard: function (e) {
      var box = document.getElementById('dialogbox');
      if (_dialogs._mode === 'dialog' && e.key === 'Tab') {
        var items = $(box).find('input, button').filter(':visible').get();
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!items.length) { return; }
        var i = items.indexOf(document.activeElement);
        i = e.shiftKey ? (i <= 0 ? items.length - 1 : i - 1) : (i + 1) % items.length;
        items[i].focus();
        return;
      }
      if (_dialogs._mode === 'dialog' && box.contains(e.target)) { return; }
      e.preventDefault();
      e.stopImmediatePropagation();
    },

    // Si la página tiene su propia ventana modal abierta con "focus trap" (ej. un
    // modal de un framework CSS), ese trap escucha focusin en document y devuelve
    // el foco a su ventana. Cortar aquí los focusin del cuadro de diálogo evita
    // que se entere y le quite el foco al diálogo de jqajaj.
    _focusGuard: function (e) {
      var box = document.getElementById('dialogbox');
      if (box && box.contains(e.target)) { e.stopPropagation(); }
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
      var one      = typ === 'select-one';
      var selected = Array.from(elem.options).filter(function (op) { return op.selected; });
      var vals     = selected.map(function (op) {
        return (op.attributes['value'] && op.attributes['value'].specified) ? op.value : op.text;
      });
      return one ? (vals[0] || null) : vals;
    }
    return elem.value;
  };

  $.fn.clearForm   = function () {
    return this.each(function () { $('input,select,textarea', this).clearFields(); });
  };
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

    data:         {},    // Extensible desde fuera
    onSessionEnd: null,  // Hook: se llama cuando la sesión expira (HTTP 200 inesperado)
    onComplete:   null,  // Hook: se llama al completar cada petición AJAX

    /**
     * Inicializa el plugin.
     *
     * @param {object} options
     * @param {string}   options.url          - URL del controlador PHP       [requerido]
     * @param {number}   options.timeout      - Timeout en ms                 [default: 10000]
     * @param {string}   options.images       - Ruta a imágenes               [default: 'Libs/APJ/images/']
     * @param {string}   options.lang         - Código de idioma base         [default: 'es']
     *                                          Incluidos: es, en, pt, fr, de
     * @param {object}   options.translations - Sobreescritura parcial de textos.
     *                                          PHP puede enviar {} (sin cambios) o un
     *                                          objeto con solo las claves a cambiar.
     *
     * Ejemplo generado por el framework:
     *   APJ.init({
     *     url:          "APJ:{url()}",
     *     timeout:       APJ:{timeout()},
     *     lang:         "APJ:{lang()}",
     *     translations:  APJ:{translations()}
     *   });
     *
     * Ejemplo con sobreescritura parcial (solo cambia lo que necesita):
     *   APJ.init({
     *     url:  '/app/controller',
     *     lang: 'es',
     *     translations: {
     *       titles:  { confirm: 'Confirme la operación' },
     *       buttons: { yes: 'Confirmar', no: 'Volver' }
     *     }
     *   });
     */
    init: function (options) {
      $.extend(_config, options || {});
      _buildTranslations(_config.lang, _config.translations || {});
      return this;
    },

    /**
     * Agrega o reemplaza un idioma completo en runtime.
     * Útil si el framework sirve idiomas adicionales sin recargar el plugin.
     *
     * @param {string} code   - Código del idioma (ej: 'it', 'ca', 'zh')
     * @param {object} locale - Objeto con estructura: { titles, buttons, prompt, http }
     *
     * @example
     *   APJ.addLang('it', { titles: { info: 'Informazione', ... }, ... });
     *   APJ.init({ url: '...', lang: 'it' });
     */
    addLang: function (code, locale) {
      _i18n[code] = locale;
      return this;
    },

    /**
     * Registra una función para ser llamada desde PHP via acción "Script".
     * Reemplaza el uso de eval().
     *
     * PHP devuelve: { "action": { "Script": [{ "fn": "nombre", "params": {} }] } }
     *
     * @example
     *   APJ.register('recargarTabla', function(p) { cargarDatos(p.filtro); });
     */
    register: function (name, fn) {
      _registry[name] = fn;
      return this;
    },

    /** Envío AJAX genérico */
    call: function (params) {
      return _ajax.send(params);
    },

    /**
     * Llama un método PHP con argumentos posicionales.
     * @example APJ.APJCall('MiMetodo', param1, param2)
     */
    APJCall: function () {
      var args = Array.prototype.slice.call(arguments);
      return _ajax.send({ action: '_APJCall', data: JSON.stringify(args) });
    },

    /**
     * Igual que APJCall, pero sin mostrar el indicador "ocupado" (#busyoverlay) - para
     * llamadas rápidas/locales donde el busy() no aporta nada, o que no deben
     * bloquear el teclado mientras corren (ej. Tab disparado desde un onblur).
     * Mismo comportamiento en todo lo demás.
     * @example APJ.APJCallSilent('MiMetodo', param1, param2)
     */
    APJCallSilent: function () {
      var args = Array.prototype.slice.call(arguments);
      return _ajax.send({ action: '_APJCall', data: JSON.stringify(args) }, { busy: false });
    },

    /**
     * Equivalente a APJSubmit: envía un formulario por AJAX.
     * @example APJ.APJSubmit('formId', 'action', extraParam1, extraParam2)
     */
    APJSubmit: function (form, action) {
      var args      = Array.prototype.slice.call(arguments);
      action        = action || 'main';
      var params    = $('form#' + form).formToArray(true);
      var hasAction = params.some(function (p) { return p.name === 'action'; });
      if (!hasAction) { params.push({ name: 'action', value: action }); }
      if (args.length > 2) {
        params.push({ name: 'parameters', value: JSON.stringify(args.slice(2)) });
      }
      return _ajax.send(params);
    },

    /**
     * Igual que APJSubmit, pero sin mostrar el indicador "ocupado" (#busyoverlay) - ver
     * APJCallSilent. Misma firma exacta que APJSubmit.
     * @example APJ.APJSubmitSilent('formId', 'action', extraParam1, extraParam2)
     */
    APJSubmitSilent: function (form, action) {
      var args   = Array.prototype.slice.call(arguments);
      action     = action || 'main';
      var params = $('form#' + form).formToArray(true);
      var hasAction = params.some(function (p) { return p.name === 'action'; });
      if (!hasAction) { params.push({ name: 'action', value: action }); }
      if (args.length > 2) {
        params.push({ name: 'parameters', value: JSON.stringify(args.slice(2)) });
      }
      return _ajax.send(params, { busy: false });
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

    stopPoll: function (method) {
      if (_polls[method]) {
        clearInterval(_polls[method].timerId);
        delete _polls[method];
      }
      return this;
    },

    stopAllPolls: function () {
      Object.keys(_polls).forEach(function (k) { APJ.stopPoll(k); });
      return this;
    },

    /** Acceso directo al módulo de diálogos */
    dialog: _dialogs,

    /**
     * Expone helpers globales en window para migración gradual desde APJ 1.9.
     * También útil si se prefiere llamarlos directamente desde atributos HTML.
     *
     * @example
     *   APJ.init({ url: '...' }).exposeGlobals();
     *   // Desde HTML: onclick="APJCall('Metodo'); return false;"
     *   // Desde HTML: onclick="jConfirm('¿Eliminar?','Confirmar',borrar,[id]); return false;"
     */
    exposeGlobals: function () {
      window.jInfo     = function (m, t, cb, p) { _dialogs.info(m, t, cb, p);    $('#dialogOk').trigger('focus'); };
      window.jWarning  = function (m, t, cb, p) { _dialogs.warning(m, t, cb, p); $('#dialogOk').trigger('focus'); };
      window.jError    = function (m, t, cb, p) { _dialogs.error(m, t, cb, p);   $('#dialogOk').trigger('focus'); };
      window.jConfirm  = function (m, t, cb, p) { _dialogs.confirm(m, t, cb, p); $('#dialogOk').trigger('focus'); };
      window.jPrompt   = function (m, t, cb, p) { _dialogs.prompt(m, t, cb, p);  $('#prompt_value').trigger('focus'); };
      window.jProcess  = function (m, t, s)     { _dialogs.process(m, t, s); };
      window.jBusy     = function ()            { _dialogs.busy(); };
      window.jClose    = function ()            { _dialogs.close(); };
      window.APJCall   = function ()            { APJ.APJCall.apply(APJ, arguments); };
      window.APJSubmit = function ()            { APJ.APJSubmit.apply(APJ, arguments); };
      window.APJCallSilent   = function ()      { APJ.APJCallSilent.apply(APJ, arguments); };
      window.APJSubmitSilent = function ()      { APJ.APJSubmitSilent.apply(APJ, arguments); };
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

  window.APJ = APJ;

})(jQuery);

/*!
 * ─── Guía de migración 1.9 → 2.0 ───────────────────────────────────────────
 *
 * 1. INICIALIZACIÓN
 *    Antes (variables globales sueltas generadas por el framework):
 *      var url     = "APJ:{self()}";
 *      var timeout = APJ:{timeout()};
 *
 *    Ahora (APJ.init generado por el framework):
 *      APJ.init({
 *        url:          "APJ:{url()}",
 *        timeout:       APJ:{timeout()},
 *        lang:         "APJ:{lang()}",
 *        translations:  APJ:{translations()}
 *      });
 *
 * 2. LLAMADAS DESDE HTML
 *    Opción A - namespace directo (recomendado):
 *      onclick="APJ.APJCall('MiMetodo', param1); return false;"
 *      onclick="APJ.APJSubmit('miForm', 'guardar'); return false;"
 *
 *    Opción B - helpers globales (migración gradual, idéntico a 1.9):
 *      APJ.init({...}).exposeGlobals();
 *      onclick="APJCall('MiMetodo', param1); return false;"
 *
 * 3. DIÁLOGOS DESDE JS
 *    Antes:          jInfo('mensaje', 'título');
 *    Ahora:          APJ.dialog.info('mensaje', 'título');
 *    Con globals:    jInfo('mensaje', 'título');  ← idéntico a 1.9
 *
 * 4. IDIOMA
 *    Sin configurar: español por defecto
 *    Desde PHP:      lang: "APJ:{lang()}"  → el framework inyecta 'es','en','pt','fr','de'
 *    Sobreescritura: translations: { buttons: { yes: 'Confirmar' } }
 *    Idioma nuevo:   APJ.addLang('it', {...}).init({ lang: 'it' });
 *
 * 5. SCRIPTS DESDE PHP (reemplaza eval)
 *    PHP devuelve: { "action": { "Script": [{ "fn": "miAccion", "params": {} }] } }
 *    JS registra:  APJ.register('miAccion', function(p) { ... });
 *
 * 6. EVENTOS jQuery
 *    .bind() fue eliminado en jQuery 3. El plugin lo resuelve automáticamente
 *    con .on() en _applyMethod. No requiere ningún cambio en PHP.
 */
