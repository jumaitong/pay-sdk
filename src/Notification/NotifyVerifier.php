<?php

declare(strict_types=1);

namespace Jumaitong\Pay\Notification;

use Jumaitong\Pay\Exception\PayException;
use Jumaitong\Pay\HttpClient;

/**
 * 支付结果通知验签
 *
 * 支付成功后，支付中心会 POST 通知到业务系统的 notify_url。
 * 业务系统必须验证通知签名，确认来源合法后再更新订单状态。
 *
 * 签名算法（与接入 API 对称）：
 *   payload 中包含 timestamp、nonce、sign 字段
 *   验签时：去掉 sign → JSON 重新编码 → 生成期望签名 → 与 sign 比较
 *
 * 使用方式：通过 PayClient::notify() 获取实例
 *   $data = $client->notify()->verify($rawBody);
 */
class NotifyVerifier
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * 验证支付通知签名
     *
     * @param  string $rawBody  收到的原始请求体（JSON 字符串）
     *
     * @return array{
     *     event: string,
     *     order_no: string,
     *     tenant_order_no: string,
     *     channel: string,
     *     amount: int,
     *     channel_trade_no: string,
     *     paid_at: string,
     *     timestamp: string,
     *     nonce: string
     * }
     *
     * @throws PayException NOTIFY_SIGN_INVALID 签名验证失败
     * @throws PayException RESPONSE_INVALID    通知格式错误
     */
    public function verify(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new PayException(PayException::RESPONSE_INVALID, '通知格式错误：无法解析 JSON');
        }

        $sign      = (string) ($payload['sign']      ?? '');
        $timestamp = (string) ($payload['timestamp'] ?? '');
        $nonce     = (string) ($payload['nonce']     ?? '');

        if ($sign === '' || $timestamp === '' || $nonce === '') {
            throw new PayException(PayException::NOTIFY_SIGN_INVALID, '通知缺少必要字段：sign / timestamp / nonce');
        }

        // 去掉 sign 字段后，用相同 JSON 序列化选项重新编码（与服务端签名逻辑严格对称）
        $dataToSign = $payload;
        unset($dataToSign['sign']);
        $body = json_encode($dataToSign, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $expectedSign = $this->http->sign($timestamp, $nonce, (string) $body);

        // hash_equals 防止时序攻击
        if (!hash_equals($expectedSign, $sign)) {
            throw new PayException(PayException::NOTIFY_SIGN_INVALID, '支付通知签名验证失败');
        }

        unset($dataToSign['timestamp'], $dataToSign['nonce']);
        return $dataToSign;
    }

    /**
     * 验签成功后，返回标准"已支付"响应体（支付中心收到 HTTP 2xx 即停止重推）
     */
    public function success(): string
    {
        return json_encode(['code' => 0, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE);
    }
}
