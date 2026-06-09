<?php
/**
 * Класс для работы с Telegram Bot API
 */

if (!defined('FLOWER_SHOP')) {
    die('Прямой доступ запрещён');
}

class TelegramApi
{
    private string $token;

    public function __construct(string $token = '')
    {
        $this->token = $token ?: tgBotToken();
    }

    /**
     * Базовый запрос к API
     */
    public function request(string $method, array $params = []): array
    {
        if (empty($this->token)) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $url = TG_API_URL . $this->token . '/' . $method;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'description' => 'cURL error: ' . $error];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'Invalid JSON response'];
    }

    /**
     * Отправить текстовое сообщение
     */
    public function sendMessage(int $chatId, string $text, array $options = []): array
    {
        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ], $options));
    }

    /**
     * Отправить фото
     */
    public function sendPhoto(int $chatId, string $photoUrl, string $caption = '', array $options = []): array
    {
        return $this->request('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ], $options));
    }

    /**
     * Ответить на callback-запрос
     */
    public function answerCallback(string $callbackId, string $text = '', bool $showAlert = false): array
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }

    /**
     * Редактировать сообщение
     */
    public function editMessage(int $chatId, int $messageId, string $text, array $options = []): array
    {
        return $this->request('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ], $options));
    }

    /**
     * Удалить сообщение
     */
    public function deleteMessage(int $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Установить webhook
     */
    public function setWebhook(string $url): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query']
        ]);
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Информация о webhook
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Информация о боте
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Установить команды бота
     */
    public function setMyCommands(array $commands): array
    {
        return $this->request('setMyCommands', ['commands' => $commands]);
    }
}

/**
 * Глобальный экземпляр API
 */
function tg(): TelegramApi
{
    static $api = null;
    if ($api === null) {
        $api = new TelegramApi();
    }
    return $api;
}

// =====================================================
// Билдеры клавиатур
// =====================================================

/**
 * Inline-клавиатура (кнопки под сообщением)
 */
function tgInlineKeyboard(array $rows): array
{
    return ['inline_keyboard' => $rows];
}

/**
 * Inline-кнопка
 */
function tgButton(string $text, string $callbackData): array
{
    return ['text' => $text, 'callback_data' => $callbackData];
}

/**
 * URL-кнопка
 */
function tgUrlButton(string $text, string $url): array
{
    return ['text' => $text, 'url' => $url];
}

/**
 * Контактная кнопка (запрос телефона)
 */
function tgContactButton(string $text): array
{
    return ['text' => $text, 'request_contact' => true];
}

/**
 * Обычная reply-клавиатура
 */
function tgReplyKeyboard(array $rows, bool $oneTime = false, bool $resize = true): array
{
    return [
        'keyboard' => $rows,
        'resize_keyboard' => $resize,
        'one_time_keyboard' => $oneTime
    ];
}

/**
 * Убрать клавиатуру
 */
function tgRemoveKeyboard(): array
{
    return ['remove_keyboard' => true];
}
