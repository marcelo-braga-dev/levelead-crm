<?php

namespace App\Enums;

enum LossReason: string
{
    case NoBudget = 'no_budget';
    case BadTiming = 'bad_timing';
    case Competition = 'competition';
    case NoInterest = 'no_interest';
    case InvalidData = 'invalid_data';
    case Other = 'other';
}
