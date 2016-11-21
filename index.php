<?php
/**
 * Currently the setup is to return a JSON instead of html
 */
error_reporting(30719);

/**
 * Returns a json with error / exception info in it.
 * This method is for use only for error dumps so it return HTTP 500
 * @param mixed $data
 */
function returnJSONError($data) {
    header('HTTP/1.1 500 Server Error');
    header("Content-Type: application/json;charset=utf-8\r\n", true);

    print json_encode($data);
}

/**
 * Returns a html with error / exception info in it.
 * This method is for use only for error dumps so it return HTTP 500
 * @param string $html
 */
function returnHTMLError($html) {
    header('HTTP/1.1 500 Server Error');
    header("Content-Type: application/json;charset=utf-8\r\n", true);
    ?>
    <html>
        <body>
            <?= $html ?>
        </body>
    </html>
    <?php
}

/**
 * Returns a good JSON response
 * 
 * @param mixed $data
 */
function returnJSON($data) {
    header('HTTP/1.1 200 OK');
    header("Content-Type: application/json;charset=utf-8\r\n", true);

    print json_encode($data);
}

/**
 * Error handler to hook to the PHP error handling.
 * 
 * @param type $code
 * @param type $description
 * @param type $file
 * @param type $line
 * @param type $context
 */
function firstErrorHandler($code, $description, $file = null, $line = null, $context = null) {
    $message = sprintf('PHP error %d: %s in %s at line %d', $code, $description, $file, $line);
    returnJSONError(array('StatusCode' => 500, 'ErrorMessage' => $message));
    die(); //prevent dumping several errors and just stop script at first one
}

/**
 * On 500 errors(syntax errors) we can catch them only in a shutdown handler
 */
function onFullShutDown() {
    $errinfo = error_get_last();

    if (!empty($errinfo) && !headers_sent()) {
        //call this only on errors prior to catching them inside the Application class
        firstErrorHandler($errinfo['type'], $errinfo['message'], !empty($errinfo['file']) ? $errinfo['file'] : null, $errinfo['line']);
    }
}

//setup error handling
set_error_handler('firstErrorHandler');
register_shutdown_function('onFullShutDown');

/**
 * Autoloader to avoid manually including each file needed. Actually this is a little slower because of existance checks, 
 * but we need to include a very little ammount of files.
 * 
 * @param string $class_name
 */
function __autoload($class_name) {
    $class_name = $class_name . '.php';

    if (file_exists(__DIR__ . '/' . $class_name)) {
        include $class_name;
    } elseif (file_exists(__DIR__ . '/core/' . $class_name)) {
        include(__DIR__ . '/core/' . $class_name);
    } elseif (file_exists(__DIR__ . '/application/controllers/' . $class_name)) {
        include(__DIR__ . '/application/controllers/' . $class_name);
    } elseif (file_exists(__DIR__ . '/application/models/' . $class_name)) {
        include(__DIR__ . '/application/models/' . $class_name);
    }
}

/**
 * Read the config.php file.
 * Isolated in a function for avoiding conflicts with global scope vars.
 * 
 * @return array
 */
function readConfig() {
    $_config = array('memcacheConfig' => array(), 'dbConfig' => array()); //name should be something we won't need to use
    //get vars from config.php and put them in the $config array
    $_vars = array_keys(get_defined_vars());

    include(__DIR__ . '/config.php');

    $_vars = array_diff(array_keys(get_defined_vars()), $_vars);
    reset($_vars);
    foreach ($_vars as $_value) {
        $_config[$_value] = $$_value;

        //free memory from config vars
        $$_value = null;
        unset($$_value);
    }

    $_vars = null;
    unset($_vars);
    //end config loading

    return $_config;
}

global $config;
$config = readConfig();

//include most used files
include(__DIR__ . '/core/MemcacheCache.php');
include(__DIR__ . '/core/Database.php');
include(__DIR__ . '/core/Model.php');

//init cache instance
global $cacheInstance;
$cacheInstance = new MemcacheCache($config['memcacheConfig']);

global $databaseInstance;
$databaseInstance = new Database($config['dbConfig']);

try {
    //parse request and route to a controller
    $path = $_SERVER['REQUEST_URI'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];

    $arr = explode('/', $scriptPath);
    array_pop($arr); //remove script name from the script real url

    $mainAppUrl = '';
    foreach ($arr as $value) {
        $mainAppUrl.= $value . '/';
    }

    $routePath = '/' . substr($path, strlen($mainAppUrl), strlen($path));
    $arr = explode('?', $routePath);

    $arr = explode('/', trim($arr[0], '/'));
    foreach ($arr as $k => $v) {
        $arr[$k] = trim($v);
    }

    $method = strtolower($_SERVER['REQUEST_METHOD']);
    $controllerMethod = 'method' . ucfirst($method);

    //in arr we have all after the main path so we have the route and all vars passed 
    if (!empty($arr) && !empty($arr[0])) {
        $route = $arr[0];
        $controllerClass = ucfirst($arr[0]) . 'Controller';

        array_shift($arr); //clear the controller name
        $params = $arr;
    } else {
        $route = 'index';
        $controllerClass = 'IndexController'; //if we want to have a fallback controller to show help/sth else
        $params = array();
    }

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();

        if (method_exists($controller, $controllerMethod)) {
            $result= call_user_func_array(array($controller, $controllerMethod), $params);
            returnJSON($result);
        } else {
            throw new Exception('The route ' . $route . ' has no method ' . $controllerMethod . '.');
        }
    } else {
        throw new Exception('The route ' . $route . ' is not available.');
    }
} catch (Exception $e) {
    //return error info as JSON
    returnJSONError(array('StatusCode' => 500, 'ErrorMessage' => 'Exception: ' . $e->getMessage()));
    die();
}
