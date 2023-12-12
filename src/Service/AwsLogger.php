<?php

namespace Tmtk\AwsLoggerBundle\Service;

use Aws\Sdk;

class AwsLogger
{
    /**
     * @var Aws
     */
    protected $aws;

    /**
     * @var string
     */
    protected $logGroup;

    /**
     * @var string
     */
    protected $logStream;

    /**
     * @var array
     */
    protected $events = [];

    public function __construct(Aws $aws, string $logGroup, string $logStream)
    {
        $this->aws = $aws;
        $this->logGroup = $logGroup;
        $this->logStream = $logStream;
    }

    public function setSdk(Sdk $sdk): void
    {
        $this->aws->setSdk($sdk);
    }

    public function addEvent(array $event): void
    {
        $this->events[] = [
            'message' => json_encode($event),
            'timestamp' => round(microtime(true) * 1000)
        ];
    }

    public function logEvents(): void
    {
        try {
            if (!empty($this->events)) {
                $this->aws->putLogEvents($this->events, $this->logGroup, $this->logStream);
            }
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->events = [];
        }
    }
}