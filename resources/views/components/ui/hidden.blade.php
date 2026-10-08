{{-- Campo nascosto. Props: name, value. Dopo un form respinto ripropone il valore inviato. --}}
@aware(['bag' => 'default'])
@props(['name', 'value' => null])
@php
    $valore = ($bag === 'default' || $errors->getBag($bag)->any()) ? old($name, $value) : $value;
@endphp
<input type="hidden" name="{{ $name }}" value="{{ $valore }}" {{ $attributes }}>
