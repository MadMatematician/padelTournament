{{-- Stato vuoto: icona, titolo e testo. Props: icon, title. --}}
@props(['icon' => 'inbox', 'title' => null])
<div {{ $attributes->class(['text-center', 'text-body-secondary', 'py-4']) }}>
    <i class="bi bi-{{ $icon }} fs-1 d-block mb-2" aria-hidden="true"></i>
    @if ($title)
        <div class="fw-semibold text-body">{{ $title }}</div>
    @endif
    <div class="small">{{ $slot }}</div>
</div>
