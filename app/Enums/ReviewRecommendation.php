<?php

namespace App\Enums;

/** A reviewer's recommendation (workflow: "ผลการประเมิน"). */
enum ReviewRecommendation: string
{
    case Accept = 'accept';   // accept without revision
    case Minor = 'minor';     // minor revision
    case Major = 'major';     // major revision
    case Reject = 'reject';
}
