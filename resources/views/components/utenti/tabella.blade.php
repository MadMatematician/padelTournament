{{-- Tabella utenti. Props: utenti (collezione di App\Models\User). --}}
@props(['utenti'])
<x-ui.table :headers="['Nome', 'Nome utente', 'Email', 'Ruolo', 'Registrato il']" empty="Nessun utente registrato.">
    @foreach ($utenti as $utente)
        <x-ui.tr>
            <x-ui.td>{{ $utente->name }}</x-ui.td>
            <x-ui.td>{{ $utente->username }}</x-ui.td>
            <x-ui.td muted>{{ $utente->email }}</x-ui.td>
            <x-ui.td><x-utenti.badge-ruolo :ruolo="$utente->ruolo" /></x-ui.td>
            <x-ui.td muted>{{ $utente->created_at?->format('d/m/Y H:i') }}</x-ui.td>
        </x-ui.tr>
    @endforeach
</x-ui.table>
