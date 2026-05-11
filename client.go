package paysdk

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"strconv"
	"time"
)

const defaultTimeout = 10 * time.Second

// Client 是 pay 支付中心的 HTTP 客户端。
type Client struct {
	apiKey     string
	apiSecret  string
	baseURL    string
	httpClient *http.Client
}

// New 创建客户端。baseURL 示例：https://pay.jumaitong.com
func New(apiKey, apiSecret, baseURL string) *Client {
	return &Client{
		apiKey:    apiKey,
		apiSecret: apiSecret,
		baseURL:   baseURL,
		httpClient: &http.Client{Timeout: defaultTimeout},
	}
}

// WithTimeout 覆盖默认超时时间。
func (c *Client) WithTimeout(d time.Duration) *Client {
	c.httpClient = &http.Client{Timeout: d}
	return c
}

func (c *Client) post(ctx context.Context, path string, req, resp any) error {
	body, err := json.Marshal(req)
	if err != nil {
		return err
	}

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	nonce := randomHex(16)
	sign := c.sign(ts, nonce, body)

	httpReq, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+path, bytes.NewReader(body))
	if err != nil {
		return err
	}
	httpReq.Header.Set("Content-Type", "application/json")
	httpReq.Header.Set("X-Api-Key", c.apiKey)
	httpReq.Header.Set("X-Timestamp", ts)
	httpReq.Header.Set("X-Nonce", nonce)
	httpReq.Header.Set("X-Sign", sign)

	httpResp, err := c.httpClient.Do(httpReq)
	if err != nil {
		return err
	}
	defer httpResp.Body.Close()

	raw, err := io.ReadAll(httpResp.Body)
	if err != nil {
		return err
	}

	var envelope struct {
		Code int             `json:"code"`
		Msg  string          `json:"msg"`
		Data json.RawMessage `json:"data"`
	}
	if err = json.Unmarshal(raw, &envelope); err != nil {
		return fmt.Errorf("pay-sdk: invalid response: %w", err)
	}
	if envelope.Code != CodeOK {
		return &ErrAPI{Code: envelope.Code, Msg: envelope.Msg}
	}
	if resp != nil && len(envelope.Data) > 0 {
		return json.Unmarshal(envelope.Data, resp)
	}
	return nil
}

// sign 生成 HMAC-SHA256 签名。
// signStr = api_key + "\n" + ts + "\n" + nonce + "\n" + SHA256(body)
func (c *Client) sign(ts, nonce string, body []byte) string {
	bodyHash := sha256Hex(body)
	signStr := c.apiKey + "\n" + ts + "\n" + nonce + "\n" + bodyHash
	mac := hmac.New(sha256.New, []byte(c.apiSecret))
	mac.Write([]byte(signStr))
	return hex.EncodeToString(mac.Sum(nil))
}

func sha256Hex(b []byte) string {
	h := sha256.Sum256(b)
	return hex.EncodeToString(h[:])
}

func randomHex(n int) string {
	b := make([]byte, n)
	rand.Read(b) //nolint:gosec
	return hex.EncodeToString(b)
}
