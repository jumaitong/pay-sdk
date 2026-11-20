package paysdk

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"regexp"
	"strconv"
	"strings"
	"testing"
	"time"
)

// 固定签名向量：必须与 pay 服务端仓库 internal/middleware/hmac_auth_test.go 中的 vector* 常量逐字一致。
// 签名串：api_key\nts\nnonce\nSHA256(body)，密钥 api_secret，HMAC-SHA256 十六进制小写。
// 任何一边改动算法，两边的测试都会失败。签名值由 openssl 独立计算，不依赖任何一边的实现。
const (
	vectorAPIKey    = "test_apikey_001"
	vectorSecret    = "test_secret_abc123xyz789"
	vectorTimestamp = "1700000000"
	vectorNonce     = "nonce-fixed-0001"
	vectorBody      = `{"amount":100,"tenant_order_no":"ORD_1"}`
	vectorSign      = "b9c0348ff79a45d2719c7a5910f1371ca412adf2636b363b14c23e4e046f5417"
)

// Webhook 通知向量：pay 服务端 NotifyService.buildSignedBody 的输出格式
// （去掉 sign 后按键字典序紧凑序列化，再按同一签名串规则签名）。签名值由 Python 独立计算。
const notifyVectorBody = `{"amount":9900,"channel":"alipay","channel_trade_no":"2026112022001","event":"payment.success","nonce":"0a1b2c3d","order_no":"0001202611201030001234AbCdEfGh","paid_at":"2026-11-20 10:35:22","sign":"65c577507271fb8b5096d7bbe298e3cf0d689c35dd653e624c32fc88e3561c9c","tenant_order_no":"ORD_1","timestamp":"1700000000"}`

// 用标准库独立实现一遍，不复用被测函数。
func refSign(apiKey, secret, ts, nonce string, body []byte) string {
	sum := sha256.Sum256(body)
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(apiKey + "\n" + ts + "\n" + nonce + "\n" + hex.EncodeToString(sum[:])))
	return hex.EncodeToString(mac.Sum(nil))
}

func TestSignVector(t *testing.T) {
	if got := refSign(vectorAPIKey, vectorSecret, vectorTimestamp, vectorNonce, []byte(vectorBody)); got != vectorSign {
		t.Fatalf("独立实现与向量不一致，向量被改动？\n got:  %s\n want: %s", got, vectorSign)
	}
	c := New(vectorAPIKey, vectorSecret, "http://unused.test")
	if got := c.sign(vectorTimestamp, vectorNonce, []byte(vectorBody)); got != vectorSign {
		t.Fatalf("Client.sign 与向量不一致，签名算法被改动？\n got:  %s\n want: %s", got, vectorSign)
	}
}

func TestSign_DependsOnEveryInput(t *testing.T) {
	c := New(vectorAPIKey, vectorSecret, "http://unused.test")
	base := c.sign(vectorTimestamp, vectorNonce, []byte(vectorBody))
	variants := map[string]string{
		"ts":     c.sign("1700000001", vectorNonce, []byte(vectorBody)),
		"nonce":  c.sign(vectorTimestamp, "nonce-fixed-0002", []byte(vectorBody)),
		"body":   c.sign(vectorTimestamp, vectorNonce, []byte(vectorBody+" ")),
		"secret": New(vectorAPIKey, "other", "http://unused.test").sign(vectorTimestamp, vectorNonce, []byte(vectorBody)),
		"apikey": New("other_key", vectorSecret, "http://unused.test").sign(vectorTimestamp, vectorNonce, []byte(vectorBody)),
	}
	for name, v := range variants {
		if v == base {
			t.Errorf("改变 %s 后签名应不同", name)
		}
	}
}

var hex32 = regexp.MustCompile(`^[0-9a-f]{32}$`)

type captured struct {
	header http.Header
	body   []byte
}

func newCaptureServer(t *testing.T, response string, out *[]captured) *httptest.Server {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		raw, _ := io.ReadAll(r.Body)
		*out = append(*out, captured{header: r.Header.Clone(), body: raw})
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(response))
	}))
	t.Cleanup(srv.Close)
	return srv
}

