@props(['status'])

<span @class([
    'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-emerald-100 text-emerald-800' => $status === \App\Enums\DocumentStatus::Ready,
    'bg-red-100 text-red-800' => $status === \App\Enums\DocumentStatus::Failed,
    'bg-sky-100 text-sky-800' => $status->isInFlight(),
])>{{ ucfirst($status->value) }}</span>
