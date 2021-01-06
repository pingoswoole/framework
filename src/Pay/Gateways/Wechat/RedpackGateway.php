<?php

namespace Pingo\Pay\Gateways\Wechat;

use Symfony\Component\HttpFoundation\Request;
use Pingo\Pay\Events;
use Pingo\Pay\Exceptions\GatewayException;
use Pingo\Pay\Exceptions\InvalidArgumentException;
use Pingo\Pay\Exceptions\InvalidSignException;
use Pingo\Pay\Gateways\Wechat;
use Pingo\Supports\Collection;

class RedpackGateway extends Gateway
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
        $payload['wxappid'] = $payload['appid'];

        if ('cli' !== php_sapi_name()) {
            $payload['client_ip'] = Request::createFromGlobals()->server->get('SERVER_ADDR');
        }

        if (Wechat::MODE_SERVICE === $this->mode) {
            $payload['msgappid'] = $payload['appid'];
        }

        unset($payload['appid'], $payload['trade_type'],
              $payload['notify_url'], $payload['spbill_create_ip']);

        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('Wechat', 'Redpack', $endpoint, $payload));

        return Support::requestApi(
            'mmpaymkttransfers/sendredpack',
            $payload,
            true
        );
    }

    /**
     * Find.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param $billno
     */
    public function find($billno): array
    {
        return [
            'endpoint' => 'mmpaymkttransfers/gethbinfo',
            'order' => ['mch_billno' => $billno, 'bill_type' => 'MCHT'],
            'cert' => true,
        ];
    }

    /**
     * Get trade type config.
     *
     * @author yansongda <me@yansongda.cn>
     */
    protected function getTradeType(): string
    {
        return '';
    }
}
