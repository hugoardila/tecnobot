<?php
declare(strict_types=1);

final class OpenAIConfig
{
    public static function getApiKey(): string
    {
        $apiKey = trim((string)getenv('OPENAI_API_KEY'));
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no esta configurada.');
        }

        return $apiKey;
    }

    public static function getModel(): string
    {
        $model = trim((string)getenv('OPENAI_MODEL'));
        return $model !== '' ? $model : 'gpt-4';
    }
}
