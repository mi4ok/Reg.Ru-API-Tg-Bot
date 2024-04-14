<?php
function handleServerDeleteRequest($serverId, $token, $link) {
    $url = $link . '/' . $serverId;
    $options = [
        'http' => [
            'header'  => "Authorization: Bearer $token\r\n" .
                "Content-Type: application/json\r\n",
            'method'  => 'DELETE'
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    return $response;
}

function handleServerReboot($serverId, $token, $link) {
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
    $response = file_get_contents($url, false, $context);

    return $response;
}

function handleServerListRequest($token, $link): string
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