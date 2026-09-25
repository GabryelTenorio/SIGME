<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FirstAccessInvitation extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $plainToken) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SIGME | Defina sua senha de primeiro acesso')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Sua conta no SIGME foi criada. Para começar, defina uma senha que somente você conhecerá.')
            ->line('Este convite não possui prazo: ele deixa de funcionar assim que você definir sua senha.')
            ->action('Definir minha senha', route('first-access.show', [
                'token' => $this->plainToken,
                'email' => $notifiable->email,
            ]))
            ->line('Se você não esperava esta mensagem, avise o responsável pelo SIGME.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purpose' => 'first-access',
        ];
    }
}
