<?php
# Блок подключения к MySQL
$host = 'localhost'; // адрес сервера
$database = 'srebroml'; // имя базы данных
$user = 'vgorodetsky'; // имя пользователя
$password = '1715804226Gor'; // пароль
# Адрес получения курсов валют
$url = 'https://api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5';
# Получаем json курсов валют
$content = file_get_contents($url);
# Декодируем. На выходе получили массив
$obj = json_decode($content, true);
# Посчитали количество вложенных массивов
$countArr = (count($obj));
# Подключаемся к базе
$connection = mysqli_connect($host, $user, $password, $database) or die("Не удалось соединиться: " . mysqli_connect_error());
# Цикл по массивам
for ($i = 0; $i < $countArr; $i++) {
    # Получили буквенный идентификатор валюты
    $currency = $obj[$i][ccy];
    # Высчитываем курс валюты + 5%. Поскольку базовая валюта гривна - 1 делим на курс продажи Приватбанка
    $exchangeRates = ($obj[$i][sale])*1.05;
    # Запрос для проверки, существует ли такая валюта на сайте
    $currencyAvailability = "SELECT EXISTS(SELECT ps_currency.id_currency FROM ps_currency WHERE ps_currency.iso_code ='" . $currency . "' AND ps_currency.deleted <> 1)";
    # Запрос обновления курса валюты. Подставляем буквенный идентификатор валюты и проверяем, что валюта не удалена с сайта
    $exchangeRatesUpdate = "UPDATE ps_currency SET conversion_rate = 1/" . $exchangeRates . " WHERE  ps_currency.iso_code ='" . $currency .  "' AND ps_currency.deleted <> 1";
    print_r($currencyAvailability);
    # Проверяем, есть ли  валюта на сайте
    $resultCurrencyAvailability = mysqli_query($connection, $currencyAvailability) or die('Запрос не удался $resultCurrencyAvailability: ' . mysqli_error($connection));
    # Если запрос вернул 1, тогда...
    if ($resultCurrencyAvailability == 1) {
        #... обновляем курс валюты
        $resultExchangeRatesUpdate = mysqli_query($connection, $exchangeRatesUpdate) or die('Запрос не удался $resultExchangeRatesUpdate: ' . mysqli_error($connection));
    }
}
# Закрываем подключение к базе
mysqli_close($connection);
?>
