<?php

namespace Pingo\Pay\Gateways\Wechat;

use Pingo\Pay\Exceptions\GatewayException;
use Pingo\Pay\Exceptions\InvalidArgumentException;
use Pingo\Pay\Exceptions\InvalidSignException;
use Pingo\Pay\Gateways\Wechat;
use Pingo\Supports\Collection;

class MiniappGateway extends MpGateway
{
    /**
     * Pay an order.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $endpoint
     *
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     */
    public function pay($endpoint, array $payload): Collection
    {
        $payload['appid'] = Support::getInstance()->miniapp_id;

        if (Wechat::MODE_SERVICE === $this->mode) {
            $payload['sub_appid'] = Support::getInstance()->sub_miniapp_id;
            $this->payRequestUseSubAppId = true;
        }

        return parent::pay($endpoint, $payload);
    }
}
