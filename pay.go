package paysdk

import "context"

// Pay 发起支付，返回支付参数。
func (c *Client) Pay(ctx context.Context, req *PayReq) (*PayResp, error) {
	var resp PayResp
	if err := c.post(ctx, "/api/v2/payments", req, &resp); err != nil {
		return nil, err
	}
	return &resp, nil
}

// PayQuery 查询支付订单状态。
func (c *Client) PayQuery(ctx context.Context, req *PayQueryReq) (*PayQueryResp, error) {
	var resp PayQueryResp
	if err := c.post(ctx, "/api/v2/payments/query", req, &resp); err != nil {
		return nil, err
	}
	return &resp, nil
}

// PayClose 关闭未支付订单。
func (c *Client) PayClose(ctx context.Context, req *PayCloseReq) error {
	return c.post(ctx, "/api/v2/payments/close", req, nil)
}
