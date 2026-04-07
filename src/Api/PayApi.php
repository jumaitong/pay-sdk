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
    private const VALID_CHANNELS  = ['alipay', 'wechat', 'unipay', 'douyin'];
    private const VALID_PAY_TYPES = ['web', 'h5', 'app', 'mini', 'scan'];

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
        if (!in_array($channel, self::VALID_CHANNELS, true)) {
            throw new PayException(PayException::PARAMS_INVALID, "不支持的支付渠道: {$channel}，可选值: " . implode(', ', self::VALID_CHANNELS));
        }

        if (!in_array($payType, self::VALID_PAY_TYPES, true)) {
            throw new PayException(PayException::PARAMS_INVALID, "不支持的支付方式: {$payType}，可选值: " . implode(', ', self::VALID_PAY_TYPES));
        }

        if ($amount <= 0) {
            throw new PayException(PayException::AMOUNT_INVALID, "金额必须大于 0，当前值: {$amount}");
        }

        if ($subject === '') {
            throw new PayException(PayException::PARAMS_INVALID, '商品标题不能为空');
        }

        if ($tenantOrderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, '业务订单号不能为空');
        }

        if (strlen($tenantOrderNo) > 64) {
            throw new PayException(PayException::PARAMS_INVALID, '业务订单号最长 64 字节，当前长度: ' . strlen($tenantOrderNo));
        }

        if ($expireMinutes < 1 || $expireMinutes > 1440) {
            throw new PayException(PayException::PARAMS_INVALID, "有效期分钟数范围 1-1440，当前值: {$expireMinutes}");
        }

        $params = [
            'channel'          => $channel,
            'pay_type'         => $payType,
            'amount'           => $amount,
            'subject'          => $subject,
            'tenant_order_no'  => $tenantOrderNo,
            'account_key'      => $accountKey,
            'expire_minutes'   => $expireMinutes,
        ];

        if ($extra !== []) {
            $params['extra'] = $extra;
        }

        return $this->http->post('/api/v1/pay/create', $params);
    }

    /**
     * 查询订单
     *
     * @param  string $tenantOrderNo  业务侧订单号（与 $orderNo 二选一）
     * @param  string $orderNo        支付中心流水号（与 $tenantOrderNo 二选一）
     * @param  bool   $forceRemote    是否强制查询第三方最新状态
     * @return array{order_no: string, tenant_order_no: string, status: int, amount: int, ...}
     *
     * @throws PayException
     */
    public function query(string $tenantOrderNo = '', string $orderNo = '', bool $forceRemote = false): array
    {
        if ($tenantOrderNo === '' && $orderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, 'order_no 和 tenant_order_no 至少填写一个');
        }

        $params = [];
        if ($tenantOrderNo !== '') {
            $params['tenant_order_no'] = $tenantOrderNo;
        }
        if ($orderNo !== '') {
            $params['order_no'] = $orderNo;
        }
        if ($forceRemote) {
            $params['force_remote'] = true;
        }

        return $this->http->post('/api/v1/pay/query', $params);
    }

    /**
     * 关闭订单（仅限待支付状态）
     *
     * @param  string $tenantOrderNo  业务侧订单号（与 $orderNo 二选一）
     * @param  string $orderNo        支付中心流水号（与 $tenantOrderNo 二选一）
     * @return array  关单成功
     *
     * @throws PayException ORDER_NOT_FOUND       订单不存在
     * @throws PayException ORDER_STATUS_INVALID  订单状态不允许关闭
     */
    public function close(string $tenantOrderNo = '', string $orderNo = ''): array
    {
        if ($tenantOrderNo === '' && $orderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, 'order_no 和 tenant_order_no 至少填写一个');
        }

        $params = [];
        if ($tenantOrderNo !== '') {
            $params['tenant_order_no'] = $tenantOrderNo;
        }
        if ($orderNo !== '') {
            $params['order_no'] = $orderNo;
        }

        return $this->http->post('/api/v1/pay/close', $params);
    }
}
