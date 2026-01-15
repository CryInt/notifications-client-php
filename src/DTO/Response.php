<?php
namespace CryCMS\Notifications\DTO;

class Response
{
    public $directSent = false;
    public $queueId;
    public $messageId;
    public $raw;
}