package main

import (
	"context"
	"log"
	"os"
	"os/signal"
	"syscall"

	"freelanceflow/go-worker/internal/config"
	"freelanceflow/go-worker/internal/queue"
	"freelanceflow/go-worker/internal/storage"
	"freelanceflow/go-worker/internal/worker"

	"github.com/joho/godotenv"
)

func main() {
	// Load .env for local development; no-op in Docker where env is injected.
	if os.Getenv("DOCKER_ENV") == "" {
		if err := godotenv.Load(".env"); err != nil {
			log.Fatalf("Failed to load .env: %v", err)
		}
	}

	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("Failed to load config: %v", err)
	}

	log.Printf("FreelanceFlow Go PDF worker starting — connecting to Redis at %s", cfg.RedisAddr)

	q := queue.New(cfg)
	defer q.Close()

	minioClient, err := storage.New(cfg)
	if err != nil {
		log.Fatalf("Failed to create MinIO client: %v", err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	if err := q.Ping(ctx); err != nil {
		log.Fatalf("Failed to connect to Redis: %v", err)
	}

	log.Printf("Redis OK. MinIO OK. Starting %d worker(s)...", cfg.WorkerPoolSize)

	worker.Run(ctx, cfg, q, minioClient)
}
