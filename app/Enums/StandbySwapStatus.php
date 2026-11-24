<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a Springerdienst swap request stands. */
enum StandbySwapStatus: string
{
    /** Waiting for the other family. */
    case Pending = 'pending';

    /** The other family agreed; the two dates were exchanged. */
    case Accepted = 'accepted';

    /** The other family said no. */
    case Declined = 'declined';

    /** Withdrawn by the family who asked. */
    case Cancelled = 'cancelled';

    /** Overtaken: one of the two dates changed hands before anyone answered. */
    case Expired = 'expired';
}
