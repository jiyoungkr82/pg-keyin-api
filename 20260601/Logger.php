<?php
use Logger;

require_once __DIR__ . '/vendor/autoload.php';

function get_logger(string $name = 'pay_submit') 
{
    static $initialized = false;
    
    if (!$initialized) {
        $cfg = __DIR__ . '/log4php.xml';
        if (is_file($cfg)) {
            Logger::configure($cfg);
        }
        $initialized = true;
    }
    return Logger::getLogger($name);
}