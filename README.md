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

`tenantOrderNo` 和 `orderNo` 二选一传入：

```php
// 按业务订单号查询
$order = $client->pay()->query(tenantOrderNo: 'ORD_202312_001');

// 按支付中心流水号查询
$order = $client->pay()->query(orderNo: '0001202602261030001234');

// 强制查询第三方最新状态
$order = $client->pay()->query(tenantOrderNo: 'ORD_202312_001', forceRemote: true);
```

### 关闭订单

`tenantOrderNo` 和 `orderNo` 二选一传入：

```php
$client->pay()->close(tenantOrderNo: 'ORD_202312_001');
// 或
$client->pay()->close(orderNo: '0001202602261030001234');
```

### 申请退款

`tenantOrderNo` 和 `orderNo` 二选一传入；`refundAmount` 传 0 或不传表示全额退款：

```php
// 部分退款
$refund = $client->refund()->create(
    tenantOrderNo:  'ORD_202312_001',
    refundAmount:   5000,              // 50.00 元，不传或传 0 则全额退款
    refundReason:   '用户申请退款',
    tenantRefundNo: 'REF_202312_001',  // 可选，用于幂等
);
// $refund['out_refund_no'] 支付中心退款流水号

// 全额退款
$refund = $client->refund()->create(tenantOrderNo: 'ORD_202312_001');
```

### 查询退款

`tenantRefundNo` 和 `outRefundNo` 二选一传入：

```php
// 按业务退款单号查询
$refund = $client->refund()->query(tenantRefundNo: 'REF_202312_001');

// 按支付中心退款流水号查询
$refund = $client->refund()->query(outRefundNo: 'RF0001202602261040001234');
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

    echo \Jumaitong\Pay\Notification\NotifyVerifier::success(); // 必须输出，否则支付中心会重复推送
} catch (PayException $e) {
    http_response_code(400);
}
```
