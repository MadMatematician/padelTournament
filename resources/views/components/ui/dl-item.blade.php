{{-- Voce di <x-ui.dl>. Props: label. --}}
@props(['label'])
<dt class="col-sm-4 text-body-secondary fw-normal">{{ $label }}</dt>
<dd {{ $attributes->class(['col-sm-8']) }}>{{ $slot }}</dd>
