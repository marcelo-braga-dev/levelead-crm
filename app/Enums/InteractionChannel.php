<?php

namespace App\Enums;

enum InteractionChannel: string
{
    case Call = 'call';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Visit = 'visit';
}
