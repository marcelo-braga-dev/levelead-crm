<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Active = 'ativa';
    case Suspended = 'suspensa';
    case Unfit = 'inapta';
    case Closed = 'baixada';
}
