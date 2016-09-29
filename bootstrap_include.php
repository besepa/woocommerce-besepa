<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 31/8/16
 * Time: 19:56
 */

include __DIR__ . '/vendor/globalcitizen/php-iban/php-iban.php';

spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'Besepa\\WCPlugin\\';

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});


spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'Besepa\\';

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/vendor/besepa/besepa/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});