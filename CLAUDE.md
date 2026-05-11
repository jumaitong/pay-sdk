# CLAUDE.md - pay-sdk

本项目由 `/Users/luxixi/projects/pay/` 会话统一管理，不单开会话。

## 项目定位

`github.com/jumaitong/pay-sdk` 是 pay 支付中心（`pay.jumaitong.com`）的 Go 客户端 SDK。
只做 HTTP transport 封装 + 签名，不含业务逻辑。

## 文件职责

| 文件 | 职责 |
|------|------|
| `client.go` | Client 结构体、`post()` 通用请求、HMAC 签名 |
| `pay.go` | Pay / PayQuery / PayClose |
| `refund.go` | Refund / RefundQuery |
| `notify.go` | VerifyNotify Webhook 验签 |
| `types.go` | 所有 Request / Response 结构体 |
| `errors.go` | ErrAPI 及业务码常量 |

## 关键约束

- 零外部依赖，只用标准库
- 签名算法与 pay 服务端严格一致：`HMAC-SHA256(api_key\nts\nnonce\nSHA256(body), api_secret)`
- `hmac.Equal` 恒定时间比较，禁止改为 `==`
- 不在 SDK 内做重试、缓存、业务逻辑

## 接口同步规则

pay 服务端接口变更时，必须同步更新本 SDK 的 types.go 和对应方法文件。
