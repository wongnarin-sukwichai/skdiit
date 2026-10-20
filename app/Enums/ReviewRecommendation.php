<?php

namespace App\Enums;

/** A reviewer's recommendation (workflow: "ผลการประเมิน"). */
enum ReviewRecommendation: string
{
    case Accept = 'accept';   // accept without revision
    case Revise = 'revise';   // revise and resubmit
    case Reject = 'reject';
}
