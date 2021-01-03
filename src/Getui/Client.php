<?php
 

namespace Pingo\GeTui;

use Pingo\GeTui\Client\Batch;
use Pingo\GeTui\Client\Single;
use Pingo\GeTui\Client\Task;

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
