<?php
namespace Pingo\Swoole;

/**
 * swoole 回调事件
 *
 * @author pingo
 * @created_at 00-00-00
 */
abstract class SwooleEvent
{
   const CALLBACK_EVENT = [
       'Start', 
       'Shutdown', 
       'WorkerStart',
       'WorkerStop',
       'WorkerExit',
       'Connect',
       'Receive',
       'Packet',
       'Task',
       'Close',
       'Finish',
       'PipeMessage',
       'WorkerError',
       'ManagerStart',
       'ManagerStop',
       'BeforeReload',
       'AfterReload',
       'Request',
       'Message',
       'Open',
       'HandShake',
        
   ];
    
   abstract protected function registerEvent(array $event);
   abstract protected function create();
    
}