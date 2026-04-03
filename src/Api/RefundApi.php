<?php

declare(strict_types=1);

namespace Jumaitong\Pay\Api;

use Jumaitong\Pay\Exception\PayException;
use Jumaitong\Pay\HttpClient;

/**
 * 退款相关接口
 *
 * 通过 PayClient::refund() 获取实例：
 *   $client->refund()->create(...)
 *   $client->refund()->query(...)
 */
class RefundApi
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * 申请退款
     *
     * @param  string      $tenantOrderNo   业务侧原支付订单号
     * @param  int         $refundAmount    退款金额（分），不能超过可退金额
     * @param  string      $reason          退款原因（可选）
     * @param  string|null $tenantRefundNo  业务侧退款单号（可选，用于幂等）
     *
     * @return array{refund_no: string, ...}
     *
     * @throws PayException
     */
    public function create(
        string  $tenantOrderNo,
        int     $refundAmount,
        string  $reason = '',
        ?string $tenantRefundNo = null,
    ): array {
        if ($refundAmount <= 0) {
            throw new PayException(PayException::AMOUNT_INVALID, "退款金额必须大于 0，当前值: {$refundAmount}");
        }

        if ($tenantOrderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, '业务订单号不能为空');
        }

        $params = [
            'tenant_order_no' => $tenantOrderNo,
            'refund_amount'   => $refundAmount,
        ];

        if ($reason !== '') {
            $params['reason'] = $reason;
        }

        if ($tenantRefundNo !== null) {
            $params['tenant_refund_no'] = $tenantRefundNo;
        }

        return $this->http->post('/api/v1/pay/refund', $params);
    }

    /**
     * 查询退款
     *
     * @param  string      $tenantOrderNo   业务侧原支付订单号
     * @param  string|null $tenantRefundNo  业务侧退款单号（可选）
     *
     * @return array
     *
     * @throws PayException
     */
    public function query(string $tenantOrderNo, ?string $tenantRefundNo = null): array
    {
        if ($tenantOrderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, '业务订单号不能为空');
        }

        $params = ['tenant_order_no' => $tenantOrderNo];

        if ($tenantRefundNo !== null) {
            $params['tenant_refund_no'] = $tenantRefundNo;
        }

        return $this->http->post('/api/v1/pay/refund-query', $params);
    }
}
