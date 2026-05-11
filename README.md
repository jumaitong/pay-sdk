# pay-sdk

[pay 支付中心](https://github.com/jumaitong/pay) 的 Go 客户端 SDK。零外部依赖，只用标准库。

## 安装

```bash
go get github.com/jumaitong/pay-sdk
```

## 快速开始

```go
import paysdk "github.com/jumaitong/pay-sdk"

client := paysdk.New("your_api_key", "your_api_secret", "https://pay.jumaitong.com")

// 发起支付
result, err := client.Pay(ctx, &paysdk.PayReq{
    Channel:       "alipay",
    PayType:       "web",
    Amount:        9900, // 单位：分
    Subject:       "年度会员",
    TenantOrderNo: "ORD_20260226_001",
})

// 查询订单
order, err := client.PayQuery(ctx, &paysdk.PayQueryReq{
    TenantOrderNo: "ORD_20260226_001",
})

// 申请退款
refund, err := client.Refund(ctx, &paysdk.RefundReq{
    TenantOrderNo: "ORD_20260226_001",
    RefundAmount:  9900,
    Reason:        "用户申请",
})

// 查询退款
detail, err := client.RefundQuery(ctx, &paysdk.RefundQueryReq{
    OutRefundNo: "RF001...",
})

// 关闭订单
err = client.PayClose(ctx, &paysdk.PayCloseReq{
    TenantOrderNo: "ORD_20260226_001",
})
```

## Webhook 验签

```go
// 在你的 HTTP handler 中
rawBody, _ := io.ReadAll(r.Body)
payload, err := client.VerifyNotify(rawBody)
if err != nil {
    // 签名验证失败，拒绝处理
    http.Error(w, "invalid sign", http.StatusBadRequest)
    return
}
// payload.TenantOrderNo, payload.Amount, payload.PaidAt ...
```

## 错误处理

```go
result, err := client.Pay(ctx, req)
if err != nil {
    var apiErr *paysdk.ErrAPI
    if errors.As(err, &apiErr) {
        switch apiErr.Code {
        case paysdk.CodePayInProgress:
            // 重复提交，勿重试
        case paysdk.CodeChannelNotConfig:
            // 渠道未配置
        }
    }
    return err
}
```

## 支持的渠道

| channel | pay_type |
|---------|----------|
| alipay | web / h5 / mini / app / scan |
| wechat | web / h5 / mini / app / scan |

完整接口文档见 [pay API.md](https://github.com/jumaitong/pay/blob/main/docs/API.md)。
