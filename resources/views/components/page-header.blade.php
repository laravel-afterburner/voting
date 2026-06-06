@props([
    'section',
    'action' => null,
    'detail' => null,
])

<h2 {{ $attributes->merge(['class' => 'font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight']) }}>
    {{ \App\Support\PageHeader::make($section, $action, $detail) }}
</h2>
