<?php

declare(strict_types=1);

namespace Jumaitong\Pay;

use Jumaitong\Pay\Api\PayApi;
use Jumaitong\Pay\Api\RefundApi;
use Jumaitong\Pay\Notification\NotifyVerifier;

/**
 * Pay 支付中心 PHP SDK 入口
 *
 * 初始化：
 *   $client = new PayClient(
 *       apiKey:    'your_api_key',
 *       apiSecret: 'your_api_secret',
 *       baseUrl:   'https://pay.jumaitong.com'
 *   );
 *
 * 发起支付：
 *   $result = $client->pay()->create('alipay', 'web', 9900, '年度会员', 'ORD_001');
 *
 * 查询订单：
 *   $order = $client->pay()->query('ORD_001');
 *
 * 申请退款：
 *   $refund = $client->refund()->create('ORD_001', 9900, '用户申请');
 *
 * 验证支付通知：
 *   $data = $client->notify()->verify($rawBody);
 */
class PayClient
{
    private HttpClient      $http;
    private ?PayApi         $payApi      = null;
    private ?RefundApi      $refundApi   = null;
    private ?NotifyVerifier $notifyVerifier = null;

    public function __construct(
        string $apiKey,
        string $apiSecret,
        string $baseUrl,
    ) {
        $this->http = new HttpClient($apiKey, $apiSecret, $baseUrl);
    }

    /**
     * 支付接口（发起/查询/关闭）
     */
    public function pay(): PayApi
    {
        return $this->payApi ??= new PayApi($this->http);
    }

    /**
     * 退款接口（申请退款/查询退款）
     */
    public function refund(): RefundApi
    {
        return $this->refundApi ??= new RefundApi($this->http);
    }

    /**
     * 支付通知验签
     */
    public function notify(): NotifyVerifier
    {
        return $this->notifyVerifier ??= new NotifyVerifier($this->http);
    }
}
