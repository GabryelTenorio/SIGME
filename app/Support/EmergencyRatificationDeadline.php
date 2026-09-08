<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class EmergencyRatificationDeadline
{
    public function calculate(CarbonInterface $authorizedAt): CarbonImmutable
    {
        $deadline = CarbonImmutable::instance($authorizedAt)->addDay();

        while ($deadline->isWeekend()) {
            $deadline = $deadline->addDay();
        }

        return $deadline->endOfDay();
    }
}
