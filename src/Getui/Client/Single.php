<?php
namespace Pingo\Getui\Client;

class Single extends Entity
{
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * 推送服务
     * @return bool|mixed
     * @throws \GeTui\ApiException
     */
    public function push()
    {
        $res = $this->buildRequestData();
        $this->alias && $res['alias'] = $this->alias;
        $this->cid && $res['cid'] = $this->cid;
        $this->reset();
        return $this->api->pushSingle($res);
    }
}