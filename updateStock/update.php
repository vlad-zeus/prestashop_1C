<?php
if (file_exists('fileFrom1C.json')) {
# Подключаем phpmailer
    require 'PHPMailer.php';
    require 'SMTP.php';
    require 'Exception.php';

# Формируем таблицу с ошибками
    $errorForEmail = "<table>";
    $errorForEmail .= "<thead>";
    $errorForEmail .= "<tr>" . PHP_EOL;
    $errorForEmail .= "<th>" . "Артикул товара" . "</th>" . "<th>" . 'Текст ошибки' . "</th>" . PHP_EOL;
    $errorForEmail .= "</tr>" . PHP_EOL;
    $errorForEmail .= "</thead>" . PHP_EOL;
    $errorForEmail .= "<tbody>" . PHP_EOL;

# Блок подключения к MySQL
    $dbConnect = new PDO('mysql:host=193.42.111.138;dbname=srebro;charset=UTF8', 'srebro_root', 'W0t8X8j6');

# Переменная файла
    $fileJson = file_get_contents('fileFrom1C.json');

# Декодируем. На выходе получили массив
    $obj = json_decode($fileJson, true);

# Получили адрес, куда отправляем отчет о ошибках
    $email = $obj['email'];

# Посчитали количество вложенных массивов
    $countArr = (count($obj['product']));

# Цикл по массивам
    for ($i = 0; $i < $countArr; $i++) {
# Получили id товара
        $idProductJson = $obj['product'][$i]['IdProduct'];

# Получили артикул товара
        $referenceJson = $obj['product'][$i]['reference'];

# Получили, насколько уменьшаем остаток товара
        $quantityJson = $obj['product'][$i]['quantity'];
        #$sqlProductAvailability = "SELECT count(*) AS countProduct FROM ps_product WHERE ps_product.reference =" . "'" . $referenceJson . "'";

# Проверка, существует ли товар на сайте. Если вернет 1 - товар есть. 0 - товара нет, больше 1 - были косяки с обновлением, надо исправлять
        $productAvailability = $dbConnect->prepare("SELECT count(*) FROM ps_product WHERE reference = :reference");
        $productAvailability->execute(['reference' => $referenceJson]);
        $resultProductAvailability = $productAvailability->fetchColumn();


        switch ($resultProductAvailability) {
            case 0:
                $errorForEmail .= "<tr>" . PHP_EOL;
                $errorForEmail .= "<td>" . "<font color=\"red\">" . $referenceJson . "<font>" . "</td>" . "<td>" . 'Такой товар отсутствует на сайте!' . "</td>" . PHP_EOL;
                $errorForEmail .= "</tr>" . PHP_EOL;
                break;
            case 1:
                $countProduct = $dbConnect->prepare("SELECT quantity FROM ps_stock_available WHERE id_product = :productId");
                $countProduct->execute(['productId' => $idProductJson]);
                $resultCountProduct = $countProduct->fetch();
                $resultCountProduct = $resultCountProduct['quantity'];
                # Проверяем, если количество на сайте меньше, чем в файле - идем в ошибку.
                if ($quantityJson <= $resultCountProduct) {
                    $newQuantity = $resultCountProduct - $quantityJson;
                    $updateQuantityStock = $dbConnect->prepare("UPDATE ps_stock_available SET quantity = :productQuantity WHERE id_product = :productId");
                    $updateQuantityStock->execute(['productQuantity' => $newQuantity, 'productId' => $idProductJson]);

                    $errorForEmail .= "<tr>" . PHP_EOL;
                    $errorForEmail .= "<td>" . $referenceJson . "</td>" . "<td>" . 'ОК! Было ' . $resultCountProduct . ', стало ' . $newQuantity . "</td>" . PHP_EOL;
                    $errorForEmail .= "</tr>" . PHP_EOL;
                } elseif ($quantityJson > $resultCountProduct) {
                    $newQuantity = 0;
                    $updateQuantityStock = $dbConnect->prepare("UPDATE ps_stock_available SET quantity = :productQuantity WHERE id_product = :productId");
                    $updateQuantityStock->execute(['productQuantity' => $newQuantity, 'productId' => $idProductJson]);
                    $errorForEmail .= "<tr>" . PHP_EOL;
                    $errorForEmail .= "<td>" . "<font color=\"red\">" . $referenceJson . "<font>" . "</td>" . "<td>" . 'На сайте недостаточно товара. Установлено количество 0' . "</td>" . PHP_EOL;
                    $errorForEmail .= "</tr>" . PHP_EOL;
                }
                break;
            default:
                $errorForEmail .= "<tr>" . PHP_EOL;
                $errorForEmail .= "<td>" . "<font color=\"red\">" . $referenceJson . "<font>" . "</td>" . "<td>" . 'На сайте несколько товаров с таким артикулом!' . "</td>" . PHP_EOL;
                $errorForEmail .= "</tr>" . PHP_EOL;
        }
        /*    # Запускаем индексацию конкретного товара
            $url = 'https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-selected&ids=' . $productIdIn;
            $ch = curl_init($url);
            curl_exec($ch);*/
    }

    $errorForEmail .= "</tbody>";
    $errorForEmail .= "</table>";

// Формирование самого письма
    $title = "Изменение остатка на " . date('d-m-Y H:i:s', ((new DateTime)->getTimestamp()));
    $body = "
<h2 align=\"center\">Отчет изменения остатка</h2>
$errorForEmail
";

// Настройки PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    try {
        $mail->isSMTP();
        $mail->CharSet = "UTF-8";
        $mail->SMTPAuth = true;
        //$mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            $GLOBALS['status'][] = $str;
        };

        // Настройки вашей почты
        $mail->Host = 'smtp.yandex.ru';     // SMTP сервера вашей почты
        $mail->Username = 'vlad-ofset';     // Логин на почте
        $mail->Password = 'cha3Kaum';       // Пароль на почте
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('vlad-ofset@yandex.ru', 'Магазин Сребро'); // Адрес самой почты и имя отправителя

        // Получатель письма
        $mail->addAddress($email);


// Отправка сообщения
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = $body;


// Проверяем отравленность сообщения
        if ($mail->send()) {
            $result = "success";
        } else {
            $result = "error";
        }

    } catch (Exception $e) {
        $result = "error";
        $status = "Сообщение не было отправлено. Причина ошибки: {$mail->ErrorInfo}";
    }
    unlink('fileFrom1C.json');
    $ch = curl_init("https://srebro.com.ua/admin741ilynxy/index.php?controller=AdminSearch&action=searchCron&ajax=1&full=1&token=24n6Vy74&id_shop=1");
    curl_exec($ch);
    $ch = curl_init("https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-missing");
    curl_exec($ch);
    $ch = curl_init("https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-missing");
    curl_exec($ch);
    $ch = curl_init("https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-missing");
    curl_exec($ch);
    $ch = curl_init("https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-missing");
    curl_exec($ch);
    /*// Отображение результата
    echo json_encode(["result" => $result, "resultfile" => $rfile, "status" => $status]);
    */
}
