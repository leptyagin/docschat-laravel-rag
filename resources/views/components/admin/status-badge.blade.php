@props(['status'])

<span @class([
    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold',
    'border-emerald-400/30 bg-emerald-400/10 text-emerald-300' => $status === \App\Enums\DocumentStatus::Ready,
    'border-red-400/30 bg-red-400/10 text-red-300' => $status === \App\Enums\DocumentStatus::Failed,
    'border-sky-400/30 bg-sky-400/10 text-sky-300' => $status->isInFlight(),
])>
    <span @class(['size-1.5 rounded-full bg-current', 'animate-pulse' => $status->isInFlight()]) aria-hidden="true"></span>
    {{ ucfirst($status->value) }}
</span>
