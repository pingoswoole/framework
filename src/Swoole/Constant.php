<?php
namespace Pingo\Swoole;
/**
 * 系统常量
 *
 * @author pingo
 * @created_at 00-00-00
 */
class Constant
{
    
    const VERSION               = '2.0.1';
    const LOGGER_HANDLER        = 'LOGGER_HANDLER';
    const ERROR_HANDLER         = 'ERROR_HANDLER';
    const ERROR_REPORT_LEVEL    = 'ERROR_REPORT_LEVEL';
    const TRIGGER_HANDLER       = 'TRIGGER_HANDLER';
    const SHUTDOWN_FUNCTION     = 'SHUTDOWN_FUNCTION';

    const HTTP_CONTROLLER_NAMESPACE = 'HTTP_CONTROLLER_NAMESPACE';
    const HTTP_EXCEPTION_HANDLER    = 'HTTP_EXCEPTION_HANDLER';
    const HTTP_GLOBAL_ON_REQUEST    = 'HTTP_GLOBAL_ON_REQUEST';
    const HTTP_GLOBAL_AFTER_REQUEST = 'HTTP_GLOBAL_AFTER_REQUEST';

    const SWOOLE_TCP_SERVER             = 'TCP';
    const SWOOLE_HTTP_SERVER            = 'HTTP';
    const SWOOLE_WEBSOCKET_SERVER       = 'WEBSOCKET';
    const SWOOLE_UDP_SERVER             = 'UDP';
    const SWOOLE_MQTT_SERVER            = 'MQTT';
    const SWOOLE_MIX_SERVER             = 'MIX';
    
}