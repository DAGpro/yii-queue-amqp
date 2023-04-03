<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Queue\AMQP\Tests\Unit;

use Exception;
use InvalidArgumentException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use Yiisoft\Yii\Queue\AMQP\Adapter;
use Yiisoft\Yii\Queue\AMQP\Exception\NoKeyInPayloadException;
use Yiisoft\Yii\Queue\AMQP\MessageSerializer;
use Yiisoft\Yii\Queue\AMQP\QueueProvider;
use Yiisoft\Yii\Queue\AMQP\Settings\Exchange as ExchangeSettings;
use Yiisoft\Yii\Queue\AMQP\Settings\Queue as QueueSettings;
use Yiisoft\Yii\Queue\AMQP\Tests\Integration\TestCase;

/**
 * Testing message serialization options
 */
final class MessageSerializerTest extends TestCase
{
    /**
     * Publishing a message using AMQPLib
     *
     * @param string $queue
     * @param string $exchange
     * @param AMQPMessage $message
     *
     * @throws Exception
     */
    private function publishWithAMQPLib(string $queue, string $exchange, AMQPMessage $message): void
    {
        $channel = $this
            ->createConnection()
            ->channel();
        $channel->queue_declare($queue);
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT);

        $channel->queue_bind($queue, $exchange);

        $channel->basic_publish($message, $exchange);
    }

    /**
     * @param string $queueExchangeName
     *
     * @throws Exception
     *
     * @return Adapter
     */
    private function getCustomAdapter(string $queueExchangeName): Adapter
    {
        $queueProvider = new QueueProvider(
            $this->createConnection(),
            $this->getQueueSettings(),
        );
        return new Adapter(
            $queueProvider
                ->withQueueSettings(new QueueSettings($queueExchangeName))
                ->withExchangeSettings(new ExchangeSettings($queueExchangeName)),
            new MessageSerializer(),
            $this->getLoop(),
        );
    }

    public function testNoKeyInPayloadExceptionName(): void
    {
        $queueExchangeName = 'yii-test-no-key-in-payload-exception-name';
        $this->publishWithAMQPLib(
            $queueExchangeName,
            $queueExchangeName,
            new AMQPMessage(
                json_encode(['test'], JSON_THROW_ON_ERROR),
                ['content_type' => 'text/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            )
        );

        $queue = $this
            ->getQueue()
            ->withAdapter($this->getCustomAdapter($queueExchangeName));

        $this->expectException(NoKeyInPayloadException::class);
        $queue->run();
    }

    public function testNoKeyInPayloadExceptionId(): void
    {
        $queueExchangeName = 'yii-test-no-key-in-payload-exception-id';
        $this->publishWithAMQPLib(
            $queueExchangeName,
            $queueExchangeName,
            new AMQPMessage(
                json_encode(['name' => 'ext-simple', 'id' => 1], JSON_THROW_ON_ERROR),
                ['content_type' => 'text/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            )
        );

        $queue = $this
            ->getQueue()
            ->withAdapter($this->getCustomAdapter($queueExchangeName));

        $this->expectException(NoKeyInPayloadException::class);
        $queue->run();
    }

    public function testNoKeyInPayloadExceptionMeta(): void
    {
        $queueExchangeName = 'yii-test-no-key-in-payload-exception-meta';
        $this->publishWithAMQPLib(
            $queueExchangeName,
            $queueExchangeName,
            new AMQPMessage(
                json_encode(['name' => 'ext-simple', 'meta' => ''], JSON_THROW_ON_ERROR),
                ['content_type' => 'text/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            )
        );

        $queue = $this
            ->getQueue()
            ->withAdapter($this->getCustomAdapter($queueExchangeName));

        $this->expectException(NoKeyInPayloadException::class);
        $queue->run();
    }

    public function testInvalidArgumentException(): void
    {
        $queueExchangeName = 'yii-test-invalid-argument-exception';
        $this->publishWithAMQPLib(
            $queueExchangeName,
            $queueExchangeName,
            new AMQPMessage(
                json_encode('test', JSON_THROW_ON_ERROR),
                ['content_type' => 'text/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            )
        );

        $queue = $this
            ->getQueue()
            ->withAdapter($this->getCustomAdapter($queueExchangeName));

        $this->expectException(InvalidArgumentException::class);
        $queue->run();
    }
}
