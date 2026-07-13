@if (! $entry)
    Por determinar
@elseif ($entry->entry_type === 'player' && $entry->player)
    {{ $entry->player->nickname
        ?: (trim(($entry->player->user?->name ?? '') . ' ' . ($entry->player->user?->lastname ?? ''))
            ?: 'Jugador sin nombre') }}
@elseif ($entry->entry_type === 'team' && $entry->team)
    {{ $entry->team->name ?: 'Equipo sin nombre' }}
@else
    Participante
@endif
