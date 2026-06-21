<?php

namespace App\Notifications;

use App\Models\SlaAlert;
use Illuminate\Notifications\Notification;

/**
 * Notifica admin/manager quando um novo `sla_alert` é gerado para um lead. Enviada de forma
 * síncrona (não implementa `ShouldQueue`) — sem worker de fila no Sail, uma notificação
 * enfileirada nunca seria entregue nem apareceria no canal `database` (in-app).
 *
 * Só o canal `database` (sino in-app, tempo real) — o canal `mail` por alerta individual foi
 * removido a favor de `SlaDigest` (1 e-mail diário resumindo todos os alertas em aberto, em vez
 * de potencialmente várias dezenas de e-mails por dia).
 */
class SlaAlertRaised extends Notification
{
    public function __construct(private SlaAlert $alert) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $lead = $this->alert->lead;

        return [
            'sla_alert_id' => $this->alert->id,
            'lead_id' => $lead->id,
            'type' => $this->alert->type->value,
            'label' => $this->alert->type->label(),
            'company_name' => $lead->company->nome_fantasia ?? $lead->company->razao_social,
        ];
    }
}
