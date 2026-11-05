<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Ingestion\IngestFile;
use App\Models\Bot;
use Illuminate\Database\Seeder;

/**
 * Creates the demo bot and queues its documents. Safe to run repeatedly:
 * documents whose content has not changed are not embedded again.
 */
class SeabreezeSeeder extends Seeder
{
    public const array DOCUMENTS = [
        'faq.md' => 'Frequently Asked Questions',
        'cancellation-policy.pdf' => 'Cancellation Policy',
        'pets-policy.md' => 'Pets Policy',
        'parking-and-transport.md' => 'Parking and Transport',
        'amenities-and-spa.md' => 'Amenities and Spa',
        'check-in-and-check-out.md' => 'Check-in and Check-out',
        'accessibility.pdf' => 'Accessibility Guide',
    ];

    public function run(IngestFile $ingestFile): void
    {
        $bot = Bot::query()->firstOrCreate(['slug' => 'seabreeze'], [
            'name' => 'Seabreeze Hotels',
            'public_key' => Bot::generatePublicKey(),
            'greeting' => 'Hi! I can answer questions about staying at Seabreeze Hotels.',
            'fallback_contact' => 'hello@seabreeze.example',
            'theme' => ['color' => '#0f766e', 'position' => 'right'],
        ]);

        foreach (self::DOCUMENTS as $fileName => $title) {
            $ingestFile->handle($bot, database_path("seed-docs/{$fileName}"), $title);
        }
    }
}
