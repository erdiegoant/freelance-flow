package config

import (
	"fmt"
	"os"
	"strconv"
)

type Config struct {
	RedisAddr      string
	RedisPassword  string
	RedisQueueKey  string
	MinioEndpoint  string
	MinioAccessKey string
	MinioSecretKey string
	MinioBucket    string
	MinioUseSSL    bool
	CallbackSecret string
	WorkerPoolSize int
}

func Load() (*Config, error) {
	cfg := &Config{
		RedisAddr:     getEnvOrDefault("REDIS_ADDR", "redis:6379"),
		RedisPassword: getEnvOrDefault("REDIS_PASSWORD", ""),
		RedisQueueKey: getEnvOrDefault("REDIS_QUEUE_KEY", "queues:invoice_generation"),
		MinioEndpoint: getEnvOrDefault("MINIO_ENDPOINT", "minio:9000"),
		MinioBucket:   getEnvOrDefault("MINIO_BUCKET", "freelanceflow"),
		MinioUseSSL:   getEnvOrDefault("MINIO_USE_SSL", "false") == "true",
	}

	var missing []string

	cfg.MinioAccessKey = os.Getenv("MINIO_ACCESS_KEY")
	if cfg.MinioAccessKey == "" {
		missing = append(missing, "MINIO_ACCESS_KEY")
	}

	cfg.MinioSecretKey = os.Getenv("MINIO_SECRET_KEY")
	if cfg.MinioSecretKey == "" {
		missing = append(missing, "MINIO_SECRET_KEY")
	}

	cfg.CallbackSecret = os.Getenv("CALLBACK_SECRET")
	if cfg.CallbackSecret == "" {
		missing = append(missing, "CALLBACK_SECRET")
	}

	if len(missing) > 0 {
		return nil, fmt.Errorf("missing required env vars: %v", missing)
	}

	poolSize, err := strconv.Atoi(getEnvOrDefault("WORKER_POOL_SIZE", "5"))
	if err != nil {
		return nil, fmt.Errorf("invalid WORKER_POOL_SIZE: %w", err)
	}

	cfg.WorkerPoolSize = poolSize

	return cfg, nil
}

func getEnvOrDefault(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return defaultValue
}
