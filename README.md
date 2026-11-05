# DocsChat

An AI chatbot that answers website visitors' questions only from a company's own documents, cites its sources, and says "I don't know" when the answer isn't there.

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.5, Laravel 13, FrankenPHP (classic mode) |
| Database | PostgreSQL 17 + pgvector (`pgvector/pgvector:0.8.7-pg17`) |
| Queues & cache | Redis 8, Laravel Horizon |
| LLM | Laravel AI SDK (OpenAI, Anthropic) |
| Widget | Vue 3, built into one standalone `widget.js` |
| Admin panel | Blade and Tailwind CSS |
| PDF extraction | poppler-utils (`pdftotext`) |
| Quality | Pest, Larastan (level 6), Laravel Pint, an eval suite for answer quality |

## Getting started

Requirements: Docker with Compose v2. No PHP or Node is needed on the host.

```bash
cp .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm node npm install
docker compose run --rm node npm run build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Seeding creates the demo bot, Seabreeze Hotels (a fictional company), and queues its seven documents for ingestion. Embeddings need `OPENAI_API_KEY`. To run everything locally without a key, set `DOCSCHAT_AI_DRIVER=fake`.

- Demo page: http://localhost:8000
- Admin panel: http://localhost:8000/admin
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

Ingesting the same content again does nothing. A changed document replaces its chunks in one transaction, so a failed re-index leaves the previous version searchable. The text is indexed again when the embedding model changes, or on request from the admin panel.

The seed PDFs in `database/seed-docs` are built from their Markdown sources with `build-pdf.py`.

## Answering questions

```bash
curl -N -X POST http://localhost:8000/api/v1/bots/{public_key}/messages \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"question": "How much is the pet fee at Harbor View?"}'
```

The answer streams as server-sent events: `started` (with the `conversation_id` to send on follow-ups), `delta` (text), and `completed` (with the cited sources). Each question goes through these steps:

1. A follow-up is rewritten into a standalone search query, using the last 6 messages.
2. Chunks are ranked by cosine similarity and by full-text search, and the two rankings are merged with Reciprocal Rank Fusion. The top 6 are kept.
3. If nothing is similar enough, the bot refuses without calling the chat model.
4. The answer is generated from the retrieved chunks, with inline citations such as `[1]`.
5. An answer without a valid citation is recorded as unanswered.
6. The message is stored with its tokens, cost and latency.

The system prompts live in `resources/prompts`, and the model prices in `config/docschat.php`.

## Embedding the widget

```html
<script src="https://your-domain/widget.js" data-bot="PUBLIC_KEY" async></script>
```

Optional attributes: `data-color` (a hex color), `data-position` (`left` or `right`), `data-greeting`, and `data-open="true"` to open the chat on load on wide screens.

The launcher lives in a shadow root and the chat in an iframe, so the styles of the site and the widget cannot affect each other. `public/embed-test.html` is a plain page with deliberately hostile styles that proves this. Serve it from another origin:

```bash
python3 -m http.server 9000 --directory public
```

## Admin panel

Logins come from `.env`: `ADMIN_EMAIL` and `ADMIN_PASSWORD` for the owner, and `DEMO_EMAIL` and `DEMO_PASSWORD` for a read-only account whose credentials are shown on the login page. An account without a password cannot log in. The panel covers the dashboard, documents, conversations with their sources, grouped unanswered questions, and bot settings.

## Protecting the demo

Each IP address may send 20 messages per hour and 60 per day. Once `DAILY_BUDGET_USD` has been spent in a day, the bot stops calling models and says so. Behind a proxy such as Cloudflare, set `TRUSTED_PROXIES`, or all visitors will share one limit.

## Quality evals

```bash
docker compose exec app php artisan docschat:eval
```

Runs the questions in `tests/evals/questions.yaml` through the real pipeline, prints source hit rate, correct and false refusals, cost and latency, and saves a report to `storage/evals`. With a real provider this spends a few cents.

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
