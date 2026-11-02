<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CommentData
{
    public function __construct(
        public string $content,
    ) {
        if (trim($this->content) === '') {
            throw new \InvalidArgumentException('Comment content cannot be empty.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $content = isset($payload['content']) ? trim((string) $payload['content']) : '';

        return new self(
            content: $content,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
