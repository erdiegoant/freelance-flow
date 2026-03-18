package pdf

import (
	"bytes"
	"fmt"

	"github.com/go-pdf/fpdf"
)

// InvoiceData mirrors the Redis payload pushed by the Laravel GenerateInvoicePdf job.
type InvoiceData struct {
	InvoiceID      int     `json:"invoice_id"`
	InvoiceNumber  string  `json:"invoice_number"`
	Client         Client  `json:"client"`
	Project        Project `json:"project"`
	Items          []Item  `json:"items"`
	Subtotal       float64 `json:"subtotal"`
	TaxRate        float64 `json:"tax_rate"`
	TaxAmount      float64 `json:"tax_amount"`
	Total          float64 `json:"total"`
	DueDate        string  `json:"due_date"`
	CallbackURL    string  `json:"callback_url"`
	CallbackSecret string  `json:"callback_secret"`
}

type Client struct {
	Name        string `json:"name"`
	Email       string `json:"email"`
	CompanyName string `json:"company_name"`
	Address     string `json:"address"`
	TaxID       string `json:"tax_id"`
}

type Project struct {
	Name       string  `json:"name"`
	HourlyRate float64 `json:"hourly_rate"`
}

type Item struct {
	Description string  `json:"description"`
	Quantity    float64 `json:"quantity"`
	UnitPrice   float64 `json:"unit_price"`
	Total       float64 `json:"total"`
}

// Generate produces a PDF invoice from the given data and returns the raw bytes.
func Generate(data InvoiceData) ([]byte, error) {
	pdf := fpdf.New("P", "mm", "A4", "")
	pdf.SetMargins(20, 20, 20)
	pdf.AddPage()

	pageWidth, _ := pdf.GetPageSize()
	contentWidth := pageWidth - 40 // 20mm margins each side

	// ── Header ──────────────────────────────────────────────────────────────
	pdf.SetFont("Helvetica", "B", 22)
	pdf.SetTextColor(30, 30, 30)
	pdf.CellFormat(contentWidth, 10, "INVOICE", "", 1, "L", false, 0, "")

	pdf.SetFont("Helvetica", "", 10)
	pdf.SetTextColor(100, 100, 100)
	pdf.CellFormat(contentWidth/2, 6, fmt.Sprintf("Invoice #: %s", data.InvoiceNumber), "", 0, "L", false, 0, "")
	pdf.CellFormat(contentWidth/2, 6, fmt.Sprintf("Due Date: %s", data.DueDate), "", 1, "R", false, 0, "")
	pdf.Ln(6)

	// ── Client Block ─────────────────────────────────────────────────────────
	pdf.SetFont("Helvetica", "B", 11)
	pdf.SetTextColor(30, 30, 30)
	pdf.CellFormat(contentWidth, 7, "Bill To", "", 1, "L", false, 0, "")

	pdf.SetFont("Helvetica", "", 10)
	pdf.SetTextColor(60, 60, 60)
	pdf.CellFormat(contentWidth, 6, data.Client.Name, "", 1, "L", false, 0, "")

	if data.Client.CompanyName != "" {
		pdf.CellFormat(contentWidth, 6, data.Client.CompanyName, "", 1, "L", false, 0, "")
	}

	if data.Client.Address != "" {
		pdf.MultiCell(contentWidth, 6, data.Client.Address, "", "L", false)
	}

	if data.Client.TaxID != "" {
		pdf.CellFormat(contentWidth, 6, fmt.Sprintf("Tax ID: %s", data.Client.TaxID), "", 1, "L", false, 0, "")
	}

	pdf.CellFormat(contentWidth, 6, data.Client.Email, "", 1, "L", false, 0, "")
	pdf.Ln(6)

	// ── Project Block ────────────────────────────────────────────────────────
	pdf.SetFont("Helvetica", "B", 11)
	pdf.SetTextColor(30, 30, 30)
	pdf.CellFormat(contentWidth, 7, "Project", "", 1, "L", false, 0, "")

	pdf.SetFont("Helvetica", "", 10)
	pdf.SetTextColor(60, 60, 60)
	pdf.CellFormat(contentWidth/2, 6, data.Project.Name, "", 0, "L", false, 0, "")
	pdf.CellFormat(contentWidth/2, 6, fmt.Sprintf("Rate: $%.2f / hr", data.Project.HourlyRate), "", 1, "R", false, 0, "")
	pdf.Ln(6)

	// ── Items Table ──────────────────────────────────────────────────────────
	colDesc := contentWidth * 0.50
	colQty := contentWidth * 0.15
	colUnit := contentWidth * 0.175
	colTotal := contentWidth * 0.175

	// Table header
	pdf.SetFillColor(240, 240, 240)
	pdf.SetFont("Helvetica", "B", 10)
	pdf.SetTextColor(30, 30, 30)
	pdf.CellFormat(colDesc, 8, "Description", "1", 0, "L", true, 0, "")
	pdf.CellFormat(colQty, 8, "Qty", "1", 0, "C", true, 0, "")
	pdf.CellFormat(colUnit, 8, "Unit Price", "1", 0, "R", true, 0, "")
	pdf.CellFormat(colTotal, 8, "Total", "1", 1, "R", true, 0, "")

	// Table rows
	pdf.SetFont("Helvetica", "", 10)
	pdf.SetTextColor(60, 60, 60)

	for i, item := range data.Items {
		fillColor := i%2 == 0
		if fillColor {
			pdf.SetFillColor(250, 250, 250)
		} else {
			pdf.SetFillColor(255, 255, 255)
		}

		pdf.CellFormat(colDesc, 7, item.Description, "1", 0, "L", true, 0, "")
		pdf.CellFormat(colQty, 7, fmt.Sprintf("%.2f", item.Quantity), "1", 0, "C", true, 0, "")
		pdf.CellFormat(colUnit, 7, fmt.Sprintf("$%.2f", item.UnitPrice), "1", 0, "R", true, 0, "")
		pdf.CellFormat(colTotal, 7, fmt.Sprintf("$%.2f", item.Total), "1", 1, "R", true, 0, "")
	}

	pdf.Ln(4)

	// ── Totals Block ─────────────────────────────────────────────────────────
	labelWidth := contentWidth * 0.75
	valueWidth := contentWidth * 0.25

	pdf.SetFont("Helvetica", "", 10)
	pdf.SetTextColor(60, 60, 60)
	pdf.CellFormat(labelWidth, 7, "Subtotal", "", 0, "R", false, 0, "")
	pdf.CellFormat(valueWidth, 7, fmt.Sprintf("$%.2f", data.Subtotal), "", 1, "R", false, 0, "")

	pdf.CellFormat(labelWidth, 7, fmt.Sprintf("Tax (%.0f%%)", data.TaxRate*100), "", 0, "R", false, 0, "")
	pdf.CellFormat(valueWidth, 7, fmt.Sprintf("$%.2f", data.TaxAmount), "", 1, "R", false, 0, "")

	pdf.SetFont("Helvetica", "B", 11)
	pdf.SetTextColor(30, 30, 30)
	pdf.CellFormat(labelWidth, 8, "Total Due", "", 0, "R", false, 0, "")
	pdf.CellFormat(valueWidth, 8, fmt.Sprintf("$%.2f", data.Total), "", 1, "R", false, 0, "")

	// ── Output ───────────────────────────────────────────────────────────────
	var buf bytes.Buffer
	if err := pdf.Output(&buf); err != nil {
		return nil, fmt.Errorf("failed to render PDF: %w", err)
	}

	return buf.Bytes(), nil
}
