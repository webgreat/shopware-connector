<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once (__DIR__ . "/../vendor/autoload.php");

use \jtl\Connector\Application\Application;
use \jtl\Core\Rpc\RequestPacket;
use \jtl\Core\Rpc\ResponsePacket;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Http\Response;
use \jtl\Connector\Shopware\Connector;

define('CONNECTOR_DIR', __DIR__ . '/../vendor/jtl/connector/');
define('ENDPOINT_DIR', realpath(__DIR__ . '/../'));

error_reporting(E_ALL);
ini_set('display_errors', 0);

function exception_handler(\Exception $exception)
{
    $trace = $exception->getTrace();
    if (isset($trace[0]['args'][0])) {
        $requestpacket = $trace[0]['args'][0];
    }
    
    $error = new Error();
    $error->setCode($exception->getCode())
        ->setData("Exception: " . substr(strrchr(get_class($exception), "\\"), 1) . " - File: {$exception->getFile()} - Line: {$exception->getLine()}")
        ->setMessage($exception->getMessage());

    $responsepacket = new ResponsePacket();
    $responsepacket->setError($error)
        ->setJtlrpc("2.0");
        
    if (isset($requestpacket) && $requestpacket !== null && is_object($requestpacket) && get_class($requestpacket) == "jtl\\Core\\Rpc\\RequestPacket") {
        $responsepacket->setId($requestpacket->getId());
    }
    
    Response::send($responsepacket);
}

function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    $types = array(
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING', 
        E_PARSE => 'E_PARSE', 
        E_NOTICE => 'E_NOTICE', 
        E_CORE_ERROR => 'E_CORE_ERROR', 
        E_CORE_WARNING => 'E_CORE_WARNING', 
        E_CORE_ERROR => 'E_COMPILE_ERROR', 
        E_CORE_WARNING => 'E_COMPILE_WARNING', 
        E_USER_ERROR => 'E_USER_ERROR', 
        E_USER_WARNING => 'E_USER_WARNING', 
        E_USER_NOTICE => 'E_USER_NOTICE', 
        E_STRICT => 'E_STRICT', 
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', 
        E_DEPRECATED => 'E_DEPRECATED', 
        E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    );

    file_put_contents("/tmp/shopware_error.log", date("[Y-m-d H:i:s] ") . "(" . $types[$errno] . ") File ({$errfile}, {$errline}): {$errstr}\n", FILE_APPEND);
}

function shutdown_handler()
{
    if (($err = error_get_last())) {
        ob_clean();

        $error = new Error();
        $error->setCode($err['type'])
            ->setData('Shutdown! File: ' . $err['file'] . ' - Line: ' . $err['line'])
            ->setMessage($err['message']);

        $reponsepacket = new ResponsePacket();
        $reponsepacket->setError($error)
            ->setJtlrpc("2.0");
    
        Response::send($reponsepacket);
    }
}

set_error_handler('error_handler', E_ALL);
set_exception_handler('exception_handler');
register_shutdown_function('shutdown_handler');

// Connector instance
$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();