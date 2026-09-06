<?php

namespace App\Services\Admin;

use App\Enums\OfficialResultCompetitionPart;

class OfficialResultReadinessIssueFormatter
{
    /**
     * @param  list<array{code: string, context: array<string, int|string|list<int>>}>  $issues
     * @return list<string>
     */
    public function format(OfficialResultCompetitionPart $part, array $issues): array
    {
        return array_map(
            fn (array $issue): string => $this->message(
                $part,
                $issue['code'],
                $issue['context'],
            ),
            $issues,
        );
    }

    /** @param array<string, int|string|list<int>> $context */
    private function message(
        OfficialResultCompetitionPart $part,
        string $code,
        array $context,
    ): string {
        return match ($code) {
            'league_already_official' => 'La Liga ya tiene un resultado oficial vigente.',
            'cup_already_official' => 'La Copa ya tiene un resultado oficial vigente.',
            'insufficient_entries' => sprintf(
                'Hay %d inscripciones aprobadas; se necesitan al menos %d para oficializar la %s.',
                $this->integer($context, 'approved_entries'),
                $part === OfficialResultCompetitionPart::LEAGUE ? 3 : 4,
                $part === OfficialResultCompetitionPart::LEAGUE ? 'Liga' : 'Copa',
            ),
            'incoherent_entry_type' => 'La inscripción tiene un tipo incoherente'.$this->id($context, 'entry_id', 'inscripción').'.',
            'missing_entry_source' => 'Falta la fuente de una inscripción'.$this->id($context, 'entry_id', 'inscripción').'.',
            'invalid_team_composition' => 'La composición del equipo no es válida'.$this->id($context, 'entry_id', 'inscripción').'.',
            'ambiguous_round' => 'Existe una jornada con clasificación ambigua'.$this->id($context, 'round_id', 'jornada').'.',
            'missing_league_round' => 'No existe ninguna jornada de Liga.',
            'invalid_round_count' => sprintf(
                'El número de jornadas de Liga no es válido (esperadas: %d; actuales: %d).',
                $this->integer($context, 'expected'),
                $this->integer($context, 'actual'),
            ),
            'empty_league_round' => 'Hay una jornada de Liga sin partidos'.$this->id($context, 'round_id', 'jornada').'.',
            'entry_repeated_in_round' => 'Una o más inscripciones se repiten en la misma jornada'
                .$this->id($context, 'round_id', 'jornada')
                .$this->ids($context, 'entry_ids', 'inscripciones').'.',
            'self_pairing' => 'Un partido enfrenta una inscripción consigo misma'.$this->id($context, 'match_id', 'partido').'.',
            'foreign_entry' => 'Un partido contiene una inscripción ajena o no aprobada'.$this->id($context, 'match_id', 'partido').'.',
            'duplicate_pairing' => 'Un emparejamiento de Liga está duplicado'.$this->id($context, 'match_id', 'partido').'.',
            'match_not_validated' => 'El partido todavía no está validado'.$this->id($context, 'match_id', 'partido').'.',
            'missing_score' => 'Falta el marcador del partido'.$this->id($context, 'match_id', 'partido').'.',
            'tied_match' => 'El partido no puede finalizar empatado'.$this->id($context, 'match_id', 'partido').'.',
            'invalid_score' => 'El marcador del partido no cumple las reglas deportivas'.$this->id($context, 'match_id', 'partido').'.',
            'missing_winner' => 'Falta registrar el ganador del partido'.$this->id($context, 'match_id', 'partido').'.',
            'inconsistent_winner' => 'El ganador registrado no coincide con el marcador'.$this->id($context, 'match_id', 'partido').'.',
            'extra_league_match' => sprintf(
                'Hay más partidos de Liga de los esperados (esperados: %d; actuales: %d).',
                $this->integer($context, 'expected'),
                $this->integer($context, 'actual'),
            ),
            'incomplete_round_robin' => sprintf(
                'La Liga no contiene todos los enfrentamientos necesarios (faltan: %d).',
                $this->integer($context, 'missing_pairs'),
            ),
            'unsupported_ranking_tie' => 'Existe un empate de ranking que no puede resolverse de forma segura'
                .$this->ids($context, 'entry_ids', 'inscripciones').'.',
            'ambiguous_cup_round' => 'Existe una ronda de Copa con clasificación ambigua'.$this->id($context, 'round_id', 'ronda').'.',
            'missing_semifinal_round' => 'Falta la ronda de semifinales.',
            'duplicate_semifinal_round' => 'Existe más de una ronda de semifinales'
                .$this->ids($context, 'round_ids', 'rondas').'.',
            'missing_final_round' => 'Falta la ronda final.',
            'duplicate_final_round' => 'Existe más de una ronda final'
                .$this->ids($context, 'round_ids', 'rondas').'.',
            'duplicate_third_place_round' => 'Existe más de una ronda de tercer puesto'
                .$this->ids($context, 'round_ids', 'rondas').'.',
            'invalid_semifinal_match_count' => sprintf(
                'Las semifinales deben contener exactamente 2 partidos (actuales: %d).',
                $this->integer($context, 'actual'),
            ),
            'invalid_final_match_count' => sprintf(
                'La final debe contener exactamente 1 partido (actuales: %d).',
                $this->integer($context, 'actual'),
            ),
            'duplicate_semifinal_participant' => 'Una inscripción aparece más de una vez en semifinales'
                .$this->ids($context, 'entry_ids', 'inscripciones').'.',
            'source_integrity_error' => 'Un partido usado para ordenar la Copa tiene datos incoherentes'
                .$this->id($context, 'match_id', 'partido').'.',
            'unsupported_cup_seed_tie' => 'Existe un empate que impide determinar de forma segura las posiciones de Copa'
                .$this->ids($context, 'entry_ids', 'inscripciones').'.',
            'invalid_cup_seed' => 'Los emparejamientos de semifinales no coinciden con las posiciones de clasificación.',
            'inconsistent_finalists' => 'Los finalistas no coinciden con los ganadores de las semifinales.',
            default => sprintf(
                'No se reconoce la condición de seguridad «%s»; no se puede oficializar.',
                $this->safeCode($code),
            ),
        };
    }

    /** @param array<string, int|string|list<int>> $context */
    private function integer(array $context, string $key): int
    {
        return is_int($context[$key] ?? null) ? $context[$key] : 0;
    }

    /** @param array<string, int|string|list<int>> $context */
    private function id(array $context, string $key, string $label): string
    {
        $value = $context[$key] ?? null;

        return is_int($value) ? sprintf(' (%s #%d)', $label, $value) : '';
    }

    /** @param array<string, int|string|list<int>> $context */
    private function ids(array $context, string $key, string $label): string
    {
        $values = $context[$key] ?? null;
        if (! is_array($values) || $values === []) {
            return '';
        }

        $ids = array_values(array_filter($values, is_int(...)));

        return $ids === [] ? '' : sprintf(' (%s: %s)', $label, implode(', ', $ids));
    }

    private function safeCode(string $code): string
    {
        $safe = preg_replace('/[^a-z0-9_:-]/i', '?', $code);

        return $safe === null || $safe === '' ? 'desconocida' : $safe;
    }
}
