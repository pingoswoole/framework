<?php

namespace Pingo\Pay\Gateways\Wechat;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Pingo\Pay\Events;
use Pingo\Pay\Exceptions\GatewayException;
use Pingo\Pay\Exceptions\InvalidArgumentException;
use Pingo\Pay\Exceptions\InvalidSignException;

class WapGateway extends Gateway
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
    public function pay($endpoint, array $payload): RedirectResponse
    {
        $payload['trade_type'] = $this->getTradeType();

        Events::dispatch(new Events\PayStarted('Wechat', 'Wap', $endpoint, $payload));

        $mweb_url = $this->preOrder($payload)->get('mweb_url');

        $url = is_null(Support::getInstance()->return_url) ? $mweb_url : $mweb_url.
                        '&redirect_url='.urlencode(Support::getInstance()->return_url);

        return new RedirectResponse($url);
    }

    /**
     * Get trade type config.
     *
     * @author yansongda <me@yansongda.cn>
     */
    protected function getTradeType(): string
    {
        return 'MWEB';
    }
}
