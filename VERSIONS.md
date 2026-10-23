# Versiones
## APJ 1.9.2412
- Mejoras en APJcommon
  - Se mejoró el metodo convertDateTime()
## APJ 2.0
  - Versión 2.0.2412 Beta para PHP 7 y 8
  - Versión 2.0.2611 (oct-2026), probada en PHP 8.3
    - APJModel: upsert(); getStructureCode(), getDocComment(), getCreateTableCode(); basicValidation() retorna true si es válido; columnas generadas (VIRTUAL/STORED) excluidas al guardar; defaults numéricos correctos en showStructure()
    - APJModelTool + apjtool.php: promover/documentar/revertir modelos (DEV/PROD, vistas, tablas inexistentes)
    - APJController: APJSubmit()/APJCall() desde el controlador (acciones nativas de jQ); absoluteUrl(); getParameters() con json_decode; argumentos de APJ:{...} sin comillas y con valores por defecto; <base href> con ROOTURL
    - APJCommon: format() trata '' como NULL en fechas; isHttps() local (detrás de proxy)
    - APJPDO: execute()/query() detectan bien la primera palabra en SQL de varias líneas; trim(null) protegido
    - APJSession: ya no invalida la sesión por cambio de IP; cookies secure detrás de proxy
    - APJAutoload: fallback a minúsculas para Controllers\ (Linux)
    - jqajaj.js 2.0.7: APJSubmitSilent/APJCallSilent, diálogos con DOM propio y teclado confinado, jConfirm "No" sin callback, radios sin marcar no se envían
    - jqajaj_multi.js 2.0.7 al día con jqajaj.js; jqajaj.css autosuficiente (no depende del CSS del sitio)
    - Manual de referencia reescrito (docx + pdf), con su generador en docs/manual/
## APJ 2.1
  - Versión 2.1.2610 para PHP 8.5 (probada con PHP 8.5.12 y E_ALL sin avisos; compatible desde PHP 8.1)
    - APJModelTool::scan(): null como índice de array (deprecado en PHP 8.5) al calcular isView
    - APJController::setFormValues(): strtotime(null) en campos datetime-local (ahora deja el campo vacío)
    - APJPDOConnection: __wakeup() reemplazado por __unserialize() (__sleep/__wakeup obsoletos en PHP 8.5)
    - APJSession: guards para HTTP_USER_AGENT/REMOTE_ADDR/SERVER_NAME ausentes (CLI/cron, clientes sin User-Agent)
    - APJModel::_values(): Extra nulo o ausente en estructuras escritas a mano
    - jqajaj.js / jqajaj.min.js 2.0.7 (igual a gestion3/aerotac3): APJSubmitSilent/APJCallSilent, diálogos con DOM propio y keyLock
    - jqajaj_multi.js / .min.js 2.0.7: puesto al día con jqajaj.js 2.0.3-2.0.7 (fix de radios, Silent, diálogos nuevos), numeración alineada
    - css/jqajaj.css / .min.css nuevos (requeridos por la estructura de diálogos de 2.0.6+)
