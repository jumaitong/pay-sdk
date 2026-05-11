package paysdk

import "fmt"

// ErrAPI 表示服务端返回非零业务码。
type ErrAPI struct {
	Code int
	Msg  string
}

func (e *ErrAPI) Error() string {
	return fmt.Sprintf("pay-sdk: code=%d msg=%s", e.Code, e.Msg)
}

// 常用业务码常量，方便调用方 switch/errors.As 判断。
const (
	CodeOK                = 0
	CodeMissingAuthHeader = 40100
	CodeTimestampExpired  = 40101
	CodeNonceReplayed     = 40102
	CodeSignInvalid       = 40103
	CodeTenantDisabled    = 40104
	CodeIPForbidden       = 40105
	CodeOpForbidden       = 40106
	CodeOrderNotFound     = 40400
	CodeOrderStateFail    = 40901
	CodeChannelNotConfig  = 40902
	CodeRefundExceeds     = 40903
	CodeDuplicate         = 40904
	CodeBadRequest        = 42200
	CodeInvalidAmount     = 42201
	CodePayInProgress     = 42900
	CodeChannelError      = 50200
)
