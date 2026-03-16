package main

import (
	"log"
	"os"
	"time"
)

func main() {
	redisHost := os.Getenv("REDIS_HOST")
	redisPort := os.Getenv("REDIS_PORT")

	if redisHost == "" {
		redisHost = "redis"
	}
	if redisPort == "" {
		redisPort = "6379"
	}

	log.Printf("FreelanceFlow Go PDF worker starting — connecting to Redis at %s:%s", redisHost, redisPort)
	log.Println("Worker implementation coming in Phase 2.")

	// Block indefinitely — Phase 2 will replace this with queue processing.
	for {
		time.Sleep(time.Hour)
	}
}
