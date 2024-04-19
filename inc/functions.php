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
function handleServerDelete(int $serverId, string $token, string $link): string
{
    $url = sprintf('%s/%d', $link, $serverId);

    $options = [
        'http' => [
            'header'  => "Authorization: Bearer $token\r\n" .
                "Content-Type: application/json\r\n",
            'method'  => 'DELETE'
        ]
    ];

    $context = stream_context_create($options);

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
    $url = sprintf('%s/%d/actions', $link, $serverId);

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                "Authorization: Bearer $token\r\n",
            'method'  => 'POST',
            'content' => json_encode(['type' => 'reboot'])
        ]
    ];

    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

/**
 * Отображает список серверов.
 *
 * @param string $token Токен для аутентификации.
 * @param string $link Ссылка на API сервера.
 * @return string Результат запроса.
 */
function handleServerListRequest(string $token, string $link): string
{
    if (empty($token) || empty($link)) {
        return 'Не указан токен или ссылка на API.';
    }

    $response = file_get_contents($link, false, stream_context_create([
        'http' => [
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer $token\r\n",
        ],
    ]));

    $responseArray = json_decode($response, true) ?: [];

    $message = '';
    foreach ($responseArray['reglets'] as $server) {
        $message .= "ID: {$server['id']}\n";
        $message .= "Имя сервера: {$server['name']}\n";
        $message .= "Статус: {$server['status']}\n";
        $message .= "IP-адрес: {$server['ip']}\n\n";
    }

    return $message ?: 'Не удалось получить список серверов.';
}

/**
 * Обрабатывает стандартное сообщение.
 *
 * @param Client $bot Объект клиента Telegram Bot API.
 * @param Update $update Объект обновления Telegram.
 */
function handleDefaultMessage(Client $bot, Update $update): void
{
    $message = $update->getMessage();
    $chatId = $message->getChat()->getId();
    $text = $message->getText();
    $bot->sendMessage($chatId, 'Ваше сообщение: ' . $text);
}

/**
 * Возвращает клавиатуру в зависимости от типа действия.
 *
 * @param string $typeAction Тип действия.
 * @param int|null $serverId ID сервера (опционально).
 * @param string|null $variableAction Дополнительная переменная действия (опционально).
 * @return InlineKeyboardMarkup|null Клавиатура или null, если тип действия неизвестен.
 */
function getKeyboard($typeAction, ?int $serverId = null, ?string $variableAction = null): ?InlineKeyboardMarkup
{
    switch ($typeAction) {
        case 'DeleteOrReloadServer':
            return new InlineKeyboardMarkup([
                [['text' => "🔄 Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                ['text' => "❌ Удалить", 'callback_data' => "delete_server_$serverId"]]
            ]);

        case 'ConfirmOrCancel':
            return new InlineKeyboardMarkup([
                [['text' => "✅ Да", 'callback_data' => "confirm_{$variableAction}_server_$serverId"],
                ['text' => "❌ Нет", 'callback_data' => "cancel_delete_server_$serverId"]]
            ]);

        default:
        case 'AllServers':
            return new InlineKeyboardMarkup([
                [['text' => "Все сервера", 'callback_data' => 'all_servers']]
            ]);
    }
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
    $keyboard = getKeyboard('AllServers');
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
        $keyboard = getKeyboard('DeleteOrReloadServer', $serverId);
        $bot->sendMessage($chatId, $server, null, false, null, $keyboard);
    }
}

/**
 * Запрашивает подтверждение на перезагрузку выбранного сервера.
 *
 * @param Client $bot          Объект клиента для редактирования сообщений
 * @param string $serverList   Список серверов в формате строки
 * @param int    $serverId     Идентификатор сервера, который требуется перезагрузить
 * @param int    $chatId       Идентификатор чата, где отображается сообщение с запросом
 * @param int    $idMessage    Идентификатор сообщения, которое нужно отредактировать
 *
 * @return void
 */
function reloadServerChecked(Client $bot, string $serverList, int $serverId, int $chatId, int $idMessage)
{
    preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
    $serverName = $matches[1] ?? 'не найден';
    $keyboard = getKeyboard('ConfirmOrCancel', $serverId, 'reload');
    $bot->editMessageText($chatId, $idMessage, 'Точно хотите перезагрузить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
}

/**
 * Запрашивает подтверждение на удаление выбранного сервера.
 *
 * @param Client $bot          Объект клиента для редактирования сообщений
 * @param string $serverList   Список серверов в формате строки
 * @param int    $serverId     Идентификатор сервера, который требуется перезагрузить
 * @param int    $chatId       Идентификатор чата, где отображается сообщение с запросом
 * @param int    $idMessage    Идентификатор сообщения, которое нужно отредактировать
 *
 * @return void
 */
function deleteServerChecked(Client $bot, string $serverList, int $serverId, int $chatId, int $idMessage)
{
    preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
    $serverName = $matches[1] ?? 'не найден';
    $keyboard = getKeyboard('ConfirmOrCancel', $serverId, 'delete');
    $bot->editMessageText($chatId, $idMessage, 'Вы точно хотите удалить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
}

/**
 * Подтверждает действие с сервером.
 *
 * @param Client $bot Объект клиента для работы с API бота.
 * @param string $serverList Список серверов в формате строки.
 * @param int $serverId Идентификатор сервера.
 * @param int $chatId Идентификатор чата.
 * @param int $idMessage Идентификатор сообщения.
 * @param string $type Тип действия ('delete' для удаления, иначе для перезагрузки).
 * @return void
 */
function confirmServerAction(Client $bot, string $serverList, int $serverId, int $chatId, int $idMessage, string $type)
{
    preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
    $serverName = $matches[1] ?? 'не найден';
    $message = ($type == 'delete') ? 'Удаляем сервер' : 'Начинаем перезагружать сервер';
    $keyboard = getKeyboard('AllServers');

    $bot->editMessageText($chatId, $idMessage, $message . ': ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
    if ($type == 'delete') {
        // handleServerDelete($serverId, TOKEN_REG_RU, URL);
        return;
    }
    // handleServerReboot($serverId, TOKEN_REG_RU, URL);
}

/**
 * Отменяет действия с сервером и отображает доступные действия.
 *
 * @param Client $bot Объект клиента для работы с API бота.
 * @param int $serverId Идентификатор сервера.
 * @param int $chatId Идентификатор чата.
 * @param int $idMessage Идентификатор сообщения.
 * @param string $serverList Список серверов в формате строки.
 * @return void
 */
function canceledServerActions(Client $bot, int $serverId, int $chatId, int $idMessage, string $serverList)
{
    $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
    $keyboard = getKeyboard('DeleteOrReloadServer', $serverId);
    $bot->editMessageText($chatId, $idMessage, reset($serverInfo), null, false, $keyboard);
}