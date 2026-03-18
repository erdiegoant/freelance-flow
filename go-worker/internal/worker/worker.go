package worker

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"sync"

	"freelanceflow/go-worker/internal/callback"
	"freelanceflow/go-worker/internal/config"
	"freelanceflow/go-worker/internal/pdf"
	"freelanceflow/go-worker/internal/queue"
	"freelanceflow/go-worker/internal/storage"

	"github.com/minio/minio-go/v7"
)

// Run spawns cfg.WorkerPoolSize goroutines that each pull jobs from the queue,
// generate a PDF, upload it to MinIO, and POST a callback to Laravel.
// It blocks until ctx is cancelled, then waits for in-flight jobs to finish.
func Run(ctx context.Context, cfg *config.Config, q *queue.Queue, minioClient *minio.Client) {
	var wg sync.WaitGroup

	for range cfg.WorkerPoolSize {
		wg.Add(1)

		go func() {
			defer wg.Done()
			runLoop(ctx, cfg, q, minioClient)
		}()
	}

	wg.Wait()
	log.Println("All workers stopped.")
}

func runLoop(ctx context.Context, cfg *config.Config, q *queue.Queue, minioClient *minio.Client) {
	for {
		select {
		case <-ctx.Done():
			return
		default:
		}

		rawPayload, err := q.Dequeue(ctx)
		if err != nil {
			log.Printf("Dequeue error: %v", err)
			continue
		}

		if rawPayload == nil {
			continue // timeout — no job, keep looping
		}

		processJob(ctx, cfg, minioClient, rawPayload)
	}
}

func processJob(ctx context.Context, cfg *config.Config, minioClient *minio.Client, rawPayload []byte) {
	var data pdf.InvoiceData
	if err := json.Unmarshal(rawPayload, &data); err != nil {
		log.Printf("Failed to unmarshal job payload: %v", err)
		return // nothing to callback to without a valid payload
	}

	log.Printf("Processing invoice %s (ID: %d)", data.InvoiceNumber, data.InvoiceID)

	if err := handleJob(ctx, cfg, minioClient, data); err != nil {
		log.Printf("Job failed for invoice %s: %v", data.InvoiceNumber, err)

		callbackErr := callback.Send(ctx, data.CallbackURL, cfg.CallbackSecret, callback.Payload{
			Status: "failed",
			Error:  err.Error(),
		})
		if callbackErr != nil {
			log.Printf("Failed to send failure callback for invoice %s: %v", data.InvoiceNumber, callbackErr)
		}

		return
	}

	log.Printf("Invoice %s processed successfully", data.InvoiceNumber)
}

func handleJob(ctx context.Context, cfg *config.Config, minioClient *minio.Client, data pdf.InvoiceData) error {
	pdfBytes, err := pdf.Generate(data)
	if err != nil {
		return fmt.Errorf("PDF generation failed: %w", err)
	}

	objectName := fmt.Sprintf("invoices/%s.pdf", data.InvoiceNumber)

	pdfPath, err := storage.Upload(ctx, minioClient, cfg.MinioBucket, objectName, pdfBytes)
	if err != nil {
		return fmt.Errorf("MinIO upload failed: %w", err)
	}

	if err := callback.Send(ctx, data.CallbackURL, cfg.CallbackSecret, callback.Payload{
		Status:  "completed",
		PdfPath: pdfPath,
	}); err != nil {
		return fmt.Errorf("callback failed: %w", err)
	}

	return nil
}
