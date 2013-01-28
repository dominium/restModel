<?php
header("Content-Type: text/plain; charset=utf-8");
require_once ("config.php");
if (version_compare(PHP_VERSION, '5.3.0') >= 0)
{
    require_once("RestClient-5.3.php");
    
} else if (version_compare(PHP_VERSION, '5.3.0') < 0)
{
	require_once("RestClient-5.2.php");
}

function class_autoloader($class)
{
    if (strpos($class, 'Model_') !== false)
    {
        $filepath = dirname(__FILE__) . "/../model/" . $class . ".php";
        if( ( file_exists($filepath) ) AND (include_once($filepath)) )
        {
            return true;
        }
    }
    trigger_error("Could not load class '{$class}' from ' . $filepath . '", E_USER_WARNING);
    return false;
}

spl_autoload_register("class_autoloader");

class RestException extends Exception
{
    public function __construct($message = null, $code = null, $prevorius = null)
    {
		header("Content-Type: text/plain; charset=utf-8");
        echo $message;
		exit;
    }
}
?>
