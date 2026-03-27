<?php

declare(strict_types=1);

namespace Jumaitong\Pay\Api;

use Jumaitong\Pay\Exception\PayException;
use Jumaitong\Pay\HttpClient;

/**
 * 支付相关接口
 *
 * 通过 PayClient::pay() 获取实例：
 *   $client->pay()->create(...)
 *   $client->pay()->query(...)
 *   $client->pay()->close(...)
 */
class PayApi
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * 发起支付
     *
     * @param  string      $channel        支付渠道：alipay / wechat / unipay / douyin
     * @param  string      $payType        支付方式：web / h5 / app / mini / scan
     * @param  int         $amount         金额（分），必须 > 0
     * @param  string      $subject        商品标题
     * @param  string      $tenantOrderNo  业务侧唯一订单号（最多 64 位）
     * @param  array       $extra          渠道扩展参数（可选）
     * @param  string      $accountKey     收款账号 key（默认 default）
     * @param  int         $expireMinutes  有效期分钟数（默认 30，范围 1-1440）
     *
     * @return array{order_no: string, pay_params: mixed}
     *
     * @throws PayException
     */
    public function create(
        string $channel,
        string $payType,
        int    $amount,
        string $subject,
        string $tenantOrderNo,
        array  $extra = [],
        string $accountKey = 'default',
        int    $expireMinutes = 30,
    ): array {
        $params = [
            'channel'          => $channel,
            'pay_type'         => $payType,
            'amount'           => $amount,
            'subject'          => $subject,
            'tenant_order_no'  => $tenantOrderNo,
            'account_key'      => $accountKey,
            'expire_minutes'   => $expireMinutes,
        ];

        if (!empty($extra)) {
            $params['extra'] = $extra;
        }

        return $this->http->post('/api/v1/pay/create', $params);
    }

    /**
     * 查询订单
     *
     * @param  string $tenantOrderNo  业务侧订单号
     * @return array{order_no: string, tenant_order_no: string, status: int, amount: int, ...}
     *
     * @throws PayException
     */
    public function query(string $tenantOrderNo): array
    {
        return $this->http->post('/api/v1/pay/query', [
            'tenant_order_no' => $tenantOrderNo,
        ]);
    }

    /**
     * 关闭订单（仅限待支付状态）
     *
     * @param  string $tenantOrderNo  业务侧订单号
     * @return array
     *
     * @throws PayException
     */
    public function close(string $tenantOrderNo): array
    {
        return $this->http->post('/api/v1/pay/close', [
            'tenant_order_no' => $tenantOrderNo,
        ]);
    }
}
