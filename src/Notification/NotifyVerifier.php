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
 * ⚠️ JSON 重组限制：验签时对原始 payload 执行 json_decode → 去 sign → json_encode，
 *    此过程要求服务端通知 body 满足：
 *    1. 所有字符串字段使用 UTF-8 原生字节（无 \u 转义），否则重组后字节不同导致签名不符
 *    2. JSON key 顺序与 PHP json_decode 还原顺序一致（服务端需保证序列化顺序稳定）
 *    如需彻底消除此限制，应与服务端协议升级为"sign 放 Header、body 原文验签"方案。
 *
 * 注意：此类仅使用 HttpClient::sign()，依赖完整 HttpClient 实例是当前设计的已知取舍，
 *       可在引入签名接口后优化，但对当前无测试的内部 SDK 影响可接受。
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
     *     paid_at: string
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

        // 去掉 sign 字段后重新编码，与服务端签名逻辑对称（限制见类注释）
        // JSON_THROW_ON_ERROR：编码失败直接抛出 JsonException，避免 false 流入签名串
        $dataToSign = $payload;
        unset($dataToSign['sign']);
        $body = json_encode($dataToSign, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

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
        return json_encode(['code' => 0, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
