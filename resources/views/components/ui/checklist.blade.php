{{-- Elenco di requisiti con spunta. Slot: <x-ui.checklist-item>. Props: title. --}}
@props(['title' => null])
<div {{ $attributes->class(['elenco-requisiti', 'mb-3']) }}>
    @if ($title)
        <div class="small fw-semibold mb-1">{{ $title }}</div>
    @endif
    <ul class="list-unstyled small mb-0">
        {{ $slot }}
    </ul>
</div>
