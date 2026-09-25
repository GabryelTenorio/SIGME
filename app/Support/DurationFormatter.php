<?php

namespace App\Support;

final class DurationFormatter
{
    public static function minutes(int $minutes): string
    {
        $remaining = max(0, $minutes);
        $days = intdiv($remaining, 1440);
        $remaining %= 1440;
        $hours = intdiv($remaining, 60);
        $minutes = $remaining % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'dia' : 'dias');
        }

        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hora' : 'horas');
        }

        if ($minutes > 0 || $parts === []) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minuto' : 'minutos');
        }

        return implode(' e ', $parts);
    }
}
