<?php

namespace App\Services;

use App\Models\Category;

class StandingsCalculatorService
{
    /**
     * Calculate standings for a given category based on validated matches.
     *
     * @param Category $category
     * @return array
     */
    public function calculate(Category $category): array
    {
        $standings = [];

        // Eager load entries with their player and team relations
        $category->loadMissing('entries.player', 'entries.team');

        // Initialize standings for all entries in the category
        foreach ($category->entries as $entry) {
            $name = $entry->entry_type === 'player' 
                ? ($entry->player ? $entry->player->name : 'Unknown Player')
                : ($entry->team ? $entry->team->name : 'Unknown Team');

            $standings[$entry->id] = [
                'entry_id' => $entry->id,
                'name' => $name,
                'points' => 0,
                'played' => 0,
                'won' => 0,
                'lost' => 0,
                'games_for' => 0,
                'games_against' => 0,
                'games_difference' => 0,
            ];
        }

        // Get all validated matches for this category's rounds
        $rounds = $category->rounds()->with('matches')->get();
        
        foreach ($rounds as $round) {
            foreach ($round->matches as $match) {
                if ($match->status !== 'validated' || $match->home_score === null || $match->away_score === null) {
                    continue;
                }

                $homeId = $match->home_entry_id;
                $awayId = $match->away_entry_id;
                $homeScore = $match->home_score;
                $awayScore = $match->away_score;

                if (!isset($standings[$homeId]) || !isset($standings[$awayId])) {
                    continue; // Skip if entry is not in the list (substitutions / withdrawn)
                }

                $standings[$homeId]['played']++;
                $standings[$awayId]['played']++;
                
                $standings[$homeId]['games_for'] += $homeScore;
                $standings[$homeId]['games_against'] += $awayScore;
                $standings[$awayId]['games_for'] += $awayScore;
                $standings[$awayId]['games_against'] += $homeScore;

                // Scoring rules: Win = 3 points, Loss = 0 (1 if $>8$ games won)
                if ($homeScore > $awayScore) {
                    // Home won
                    $standings[$homeId]['won']++;
                    $standings[$awayId]['lost']++;
                    
                    $standings[$homeId]['points'] += 3;
                    if ($awayScore > 8) {
                        $standings[$awayId]['points'] += 1;
                    }
                } elseif ($awayScore > $homeScore) {
                    // Away won
                    $standings[$awayId]['won']++;
                    $standings[$homeId]['lost']++;
                    
                    $standings[$awayId]['points'] += 3;
                    if ($homeScore > 8) {
                        $standings[$homeId]['points'] += 1;
                    }
                } else {
                    // Tie scenario (rarely happens in Galotxa, but handled safely)
                    $standings[$homeId]['points'] += 1;
                    $standings[$awayId]['points'] += 1;
                }
            }
        }

        // Calculate differences and convert to array list
        $result = [];
        foreach ($standings as &$standing) {
            $standing['games_difference'] = $standing['games_for'] - $standing['games_against'];
            $result[] = $standing;
        }

        // Sort by points DESC, then games_diff DESC, then games_for DESC
        usort($result, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['games_difference'] !== $b['games_difference']) {
                return $b['games_difference'] <=> $a['games_difference'];
            }
            return $b['games_for'] <=> $a['games_for'];
        });

        // Assign positions
        $position = 1;
        foreach ($result as &$teamStanding) {
            $teamStanding['position'] = $position++;
        }

        return $result;
    }
}
