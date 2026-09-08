<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('sigme:create-admin {--name=} {--email=} {--generate : Gera uma senha segura e a mostra uma única vez}')]
#[Description('Cria ou atualiza o administrador técnico local do SIGME')]
class CreatePlatformAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nome do administrador', 'Administrador SIGME');
        $email = Str::lower($this->option('email') ?: $this->ask('E-mail local'));
        $generated = (bool) $this->option('generate');
        $password = $generated ? Str::password(20) : $this->secret('Senha (mínimo de 12 caracteres)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'organization_id' => null,
                'is_platform_admin' => true,
                'is_active' => true,
            ],
        );

        $this->info("Administrador local pronto: {$user->email}");

        if ($generated) {
            $this->warn("Senha gerada (exibida somente agora): {$password}");
        }

        return self::SUCCESS;
    }
}
