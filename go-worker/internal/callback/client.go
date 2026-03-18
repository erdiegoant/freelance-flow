package callback

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
)

// Payload is the JSON body sent to the Laravel callback endpoint.
// Field names must match Laravel's validation rules: status, pdf_path, error.
type Payload struct {
	Status  string `json:"status"`
	PdfPath string `json:"pdf_path,omitempty"`
	Error   string `json:"error,omitempty"`
}

// Send POSTs the payload as JSON to callbackURL with the X-Callback-Secret header.
// Returns an error if the HTTP request fails or the response is non-2xx.
func Send(ctx context.Context, callbackURL, secret string, payload Payload) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal callback payload: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, callbackURL, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("failed to create callback request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Callback-Secret", secret)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return fmt.Errorf("callback request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		responseBody, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("callback returned non-2xx status %d: %s", resp.StatusCode, responseBody)
	}

	return nil
}
