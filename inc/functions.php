<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Update;

/**
 * Обрабатывает запрос на удаление сервера.
 *
 * @param int $serverId Идентификатор сервера для удаления.
 * @param string $token Токен для аутентификации.
 * @param string $link Ссылка на API сервера.
 * @return string Результат запроса на удаление.
 */
function handleServerDeleteRequest(int $serverId, string $token, string $link): string
{
    $url = $link . '/' . $serverId;
    $options = [
        'http' => [
            'header'  => "Authorization: Bearer $token\r\n" .
                "Content-Type: application/json\r\n",
            'method'  => 'DELETE'
        ]
    ];

    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

/**
 * Обрабатывает запрос на перезагрузку сервера.
 *
 * @param int $serverId Идентификатор сервера для перезагрузки.
 * @param string $token Токен для аутентификации.
 * @param string $link Ссылка на API сервера.
 * @return string Результат запроса на перезагрузку.
 */
function handleServerReboot(int $serverId, string $token, string $link): string
{
    $url = $link . '/' . $serverId . '/actions';
    $data = json_encode(['type' => 'reboot']);

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                "Authorization: Bearer $token\r\n",
            'method'  => 'POST',
            'content' => $data
        ]
    ];

    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

/**
 * Обрабатывает запрос на перезагрузку сервера.
 *
 * @param string $token Токен для аутентификации.
 * @param string $link Ссылка на API сервера.
 * @return string Результат запроса на перезагрузку.
 */
function handleServerListRequest(string $token, string $link): string
{
    if (empty($token) || empty($link)) {
        return 'Не указан токен или ссылка на API.';
    }

    $response = file_get_contents($link, false, stream_context_create([
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                "Authorization: Bearer $token\r\n"
        ]
    ]));

    $responseArray = json_decode($response, true);

    if (isset($responseArray['reglets'])) {
        $serverList = $responseArray['reglets'];
        $message = '';
        foreach ($serverList as $server) {
            $message .= "ID: {$server['id']}\n";
            $message .= "Имя сервера: {$server['name']}\n";
            $message .= "Статус: {$server['status']}\n";
            $message .= "IP-адрес: {$server['ip']}\n\n";
        }
        return $message;
    } else {
        return 'Не удалось получить список серверов.';
    }
}

/**
 * Извлекает идентификатор сервера из данных обратного вызова.
 *
 * @param string $callback_data Данные обратного вызова.
 * @return string|null Идентификатор сервера или null, если не удалось извлечь.
 */
function extractServerIdFromCallbackData(string $callback_data) {
    preg_match('/_(\d+)$/', $callback_data, $matches);
    return $matches[1] ?? null;
}

/**
 * Обрабатывает стандартное сообщение.
 *
 * @param Client $bot Объект клиента Telegram Bot API.
 * @param Update $update Объект обновления Telegram.
 */
function handleDefaultMessage(Client $bot, Update $update)
{
    $message = $update->getMessage();
    $chatId = $message->getChat()->getId();
    $text = $message->getText();
    $bot->sendMessage($chatId, 'Ваше сообщение: ' . $text);
}

/**
 * Отправляет стартовое сообщение с клавиатурой.
 *
 * @param Client $bot Объект клиента Telegram Bot API.
 * @param mixed $message Сообщение, на основе которого будет отправлен ответ.
 */
function sendStartMessage(Client $bot, $message)
{
    $toMessage = 'Привет. Бот для управления своими серверами Reg.Ru.';
    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
        [
            ['text' => "Все сервера", 'callback_data' => 'all_servers']
        ]
    ]);
    $bot->sendMessage($message->getChat()->getId(), $toMessage, null, false, null, $keyboard);
}