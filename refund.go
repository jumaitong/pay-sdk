package paysdk

import "context"

// Refund 发起退款。
func (c *Client) Refund(ctx context.Context, req *RefundReq) (*RefundResp, error) {
	var resp RefundResp
	if err := c.post(ctx, "/api/v2/refunds", req, &resp); err != nil {
		return nil, err
	}
	return &resp, nil
}

// RefundQuery 查询退款状态。
func (c *Client) RefundQuery(ctx context.Context, req *RefundQueryReq) (*RefundQueryResp, error) {
	var resp RefundQueryResp
	if err := c.post(ctx, "/api/v2/refunds/query", req, &resp); err != nil {
		return nil, err
	}
	return &resp, nil
}