// 请求头与签名：服务端收到的 X-Sign 必须等于对“实际收到的 body”独立复算的结果。
func TestPost_HeadersAndSignature(t *testing.T) {
	var got []captured
	srv := newCaptureServer(t, `{"code":0,"msg":"ok","data":{"k":"v"}}`, &got)
	c := New(vectorAPIKey, vectorSecret, srv.URL)

	var resp map[string]string
	for i := 0; i < 2; i++ {
		if err := c.post(context.Background(), "/api/v2/x", map[string]any{"amount": 100}, &resp); err != nil {
			t.Fatalf("post 失败: %v", err)
		}
	}
	if resp["k"] != "v" {
		t.Fatalf("响应 data 解析错误: %v", resp)
	}
	if len(got) != 2 {
		t.Fatalf("应收到 2 次请求，实际 %d", len(got))
	}

	for i, g := range got {
		h := g.header
		ts, nonce, sign := h.Get("X-Timestamp"), h.Get("X-Nonce"), h.Get("X-Sign")
		if h.Get("X-Api-Key") != vectorAPIKey || h.Get("Content-Type") != "application/json" {
			t.Fatalf("请求 %d 头不正确: %v", i, h)
		}
		if sec, err := strconv.ParseInt(ts, 10, 64); err != nil || time.Since(time.Unix(sec, 0)) > 5*time.Second {
			t.Fatalf("X-Timestamp 应为当前秒级时间戳，实际 %q", ts)
		}
		if !hex32.MatchString(nonce) {
			t.Fatalf("X-Nonce 应为 32 位小写十六进制，实际 %q", nonce)
		}
		if want := refSign(vectorAPIKey, vectorSecret, ts, nonce, g.body); sign != want {
			t.Fatalf("请求 %d 签名与独立复算不一致\n got:  %s\n want: %s", i, sign, want)
		}
	}
	if got[0].header.Get("X-Nonce") == got[1].header.Get("X-Nonce") {
		t.Fatal("两次请求不得使用相同 nonce")
	}
}

func TestPost_APIError(t *testing.T) {
	var got []captured
	srv := newCaptureServer(t, `{"code":40103,"msg":"签名验证失败","data":null}`, &got)
	c := New(vectorAPIKey, vectorSecret, srv.URL)

	err := c.post(context.Background(), "/api/v2/x", map[string]any{}, nil)
	var apiErr *ErrAPI
	if !errors.As(err, &apiErr) || apiErr.Code != 40103 {
		t.Fatalf("应返回 *ErrAPI{Code:40103}，实际 %v", err)
	}
}

func TestPost_InvalidResponse(t *testing.T) {
	var got []captured
	srv := newCaptureServer(t, `not json`, &got)
	if err := New(vectorAPIKey, vectorSecret, srv.URL).post(context.Background(), "/x", map[string]any{}, nil); err == nil {
		t.Fatal("非法响应应返回错误")
	}
}

func TestRandomHex(t *testing.T) {
	seen := map[string]bool{}
	for i := 0; i < 500; i++ {
		v, err := randomHex(16)
		if err != nil || !hex32.MatchString(v) {
			t.Fatalf("randomHex 不合法: %q err=%v", v, err)
		}
		seen[v] = true
	}
	if len(seen) != 500 {
		t.Fatalf("500 次生成不应重复，唯一值 %d", len(seen))
	}
}

// ---------- Webhook 验签 ----------

func TestVerifyNotify_Vector(t *testing.T) {
	c := New(vectorAPIKey, vectorSecret, "http://unused.test")
	p, err := c.VerifyNotify([]byte(notifyVectorBody))
	if err != nil {
		t.Fatalf("向量验签应通过: %v", err)
	}
	if p.Event != "payment.success" || p.Amount != 9900 || p.TenantOrderNo != "ORD_1" || p.Channel != "alipay" {
		t.Fatalf("payload 字段解析不正确: %+v", p)
	}
}

func TestVerifyNotify_Rejects(t *testing.T) {
	c := New(vectorAPIKey, vectorSecret, "http://unused.test")
	other := New(vectorAPIKey, "wrong_secret", "http://unused.test")

	replace := func(old, new string) []byte {
		s := notifyVectorBody
		i := strings.Index(s, old)
		if i < 0 {
			t.Fatalf("测试向量中找不到 %q", old)
		}
		return []byte(s[:i] + new + s[i+len(old):])
	}
	cases := []struct {
		name string
		cli  *Client
		body []byte
	}{
		{"篡改金额", c, replace(`"amount":9900`, `"amount":1`)},
		{"篡改订单号", c, replace(`ORD_1`, `ORD_2`)},
		{"篡改 nonce", c, replace(`0a1b2c3d`, `0a1b2c3e`)},
		{"篡改 timestamp", c, replace(`1700000000`, `1700000001`)},
		{"篡改签名", c, replace(`65c577`, `65c578`)},
		{"错误密钥", other, []byte(notifyVectorBody)},
		{"非 JSON", c, []byte("not json")},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			if _, err := tc.cli.VerifyNotify(tc.body); err == nil {
				t.Fatal("应验签失败")
			}
		})
	}
	if _, err := c.VerifyNotify(replace(`"amount":9900`, `"amount":1`)); !errors.Is(err, ErrInvalidSign) {
		t.Fatalf("篡改应返回 ErrInvalidSign，实际 %v", err)
	}
}
