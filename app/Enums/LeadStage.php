<?php

namespace App\Enums;

enum LeadStage: string
{
    case New = 'new';
    case AttemptingContact = 'attempting_contact';
    case ContactMade = 'contact_made';
    case Qualified = 'qualified';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function isTerminal(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Lead Novo',
            self::AttemptingContact => 'Tentando Contato',
            self::ContactMade => 'Contato Realizado',
            self::Qualified => 'Qualificado',
            self::ProposalSent => 'Proposta Enviada',
            self::Negotiation => 'Negociação',
            self::Won => 'Ganho',
            self::Lost => 'Perdido',
        };
    }
}
