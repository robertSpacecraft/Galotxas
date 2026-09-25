<?php

namespace App\Exceptions;

use DomainException;

/**
 * A CategoryEntry mutation would break the competitive-identity invariants.
 *
 * Rendered as a controlled 422 for API clients and as flash feedback for Blade,
 * never as SQL or internal constraint details.
 */
class CategoryEntryIntegrityException extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'entry')
    {
        parent::__construct($message);
    }

    public static function invalidIdentity(): self
    {
        return new self('Un participante debe referenciar exactamente un jugador o un equipo.', 'entry_type');
    }

    public static function typeMismatch(): self
    {
        return new self('El tipo de participante no coincide con el jugador o el equipo indicado.', 'entry_type');
    }

    public static function playerModalityRequired(): self
    {
        return new self('Las categorías de individuales solo admiten participantes de tipo jugador.', 'entry_type');
    }

    public static function teamModalityRequired(): self
    {
        return new self('Las categorías de dobles solo admiten participantes de tipo equipo.', 'entry_type');
    }

    public static function playerNotFound(): self
    {
        return new self('El jugador indicado no existe.', 'player_id');
    }

    public static function teamNotFound(): self
    {
        return new self('El equipo indicado no existe.', 'team_id');
    }

    public static function playerNotRegistered(): self
    {
        return new self('El jugador debe estar inscrito y aprobado en la categoría para participar.', 'player_id');
    }

    public static function teamPlayersNotRegistered(): self
    {
        return new self('Los jugadores del equipo deben estar inscritos en la categoría', 'team_id');
    }

    public static function teamOutsideCategory(): self
    {
        return new self('El equipo no pertenece a esta categoría.', 'team_id');
    }

    public static function invalidTeamComposition(): self
    {
        return new self(
            'El equipo debe estar formado por dos jugadores distintos, uno delantero y otro zaguero.',
            'team_id'
        );
    }

    public static function existingEntryIncompatible(): self
    {
        return new self(
            'El jugador ya tiene una entrada en esta categoría que no está aprobada o no es coherente. '
            .'Revísala manualmente antes de inscribirlo.',
            'player_id'
        );
    }

    public static function duplicatePlayer(): self
    {
        return new self('Este jugador ya participa en esta categoría.', 'player_id');
    }

    public static function duplicateTeam(): self
    {
        return new self('Este equipo ya participa en esta categoría.', 'team_id');
    }
}
