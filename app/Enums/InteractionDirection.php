<?php

namespace App\Enums;

enum InteractionDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
