<?php

declare(strict_types=1);

namespace App\Notification;

/** Port to the external notification service. One implementation per channel (Adapter). */
interface NotifierClient
{
    /** Must not throw on HTTP or network errors; report them in the result. */
    public function send(string $idempotencyKey, string $jsonBody): SendResult;
}
