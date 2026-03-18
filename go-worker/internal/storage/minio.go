package storage

import (
	"bytes"
	"context"
	"fmt"

	"freelanceflow/go-worker/internal/config"

	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
)

func New(cfg *config.Config) (*minio.Client, error) {
	client, err := minio.New(cfg.MinioEndpoint, &minio.Options{
		Creds:  credentials.NewStaticV4(cfg.MinioAccessKey, cfg.MinioSecretKey, ""),
		Secure: cfg.MinioUseSSL,
	})

	if err != nil {
		return nil, fmt.Errorf("failed to create MinIO client: %w", err)
	}

	return client, nil
}

// Upload puts a PDF byte slice into MinIO under the given bucket and object name.
// Returns the object path (e.g. "invoices/INV-2026-0001.pdf") on success.
func Upload(ctx context.Context, client *minio.Client, bucket, objectName string, data []byte) (string, error) {
	_, err := client.PutObject(ctx, bucket, objectName, bytes.NewReader(data), int64(len(data)), minio.PutObjectOptions{
		ContentType: "application/pdf",
	})
	if err != nil {
		return "", fmt.Errorf("failed to upload %q to MinIO: %w", objectName, err)
	}

	return objectName, nil
}
