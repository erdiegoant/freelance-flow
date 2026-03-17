package main

import (
	"context"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"freelanceflow/go-worker/internal/config"
	"freelanceflow/go-worker/internal/queue"

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

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	if err := q.Ping(ctx); err != nil {
		log.Fatalf("Failed to connect to Redis: %v", err)
	}

	log.Println("Redis connection OK. Waiting for jobs...")

	for {
		select {
		case <-ctx.Done():
			log.Println("Shutdown signal received. Exiting.")
			return
		default:
		}

		payload, err := q.Dequeue(ctx)
		if err != nil {
			log.Printf("Dequeue error: %v", err)
			time.Sleep(2 * time.Second)
			continue
		}

		if payload == "" {
			continue // timeout — no job, keep looping
		}

		log.Printf("Received payload: %s", payload)
	}
}
