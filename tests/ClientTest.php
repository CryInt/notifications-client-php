<?php
declare(strict_types=1);

use CryCMS\Notifications\Client;
use CryCMS\Notifications\DTO\MessageSMTP;
use CryCMS\Notifications\DTO\MessageTelegram;
use CryCMS\Notifications\DTO\Queue;
use CryCMS\Notifications\DTO\QueueMessage;
use CryCMS\Notifications\DTO\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected const SERVER_SMTP = 'fake-email';
    protected const SERVER_TELEGRAM = 'notifications-telegram';

    protected $client;

    public function setUp(): void
    {
        parent::setUp();

        $host = self::getENV('NOTIFICATIONS_HOST');
        $clientPrefix = self::getENV('NOTIFICATIONS_CLIENT_PREFIX');
        $apiKey = self::getENV('NOTIFICATIONS_API_KEY');

        $this->client = new Client($host, $clientPrefix, $apiKey);
        $this->client->setInstance('INSTANCE-TEST');
    }

    public function testErrorResult(): void
    {
        $host = self::getENV('NOTIFICATIONS_HOST');
        $clientPrefix = self::getENV('NOTIFICATIONS_CLIENT_PREFIX');

        $client = new Client($host, $clientPrefix, 'bad key');
        $result = $client->ping();
        $this->assertFalse($result);
        $this->assertEquals('Forbidden', $client->getError());
    }

    public function testPing(): void
    {
        $result = $this->client->ping();
        $this->assertTrue($result);
    }

    public function testGetServerList(): void
    {
        $result = $this->client->getServerList();
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testSendMessageSMTP(): ?int
    {
        $message = new MessageSMTP();
        $message->recipient = 'cry.int@gmail.com';
        $message->subject = 'TEST';
        $message->content = date('Y-m-d H:i:s');

        $result = $this->client->send(self::SERVER_SMTP, $message);
        $this->assertNotNull($result);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotEmpty($result->queueId);
        $this->assertNotEmpty($result->messageId);

        return $result->messageId;
    }

    /*
    public function testSendMessageTelegram(): void
    {
        $message = new MessageTelegram();
        $message->recipient = '409980849';
        $message->content = date('Y-m-d H:i:s') . ' [SEND IN QUEUE]';

        $result = $this->client->send(self::SERVER_TELEGRAM, $message);
        $this->assertNotNull($result);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotEmpty($result->queueId);
        $this->assertNotEmpty($result->messageId);
    }

    public function testSendMessageTelegramDirect(): void
    {
        $message = new MessageTelegram();
        $message->recipient = '409980849';
        $message->content = date('Y-m-d H:i:s') . ' [SEND DIRECT MESSAGE]';

        $result = $this->client->send(self::SERVER_TELEGRAM, $message, true);
        $this->assertNotNull($result);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotEmpty($result->queueId);
        $this->assertNotEmpty($result->messageId);
        $this->assertTrue($result->directSent);
    }
    */

    /**
     * @depends testSendMessageSMTP
     */
    public function testGetMessageInfo(?int $messageId): void
    {
        $messageInfo = $this->client->getMessageInfo(self::SERVER_SMTP, $messageId, true);

        $this->assertNotNull($messageInfo);
        $this->assertEquals($messageId, $messageInfo->messageId);
        $this->assertEquals(self::SERVER_SMTP, $messageInfo->server);
        $this->assertEquals('cry.int@gmail.com', $messageInfo->recipient);
        $this->assertEquals('New',  $messageInfo->status);
        $this->assertNotEmpty($messageInfo->content);
        $this->assertNull($messageInfo->error);
    }

    public function testQueue(): void
    {
        $queue = $this->client->getQueue(0, 1);

        $this->assertNotNull($queue);
        $this->assertIsArray($queue->list);
        $this->assertNotEmpty($queue->count);

        foreach ($queue->list as $queue) {
            $this->assertInstanceOf(Queue::class, $queue);
            $this->assertNotEmpty($queue->queueId);
            $this->assertNotEmpty($queue->message);
            $this->assertInstanceOf(QueueMessage::class, $queue->message);
            $this->assertNotEmpty($queue->message->id);
        }
    }

    protected static function getENV(string $key): ?string
    {
        if (isset($_ENV) && array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (!empty($envHost = getenv($key))) {
            return $envHost;
        }

        return null;
    }
}