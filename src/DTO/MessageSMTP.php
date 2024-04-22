<?php
namespace CryCMS\Notifications\DTO;

class MessageSMTP extends Message
{
    public $recipient;
    public $subject;
    public $content;
}