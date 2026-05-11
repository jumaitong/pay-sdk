package paysdk

// ---- 支付 ----

type PayReq struct {
	Channel       string         `json:"channel"`
	PayType       string         `json:"pay_type"`
	Amount        int64          `json:"amount"`
	Subject       string         `json:"subject"`
	TenantOrderNo string         `json:"tenant_order_no"`
	AccountKey    string         `json:"account_key,omitempty"`
	ExpireMinutes int            `json:"expire_minutes,omitempty"`
	Extra         map[string]any `json:"extra,omitempty"`
}

type PayResp struct {
	OrderNo   string `json:"order_no"`
	PayParams any    `json:"pay_params"`
}

type PayQueryReq struct {
	OrderNo       string `json:"order_no,omitempty"`
	TenantOrderNo string `json:"tenant_order_no,omitempty"`
	ForceRemote   bool   `json:"force_remote,omitempty"`
}

type PayQueryResp struct {
	OrderNo        string `json:"order_no"`
	TenantOrderNo  string `json:"tenant_order_no"`
	Channel        string `json:"channel"`
	PayType        string `json:"pay_type"`
	Amount         int64  `json:"amount"`
	AmountYuan     string `json:"amount_yuan"`
	Subject        string `json:"subject"`
	Status         int    `json:"status"`
	StatusLabel    string `json:"status_label"`
	ChannelTradeNo string `json:"channel_trade_no"`
	PaidAt         string `json:"paid_at"`
	CreatedAt      string `json:"created_at"`
}

type PayCloseReq struct {
	OrderNo       string `json:"order_no,omitempty"`
	TenantOrderNo string `json:"tenant_order_no,omitempty"`
}

// ---- 退款 ----

type RefundReq struct {
	OrderNo        string `json:"order_no,omitempty"`
	TenantOrderNo  string `json:"tenant_order_no,omitempty"`
	RefundAmount   int64  `json:"refund_amount"`
	Reason         string `json:"reason,omitempty"`
	TenantRefundNo string `json:"tenant_refund_no,omitempty"`
	AccountKey     string `json:"account_key,omitempty"`
}

type RefundResp struct {
	OutRefundNo     string `json:"out_refund_no"`
	TenantRefundNo  string `json:"tenant_refund_no"`
	ChannelRefundNo string `json:"channel_refund_no"`
	RefundAmount    int64  `json:"refund_amount"`
	Status          int    `json:"status"`
	StatusLabel     string `json:"status_label"`
	CreatedAt       string `json:"created_at"`
}

type RefundQueryReq struct {
	OutRefundNo    string `json:"out_refund_no,omitempty"`
	TenantRefundNo string `json:"tenant_refund_no,omitempty"`
}

type RefundQueryResp struct {
	OutRefundNo        string `json:"out_refund_no"`
	TenantRefundNo     string `json:"tenant_refund_no"`
	ChannelRefundNo    string `json:"channel_refund_no"`
	RefundAmount       int64  `json:"refund_amount"`
	RefundAmountYuan   string `json:"refund_amount_yuan"`
	Reason             string `json:"reason"`
	Status             int    `json:"status"`
	StatusLabel        string `json:"status_label"`
	RefundedAt         string `json:"refunded_at"`
	CreatedAt          string `json:"created_at"`
	UpdatedAt          string `json:"updated_at"`
}

// ---- Webhook ----

type NotifyPayload struct {
	Event          string `json:"event"`
	OrderNo        string `json:"order_no"`
	TenantOrderNo  string `json:"tenant_order_no"`
	Channel        string `json:"channel"`
	Amount         int64  `json:"amount"`
	ChannelTradeNo string `json:"channel_trade_no"`
	PaidAt         string `json:"paid_at"`
	Timestamp      string `json:"timestamp"`
	Nonce          string `json:"nonce"`
	Sign           string `json:"sign"`
}
