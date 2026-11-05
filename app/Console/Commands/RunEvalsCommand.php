<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Evals\EvalReport;
use App\Data\Evals\EvalResult;
use App\Models\Bot;
use App\Services\Evals\EvalRunner;
use App\Services\Evals\EvalSuite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Signature('docschat:eval
    {--bot=seabreeze : The slug of the bot to test}
    {--questions=tests/evals/questions.yaml : The question file, relative to the project root}')]
#[Description('Run the quality evals against the answer pipeline and save a report')]
final class RunEvalsCommand extends Command
{
    public function handle(EvalSuite $suite, EvalRunner $runner, Filesystem $files): int
    {
        $bot = Bot::query()->firstWhere('slug', $this->option('bot'));

        if ($bot === null) {
            $this->components->error("No bot with the slug [{$this->option('bot')}] exists.");

            return self::FAILURE;
        }

        if (config('docschat.ai.driver') === 'fake') {
            $this->components->warn('The fake driver is active, so these numbers say nothing about real answer quality.');
        }

        $path = base_path((string) $this->option('questions'));

        if (! $files->exists($path)) {
            $this->components->error("The question file [{$path}] does not exist.");

            return self::FAILURE;
        }

        $questions = $suite->load($path);
        $progress = $this->output->createProgressBar(count($questions));

        $report = $runner->run($bot, $questions, function () use ($progress): void {
            $progress->advance();
        });

        $progress->finish();
        $this->newLine(2);

        $this->renderSummary($report);
        $this->renderFailures($report);

        $reportPath = storage_path('evals/'.now()->format('Y-m-d_His').'.json');

        $files->ensureDirectoryExists(dirname($reportPath));
        $files->put($reportPath, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->components->info("Report saved to {$reportPath}");

        return self::SUCCESS;
    }

    private function renderSummary(EvalReport $report): void
    {
        $answerable = count($report->answerable());
        $unanswerable = count($report->unanswerable());

        $this->table(['Metric', 'Result'], [
            ['Questions', count($report->results)],
            ['Source hit rate', $this->rate($report->sourceHits(), $answerable)],
            ['Answers with expected facts', $this->rate($report->keywordHits(), count($report->withExpectedWords()))],
            ['Correct refusals', $this->rate($report->correctRefusals(), $unanswerable)],
            ['False refusals', $this->rate($report->falseRefusals(), $answerable)],
            ['Errors', $report->errors()],
            ['Average cost', '$'.$report->averageCost()],
            ['Total cost', '$'.$report->totalCost()],
            ['Average latency', $report->averageLatencyMs().' ms'],
        ]);
    }

    private function renderFailures(EvalReport $report): void
    {
        $failures = $report->failures();

        if ($failures === []) {
            return;
        }

        $this->table(['Failed question', 'Expected', 'Cited', 'Answer'], array_map(
            fn (EvalResult $result): array => [
                Str::limit($result->question->question, 45),
                $result->question->expectedDocument ?? 'refusal',
                implode(', ', $result->citedDocuments) ?: ($result->refusalReason ?? '-'),
                Str::limit($result->answer, 60),
            ],
            $failures,
        ));
    }

    private function rate(int $count, int $total): string
    {
        return $total === 0 ? '-' : sprintf('%d/%d (%d%%)', $count, $total, round($count / $total * 100));
    }
}
