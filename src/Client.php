<?php
namespace CryCMS\Notifications;

use CryCMS\Notifications\DTO\MessageSMTP;
use CryCMS\Notifications\DTO\MessageTelegram;
use JsonException;
use RuntimeException;

class Client
{
    protected $host;
    protected $clientPrefix;
    protected $apiKey;

    protected const METHOD_SERVER_LIST = '/api/?serversList';
    protected const METHOD_MESSAGE_SEND = '/api/?messageSend';
    protected const METHOD_DIRECT_SEND = '/api/?directSend';

    protected $error;

    public function __construct(string $host, string $clientPrefix, string $apiKey)
    {
        $this->host = $host;
        $this->clientPrefix = $clientPrefix;
        $this->apiKey = $apiKey;
    }

    public function getServerList(): ?array
    {
        $this->error = null;

        try {
            $response = $this->cUrl($this->host . self::METHOD_SERVER_LIST, []);
        } catch (JsonException|RuntimeException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        return $response['list'] ?? null;
    }

    public function send(string $serverPrefix, $message, bool $direct = false): ?array
    {
        $this->error = null;

        if ($message instanceof MessageSMTP) {
            $data = [
                'server' => $serverPrefix,
                'recipient' => $message->recipient,
                'subject' => $message->subject,
                'content' => $message->content,
            ];
        }
        elseif ($message instanceof MessageTelegram) {
            $data = [
                'server' => $serverPrefix,
                'recipient' => $message->recipient,
                'content' => $message->content,
            ];
        }
        else {
            $this->error = 'Unsupported message type';
            return null;
        }

        try {
            $response = $this->cUrl($this->host . self::METHOD_MESSAGE_SEND, $data);
        } catch (JsonException|RuntimeException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        if (!$direct) {
            if (!empty($response['success'])) {
                return [
                    'queue_id' => $response['queue_id'] ?? '',
                    'message_id' => $response['message_id'] ?? '',
                ];
            }

            return null;
        }

        try {
            $responseDirect = $this->cUrl($this->host . self::METHOD_DIRECT_SEND, ['server' => $serverPrefix] + $response);
        } catch (JsonException|RuntimeException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        if (!empty($responseDirect['success'])) {
            return [
                'directSent' => 'success',
                'queue_id' => $response['queue_id'] ?? '',
                'message_id' => $response['message_id'] ?? '',
            ];
        }

        return null;
    }

    /** @noinspection PhpUnused */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @throws JsonException
     */
    protected function cUrl(string $url, array $data = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 720);
        curl_setopt($ch, CURLOPT_TIMEOUT, 720);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Login: ' . $this->clientPrefix,
            'Authorization: Bearer ' . md5($this->clientPrefix . ':' . $this->apiKey),
        ]);

        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!empty($result) && self::isJson($result)) {
            $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
            if ($result === false) {
                throw new RuntimeException('Error parse Json', 95);
            }

            if (!empty($result['error'])) {
                $error = $result['error'];
                if (!empty($result['details'])) {
                    $error .= ': ' . $result['details'];
                }

                throw new RuntimeException($error, 95);
            }

            return $result;
        }

        throw new RuntimeException($result, $httpCode);
    }

    public static function isJson($string): bool
    {
        if (is_array($string)) {
            return false;
        }

        if (is_object($string)) {
            return false;
        }

        if (is_null($string)) {
            return false;
        }

        $ss = preg_replace('/"(\\.|[^"\\\\])*"/', '', $string);
        if (preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', $ss) === false) {
            return true;
        }

        try {
            $json = json_decode($string, false, 512, JSON_THROW_ON_ERROR);
            return $json && $string !== $json;
        }
        catch (JsonException $exception) {
        }

        return false;
    }
}