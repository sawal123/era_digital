<?php

namespace App\Exceptions;

use RuntimeException;

class PosChatbotUnavailable extends RuntimeException
{
    public function __construct(int $providerStatus = 0)
    {
        parent::__construct('Bantuan AI sedang tidak tersedia. Silakan coba lagi nanti.', $providerStatus);
    }
}
