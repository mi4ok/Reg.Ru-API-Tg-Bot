<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Update;

require_once "vendor/autoload.php";
require_once "inc/functions.php";
require_once "inc/config.php";


try {
    $bot = new Client(TG_BOT_TOKEN);

    $bot->command('start', function ($message) use ($bot) {
        sendStartMessage($bot, $message);
    });

    $bot->callbackQuery(function (\TelegramBot\Api\Types\CallbackQuery $callback) use ($bot) {
        $message = $callback->getMessage();
        $chat_id = $message->getChat()->getId();
        $callback_data = $callback->getData();

        switch ($callback_data) {
            case 'all_servers':
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                if (substr_count($serverList, 'ID:') > 1) {
                    // Если количество серверов больше одного, отправляем каждый сервер в отдельном сообщении
                    $servers = explode("\n\n", $serverList);
                    foreach ($servers as $server) {
                        // Извлекаем ID сервера из строки
                        preg_match('/ID: (\d+)/', $server, $matches);
                        $serverId = $matches[1] ?? null;

                        $keyboard = new InlineKeyboardMarkup([
                            [
                                ['text' => "🔄 Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                                ['text' => "❌ Удалить", 'callback_data' => "delete_server_$serverId"],
                            ]
                        ]);
                        $bot->sendMessage($chat_id, $server, null, false, null, $keyboard);
                    }
                } else {
                    // Если только один сервер, отправляем его в единственном сообщении
                    // Извлекаем ID сервера из строки
                    preg_match('/ID: (\d+)/', $serverList, $matches);
                    $serverId = $matches[1] ?? null;

                    $keyboard = new InlineKeyboardMarkup([
                        [
                            ['text' => "🔄 Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                            ['text' => "❌ Удалить", 'callback_data' => "delete_server_$serverId"]
                        ]
                    ]);
                    $bot->editMessageText($chat_id, $message->getMessageId(), $serverList, null, false, $keyboard);
                }

                break;
            case (bool)preg_match('/^reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                // Получаем информацию о серверах
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
                // Извлекаем имя сервера
                preg_match('/Имя сервера: (.+)/', reset($serverInfo), $names);
                $serverName = $names[1] ?? 'не найден';

                $keyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => "✅ Да", 'callback_data' => "confirm_reload_server_$serverId"],
                        ['text' => "❌ Нет", 'callback_data' => "cancel_delete_server_$serverId"],
                    ]
                ]);
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Точно хотите перезагрузить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
                break;
            case (bool)preg_match('/^delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                // Получаем информацию о серверах
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
                // Извлекаем имя сервера
                preg_match('/Имя сервера: (.+)/', reset($serverInfo), $names);
                $serverName = $names[1] ?? 'не найден';

                $keyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => "✅ Да", 'callback_data' => "confirm_delete_server_$serverId"],
                        ['text' => "❌ Нет", 'callback_data' => "cancel_delete_server_$serverId"],
                    ]
                ]);
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Вы точно хотите удалить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
                handleServerDeleteRequest($serverId, TOKEN_REG_RU, URL);
                break;
            case (bool)preg_match('/^confirm_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                // Получаем информацию о серверах
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
                // Извлекаем имя сервера
                preg_match('/Имя сервера: (.+)/', reset($serverInfo), $names);
                $serverName = $names[1] ?? 'не найден';

                $keyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => "Все сервера", 'callback_data' => 'all_servers']
                    ]
                ]);
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Удаляем сервер: ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
                //handleServerDeleteRequest($serverId, TOKEN_REG_RU, URL);
                break;
            case (bool)preg_match('/^cancel_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));

                $keyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => "🔄 Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                        ['text' => "❌ Удалить", 'callback_data' => "delete_server_$serverId"]
                    ]
                ]);

                // Отправляем информацию только о выбранном сервере
                $bot->editMessageText($chat_id, $message->getMessageId(), reset($serverInfo), null, false, $keyboard);
                break;
            case (bool)preg_match('/^confirm_reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                // Получаем информацию о серверах
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
                // Извлекаем имя сервера
                preg_match('/Имя сервера: (.+)/', reset($serverInfo), $names);
                $serverName = $names[1] ?? 'не найден';

                $keyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => "Все сервера", 'callback_data' => 'all_servers']
                    ]
                ]);
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Начинаем перезагружать сервер: ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
                handleServerReboot($serverId, TOKEN_REG_RU, URL);
                break;

            default:
                $bot->sendMessage($chat_id, 'Неизвестная опция' . $callback_data);
        }
    });

    $bot->on(function (Update $update) use ($bot) {
        handleDefaultMessage($bot, $update);
    }, function () {
        return true;
    });

    $bot->run();

} catch (\TelegramBot\Api\Exception $e) {
    $error_message = $e->getMessage();
    error_log($error_message);
}