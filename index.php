<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\CallbackQuery;
use TelegramBot\Api\Types\Update;

require_once "vendor/autoload.php";
require_once "inc/functions.php";
require_once "inc/config.php";
require_once "inc/ServerManager.php";

try {
    $bot = new Client(TG_BOT_TOKEN);
    $serverManager = new ServerManager(TOKEN_REG_RU, URL, $bot);

    $bot->command('start', function ($message) use ($serverManager) {
        $serverManager->sendStartMessage($message);
    });

    $bot->callbackQuery(function (CallbackQuery $callback) use ($serverManager, $bot) {
        $message = $callback->getMessage();
        $idMessage = $message->getMessageId();
        $chatId = $message->getChat()->getId();
        $callback_data = $callback->getData();

        switch ($callback_data) {
            case 'all_servers':
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->pushServersList($chatId, $serverList);
                break;

            case 'balance':
                $serverManager->pushBalance($chatId, $idMessage);
                break;

            case (bool)preg_match('/^reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->reloadServerChecked($serverList, $serverId, $chatId, $idMessage);
                break;

            case (bool)preg_match('/^delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->deleteServerChecked($serverList, $serverId, $chatId, $idMessage);
                break;

            case (bool)preg_match('/^confirm_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->confirmServerAction($serverList, $serverId, $chatId, $idMessage, 'delete');
                break;

            case (bool)preg_match('/^confirm_reload_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->confirmServerAction($serverList, $serverId, $chatId, $idMessage, 'reload');
                break;

            case (bool)preg_match('/^cancel_delete_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->canceledServerActions($serverId, $chatId, $idMessage, $serverList);
                break;

            case (bool)preg_match('/^reset_password_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->resetPasswordServerChecked($serverList, $serverId, $chatId, $idMessage);
                break;

            case (bool)preg_match('/^confirm_reset_server_(\d+)$/', $callback_data, $matches):
                $serverId = $matches[1];
                $serverList = $serverManager->handleServerListRequest();
                $serverManager->confirmServerAction($serverList, $serverId, $chatId, $idMessage, 'reset');
                break;

            default:
                $bot->sendMessage($chatId, 'Неизвестная опция: ' . $callback_data);
        }
    });

    $bot->on(function (Update $update) use ($serverManager) {
        $serverManager->handleDefaultMessage($update);
    }, function () {
        return true;
    });

    $bot->run();

} catch (\TelegramBot\Api\Exception $e) {
    $error_message = $e->getMessage();
    error_log($error_message);
}