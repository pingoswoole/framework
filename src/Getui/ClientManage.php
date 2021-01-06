<?php
namespace Pingo\Getui;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use GuzzleHttp\Client as HttpClient;

trait ClientManage
{

    /**
     * 请求uri
     * @var string
     */
    protected $baseUri = 'https://restapi.getui.com/v2/';

    /**
     * 授权token
     * @var string
     */
    protected $autToken;

    /**
     * 文件缓存实例
     * @var FilesystemAdapter
     */
    protected $cache;

    /**
     * 配置数组
     * @var array
     */
    protected $config;

    /**
     * 设置配置
     * @param array $config
     */
    public function init(array $config)
    {
        $this->config = $config;
        $this->baseUri .= $this->config['app_id'];
        $this->cache = new FilesystemAdapter();
    }

    /**
     * 获取鉴权的authToken
     * @return mixed
     * @throws ApiException
     */
    public function getAuthToken()
    {
        if ($this->autToken) {
            return $this->autToken;
        }
        $auth_token = $this->cache->getItem('auth_token.' . $this->config['app_id']);
        if ($auth_token->isHit()) {
            $this->autToken = $auth_token->get();
            return $this->autToken;
        }
        $this->autToken = $this->auth();
        $auth_token->set('auth_token.' . $this->config['app_id'], $this->autToken, 3600 * 20);
        return $this->autToken;
    }

    /**
     * 个推鉴权
     * @return mixed
     * @throws ApiException
     */
    public function auth()
    {
        list($usec, $sec) = explode(" ", microtime());
        $msectime = floatval($sec . substr($usec, 2, 3));
        $data = [
            'appkey' => $this->config['app_key'],
            'timestamp' =>  $msectime,
        ];
        $data['sign'] = hash('sha256', "{$data['appkey']}{$data['timestamp']}{$this->config['master_secret']}");
       
        $ret = $this->request('POST', $this->baseUri . '/auth', $data, false);
         
        if ($ret['code'] !== 0 && isset($ret['data']['token'])) {
            throw new ApiException('鉴权失败');
        }
        return $ret['data']['token'];
    }

    /**
     * 销毁授权token，删除auth缓存
     * @return bool
     * @throws ApiException
     */
    public function authDestroy()
    {
        $auth_token = $this->cache->getItem('auth_token.' . $this->config['app_id']);
        $ret = $this->request('DELETE', $this->baseUri . '/auth/' . $auth_token, []);
        if ($ret['code'] !== 0 ) {
            return false;
        }
        $this->cache->delete('auth_token.' . $this->config['app_id']);
        $this->autToken = '';
        return true;
    }

    /**
     * 发送http请求
     * @param $method
     * @param $url
     * @param array $data
     * @param bool $is_auth
     * @return mixed
     * @throws ApiException
     */
    protected function request($method, $url, array $data = [], $is_auth = true)
    {
         
        $client = new HttpClient(['timeout' => 5.0]);
        $response = $client->request($method, $url, [
            'json' => $data,
            'headers' => [
                'token' => $is_auth ? $this->getAuthToken() : '',
            ]
        ]);
        if ($response->getStatusCode() != 200) {
            throw new ApiException('请求个推服务器接口出现了异常，响应状态：' . $response->getStatusCode());
        }
        $ret = json_decode($response->getBody()->getContents(), true);
        if (!$ret) {
            throw new ApiException('个推响应结果异常，异常内容：' . $response->getBody()->getContents());
        }
        return $ret;
    }
}