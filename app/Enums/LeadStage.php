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
}
