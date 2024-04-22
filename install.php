<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инсталлятор</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f2f2f2;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card.success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .card.error {
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .card .card-title {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .card .card-content {
            margin-bottom: 20px;
        }
        input[type="text"],
        input[type="url"],
        input[type="submit"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 16px;
            margin-bottom: 10px;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token_reg_ru = filter_input(INPUT_POST, 'token_reg_ru', FILTER_SANITIZE_STRING);
        $tg_bot_token = filter_input(INPUT_POST, 'tg_bot_token', FILTER_SANITIZE_STRING);
        $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);

        $content = <<<EOT
<?php
const TOKEN_REG_RU = '$token_reg_ru';
const TG_BOT_TOKEN = '$tg_bot_token';
const URL = '$url';
EOT;

        $file_path = 'inc/config.php';
        if (file_put_contents($file_path, $content)) {
            echo '<div class="card success"><div class="card-title">Успешно</div><div class="card-content">Файл "inc/config.php" создан. Установка пройдена. Удалите файл "install.php".</div></div>';
            return;
        }
        echo '<div class="card error"><div class="card-title">Ошибка</div><div class="card-content">Ошибка при создании файла.</div></div>';
    }

    $random_string = bin2hex(random_bytes(32));
    if (file_exists('inc/config.php')) {
        echo '<div class="card error"><div class="card-title">Ошибка</div><div class="card-content">Файл "inc/config.php" уже существует. Удалите файл "install.php".</div></div>';
        return;
    }
    ?>
    <div class="card">
        <div class="card-title">Введите данные:</div>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
            <label for="token_reg_ru">Токен облака reg.ru:</label><br>
            <input type="text" id="token_reg_ru" name="token_reg_ru" placeholder="<?= $random_string ?>"><br>
            <label for="tg_bot_token">Токен бота TG:</label><br>
            <input type="text" id="tg_bot_token" name="tg_bot_token" placeholder="1234567890:zyx57W2v1u123ew11-zu123ew11"><br>
            <label for="url">URL:</label><br>
            <input type="url" id="url" name="url" value="https://api.cloudvps.reg.ru/v1/reglets" placeholder="https://api.cloudvps.reg.ru/v1/reglets"><br><br>
            <input type="submit" value="Создать файл">
        </form>
    </div>
</body>
</html>