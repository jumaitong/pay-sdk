# Pay SDK

Jumaitong 支付中心 PHP SDK，供内部业务系统接入使用。

**要求**：PHP >= 8.4，启用 `curl` 和 `json` 扩展。

---

## 安装

### 1. 配置私有仓库

在业务项目的 `composer.json` 中添加 `repositories` 配置，指向本 SDK 的 Git 仓库：

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:jumaitong/pay-sdk.git"
        }
    ],
    "require": {
        "jumaitong/pay-sdk": "^1.0"
    }
}
```

### 2. 执行安装

```bash
composer install
```

或直接用 `require` 命令一步完成：

```bash
composer config repositories.pay-sdk vcs git@github.com:jumaitong/pay-sdk.git
composer require jumaitong/pay-sdk:^1.0
```

> 确保运行环境已配置对 `github.com:jumaitong` 的 SSH 访问权限，或在 `auth.json` 中配置 HTTPS Token。

---

## 快速开始

### 初始化

```php
use Jumaitong\Pay\PayClient;
use Jumaitong\Pay\Exception\PayException;

$client = new PayClient(
    apiKey:    $_ENV['PAY_API_KEY'],
    apiSecret: $_ENV['PAY_API_SECRET'],
    baseUrl:   $_ENV['PAY_BASE_URL'],
);
```

凭证通过环境变量注入，禁止硬编码。

### 发起支付

```php
try {
    $result = $client->pay()->create(
        channel:       'alipay',   // alipay / wechat / unipay / douyin
        payType:       'web',      // web / h5 / app / mini / scan
        amount:        9900,       // 单位：分（9900 = 99.00 元）
        subject:       '年度会员',
        tenantOrderNo: 'ORD_202312_001',
    );

    // $result['order_no']   支付中心订单号
    // $result['pay_params'] 渠道支付参数（跳转 URL 或 JSAPI 参数等）
} catch (PayException $e) {
    // $e->getBizCode() 获取错误码，$e->getMessage() 获取描述
}
```

### 查询订单

```php
$order = $client->pay()->query('ORD_202312_001');
// $order['status'] => pending / paid / closed / refunded
```

### 申请退款

```php
$refund = $client->refund()->create(
    tenantOrderNo:  'ORD_202312_001',
    refundAmount:   9900,
    reason:         '用户申请退款',
    tenantRefundNo: 'REF_202312_001', // 可选，用于幂等
);
```

### 处理异步通知

在支付回调路由中验证签名并返回响应：

```php
$rawBody = $request->rawBody();

try {
    $data = $client->notify()->verify($rawBody);
    // $data['tenant_order_no'] 业务订单号
    // $data['amount']          实付金额（分）
    // $data['channel_trade_no'] 渠道交易号

    // 更新业务订单状态...

    echo $client->notify()->success(); // 必须输出，否则支付中心会重复推送
} catch (PayException $e) {
    http_response_code(400);
}
```
