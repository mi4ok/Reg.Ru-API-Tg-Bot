<?php
require_once "vendor/autoload.php";
const TOKEN_REG_RU = '847ff2a5ad1a03f4a61c33c4e7afad8db2a051eca0b7a25ac73d4402d653a09d4e5667f77eaf3268b6881e780142381a';
function handleServerReboot($serverId, $token) {
    $url = 'https://api.cloudvps.reg.ru/v1/reglets/' . $serverId . '/actions';

    $data = json_encode(['type' => 'reboot']);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function handleServerListRequest($token) {
    $url = 'https://api.cloudvps.reg.ru/v1/reglets';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

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

try {
    $bot = new \TelegramBot\Api\Client('6953285920:AAHC5E5ejYrQ9Tu2y-pQEKPn0zzVPB61sK0');

    $bot->command('ping', function ($message) use ($bot) {
        $serverList = handleServerListRequest();
        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
            [
                ['text' => "Перезагрузить", 'callback_data' => 'reload_servers']
            ]
        ]);
        $bot->sendMessage($message->getChat()->getId(), $serverList, null, false, null, $keyboard);
    });

    $bot->callbackQuery(function (\TelegramBot\Api\Types\CallbackQuery $callback) use ($bot) {
        $message = $callback->getMessage();
        $chat_id = $message->getChat()->getId();
        $callback_data = $callback->getData();

        switch ($callback_data) {
            case 'reload_servers':
                $serverInfo = handleServerListRequest(); // Получаем информацию о сервере
                // Извлекаем ID сервера из строки
                preg_match('/ID: (\d+)/', $serverInfo, $matches);
                preg_match('/Имя сервера: (.+)/', $serverInfo, $names);
                $serverId = $matches[1] ?? 'не найден';
                $serverName = $names[1] ?? 'не найден';
                $bot->editMessageText($chat_id, $message->getMessageId(), 'Начинаем перезагружать сервер: ' . $serverName);
                handleServerReboot($serverId, $token);
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
