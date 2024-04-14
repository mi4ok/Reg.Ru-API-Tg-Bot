<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\CallbackQuery;
use TelegramBot\Api\Types\Update;

require_once "vendor/autoload.php";
require_once "inc/functions.php";
require_once "inc/config.php";

try {
    $bot = new Client(TG_BOT_TOKEN);

    $bot->command('start', function ($message) use ($bot) {
        sendStartMessage($bot, $message);
    });

    $bot->callbackQuery(function (CallbackQuery $callback) use ($bot) {
        $message = $callback->getMessage();
        $idMessage = $message->getMessageId();
        $chatId = $message->getChat()->getId();
        $callback_data = $callback->getData();

        switch ($callback_data) {
            case 'all_servers':
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                pushServersList($bot, $chatId, $serverList);
                break;

            case (bool)preg_match('/^reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                reloadServerChecked($bot, $serverList, $serverId, $chatId, $idMessage);
                break;

            case (bool)preg_match('/^delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                deleteServerChecked($bot, $serverList, $serverId, $chatId, $idMessage);
                break;

            case (bool)preg_match('/^confirm_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                confirmServerAction($bot, $serverList, $serverId, $chatId, $idMessage, 'delete');
                break;

            case (bool)preg_match('/^confirm_reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                confirmServerAction($bot, $serverList, $serverId, $chatId, $idMessage, 'reload');
                break;

            case (bool)preg_match('/^cancel_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = handleServerListRequest(TOKEN_REG_RU, URL);
                canceledServerActions($bot, $serverId, $chatId, $idMessage, $serverList);

                break;

            default:
                $bot->sendMessage($chatId, 'Неизвестная опция: ' . $callback_data);
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