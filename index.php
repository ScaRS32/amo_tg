<?php
require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;


require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', 'alikson');
define('CLIENT_ID', 'a538e0a8-a03b-43fe-95af-069f826bac95');
define('CLIENT_SECRET', '0fPmA05v8wEeGCsrKN7Mrl4pZxYy2OcG6VaurRwVwohETviebMrhITkv6j1SH0xM');
define('CODE', 'def502006eb07f410eae9a15b37a56ab9489f4795ebe4d72d39060937e9d7571251dac1f281e1a42e6f234adcecc950c4f58df8c94c145d6e202c67fd5937e9dd24718ad2bca00581b7bb0f1cb9bf5b6f7503a605ce24ec9bade1d544abfbe28b2afebc93d1606e309fbdcd19e5bd534fecf6ce482926fe83c5814736290a69f567a71795ba7c97f9aee11e9a1193bb9168aa13dcda706d63f269c99a073902972ed645afe4f1bfef93842436e616cff84a8d71fe8e068f504478ea1b7c2896c9e606621d7ad1dcffb9857b9227f9155e9ff0c4b956d55f369c1ad748e89c23889cfe8286c32e7db41257bc9a07663f17f88e6446d993cfa1a474d623d5ffe5b2fd86565d27b5f7b6f503dc7ee5b7ddc0675f519880b870fcc80ead6bd69c1e45925697c70ac0ca5736961e9c9297da291f454f226cbd81eca8d2f6bd08d6e0d74fb42c39a5a040dbea7c1789f29375b92782dad0f05d155da3d83c23c4d65bb1ee5e9a2fdfa9b354b33039ffec37f1bcdcd4c2385d7a8f0a6ce2f05bbe9621fceb8282ae102a13c17dc61ec7a63594d70cbfa028b3ed335f259af971356f3ccb0c196e7ec9fdbc621ed11fcea1fac2e6e0ad77499df64ce1e53308ef239b367ce447e47e4b7f49d0c3b4caac3aeb0339770549f52f26fe0247919421655850e41561910d27ca0099e');
define('REDIRECT_URL', 'https://alikson.amocrm.ru');

// Токен телеграм-бота  6098750394:AAEukzP6v28GowwvNoNFVZ5wsJJzVM1AJFQ

// ID чата (пользователя или группы) для отправки сообщений в Telegram -956981447' TEEEEEEEST -667224067 -1001538634776

echo "<pre>";


$responsibleUserIds = [9649906, 9649914, 9810786, 9823110, 9860838, 9900262, 9962898, 9963002, 9999670, 10009502];
$sumsByManager = [];
$managerNames = []; // Инициализация массива для имен менеджеров
$telegram = new \TelegramBot\Api\BotApi('6098750394:AAEukzP6v28GowwvNoNFVZ5wsJJzVM1AJFQ');
$uniqueLeadIds =[];
$midnightUnixTime = strtotime('today');

try {
    $amoV4Client = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    foreach ($responsibleUserIds as $managerId) {
        $managerData = $amoV4Client->GETRequestApi('users/' . $managerId);
        $managerName = $managerData['name'];
        $managerNames[$managerId] = $managerName;
    }


    // Получаем события по изменению этапов
    $eventsResponse = $amoV4Client->GETRequestApi('events', [
        'filter[created_at][from]' => $midnightUnixTime,
        'filter[type]' => 'lead_status_changed',
        'filter[entity_type]'=>'lead',
        'filter[value_after][leads_statuses][0][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][0][status_id]' => 57502230,
        'filter[value_after][leads_statuses][1][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][1][status_id]' => 59637470
    ])['_embedded']['events'];


    foreach ($eventsResponse as $event) {
        $entityId = $event['entity_id'];
        if (!in_array($entityId, $uniqueLeadIds)) {
            $uniqueLeadIds[] = $entityId;
        }
    }
    $processedLeadIds = []; // Массив для хранения уже обработанных сделок

    foreach ($uniqueLeadIds as $leadId) {
        // Проверяем, что сделка еще не была обработана
        if (!in_array($leadId, $processedLeadIds)) {
            $leadResponse = $amoV4Client->GETRequestApi("leads/$leadId");
            /*var_dump($leadResponse);*/
            if (isset($leadResponse['price']) && isset($leadResponse['responsible_user_id'])) {
                $price = $leadResponse['price'];
                $managerId = $leadResponse['responsible_user_id'];

                // Суммируем бюджеты по менеджерам
                if (!isset($sumsByManager[$managerId])) {
                    $sumsByManager[$managerId] = 0;
                }
                $sumsByManager[$managerId] += $price;

                // Добавляем сделку в список обработанных
                $processedLeadIds[] = $leadId;
            }
        }
    }

// Сортируем менеджеров по убыванию суммы
    arsort($sumsByManager);

// Формируем сообщение для отправки в Telegram
    $message = '';
    $position = 1;

    foreach ($sumsByManager as $managerId => $sum) {
        // Проверяем, что $managerId не равен 9584038 и не равен 9698626
        if (!empty($managerId) &&  $sum > 0 && $managerId !== 9584038 && $managerId !== 9698626) {
            $managerName = $managerNames[$managerId]; // Получаем имя менеджера по его ID
            $messagePart = "{$position}. ";

            if ($position === 1 && $sum > 300000) {
                $messagePart .= "{$managerName} сегодня продал(а) больше всех на сумму {$sum} и по итогам дня получает 1000 руб.\n\n";
            } else {
                $messagePart .= "{$managerName} продал(а) на сумму {$sum}. В следующий раз обязательно получится!\n\n";
            }

            $message .= $messagePart;
            $position++;
        }
    }

// Проверяем наличие сумм по менеджерам перед отправкой сообщения
    if (!empty($message)) {
        // Отправляем сообщение с отчетом в группу Телеграм
        $telegram->sendMessage($chatId = -1001538634776, $text = $message);
    }
} catch (Exception $ex) {
    var_dump($ex);
    file_put_contents("ErrLog.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки: ' . $ex->getCode());
}