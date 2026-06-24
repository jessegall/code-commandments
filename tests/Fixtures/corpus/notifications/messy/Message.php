<?php

namespace App\Notifications;

// mutable array-bag "value object" with no types
class Message
{
    public $recipient;
    public $subject;
    public $body;
    public $type;

    public function __construct(array $data)
    {
        $this->recipient = $data['recipient'] ?? null;
        $this->subject = $data['subject'] ?? '';
        $this->body = $data['body'] ?? '';
        $this->type = $data['type'] ?? 'email';
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return compact('recipient', 'subject', 'body', 'type');
    }
}
