<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Queue\Driver\AMQP;

use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Yiisoft\Serializer\SerializerInterface;
use Yiisoft\Yii\Queue\Cli\LoopInterface;
use Yiisoft\Yii\Queue\Driver\DriverInterface;
use Yiisoft\Yii\Queue\Enum\JobStatus;
use Yiisoft\Yii\Queue\Job\JobInterface;
use Yiisoft\Yii\Queue\Job\PrioritisedJobInterface;
use Yiisoft\Yii\Queue\MessageInterface;
use Yiisoft\Yii\Queue\Tests\App\DelayableJob;

class Driver implements DriverInterface
{
    protected QueueProviderInterface $queueProvider;
    protected SerializerInterface $serializer;
    protected LoopInterface $loop;

    public function __construct(
        QueueProviderInterface $queueProvider,
        SerializerInterface $serializer,
        LoopInterface $loop
    ) {
        $this->queueProvider = $queueProvider;
        $this->serializer = $serializer;
        $this->loop = $loop;
    }

    /**
     * @inheritDoc
     */
    public function nextMessage(): ?MessageInterface
    {
        $message = null;

        $channel = $this->queueProvider->getChannel();
        $channel->basic_consume(
            $this->queueProvider->getQueueSettings()->getName(),
            '',
            false,
            true,
            false,
            false,
            function (AMQPMessage $amqpMessage) use (&$message): void {
                $message = $this->createMessage($amqpMessage);
            }
        );
        $channel->wait(null, true);

        return $message;
    }

    protected function createMessage(AMQPMessage $message): MessageInterface {
        return new Message($this->serializer->unserialize($message->body));
    }

    /**
     * @inheritDoc
     */
    public function status(string $id): JobStatus
    {
        throw new RuntimeException('Status check is not supported by the driver');
    }

    /**
     * @inheritDoc
     */
    public function push(JobInterface $job): MessageInterface
    {
        $amqpMessage = new AMQPMessage($this->serializer->serialize($job));
        $exchange = $this->queueProvider->getExchangeSettings()->getName();
        $this->queueProvider->getChannel()->basic_publish($amqpMessage, $exchange);

        return new Message($job);
    }

    /**
     * @inheritDoc
     */
    public function subscribe(callable $handler): void
    {
        while ($this->loop->canContinue()) {
            $channel = $this->queueProvider->getChannel();
            $channel->basic_consume(
                $this->queueProvider->getQueueSettings()->getName(),
                '',
                false,
                true,
                false,
                false,
                fn (AMQPMessage $amqpMessage) => $handler($this->createMessage($amqpMessage))
            );

            $channel->wait(null, true);
        }
    }

    /**
     * @inheritDoc
     */
    public function canPush(JobInterface $job): bool
    {
        return !$job instanceof DelayableJob && !$job instanceof PrioritisedJobInterface;
    }
}
