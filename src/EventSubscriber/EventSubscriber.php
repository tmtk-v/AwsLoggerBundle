<?php

namespace Tmtk\AwsLoggerBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Tmtk\AwsLoggerBundle\Service\AwsLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     * @var AwsLogger
     */
    protected $awsLogger;

    /**
     * @var LoggerInterface
     */
    protected $errorLogger;

    /**
     * @var bool
     */
    protected $logAwsEvents;

    /**
     * @var bool
     */
    protected $logAwsErrors;

    public function __construct(AwsLogger $awsLogger, LoggerInterface $errorLogger, bool $logAwsEvents, bool $logAwsErrors)
    {
        $this->awsLogger = $awsLogger;
        $this->errorLogger = $errorLogger;
        $this->logAwsEvents = $logAwsEvents;
        $this->logAwsErrors = $logAwsErrors;
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::TERMINATE => [
                ['logWebserviceCalls', 0],
            ],
        ];
    }

    public function logWebserviceCalls(TerminateEvent $event)
    {
        if ($this->logAwsEvents) {
            try {
                $this->awsLogger->logEvents();
            } catch (\Exception $e) {
                if ($this->logAwsErrors) {
                    $this->errorLogger->error('AWS SDK error: ' . $e->getMessage());
                }
            }
        }
    }
}