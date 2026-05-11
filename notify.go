package paysdk

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
)

var ErrInvalidSign = errors.New("pay-sdk: webhook 签名验证失败")

// VerifyNotify 验证支付中心推送的 Webhook 签名，验证通过返回解析后的 payload。
// rawBody 为收到的原始请求体字节。
func (c *Client) VerifyNotify(rawBody []byte) (*NotifyPayload, error) {
	var payload NotifyPayload
	if err := json.Unmarshal(rawBody, &payload); err != nil {
		return nil, err
	}

	// 去掉 sign 字段后重新序列化
	m := make(map[string]any)
	if err := json.Unmarshal(rawBody, &m); err != nil {
		return nil, err
	}
	delete(m, "sign")
	body, err := json.Marshal(m)
	if err != nil {
		return nil, err
	}

	signStr := c.apiKey + "\n" + payload.Timestamp + "\n" + payload.Nonce + "\n" + sha256Hex(body)
	mac := hmac.New(sha256.New, []byte(c.apiSecret))
	mac.Write([]byte(signStr))
	expected := hex.EncodeToString(mac.Sum(nil))

	if !hmac.Equal([]byte(expected), []byte(payload.Sign)) {
		return nil, ErrInvalidSign
	}
	return &payload, nil
}
