<?php
if (file_exists('Nomen1C.json')) {
# Подключаем phpmailer
    require 'PHPMailer/Exception.php';
    require 'PHPMailer/SMTP.php';
    require 'PHPMailer/PHPMailer.php';
    /*
    # Формируем таблицу с ошибками
    $errorForEmail = "<table>";
    $errorForEmail .= "<thead>";
    $errorForEmail .= "<tr>" . PHP_EOL;
    $errorForEmail .= "<th>" . "Артикул товара" . "</th>" . "<th>" . 'Текст ошибки' . "</th>" . PHP_EOL;
    $errorForEmail .= "</tr>" . PHP_EOL;
    $errorForEmail .= "</thead>" . PHP_EOL;
    $errorForEmail .= "<tbody>" . PHP_EOL;*/

# Блок подключения к MySQL
    $dbConnect = new PDO('mysql:host=193.42.111.138;dbname=srebro;charset=UTF8', 'srebro_root', 'W0t8X8j6');

# Переменная файла
    $fileJson = file_get_contents('Nomen1C.json');

# Декодируем. На выходе получили массив
    $obj = json_decode($fileJson, true);

# Получили адрес, куда отправляем отчет о ошибках
    $email = $obj['email'];

# Посчитали количество вложенных массивов
    $countArr = (count($obj['product']));

# Цикл по массивам
    for ($i = 0; $i < $countArr; $i++) {

        # Получили id товара
        $productIdIn = $obj['product'][$i]['IdProduct'];

        # Получили количество товара
        $productQuantityIn = $obj['product'][$i]['quantity'];

        # Получили id магазина
        $defaultShopIdIn = $obj['product'][$i]['IDshop'];

        # Получили цену продажи
        $productPriceIn = $obj['product'][$i]['CenaOUT'];

        # Получили цену закупки
        $productWholesalePriceIn = $obj['product'][$i]['CenaIN'];

        # Получили артикул товара
        $productReferenceIn = $obj['product'][$i]['Art'];

        # Получили вес товара
        $productWeightIn = $obj['product'][$i]['Ves'];

        # Получили meta description товара
        $productMetaDescriptionIn = $obj['product'][$i]['O_meta'];

        # Получили meta key товара
        $productMetaKeywordsIn = $obj['product'][$i]['O_meta_key'];

        # Получили meta title товара
        $productMetaTitleIn = $obj['product'][$i]['O_meta_title'];

        # Получили описание товара
        $productDescriptionIn = $obj['product'][$i]['Opis_Dop'];

        # Получили название товара
        $productDescriptionShortIn = $obj['product'][$i]['Nom_Name'];

        # Получили описание краткое описание товара
        $productNameIn = $obj['product'][$i]['Nom_Opis'];

        # Получили размеры товара
        $listSizeNameIn = $obj['product'][$i]['RAzmer'];

        # Получили категории товара
        $listCategoryIdIn = $obj['product'][$i]['Category'];

        # Получили характеристики товара
        $listFeatureIdIn = $obj['product'][$i]['XAR'];

        # Получили группы prom.ua
        $productPromGroupIn = $obj['product'][$i]['ID_Prom'];

        # Получили подгруппы prom.ua
        $productPromSubsectionIn = $obj['product'][$i]['ID_PodRazdel'];

        # Получили название группы prom.ua
        $productPromGroupDescriptionIn = $obj['product'][$i]['Group_Prom'];

        $callProcedureInsertProduct = 'call insert_product(' . '\'' . $productIdIn . '\','
            . '\'' . $productQuantityIn . '\','
            . '\'' . $defaultShopIdIn . '\','
            . '\'' . $productPriceIn . '\','
            . '\'' . $productWholesalePriceIn . '\','
            . '\'' . $productReferenceIn . '\','
            . '\'' . $productWeightIn . '\','
            . '\'' . $productMetaDescriptionIn . '\','
            . '\'' . $productMetaKeywordsIn . '\','
            . '\'' . $productMetaTitleIn . '\','
            . '\'' . $productDescriptionIn . '\','
            . '\'' . $productDescriptionShortIn . '\','
            . '\'' . $productNameIn . '\','
            . '\'' . $listSizeNameIn . '\','
            . '\'' . $listCategoryIdIn . '\','
            . '\'' . $listFeatureIdIn . '\','
            . '\'' . $productPromGroupIn . '\','
            . '\'' . $productPromSubsectionIn . '\','
            . '\'' . $productPromGroupDescriptionIn . '\')';


# Выполняем процедуру
        $callProcedureInsertProductExecute = $dbConnect->query($callProcedureInsertProduct);

# Запускаем индексацию конкретного товара
//    $url = 'https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-selected&ids=' . $productIdIn;
//    $ch = curl_init($url);
//    curl_exec($ch);
    }

    $errorForEmail = 'Обновление завершено';


// Формирование самого письма
    $title = "Изменение остатка остатка на " . date('d-m-Y H:i:s', ((new DateTime)->getTimestamp()));
    $body = "
<h2 align=\"center\">Отчет обновления остатков</h2>
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

    unlink('Nomen1C.json');
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
    $ch = curl_init("https://srebro.com.ua/module/amazzingfilter/cron?token=c9b8e8bb76053477722f1c2bfeb0f05a&id_shop=1&action=index-missing");
    curl_exec($ch);


    /*// Отображение результата
    echo json_encode(["result" => $result, "resultfile" => $rfile, "status" => $status]);
    */
}
