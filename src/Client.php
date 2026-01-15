<?php
namespace CryCMS\Notifications;

use CryCMS\CURL\CURL;
use CryCMS\Notifications\DTO\Message;
use CryCMS\Notifications\DTO\MessageSMTP;
use CryCMS\Notifications\DTO\MessageTelegram;
use CryCMS\Notifications\DTO\MessageGreenAPI;
use CryCMS\Notifications\DTO\Response;
use CryCMS\Notifications\Exception\SendException;
use JsonException;

class Client
{
    protected $host;
    protected $clientPrefix;
    protected $apiKey;

    protected const COMPOSER_FILE = __DIR__ . '/../composer.json';

    protected const METHOD_PING = '/api/?ping';
    protected const METHOD_SERVER_LIST = '/api/?serversList';
    protected const METHOD_MESSAGE_SEND = '/api/?messageSend';
    protected const METHOD_DIRECT_SEND = '/api/?directSend';

    protected $error;

    protected $version;

    public function __construct(string $host, string $clientPrefix, string $apiKey)
    {
        $this->host = $host;
        $this->clientPrefix = $clientPrefix;
        $this->apiKey = $apiKey;

        $this->version = $this->getClientVersion();
    }

    protected function getClientVersion(): ?string
    {
        if (file_exists(self::COMPOSER_FILE)) {
            $composerContent = file_get_contents(self::COMPOSER_FILE);
            if (self::isJson($composerContent)) {
                try {
                    $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);
                    if (!empty($composerData['version'])) {
                        if (mb_strpos($composerData['version'], 'v', 0, 'UTF-8') !== 0) {
                            $composerData['version'] = 'v' . $composerData['version'];
                        }

                        return $composerData['version'];
                    }
                }
                catch (JsonException $exception) {

                }
            }
        }

        return null;
    }

    public function ping(): bool
    {
        $this->error = null;

        try {
            $response = $this->cUrl($this->host . self::METHOD_PING);
        } catch (SendException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }

        return !empty($response['success']);
    }

    public function getServerList(): ?array
    {
        $this->error = null;

        try {
            $response = $this->cUrl($this->host . self::METHOD_SERVER_LIST);
        } catch (SendException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        return $response['list'] ?? null;
    }

    public function send(string $serverPrefix, Message $message, bool $direct = false, bool $raw = false): ?Response
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
        elseif ($message instanceof MessageGreenAPI) {
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
            $responseCURL = $this->cUrl($this->host . self::METHOD_MESSAGE_SEND, $data, $raw);
            if ($raw) {
                $response = new Response();
                $response->raw = $responseCURL;
                return $response;
            }
        } catch (SendException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        if (!$direct) {
            if (!empty($responseCURL['success'])) {
                $response = new Response();
                $response->queueId = $responseCURL['queue_id'] ?? '';
                $response->messageId = $responseCURL['message_id'] ?? '';
                return $response;
            }

            return null;
        }

        try {
            $responseDirect = $this->cUrl($this->host . self::METHOD_DIRECT_SEND, [
                'server' => $serverPrefix,
                'queue_id' => $responseCURL['queue_id'] ?? '',
                'message_id' => $responseCURL['message_id'] ?? '',
            ]);
        } catch (SendException $exception) {
            $this->error = $exception->getMessage();
            return null;
        }

        if (!empty($responseDirect['success'])) {
            $response = new Response();
            $response->directSent = true;
            $response->queueId = $responseCURL['queue_id'] ?? '';
            $response->messageId = $responseCURL['message_id'] ?? '';
            return $response;
        }

        return null;
    }

    /** @noinspection PhpUnused */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @throws SendException
     */
    protected function cUrl(string $url, array $data = [], $raw = false)
    {
        $response = CURL::post($url)
            ->data($data)
            ->timeout(30)
            ->header('Login', $this->clientPrefix)
            ->header('Version', $this->version ?? '-')
            ->authorizationBearer(md5($this->clientPrefix . ':' . $this->apiKey))
            ->send();

        if ($raw) {
            return $response->body;
        }

        if (!empty($response->body) && self::isJson($response->body)) {
            try {
                $result = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
                if ($result === false) {
                    throw new SendException('Error parse Json', 95);
                }
            } catch (JsonException $e) {
                throw new SendException($e->getMessage(), $e->getCode());
            }

            if (!empty($result['error'])) {
                $error = $result['error'];
                if (!empty($result['details'])) {
                    if (is_array($result['details'])) {
                        foreach ($result['details'] as $field => $detail) {
                            $error .= ' [' . $field . ': ' . $detail . ']';
                        }
                    }
                    else {
                        $error .= ': ' . $result['details'];
                    }
                }

                throw new SendException($error, 95);
            }

            return $result;
        }

        throw new SendException($response->body, $response->httpCode);
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