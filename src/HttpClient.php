<?php

declare(strict_types=1);

namespace Jumaitong\Pay;

use Jumaitong\Pay\Exception\PayException;

/**
 * HTTP 客户端（内部使用）
 *
 * 封装签名生成、Header 组装、curl 请求、响应解析。
 * 业务代码不直接使用此类，通过 PayApi / RefundApi 调用。
 */
class HttpClient
{
    private const CONNECT_TIMEOUT = 5;   // 连接超时（秒）
    private const READ_TIMEOUT    = 10;  // 读取超时（秒）

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl,
    ) {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new \InvalidArgumentException("baseUrl 必须使用 HTTPS，当前值: {$baseUrl}");
        }
    }

    /**
     * 发送 POST 请求（自动签名）
     *
     * @param  string  $path  接口路径，如 /api/v1/pay/create
     * @param  array   $data  请求参数（会被 JSON 序列化）
     * @return array          响应 data 字段（code=0 时）
     *
     * @throws PayException   业务错误或网络错误
     */
    public function post(string $path, array $data): array
    {
        // JSON_THROW_ON_ERROR：编码失败直接抛出 JsonException，避免 false 流入签名串
        $body      = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce     = bin2hex(random_bytes(16));
        $sign      = $this->sign($timestamp, $nonce, $body);
        $url       = rtrim($this->baseUrl, '/') . $path;

        $ch = curl_init();
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT        => self::READ_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'X-Api-Key: '   . $this->apiKey,
                    'X-Timestamp: ' . $timestamp,
                    'X-Nonce: '     . $nonce,
                    'X-Sign: '      . $sign,
                ],
            ]);

            $response = curl_exec($ch);
            $curlErr  = curl_error($ch);
        } finally {
            // 常驻进程必须保证句柄释放，避免文件描述符泄露
            curl_close($ch);
        }

        if ($response === false || $curlErr !== '') {
            throw new PayException(PayException::HTTP_ERROR, "HTTP 请求失败: {$curlErr}");
        }

        try {
            $result = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $preview = mb_substr((string) $response, 0, 100);
            throw new PayException(PayException::RESPONSE_INVALID, "响应不是合法 JSON（前100字符）: {$preview}");
        }

        if (!is_array($result)) {
            throw new PayException(PayException::RESPONSE_INVALID, '响应格式错误：顶层结构不是对象');
        }

        $code = (int) ($result['code'] ?? -1);
        if ($code !== 0) {
            throw new PayException($code, (string) ($result['msg'] ?? '未知错误'));
        }

        return (array) ($result['data'] ?? []);
    }

    /**
     * 生成 HMAC-SHA256 签名（v2.0，SHA256 摘要）
     *
     * 签名串：api_key\ntimestamp\nnonce\nSHA256(body)
     */
    public function sign(string $timestamp, string $nonce, string $body): string
    {
        $signStr = $this->apiKey . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        return hash_hmac('sha256', $signStr, $this->apiSecret);
    }
}
