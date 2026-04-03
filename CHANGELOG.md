# Changelog

本文件记录所有版本的重要变更。格式遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [Semantic Versioning](https://semver.org/)。

---

## [1.0.0] - 2026-04-03

首个正式版本。

### 新增

- `PayClient`：SDK 统一入口，持有 `PayApi` / `RefundApi` / `NotifyVerifier` 三个子模块
- `PayApi`：支付接口，支持 `create()` / `query()` / `close()`
- `RefundApi`：退款接口，支持 `create()` / `query()`
- `NotifyVerifier`：异步通知验签，`verify()` 返回通知数据，`success()` 返回标准响应体
- `HttpClient`：内部 cURL 封装，HMAC-SHA256 v2.0 签名，自动组装认证请求头
- `PayException`：统一异常类，含全部业务错误码常量
- 支持渠道：`alipay` / `wechat` / `unipay` / `douyin`
- 支持支付方式：`web` / `h5` / `app` / `mini` / `scan`

### 安全

- SSL 证书验证显式开启（`CURLOPT_SSL_VERIFYPEER`）
- `baseUrl` 强制 HTTPS，构造时校验
- 通知验签使用 `hash_equals()` 防时序攻击
- 双重防重放保护：时间戳过期检测 + Nonce 唯一性检测

---

## 版本规范说明

| 变更类型 | 版本号影响 |
|---------|-----------|
| 不兼容的 API 变更 | 主版本号（1.x.x → 2.0.0） |
| 向后兼容的新功能 | 次版本号（1.0.x → 1.1.0） |
| 向后兼容的 Bug 修复 | 修订号（1.0.0 → 1.0.1） |
