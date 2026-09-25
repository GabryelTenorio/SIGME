<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('sigme:create-admin {--name=} {--email=} {--generate : Compatibilidade com instaladores anteriores}')]
#[Description('Cria o administrador técnico do SIGME e envia o convite de primeiro acesso')]
class CreatePlatformAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nome do administrador', 'Administrador SIGME');
        $email = Str::lower($this->option('email') ?: $this->ask('E-mail local'));

        $validator = Validator::make(compact('name', 'email'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $created = ! $user->exists;
        $attributes = [
            'name' => $name,
            'organization_id' => null,
            'is_platform_admin' => true,
            'is_active' => true,
        ];

        if ($created) {
            $attributes += [
                'password' => Str::random(64),
                'password_set_at' => null,
            ];
        }

        $user->forceFill($attributes)->save();

        if ($created) {
            $this->info("Administrador criado: {$user->email}");
            $this->info('O convite para definir a senha foi enviado por e-mail.');
        } else {
            $this->info("Administrador atualizado sem alterar a senha: {$user->email}");
        }

        return self::SUCCESS;
    }
}
