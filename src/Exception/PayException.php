<?php

declare(strict_types=1);

namespace Jumaitong\Pay\Exception;

/**
 * SDK 业务异常
 *
 * getBizCode() 返回支付中心的业务错误码，可用于精确处理特定错误。
 */
class PayException extends \RuntimeException
{
    // ── 认证/鉴权 ──────────────────────────────────────────
    public const MISSING_AUTH_HEADER  = 40100; // 缺少认证头
    public const TIMESTAMP_EXPIRED    = 40101; // 时间戳过期（防重放）
    public const NONCE_USED           = 40102; // Nonce 已使用（防重放）
    public const SIGN_INVALID         = 40103; // 签名验证失败
    public const TENANT_NOT_FOUND     = 40104; // 租户不存在或已禁用
    public const IP_NOT_ALLOWED       = 40105; // IP 不在白名单
    public const PERMISSION_DENIED    = 40106; // 接口级权限不足

    // ── 参数验证 ────────────────────────────────────────────
    public const PARAMS_INVALID       = 42200; // 请求参数不合法
    public const AMOUNT_INVALID       = 42201; // 金额不合法

    // ── 支付业务 ────────────────────────────────────────────
    public const PAY_IN_PROGRESS      = 42900; // 支付处理中（请勿重复提交）
    public const ORDER_NOT_FOUND      = 40400; // 订单不存在
    public const ORDER_STATUS_INVALID = 40901; // 订单状态不允许此操作
    public const CHANNEL_NOT_CONFIG   = 40902; // 渠道未配置
    public const REFUND_EXCEED        = 40903; // 退款金额超出可退金额
    public const CONFLICT             = 40904; // 资源已存在（唯一约束冲突）

    // ── 第三方渠道 ──────────────────────────────────────────
    public const CHANNEL_FAILED       = 50200; // 第三方渠道调用失败

    // ── SDK 本地错误（负数，与服务端错误码不冲突）────────────
    public const HTTP_ERROR           = -1;    // HTTP 请求失败（网络/超时）
    public const RESPONSE_INVALID     = -2;    // 响应格式错误
    public const NOTIFY_SIGN_INVALID  = -3;    // 通知签名验证失败

    private readonly int $bizCode;

    public function __construct(int $bizCode, string $message = '')
    {
        // 业务错误码存入独立属性，不复用 $code（PHP 内部保留字段，语义不同）
        $this->bizCode = $bizCode;
        parent::__construct($message ?: "支付中心错误 [{$bizCode}]");
    }

    public function getBizCode(): int
    {
        return $this->bizCode;
    }
}
