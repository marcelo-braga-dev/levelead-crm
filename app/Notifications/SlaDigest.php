<?php

namespace App\Notifications;

use App\Models\SlaAlert;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * 1 e-mail diário resumindo todos os `sla_alerts` ainda em aberto, agrupados por tipo — em vez
 * do antigo "1 e-mail por alerta criado" (`SlaAlertRaised`, que manteve só o canal `database`).
 * Síncrona como as demais notificações deste projeto (sem worker de fila no Sail).
 */
class SlaDigest extends Notification
{
    /** @param  Collection<int, SlaAlert>  $alerts */
    public function __construct(private Collection $alerts) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Resumo diário de SLA — {$this->alerts->count()} alerta(s) em aberto")
            ->line('Os seguintes leads têm alertas de SLA não resolvidos:');

        foreach ($this->alerts->groupBy(fn ($alert) => $alert->type->value) as $alerts) {
            $message->line('');
            $message->line('**'.$alerts->first()->type->label().'**');

            foreach ($alerts as $alert) {
                $company = $alert->lead->company;
                $companyName = $company->nome_fantasia ?? $company->razao_social;
                $message->line("— {$companyName}");
            }
        }

        return $message->action('Ver no Kanban', url('/kanban'));
    }
}
