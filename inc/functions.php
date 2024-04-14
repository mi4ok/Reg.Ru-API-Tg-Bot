<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Update;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

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

/**
 * Отправляет список серверов в виде сообщений с кнопками действий.
 *
 * @param Client $bot       Объект клиента для отправки сообщений
 * @param int    $chatId    Идентификатор чата, куда отправляется список серверов
 * @param string $serverList Список серверов в формате строки
 *
 * @return void
 */
function pushServersList(Client $bot, int $chatId, string $serverList)
{
    $servers = explode("\n\n", $serverList);
    foreach ($servers as $server) {
        preg_match('/ID: (\d+)/', $server, $matches);
        $serverId = $matches[1] ?? null;

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => "🔄 Перезагрузить $serverId", 'callback_data' => "reload_server_$serverId"],
                ['text' => "❌ Удалить $serverId", 'callback_data' => "delete_server_$serverId"]
            ]
        ]);

        $bot->sendMessage($chatId, $server, null, false, null, $keyboard);
    }
}