FROM golang:1.23-alpine AS builder

WORKDIR /app

COPY go-worker/go.mod ./
RUN go mod download

COPY go-worker/ .
RUN go build -o worker .

# --- Final stage ---
FROM alpine:latest

RUN apk --no-cache add ca-certificates tzdata

WORKDIR /app

COPY --from=builder /app/worker .

CMD ["./worker"]
