<?php

namespace App\Enums;

enum SlaAlertType: string
{
    case NoInteraction24h = 'no_interaction_24h';
    case Negotiation15d = 'negotiation_15d';
    case NoReturn7d = 'no_return_7d';
    case AttemptsExhausted = 'attempts_exhausted';

    public function label(): string
    {
        return match ($this) {
            self::NoInteraction24h => 'Sem interação há 24h',
            self::Negotiation15d => 'Em negociação há mais de 15 dias',
            self::NoReturn7d => 'Sem retorno 7 dias após proposta enviada',
            self::AttemptsExhausted => 'Tentativas de contato esgotadas (7+)',
        };
    }
}
