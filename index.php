<?php
require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;


require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', '*');
define('CLIENT_ID', '***');
define('CLIENT_SECRET', '*');
define('CODE', '*****');
define('REDIRECT_URL', '****');


echo "<pre>";


$responsibleUserIds = [9649906, 9649914, 9810786, 9823110, 9860838, 9900262, 9962898, 9963002, 9999670, 10009502];
$sumsByManager = [];
$managerNames = []; // Инициализация массива для имен менеджеров
$telegram = new \TelegramBot\Api\BotApi('*****');
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
        $telegram->sendMessage($chatId = ****, $text = $message);
    }
} catch (Exception $ex) {
    var_dump($ex);
    file_put_contents("ErrLog.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки: ' . $ex->getCode());
}
