# APJ (Asynchronous PHP and JQuery MVC Framework)
## APJ: Framework MVC para PHP y JQuery de forma asíncrona
### Versión: 2.1.2610
### ¿Que es?
- Es un Framework para PHP basado en el patrón MVC (Modelo-Vista-Controlador) que utiliza JQuery, enfocado en el intercambio de datos de forma asíncrona con formato JSON, entre el controlador y la vista en el navegador.
- Permite la inyección de instrucciones JQuery (javascript) desde el mismo controlador en PHP.
- El modelo tiene un ORM basado en PDO simple, pero potente, que facilita la manipulación y el intercambio de datos, entre el modelo, el controlador y la vista.
- A pesar de que está enfocado en la comunicación asíncrona, entre el controlador y la vista, también puede trabajar en forma síncrona
- Especialmente diseñado para el desarrollo de sistemas web.

### Motivación:
>Después de aprender y probar muchos frameworks para PHP, ninguno me satisfacía por completo, en especial cuando se trataba del intercambio asíncrono de datos, comunicación con javascript desde PHP para modificación del DOM de las vistas. Así que me decidí hacer mi propio framework, el cual satisficiera mis necesidades y cuya curva de aprendizaje sea baja.
>En resumen un framework simple pero potente que cubriera mis necesidades en el desarrollo de aplicaciones web con PHP.

### Requerimientos:
1. Apache con PHP 8.5 (probado con PHP 8.5.12; el código sigue siendo compatible desde PHP 8.1) (XAMPP, LAMP, Appserv u otro)
1. MySQL o MariaDB que tenga PDO e INNODB habilitado (también es posible hacer conexiones con otros tipos de bases de datos con DSN)
1. Un navegador actualizado compatible con HTML5 (Chrome, Edge, Safari o Firefox)

### Instalación:
>Por ahora la instalación es manual, luego implementare la instalación por medio de “Composer”
1. Bajar el framework desde https://github.com/seifox/APJ2 y copiar su contenido en la carpeta de su proyecto.
1. Deben estar la carpeta APJ (núcleo del framework; en APJ 1.x era Libs/APJ), las carpetas Models, Views y Helpers, el archivo init.php y la carpeta de Composer con su autoload.php (obligatoria; su ubicación la define la constante VENDORS de init.php)
1. Editar el archivo init.php
	1. Modificar la constante DEVELOPMENT para definir si se encuentra en modo de desarrollo o producción
    1. Modificar la constante APPNAME por el nombre de su aplicación
    1. Modificar la variable $rootUrl para definir la carpeta de su aplicación. Déjelo como “/” si se encuentra en la raíz.
    1. Modificar la variable $domain con el nombre del dominio que utilizara en producción. Deje _localhost_ como está, es para desarrollo.
    1. Modificar la constante LOGIN para definir el controlador de acceso a su aplicación
    1. Modificar la constante TIMEZONE para definir la zona horaria, si es que lo requiere
    1. Opcionalmente puede cambiar los formatos de visualización de la constante FORMATS
	   1. _int_: Enteros (N° de decimales, separador de decimales, separador de miles)
	   1. _decimal_: Decimales (N° de decimales, separador de decimales, separador de miles)
	   1. _date_: Fecha (formato de PHP)
	   1. _datetime_: Fecha y hora (formato de PHP)
	   1. _time_: Hora (formato de PHP)
	   1. _timestamp_: Fecha y hora formato unix (formato de PHP)
	   1. _booleanTrue_ / _booleanFalse_: Texto para mostrar un booleano (por ejemplo "Si" y "No")
1. Editar el archivo APJ/APJPDO.ini.php (archivo de configuración para el acceso a la base de datos)
	1. Cambie el valor de user, por la de un usuario válido, que tenga acceso a mostrar la estructura de las tablas (SHOW FULL COLUMNS FROM), No es obligatorio, pero en desarrollo es muy útil.
    1. Cambie el valor de password por una contraseña válida. Si la contraseña tiene símbolos como $, agregue comillas simples a la contraseña.
    1. Cambie el valor de dbname por en nombre de su base de datos.
    1. Opcionalmente cambie charset
>Eso es todo lo que necesita para comenzar. Para el detalle completo vea el "MANUAL DE REFERENCIA DE APJ 2" (.pdf o .docx) en la raíz del repositorio.

## Autor:
>**Ricardo Seiffert**
>_Programador Senior_
### Próximas contribuciones:
-	Videos tutoriales
