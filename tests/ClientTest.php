<?php
declare(strict_types=1);

use CryCMS\Notifications\Client;
use CryCMS\Notifications\DTO\MessageSMTP;
use CryCMS\Notifications\DTO\MessageTelegram;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected const SERVER_SMTP = 'fake-email';
    protected const SERVER_TELEGRAM = 'notifications-telegram';

    protected $client;

    public function setUp(): void
    {
        parent::setUp();

        if (isset($_ENV) && array_key_exists('NOTIFICATIONS_HOST', $_ENV)) {
            $host = $_ENV['NOTIFICATIONS_HOST'];
        }
        elseif (!empty($envHost = getenv('NOTIFICATIONS_HOST'))) {
            $host = $envHost;
        }
        else {
            $host = null;
        }

        if (isset($_ENV) && array_key_exists('NOTIFICATIONS_CLIENT_PREFIX', $_ENV)) {
            $clientPrefix = $_ENV['NOTIFICATIONS_CLIENT_PREFIX'];
        }
        elseif (!empty($envToken = getenv('NOTIFICATIONS_CLIENT_PREFIX'))) {
            $clientPrefix = $envToken;
        }
        else {
            $clientPrefix = null;
        }

        if (isset($_ENV) && array_key_exists('NOTIFICATIONS_API_KEY', $_ENV)) {
            $apiKey = $_ENV['NOTIFICATIONS_API_KEY'];
        }
        elseif (!empty($envToken = getenv('NOTIFICATIONS_API_KEY'))) {
            $apiKey = $envToken;
        }
        else {
            $apiKey = null;
        }

        $this->client = new Client($host, $clientPrefix, $apiKey);
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

    public function testSendMessageSMTP(): void
    {
        $message = new MessageSMTP();
        $message->recipient = 'cry.int@gmail.com';
        $message->subject = 'TEST';
        $message->content = date('Y-m-d H:i:s');

        $result = $this->client->send(self::SERVER_SMTP, $message);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('queue_id', $result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertNotEmpty($result['queue_id']);
        $this->assertNotEmpty($result['message_id']);
    }

    public function testSendMessageTelegram(): void
    {
        $message = new MessageTelegram();
        $message->recipient = '409980849';
        $message->content = date('Y-m-d H:i:s');

        $result = $this->client->send(self::SERVER_TELEGRAM, $message);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('queue_id', $result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertNotEmpty($result['queue_id']);
        $this->assertNotEmpty($result['message_id']);
    }

    public function testSendMessageTelegramDirect(): void
    {
        $message = new MessageTelegram();
        $message->recipient = '409980849';
        $message->content = date('Y-m-d H:i:s');

        $result = $this->client->send(self::SERVER_TELEGRAM, $message, true);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('directSent', $result);
        $this->assertArrayHasKey('queue_id', $result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertEquals('success', $result['directSent']);
        $this->assertNotEmpty($result['queue_id']);
        $this->assertNotEmpty($result['message_id']);
    }
}