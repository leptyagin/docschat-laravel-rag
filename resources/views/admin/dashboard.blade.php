<x-admin.layout title="Dashboard">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-admin.stat label="Questions today" :value="$stats->questionsToday" />
        <x-admin.stat label="Questions, 7 days" :value="$stats->questionsLastWeek" />
        <x-admin.stat label="Answer rate" :value="$stats->answerRate === null ? '–' : round($stats->answerRate * 100).'%'" />
        <x-admin.stat label="Avg. cost per answer" :value="$stats->averageCostUsd === null ? '–' : '$'.number_format($stats->averageCostUsd, 5)" />
        <x-admin.stat label="Avg. latency" class="col-span-2 lg:col-span-1" :value="$stats->averageLatencyMs === null ? '–' : number_format($stats->averageLatencyMs / 1000, 1).' s'" />
    </div>

    <x-admin.card>
        <h2 class="mb-2 font-semibold">Top unanswered questions</h2>
        <x-admin.question-groups :groups="$unanswered" />
    </x-admin.card>
</x-admin.layout>
