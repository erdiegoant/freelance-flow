package queue

import (
	"context"
	"errors"
	"log"
	"time"

	"freelanceflow/go-worker/internal/config"

	"github.com/redis/go-redis/v9"
)

type Queue struct {
	client *redis.Client
	cfg    *config.Config
}

func New(cfg *config.Config) *Queue {
	client := redis.NewClient(&redis.Options{
		Addr:     cfg.RedisAddr,
		Password: cfg.RedisPassword,
		DB:       0,
	})

	return &Queue{
		client: client,
		cfg:    cfg,
	}
}

// Dequeue performs a blocking left-pop (BLPOP) with a 5-second timeout.
// Laravel pushes jobs with RPUSH, so BLPOP gives FIFO order.
// Returns ("", nil) on timeout — callers should loop without treating it as an error.
func (q *Queue) Dequeue(ctx context.Context) (string, error) {
	result, err := q.client.BLPop(ctx, 5*time.Second, q.cfg.RedisQueueKey).Result()

	if err != nil {
		if errors.Is(err, redis.Nil) {
			// Timeout — no job available, not a real error.
			return "", nil
		}

		return "", err
	}

	// BLPop returns [key, value]; result[1] is the payload.
	log.Printf("Job received from Redis queue %q", q.cfg.RedisQueueKey)

	return result[1], nil
}

func (q *Queue) Ping(ctx context.Context) error {
    return q.client.Ping(ctx).Err()
}

func (q *Queue) Close() error {
    return q.client.Close()
}
