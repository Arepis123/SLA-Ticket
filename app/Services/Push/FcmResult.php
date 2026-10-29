<?php

namespace App\Services\Push;

enum FcmResult
{
    case Sent;

    /** The device no longer exists; forget its token. */
    case DeadToken;

    /** Temporary problem; the token is kept. */
    case Failed;
}
