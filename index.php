<?php
require_once "vendor/autoload.php";
require_once "inc/functions.php";
const TOKEN_REG_RU = '847ff2a5ad1a03f4a61c33c4e7afad8db2a051eca0b7a25ac73d4402d653a09d4e5667f77eaf3268b6881e780142381a';
const TG_BOT_TOKEN = '6953285920:AAHC5E5ejYrQ9Tu2y-pQEKPn0zzVPB61sK0';
const URL = 'https://api.cloudvps.reg.ru/v1/reglets';

try {
    $bot = new \TelegramBot\Api\Client(TG_BOT_TOKEN);

    $bot->command('start', function ($message) use ($bot) {
        $toMessage = 'Привет. Бот для управления своими серверами Reg.Ru.';
        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
            [
                ['text' => "Все сервера", 'callback_data' => 'all_servers']
            ]
        ]);
        $bot->sendMessage($message->getChat()->getId(), $toMessage, null, false, null, $keyboard);
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
                        //preg_match('/ID: (\d+)/', $server, $matches);
                        //$serverId = $matches[1] ?? null;
                        $serverId = extractServerIdFromCallbackData($server);

                        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
                            [
                                ['text' => "Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                                ['text' => "Удалить", 'callback_data' => "delete_server_$serverId"],
                            ]
                        ]);
                        $bot->sendMessage($chat_id, $server, null, false, null, $keyboard);
                    }
                } else {
                    // Если только один сервер, отправляем его в единственном сообщении
                    // Извлекаем ID сервера из строки
                    //preg_match('/ID: (\d+)/', $serverList, $matches);
                    //$serverId = $matches[1] ?? null;
                    $serverId = extractServerIdFromCallbackData($serverList);

                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
                        [
                            ['text' => "Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                            ['text' => "Удалить", 'callback_data' => "delete_server_$serverId"]
                        ]
                    ]);
                    $bot->editMessageText($chat_id, $message->getMessageId(), $serverList, null, false, $keyboard);
                }

                break;

            case (bool)preg_match('/^reload_server_(\d+)$/', $callback_data, $matches):
                //$serverId = $matches[1];
                $serverId = extractServerIdFromCallbackData($callback_data);
                // Получаем информацию о серверах
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                // Извлекаем информацию о конкретном сервере
                $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
                // Извлекаем имя сервера
                preg_match('/Имя сервера: (.+)/', reset($serverInfo), $names);
                $serverName = $names[1] ?? 'не найден';

                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
                    [
                        ['text' => "Все сервера", 'callback_data' => 'all_servers']
                    ]
                ]);
                $bot->sendMessage($chat_id, 'Начинаем перезагружать сервер: ' . $serverName, null, false, null, $keyboard);
                handleServerReboot($serverId, TOKEN_REG_RU, URL);
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

                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
                    [
                        ['text' => "Все сервера", 'callback_data' => 'all_servers']
                    ]
                ]);
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Удаляем сервер: ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
                handleServerDeleteRequest($serverId, TOKEN_REG_RU, URL);
                break;
            default:
                $bot->sendMessage($chat_id, 'Неизвестная опция');
        }
    });

    $bot->on(function (\TelegramBot\Api\Types\Update $update) use ($bot) {
        $message = $update->getMessage();
        $id = $message->getChat()->getId();
        $bot->sendMessage($id, 'Ваше сообщение: ' . $message->getText());
    }, function () {
        return true;
    });

    $bot->run();

} catch (\TelegramBot\Api\Exception $e) {
    $e->getMessage();
}
