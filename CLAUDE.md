# Pay SDK — CLAUDE.md

## 项目概述

Jumaitong 支付中心 PHP SDK，供内部业务系统接入支付中心使用。

- **包名**: `jumaitong/pay-sdk`
- **命名空间**: `Jumaitong\Pay\`
- **最低 PHP**: 8.4
- **外部依赖**: 仅 `ext-curl` 和 `ext-json`，零 Composer 第三方依赖

---

## 接口规范获取

每次开发接口调用代码前，先执行以下命令获取 pay 最新接口文档：

```bash
curl -H "Authorization: token $GIT_JMT_TOKEN" \
  https://raw.githubusercontent.com/jumaitong/pay/main/docs/API.md
```

`GIT_JMT_TOKEN` 从环境变量读取，不得硬编码。

根据获取到的 API.md 内容编写接口调用代码，严格遵循其中定义的接口路径、请求方法、参数结构、响应结构和字段类型，不得凭记忆假设。

---

## 目录结构

```
src/
├── PayClient.php              # SDK 唯一入口，持有 apiKey/apiSecret/baseUrl
├── HttpClient.php             # 封装 cURL，负责签名生成与请求发送
├── Api/
│   ├── PayApi.php             # 支付接口：create / query / close
│   └── RefundApi.php          # 退款接口：create / query
├── Exception/
│   └── PayException.php       # 唯一异常类，含所有业务错误码常量
└── Notification/
    └── NotifyVerifier.php     # 异步通知验签：verify / success
```

---

## 认证机制（必须了解）

所有请求使用 HMAC-SHA256 签名，由 `HttpClient` 自动处理，**业务代码无需手动签名**。

**签名串格式**（v2.0）：
```
api_key + "\n" + timestamp + "\n" + nonce + "\n" + SHA256(json_body)
```

**请求头**：
```
X-Api-Key: {api_key}
X-Timestamp: {unix_timestamp}
X-Nonce: {16字节随机十六进制}
X-Sign: {hmac_sha256_signature}
```

**防重放保护**：时间戳过期检测（服务端）+ Nonce 唯一性检测（服务端）。

**通知验签**：`NotifyVerifier::verify()` 使用 `hash_equals()` 防时序攻击，去除 `sign` 字段后重新计算签名比对。

---

## 错误码体系

所有错误均抛出 `PayException`，通过 `$e->getBizCode()` 获取错误码，`$e->getMessage()` 获取描述。

| 分类 | 错误码 | 常量名 |
|------|--------|--------|
| 缺少认证头 | 40100 | `MISSING_AUTH_HEADER` |
| 时间戳过期 | 40101 | `TIMESTAMP_EXPIRED` |
| Nonce 已使用 | 40102 | `NONCE_USED` |
| 签名无效 | 40103 | `SIGN_INVALID` |
| 租户不存在 | 40104 | `TENANT_NOT_FOUND` |
| IP 不在白名单 | 40105 | `IP_NOT_ALLOWED` |
| 接口权限不足 | 40106 | `PERMISSION_DENIED` |
| 参数不合法 | 42200 | `PARAMS_INVALID` |
| 金额不合法 | 42201 | `AMOUNT_INVALID` |
| 支付处理中 | 42900 | `PAY_IN_PROGRESS` |
| 订单不存在 | 40400 | `ORDER_NOT_FOUND` |
| 订单状态不允许 | 40901 | `ORDER_STATUS_INVALID` |
| 渠道未配置 | 40902 | `CHANNEL_NOT_CONFIG` |
| 退款金额超限 | 40903 | `REFUND_EXCEED` |
| 资源冲突 | 40904 | `CONFLICT` |
| 渠道调用失败 | 50200 | `CHANNEL_FAILED` |
| HTTP 请求失败（SDK 本地） | -1 | `HTTP_ERROR` |
| 响应格式错误（SDK 本地） | -2 | `RESPONSE_INVALID` |
| 通知签名无效（SDK 本地） | -3 | `NOTIFY_SIGN_INVALID` |

---

## 支持的支付渠道与方式

| 渠道（channel） | 说明 |
|----------------|------|
| `alipay` | 支付宝 |
| `wechat` | 微信支付 |
| `unipay` | 银联 |
| `douyin` | 抖音支付 |

| 支付方式（pay_type） | 说明 |
|--------------------|------|
| `web` | PC 网页 |
| `h5` | 手机 H5 |
| `app` | 移动 App |
| `mini` | 小程序 |
| `scan` | 扫码 |

---

## 关键约定

- **金额单位**：全部为「分」（整数），`PayApi::create()` 的 `$amount` 和退款金额均以分传入。
- **业务订单号**：`tenantOrderNo` 最长 64 位，作为幂等键，同一订单号重复请求返回 `PAY_IN_PROGRESS` 或 `CONFLICT`。
- **退款幂等**：`tenantRefundNo` 为可选的业务退款单号，传入时用于幂等保护。
- **超时配置**：连接超时默认 5 秒，读取超时默认 10 秒，通过 `PayClient` 构造函数可覆盖。
- **通知响应**：收到通知验签成功后，必须调用 `$client->notify()->success()` 并输出其返回值，支付中心收到 2xx 后停止重推。

---

## 禁止修改的模块

- `HttpClient` 签名算法与请求头格式——与支付中心服务端严格对应，擅改会导致所有请求 403。
- `PayException` 错误码常量值——业务系统可能按常量值做 switch，修改会产生隐性 bug。
- `NotifyVerifier::verify()` 中 `hash_equals()` 的使用——替换为 `===` 会引入时序攻击漏洞。

---

## 接入示例

```php
use Jumaitong\Pay\PayClient;
use Jumaitong\Pay\Exception\PayException;

// 初始化（凭证走环境变量，禁止硬编码）
$client = new PayClient(
    apiKey:    $_ENV['PAY_API_KEY'],
    apiSecret: $_ENV['PAY_API_SECRET'],
    baseUrl:   $_ENV['PAY_BASE_URL'],
);

// 发起支付
try {
    $result = $client->pay()->create(
        channel:       'alipay',
        payType:       'web',
        amount:        9900,         // 99.00 元
        subject:       '年度会员',
        tenantOrderNo: 'ORD_202312_001',
        expireMinutes: 15,
    );
    // $result['order_no']    支付中心订单号
    // $result['pay_params']  渠道支付参数（跳转 URL 或 JSAPI 参数等）
} catch (PayException $e) {
    // 按错误码分支处理
}

// 异步通知处理（在 Webman 路由中）
$rawBody = $request->rawBody();
try {
    $data = $client->notify()->verify($rawBody);
    // 更新业务订单状态
    echo $client->notify()->success();
} catch (PayException $e) {
    http_response_code(400);
}
```

---

## 开发注意事项

- 本 SDK 在 Webman 常驻进程中使用时，`PayClient` 实例可安全注入容器（不含请求级状态）。
- `HttpClient` 每次请求都生成新的 `timestamp` 和 `nonce`，无状态问题。
- 新增渠道或支付方式时，只需更新 `PayApi` 中的验证数组（`VALID_CHANNELS` / `VALID_PAY_TYPES`），无需改动签名和 HTTP 层。
- 如需升级签名版本，修改 `HttpClient::sign()` 和 `HttpClient::post()` 中的请求头，并同步更新 `NotifyVerifier::verify()` 中的验签逻辑（两者算法必须对称）。
