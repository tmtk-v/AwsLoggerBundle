<?php

namespace Tmtk\AwsLoggerBundle\Service;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\Sdk;
use GuzzleHttp\Promise;

class Aws
{
    /**
     * @var Sdk
     */
    protected $sdk;

    /**
     * @var string
     */
    protected $accessKeyId;

    /**
     * @var string
     */
    protected $secretAccessKey;

    public function __construct(string $accessKeyId, string $secretAccessKey)
    {
        $this->accessKeyId = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
    }

    public function setSdk(Sdk $sdk): void
    {
        $this->sdk = $sdk;
    }

    public function createSdk(): Sdk
    {
        $config = [
            'version' => 'latest',
            'region' => 'eu-west-1'
        ];

        if ($this->accessKeyId && $this->secretAccessKey) {
            $config['credentials'] = function () {
                return Promise\Create::promiseFor(
                    new Credentials($this->accessKeyId, $this->secretAccessKey)
                );
            };
        }

        return new Sdk($config);
    }

    public function putLogEvents(array $events, string $group, string $stream): void
    {
        if (!isset($this->sdk)) {
            $this->sdk = $this->createSdk();
        }

        $client = $this->sdk->createCloudWatchLogs();

        $args = [
            'logEvents' => $events,
            'logGroupName' => $group,
            'logStreamName' => $stream
        ];

        $sequenceToken = $this->getUploadSequenceToken($group, $stream, $client);
        if ($sequenceToken) {
            $args['sequenceToken'] = $sequenceToken;
        }

        $client->putLogEvents($args);
    }

    protected function getUploadSequenceToken(string $group, string $stream, CloudWatchLogsClient $client): string
    {
        $result = $client->describeLogStreams([
            'logGroupName' => $group
        ]);

        foreach ($result['logStreams'] as $streamInfo) {
            if ($streamInfo['logStreamName'] == $stream && isset($streamInfo['uploadSequenceToken'])) {
                return $streamInfo['uploadSequenceToken'];
            }
        }

        return '';
    }
}