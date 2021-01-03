<?php
namespace Pingo\Getui;

use Pingo\Getui\Client\Batch;
use Pingo\Getui\Client\Single;
use Pingo\Getui\Client\Task;

class Client
{
    /**  
     * api
     *
     * @var Api
     */
    public $api;

    /**
     * single
     *
     * @var Single
     */
    public $single;

    /**
     * batch
     *
     * @var Batch
     */
    public $batch;

    /**
     * task
     *
     * @var Task
     */
    public $task;

    /**
     * Client constructor.
     * @param array $config
     * @throws ApiException
     */
    public function __construct(array $config)
    {
        if (!$config) {
            throw new ApiException('未设置配置信息');
        }
        $this->api = new Api($config);
        $this->single = new Single($config);
        $this->batch = new Batch($config);
        $this->task = new Task($config);
    }
}
