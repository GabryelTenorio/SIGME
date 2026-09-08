<?php

namespace App\Console\Commands;

use App\Support\EmergencyRatificationAlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('service-orders:alert-overdue-ratifications')]
#[Description('Notifica gestores e aprovadores sobre ratificações emergenciais vencidas')]
class SendOverdueEmergencyRatificationAlerts extends Command
{
    public function handle(EmergencyRatificationAlertService $alertService): int
    {
        $inserted = $alertService->send();

        $this->info("{$inserted} alerta(s) de ratificação emergencial criado(s).");

        return self::SUCCESS;
    }
}
