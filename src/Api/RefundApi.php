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
     * @param  string      $tenantOrderNo   业务侧原支付订单号（与 $orderNo 二选一）
     * @param  string      $orderNo         支付中心流水号（与 $tenantOrderNo 二选一）
     * @param  int         $refundAmount    退款金额（分），传 0 表示全额退款
     * @param  string      $refundReason    退款原因（可选）
     * @param  string|null $tenantRefundNo  业务侧退款单号（可选，用于幂等）
     *
     * @return array{out_refund_no: string, refund_amount: int, status: string}
     *
     * @throws PayException
     */
    public function create(
        string  $tenantOrderNo = '',
        string  $orderNo = '',
        int     $refundAmount = 0,
        string  $refundReason = '',
        ?string $tenantRefundNo = null,
    ): array {
        if ($tenantOrderNo === '' && $orderNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, 'order_no 和 tenant_order_no 至少填写一个');
        }

        if ($refundAmount < 0) {
            throw new PayException(PayException::AMOUNT_INVALID, "退款金额不能为负数，当前值: {$refundAmount}");
        }

        $params = [];
        if ($tenantOrderNo !== '') {
            $params['tenant_order_no'] = $tenantOrderNo;
        }
        if ($orderNo !== '') {
            $params['order_no'] = $orderNo;
        }
        if ($refundAmount > 0) {
            $params['refund_amount'] = $refundAmount;
        }
        if ($refundReason !== '') {
            $params['refund_reason'] = $refundReason;
        }
        if ($tenantRefundNo !== null) {
            $params['tenant_refund_no'] = $tenantRefundNo;
        }

        return $this->http->post('/api/v1/pay/refund', $params);
    }

    /**
     * 查询退款
     *
     * @param  string $tenantRefundNo  业务侧退款单号（与 $outRefundNo 二选一）
     * @param  string $outRefundNo     支付中心退款流水号（与 $tenantRefundNo 二选一）
     *
     * @return array
     *
     * @throws PayException
     */
    public function query(string $tenantRefundNo = '', string $outRefundNo = ''): array
    {
        if ($tenantRefundNo === '' && $outRefundNo === '') {
            throw new PayException(PayException::PARAMS_INVALID, 'out_refund_no 和 tenant_refund_no 至少填写一个');
        }

        $params = [];
        if ($tenantRefundNo !== '') {
            $params['tenant_refund_no'] = $tenantRefundNo;
        }
        if ($outRefundNo !== '') {
            $params['out_refund_no'] = $outRefundNo;
        }

        return $this->http->post('/api/v1/pay/refund-query', $params);
    }
}
