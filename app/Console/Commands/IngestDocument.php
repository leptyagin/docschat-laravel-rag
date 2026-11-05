<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ingestion\IngestFile;
use App\Actions\Ingestion\IngestUrl;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Bot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('docschat:ingest
    {source : A path to a PDF, Markdown or text file, or an http(s) URL}
    {--bot=seabreeze : The slug of the bot that owns the document}
    {--title= : The document title, derived from the source when omitted}')]
#[Description('Queue a file or URL for ingestion')]
final class IngestDocument extends Command
{
    public function handle(IngestFile $ingestFile, IngestUrl $ingestUrl): int
    {
        $source = (string) $this->argument('source');
        $title = $this->option('title');
        $title = is_string($title) ? $title : null;

        $bot = Bot::query()->firstWhere('slug', $this->option('bot'));

        if ($bot === null) {
            $this->components->error("No bot with the slug [{$this->option('bot')}] exists.");

            return self::FAILURE;
        }

        try {
            $document = Str::startsWith($source, ['http://', 'https://'])
                ? $ingestUrl->handle($bot, $source, $title)
                : $ingestFile->handle($bot, $source, $title);
        } catch (UnprocessableSourceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Document #{$document->id} [{$document->title}] is {$document->status->value}.");

        return self::SUCCESS;
    }
}
