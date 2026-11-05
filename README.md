# DocsChat

An AI chatbot that answers website visitors' questions only from a company's own documents, cites its sources, and says "I don't know" when the answer isn't there.

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.5, Laravel 13, FrankenPHP (classic mode) |
| Database | PostgreSQL 17 + pgvector (`pgvector/pgvector:0.8.7-pg17`) |
| Queues & cache | Redis 8, Laravel Horizon |
| LLM | Laravel AI SDK (OpenAI, Anthropic) |
| PDF extraction | poppler-utils (`pdftotext`) |
| Quality | Pest, Larastan (level 6), Laravel Pint |

## Getting started

Requirements: Docker with Compose v2. No PHP or Node is needed on the host.

```bash
cp .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Seeding creates the demo bot, Seabreeze Hotels (a fictional company), and queues its seven documents for ingestion. Embeddings need `OPENAI_API_KEY`. To run everything locally without a key, set `DOCSCHAT_AI_DRIVER=fake`.

- App: http://localhost:8000
- Health check: http://localhost:8000/up
- Horizon: http://localhost:8000/horizon

## Ingesting documents

```bash
docker compose exec app php artisan docschat:ingest path/to/guide.pdf --title="Guest Guide"
docker compose exec app php artisan docschat:ingest https://example.com/faq --bot=seabreeze
```

PDF, Markdown and plain text files and public web pages are supported. Each source goes through three queued jobs on the `ingestion` queue:

1. `ExtractText` reads the source into Markdown-flavoured text. PDF headings are recognised from the page layout, and web pages are reduced to their main content after checking `robots.txt`.
2. `ChunkText` splits the text by headings, then by size (600 tokens, 80 overlap), keeping the heading trail and page number on each chunk.
3. `EmbedChunks` embeds the chunks in batches and publishes them.

Ingesting the same content again does nothing. A changed document replaces its chunks in one transaction, so a failed re-index leaves the previous version searchable.

## Services

| Service | Purpose |
| --- | --- |
| `app` | FrankenPHP web server |
| `worker` | Horizon (queues `default` and `ingestion`) |
| `scheduler` | `schedule:work` |
| `postgres` | PostgreSQL 17 + pgvector, plus a `docschat_testing` database for tests |
| `redis` | Queues, cache, sessions |
| `node` | On-demand tooling: `docker compose run --rm node npm ...` |

## Quality checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
```

## Production

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Set `DOMAIN`, `APP_URL`, `APP_KEY` and strong database credentials in `.env` first. FrankenPHP (Caddy) issues the HTTPS certificate for `DOMAIN` automatically. The database and Redis ports are not exposed, every container has a memory limit and restarts automatically, and migrations run when the app starts.
