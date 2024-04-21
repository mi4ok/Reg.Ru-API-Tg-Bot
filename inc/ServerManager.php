<?php

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Update;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class ServerManager
{
    private $token;
    private $link;
    private $bot;

    /**
     * Создает новый экземпляр ServerManager.
     *
     * @param string $token Токен для доступа к API.
     * @param string $link Ссылка на сервера.
     * @param Client $bot Экземпляр бота Telegram.
     */
    public function __construct(string $token, string $link, Client $bot)
    {
        $this->token = $token;
        $this->link = $link;
        $this->bot = $bot;
    }

    /**
     * Возвращает параметры для HTTP-запроса.
     *
     * @param string $method HTTP-метод.
     * @param array $data Данные для отправки (по умолчанию пустой массив).
     * @return array Массив параметров для HTTP-запроса.
     */
    private function getOptions(string $method, array $data = []): array
    {
        return [
            'http' => [
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->token}\r\n",
                'method' => $method,
                'content' => json_encode($data),
            ],
        ];
    }

    /**
     * Обрабатывает запрос на удаление сервера.
     *
     * @param int $serverId ID сервера для удаления.
     * @throws Exception Если удаление не удалось.
     * @return string Результат запроса на удаление.
     */
    public function handleServerDelete(int $serverId): string
    {
        $url = sprintf('%s/%d', $this->link, $serverId);
        $options = $this->getOptions('DELETE');
        return file_get_contents($url, false, stream_context_create($options));
    }

    /**
     * Обрабатывает запрос на перезагрузку сервера.
     *
     * @param int $serverId ID сервера для перезагрузки.
     * @return string Ответ от сервера после запроса на перезагрузку.
     */
    public function handleServerReboot(int $serverId): string
    {
        $url = sprintf('%s/%d/actions', $this->link, $serverId);
        $options = $this->getOptions('POST', ['type' => 'reboot']);
        return file_get_contents($url, false, stream_context_create($options));
    }

    /**
     * Отправляет запрос на сброс пароля для сервера.
     *
     * @param int $serverId ID сервера для сброса пароля.
     * @return string Содержимое ответа после отправки запроса на сброс пароля.
     */
    public function sendServerPasswordResetRequest(int $serverId): string
    {
        $url = sprintf('%s/%d/actions', $this->link, $serverId);
        $options = $this->getOptions('POST', ['type' => 'password_reset']);
        return file_get_contents($url, false, stream_context_create($options));
    }

    /**
     * Получает список серверов и возвращает его в виде форматированной строки.
     *
     * @return string Форматированный список серверов или сообщение об ошибке, если список не удалось получить.
     */
    public function handleServerListRequest(): string
    {
        $response = file_get_contents($this->link, false, stream_context_create([
            'http' => [
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->token}\r\n",
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
    * Обрабатывает сообщение по умолчанию, полученное ботом.
    *
    * @param Update $update Объект обновления, содержащий информацию о сообщении.
    * @throws Exception Если произошла ошибка при обработке сообщения.
    * @return void
    */
    public function handleDefaultMessage(Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $this->bot->sendMessage($chatId, 'Ваше сообщение: ' . $text);
    }
    
    /**
     * Возвращает InlineKeyboardMarkup на основе указанного typeAction, serverId и variableAction.
     *
     * @param string $typeAction Тип действия для создания клавиатуры.
     * @param int|null $serverId ID сервера (необязательно).
     * @param string|null $variableAction Дополнительное переменное действие (необязательно).
     * @return InlineKeyboardMarkup|null Сгенерированная клавиатура или null, если typeAction неизвестен.
     */
    public function getKeyboard($typeAction, ?int $serverId = null, ?string $variableAction = null): ?InlineKeyboardMarkup
    {
        switch ($typeAction) {
            case 'DeleteOrReloadServer':
                return new InlineKeyboardMarkup([
                    [
                        ['text' => "🔄 Перезагрузить", 'callback_data' => "reload_server_$serverId"],
                        ['text' => "❌ Удалить", 'callback_data' => "delete_server_$serverId"]
                    ],
                    [
                        ['text' => "♻️ Сбросить пароль root", 'callback_data' => "reset_password_server_$serverId"]
                    ]
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
     * Отправляет стартовое сообщение пользователю.
     *
     * @param mixed $message Объект сообщения, из которого извлекается идентификатор чата.
     * @throws Exception Если произошла ошибка при отправке сообщения.
     * @return void
     */
    public function sendStartMessage($message)
    {
        $toMessage = 'Привет. Бот для управления своими серверами Reg.Ru.';
        $keyboard = $this->getKeyboard('AllServers');
        $this->bot->sendMessage($message->getChat()->getId(), $toMessage, null, false, null, $keyboard);
    }

    /**
     * Отправляет список серверов в виде сообщений с клавиатурой в чат.
     *
     * @param int $chatId ID чата, в который будут отправлены серверы.
     * @param string $serverList Список серверов в формате строки
     * "ID: xxx\nName: xxx\nStatus: xxx\nIP: xxx\n\n".
     * @throws Exception Если при отправке сообщений произошла ошибка.
     * @return void
     */
    public function pushServersList(int $chatId, string $serverList)
    {
        $servers = explode("\n\n", $serverList);
        foreach ($servers as $server) {
            preg_match('/ID: (\d+)/', $server, $matches);
            $serverId = $matches[1] ?? null;
            $keyboard = $this->getKeyboard('DeleteOrReloadServer', $serverId);
            $this->bot->sendMessage($chatId, $server, null, false, null, $keyboard);
        }
    }

    /**
     * Сбрасывает пароль сервера и отправляет подтверждение пользователю.
     *
     * @param string $serverList Список серверов в формате строки
     * "ID: xxx\nИмя сервера: xxx\nСтатус: xxx\nIP: xxx\n\n".
     * @param int $serverId ID сервера для сброса пароля.
     * @param int $chatId ID чата, в который будет отправлено подтверждение.
     * @param int $idMessage ID сообщения для редактирования.
     * @throws Exception Если список серверов не может быть пропаршен или сообщение не может быть отредактировано.
     * @return void
     */
    public function resetPasswordServerChecked(string $serverList, int $serverId, int $chatId, int $idMessage)
    {
        preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
        $serverName = $matches[1] ?? 'не найден';
        $keyboard = $this->getKeyboard('ConfirmOrCancel', $serverId, 'reset');
        $this->bot->editMessageText($chatId, $idMessage, 'Точно хотите сбросить пароль Root пользователя? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
    }

    /**
     * Перезагружает сервер и отправляет пользователю сообщение с подтверждением.
     *
     * @param string $serverList Список серверов в формате "ID: xxx\nName: xxx\nStatus: xxx\nIP: xxx\n\n".
     * @param int $serverId ID сервера для перезагрузки.
     * @param int $chatId ID чата, куда будет отправлено подтверждение.
     * @param int $idMessage ID сообщения для редактирования.
     * @throws Exception Если не удается разобрать список серверов или отредактировать сообщение.
     * @return void
     */
    public function reloadServerChecked(string $serverList, int $serverId, int $chatId, int $idMessage)
    {
        preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
        $serverName = $matches[1] ?? 'не найден';
        $keyboard = $this->getKeyboard('ConfirmOrCancel', $serverId, 'reload');
        $this->bot->editMessageText($chatId, $idMessage, 'Точно хотите перезагрузить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
    }

    /**
     * Удаляет сервер и отправляет пользователю сообщение с подтверждением.
     *
     * @param string $serverList Список серверов в формате "ID: xxx\nName: xxx\nStatus: xxx\nIP: xxx\n\n".
     * @param int $serverId ID сервера для удаления.
     * @param int $chatId ID чата, куда будет отправлено подтверждение.
     * @param int $idMessage ID сообщения для редактирования.
     * @throws Exception Если не удается разобрать список серверов или отредактировать сообщение.
     * @return void
     */
    public function deleteServerChecked(string $serverList, int $serverId, int $chatId, int $idMessage)
    {
        preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
        $serverName = $matches[1] ?? 'не найден';
        $keyboard = $this->getKeyboard('ConfirmOrCancel', $serverId, 'delete');
        $this->bot->editMessageText($chatId, $idMessage, 'Вы точно хотите удалить сервер? ' . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, null, false, $keyboard);
    }

    /**
     * Подтверждает действие с сервером путем редактирования сообщения и выполнения соответствующего действия.
     *
     * @param string $serverList Список серверов в формате "ID: xxx\nName: xxx\nStatus: xxx\nIP: xxx\n\n".
     * @param int $serverId ID сервера, над которым будет выполнено действие.
     * @param int $chatId ID чата, куда будет отправлено сообщение с подтверждением.
     * @param int $idMessage ID сообщения для редактирования.
     * @param string $type Тип действия для выполнения. Допустимые значения: 'delete', 'reset' и любое другое значение для действия перезагрузки.
     * @throws Exception Если не удается разобрать список серверов или отредактировать сообщение.
     * @return void
     */
    public function confirmServerAction(string $serverList, int $serverId, int $chatId, int $idMessage, string $type)
    {
        preg_match('/ID: ' . $serverId . '\nИмя сервера: (.+)/', $serverList, $matches);
        $serverName = $matches[1] ?? 'не найден';
        
        switch ($type) {
            case 'delete':
                $message = 'Удаляем сервер:';
                break;
            case 'reset':
                $message = 'Отправляем запрос на сброс пароля сервера.'.PHP_EOL.'`По завершению операции на ваш e-mail будет отправлено письмо с новым root-паролем.`';
                break;
            default:
                $message = 'Начинаем перезагружать сервер:';
                break;
        }
        
        $keyboard = $this->getKeyboard('AllServers');
        $this->bot->editMessageText($chatId, $idMessage, $message . PHP_EOL . $serverName . PHP_EOL . 'ID: ' . $serverId, 'Markdown', false, $keyboard);
        
        switch ($type) {
            case 'delete':
                $this->handleServerDelete($serverId);
                break;
            case 'reset':
                $this->sendServerPasswordResetRequest($serverId);
                break;
            default:
                $this->handleServerReboot($serverId);
                break;
        }
    }

    /**
     * Отменяет действия с сервером и отображает доступные действия.
     *
     * @param int $serverId ID сервера, для которого нужно отменить действия.
     * @param int $chatId ID чата, где находится сообщение.
     * @param int $idMessage ID сообщения для редактирования.
     * @param string $serverList Список серверов в формате "ID: xxx\nName: xxx\nStatus: xxx\nIP: xxx\n\n".
     * @return void
     */
    public function canceledServerActions(int $serverId, int $chatId, int $idMessage, string $serverList)
    {
        $serverInfo = preg_grep("/ID: $serverId/", explode("\n\n", $serverList));
        $keyboard = $this->getKeyboard('DeleteOrReloadServer', $serverId);
        $this->bot->editMessageText($chatId, $idMessage, reset($serverInfo), null, false, $keyboard);
    }
}