# APJ (Asynchronous PHP and JQuery MVC Framework)
## APJ: Framework MVC para PHP y JQuery de forma asíncrona
### Versión: 2.0.2412
### ¿Que es?
- Es un Framework simple para PHP basado en el patrón MVC (Modelo-Vista-Controlador) que utiliza JQuery, enfocado en el intercambio de datos de forma asíncrona con formato JSON, entre el controlador y la vista en el navegador.
- Permite la inyección de instrucciones JQuery (javascript) desde el mismo controlador en PHP.
- El modelo tiene un ORM basado en PDO muy simple, pero potente, que facilita la manipulación y el intercambio de datos, entre el modelo, el controlador y la vista.
- A pesar de que está enfocado en la comunicación asíncrona, entre el controlador y la vista, también puede trabajar en forma síncrona
- Especialmente diseñado para el desarrollo de aplicaciones web.

### Motivación:
>Después de aprender y probar muchos frameworks para PHP, ninguno me satisfacía por completo, en especial cuando se trataba del intercambio asíncrono de datos, comunicación con javascript desde PHP para modificación del DOM de las vistas. Así que me decidí hacer mi propio framework, el cual satisficiera mis necesidades y cuya curva de aprendizaje sea baja.
>En resumen un framework simple pero potente que cubriera mis necesidades en el desarrollo de aplicaciones web con PHP.

### Requerimientos:
1. Apache con PHP 7.3 o mayor (XAMPP, LAMP, Appserv u otro)
1. MySQL o MariaDB que tenga PDO e INNODB habilitado (también es posible hacer conexiones con otros tipos de bases de datos con DSN)
1. Un navegador actualizado compatible con HTML5 (Chrome, Edge, Safari o Firefox)

### Instalación:
>Por ahora la instalación es manual, luego implementare la instalación por medio de “Composer”
1. Bajar el framework y copiar el contenido de APJ en la carpeta para su proyecto.
1. Deben estar las carpetas Libs, Models, Views, Helpers, Vendor (opcional) y el archivo init.php
1. Editar el archivo init.php
	1. Modificar la constante DEVELOPMENT para definir si se encuentra en modo de desarrollo o producción
    1. Modificar la constante APPNAME por el nombre de su aplicación
    1. Modificar la variable $rootUrl para definir la carpeta de su aplicación. Déjelo como “/” si se encuentra en la raíz.
    1. Modificar la variable $domain con el nombre del dominio que utilizara en producción. Deje _localhost_ como está, es para desarrollo.
    1. Modificar la constante LOGIN para definir el controlador de acceso a su aplicación
    1. Modificar la contante TIMEZONE para definir la zona horaria, si es que lo requiere
    1. Opcionalmente puede cambiar los formatos de visualización de la contante FORMAT
	   1. _int_: Enteros (N° de decimales, separador de decimales, separador de miles)
	   1. _decimal_: Decimales (N° de decimales, separador de decimales, separador de miles)
	   1. _date_: Fecha (formato de PHP)
	   1. _datetime_: Fecha y hora (formato de PHP)
	   1. _time_: Hora (formato de PHP)
	   1: _timestamp_: Fecha y hora formato unix (formato de PHP)
1. Editar el archivo Libs/APJ/APJPDO.ini (archivo de configuración para el acceso a la base de datos)
	1. Cambie el valor de user, por la de un usuario válido, que tenga acceso a mostrar la estructura de las tablas (SHOW FULL COLUMNS FROM), No es obligatorio, pero en desarrollo es muy útil.
    1. Cambie el valor de password por una contraseña válida. Si la contraseña tiene símbolos como $, agregue comillas simples a la contraseña.
    1. Cambie el valor de dbname por en nombre de su base de datos.
    1. Opcionalmente cambie charset
>Eso es todo lo que necesita para comenzar.

## Autor:
>**Ricardo Seiffert**
>_Programador Senior_
### Próximas contribuciones:
-	Videos tutoriales
