<?php

namespace Support\Logging;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;

final class RedactingLogManager extends LogManager
{
    protected function tap($name, Logger $logger)
    {
        $logger = parent::tap($name, $logger);
        $underlying = $logger->getLogger();
        if (method_exists($underlying, 'pushProcessor')) {
            $underlying->pushProcessor(new RedactLogRecord);
        }

        return new RedactingLogger($underlying, $this->app['events']);
    }

    protected function createEmergencyLogger()
    {
        $logger = parent::createEmergencyLogger()->getLogger();
        $logger->pushProcessor(new RedactLogRecord);

        return new RedactingLogger($logger, $this->app['events']);
    }
}
