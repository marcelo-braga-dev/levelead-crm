<?php

namespace App\Enums;

enum InteractionType: string
{
    case Call = 'call';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Visit = 'visit';
    case Note = 'note';
    case StageChange = 'stage_change';
    case FollowUp = 'follow_up';
    case System = 'system';
}
