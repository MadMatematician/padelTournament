{{-- Avviso. Props: variant (success, danger, warning, info…), icon, dismissible. --}}
@props(['variant' => 'info', 'icon' => null, 'dismissible' => false])
<div role="alert" {{ $attributes->class(['alert', 'alert-'.$variant, 'd-flex', 'align-items-start', 'gap-2', 'alert-dismissible fade show' => $dismissible]) }}>
    @if ($icon)
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    @endif
    <div>{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    @endif
</div>
