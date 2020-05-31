create
    definer = srebro_root@`%` procedure insert_product(IN productIdIn varchar(255), IN productQuantityIn varchar(255),
                                                       IN defaultShopIdIn varchar(255), IN productPriceIn varchar(255),
                                                       IN productWholesalePriceIn varchar(255),
                                                       IN productReferenceIn varchar(255),
                                                       IN productWeightIn varchar(255),
                                                       IN productMetaDescriptionIn varchar(255),
                                                       IN productMetaKeywordsIn varchar(255),
                                                       IN productMetaTitleIn varchar(255),
                                                       IN productDescriptionIn varchar(10000),
                                                       IN productDescriptionShortIn varchar(1000),
                                                       IN productNameIn varchar(255), IN listSizeNameIn varchar(255),
                                                       IN listCategoryIdIn varchar(1000),
                                                       IN listFeatureIdIn varchar(255),
                                                       IN productPromGroupIn varchar(255),
                                                       IN productPromSubsectionIn varchar(255),
                                                       IN productPromGroupDescriptionIn varchar(1000))
BEGIN

    # ! Универсальный, работает как на 1,6 так и на 1,7

    # ! Процедура писалась на 5.5.64-MariaDB. На более высоких версиях можно столкнуться со строгой проверкой входящих параметров.
    # ! В 8-й я это просто отключил. Но не факт, что у хостера такая возможность будет.
    # ! Так что, пока оставлю в планы, переписать и проверить работоспособность при включенной проверке.

    # * Changelog
    # * 30.05.2020
    # add В таблицу ps_product_prom_category добавлен столбец prom_group_description.
    
    # * 27.05.2020
    # fix Исправлено присвоение категорий Prom.ua при обновлениии товара. Теперь не просто Update, а Insert or Update.
    # fix Ошибка была потому что товар уже есть, мы его обновляем. Но в таблице категорий Prom.ua записей не было. Обновлять было нечего.
    
    # * 24.05.2020
    # add Добавлена проверка характеристики на присутствие значения. Если значение отсутствует - прерываем вставку характеристики.
    # add До этого, даже если значение характеристики отсутвовало - происходила вставка пустой характеристики. И пустое значение также летело на Prom.ua
    # fix Исправлена автоматическая подстановка пола и страны. Было неправильно определено условие срабатывания.
    
    # * 23.05.2020
    # add В базу добавлена еще одна таблица ps_product_prom_category. В ней храним связку товара с группами/подразделами Prom.ua
    # add Во входящие параметры добавлены productPromGroupIn, productPromSubsectionIn. Это как раз группы/подразделы Prom.ua
    # add Добавлена автоматическая подстановка страны и пола, если они явно не указаны в строке характеристик

    # * Очистка временной таблицы, в которую мы пишем id товара.
    TRUNCATE z_product;

    # * Заменяем запятую на точку, поскольку в базе разделитель - точка
    SET productWeightIn = REPLACE(productWeightIn, ',', '.');
    SET productPriceIn = REPLACE(productPriceIn, ',', '.');
    SET productWholesalePriceIn = REPLACE(productWholesalePriceIn, ',', '.');
    SET listSizeNameIn = REPLACE(listSizeNameIn, ',', '.');

    # * Работа с описанием товара, убираем переносы строк, ставим теги. Иначе ломает абзацы и прочую хрень. */
    # ? Заменяем перевод строки на теги начала/конца абзаца.
    SET productDescriptionIn = REPLACE(productDescriptionIn, '\n', '</p><p>');

    # ? Два перевода строки стали вот такими '</p><p></p><p>'. Заменяем на правильное. Заодно расставляем тег выключки по ширине.
    SET productDescriptionIn = REPLACE(productDescriptionIn, '</p><p></p><p>', '</p>\r\n<p style="text-align:justify;">');

    # ? Ставим тег абзаца в начале текста и в конце текста.
    SET productDescriptionIn = CONCAT('<p style="text-align:justify;">', productDescriptionIn, '</p>');


    # * Отсюда и до конца IF - вставка самого товара.

    # ? Если id_product_in пустой, значит товар еще не вставлялся на сайт.
    IF productIdIn = '' THEN

        # ? Вставка товара.
        INSERT INTO `ps_product` (`id_product`, `id_supplier`, `id_manufacturer`, `id_category_default`,
                                  `id_shop_default`, `id_tax_rules_group`, `on_sale`, `online_only`, `ean13`, `upc`,
                                  `ecotax`, `quantity`, `minimal_quantity`, `price`, `wholesale_price`, `unity`,
                                  `unit_price_ratio`, `additional_shipping_cost`, `reference`, `supplier_reference`,
                                  `location`, `width`, `height`, `depth`, `weight`, `out_of_stock`, `quantity_discount`,
                                  `customizable`, `uploadable_files`, `text_fields`, `active`, `redirect_type`,
                                  `available_for_order`, `available_date`, `condition`, `show_price`, `indexed`,
                                  `visibility`, `cache_is_pack`, `cache_has_attachments`, `is_virtual`,
                                  `cache_default_attribute`, `date_add`, `date_upd`, `advanced_stock_management`,
                                  `pack_stock_type`)
        VALUES (NULL, 0, 0, 2, defaultShopIdIn, 0, 0, 0, '', '', '0.000000', productQuantityIn, 1,
                productPriceIn,
                productWholesalePriceIn, '', '0.000000', '0.00', productReferenceIn, '', '', productWeightIn,
                '0.000000', '0.000000',
                '0.000000', 2, 0, 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, 0, 0,
                current_timestamp, current_timestamp, 0, 3);

        # ? Записываем id товара во временную таблицу.
        INSERT INTO z_product (id_product) VALUE (LAST_INSERT_ID());

        # ? Даем переменной последний вставленный id.
        SET @productId = (LAST_INSERT_ID());

        # ? Вставка товара. Привязка к магазину.
        INSERT INTO `ps_product_shop` (`id_product`, `id_shop`, `id_category_default`, `id_tax_rules_group`, `on_sale`,
                                       `online_only`, `ecotax`, `minimal_quantity`, `price`, `wholesale_price`, `unity`,
                                       `unit_price_ratio`, `additional_shipping_cost`, `customizable`,
                                       `uploadable_files`, `text_fields`, `active`, `redirect_type`,
                                       `available_for_order`, `available_date`, `condition`, `show_price`, `indexed`,
                                       `visibility`, `cache_default_attribute`, `advanced_stock_management`, `date_add`,
                                       `date_upd`, `pack_stock_type`)
        VALUES (@productId, defaultShopIdIn, 2, 0, 0, 0, '0.000000', 1, productPriceIn,
                productWholesalePriceIn, '',
                '0.000000', '0.00', 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, current_timestamp,
                current_timestamp, 3);

        # ? Вставка товара. Описание товара.
        INSERT INTO `ps_product_lang` (`id_product`, `id_shop`, `id_lang`, `description`, `description_short`,
                                       `link_rewrite`, `meta_description`, `meta_keywords`, `meta_title`, `name`,
                                       `available_now`, `available_later`)
        VALUES (@productId, defaultShopIdIn, 1, productDescriptionIn, productDescriptionShortIn,
                (SELECT transliterate(CONCAT(productReferenceIn, '-', productNameIn))), productMetaDescriptionIn,
                productMetaKeywordsIn,
                productMetaTitleIn, productNameIn, 'В наличии!', 'Под заказ!');

        # ? Вставка товара. Остатки.
        INSERT INTO ps_stock_available (id_stock_available, id_product, id_product_attribute, id_shop, id_shop_group,
                                        quantity, depends_on_stock, out_of_stock)
        VALUES (NULL, @productId, 0, defaultShopIdIn, 0, productQuantityIn, 0, 2);

        # ? Вставка товара. Записываем категории Prom.ua
        INSERT INTO ps_product_prom_category (id_product, prom_group, prom_subsection, prom_group_description)
        VALUES (@productId, productPromGroupIn, productPromSubsectionIn, productPromGroupDescriptionIn);

    ELSE
        # ? Даем переменной id товара. Сделано потому что дальше у нас используется id товара, будем теперь к ней обращаться
        SET @productId = (productIdIn);

        # ? Обновление товара
        UPDATE ps_product
        SET quantity        = productQuantityIn,
            price           = productPriceIn,
            wholesale_price = productWholesalePriceIn,
            date_upd        = current_timestamp
        WHERE id_product = productIdIn;

        # ? Обновление товара. Привязка к магазину.
        UPDATE ps_product_shop
        SET price           = productPriceIn,
            wholesale_price = productWholesalePriceIn,
            date_upd        = current_timestamp
        WHERE id_product = productIdIn;

        # ? Обновление товара. Описание товара.
        UPDATE ps_product_lang
        SET name              = productNameIn,
            meta_description  = productMetaDescriptionIn,
            meta_keywords     = productMetaKeywordsIn,
            meta_title        = productMetaTitleIn,
            description       = productDescriptionIn,
            description_short = productDescriptionShortIn
        WHERE id_product = productIdIn;

        # ? Обновление товара. Остатки.
        UPDATE ps_stock_available
        SET quantity = productQuantityIn
        WHERE id_product = productIdIn;

        # ? Обновление товара. Записываем категории Prom.ua
        INSERT INTO ps_product_prom_category (id_product, prom_group, prom_subsection, prom_group_description)
        VALUES (@productId, productPromGroupIn, productPromSubsectionIn, productPromGroupDescriptionIn)
        ON DUPLICATE KEY UPDATE prom_group = productPromGroupIn,
                                prom_subsection = productPromSubsectionIn,
                                prom_group_description = productPromGroupDescriptionIn;

        # ? Записываем id товара во временную таблицу.
        INSERT INTO z_product (id_product)
            VALUE (productIdIn);

    END IF;


    # * Присваиваем категории товару.

    # ? Сначала удаляем  существующие привязки категорий товару.
    DELETE
    FROM ps_category_product
    WHERE id_product = @productId;

    # ? Присваиваем категорию по умолчанию. Весь товар должен быть присвоен главной категории.
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    SELECT '2',
           @productId,
           MAX(`position`) + 1
    FROM ps_category_product;
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    SELECT '10',
           @productId,
           MAX(`position`) + 1
    FROM ps_category_product;

    # ? Присваеваем список категорий переменной.
    SET @list = listCategoryIdIn;

    # ? Цикл по списку.
    WHILE @list != ''
        DO
            # ? Извлекаем одну категорию
            SET @oneCategory = SUBSTRING_INDEX(@list, ':', 1);
            # ? Уменьшаем строку первоначальных параметров на длинну одной категории
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@oneCategory) + 2);

            # ? Вставка категории
            INSERT `ps_category_product` (`id_category`, id_product, `position`)
            SELECT @oneCategory,
                   @productId,
                   MAX(`position`) + 1
            FROM ps_category_product;
        END WHILE;


    # * Присваиваем характеристики товару.

    # ? Удаляем привязки характеристики товара.
    DELETE
    FROM ps_feature_product
    WHERE id_product = @productId;

    # ? Присваеваем список характеристик переменной.
    SET @list = listFeatureIdIn;

    # ? Проверяем, есть ли в строке страна производителя. Если нет - применяем Украина
    SET @countryExists = (SELECT LOCATE('7:', @list));
    IF @countryExists = 0
    THEN
        INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
        VALUES (7, @productId, (SELECT ps_feature_value_lang.id_feature_value
                                FROM ps_feature_value_lang
                                WHERE ps_feature_value_lang.value = 'Украина'
                                LIMIT 1));
    end if;

    # ? Проверяем, есть ли в строке пол. Если нет - применяем Унисекс
    SET @countryExists = (SELECT LOCATE('11:', @list));
    IF @countryExists = 0
    THEN
        INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
        VALUES (11, @productId, (SELECT ps_feature_value_lang.id_feature_value
                                 FROM ps_feature_value_lang
                                 WHERE ps_feature_value_lang.value = 'Унисекс'
                                 LIMIT 1)) ;
    end if;

    # ? Цикл по списку.
    WHILE @list != ''
        DO
            # ? Извлекаем одну характеристику.
            SET @featureOneList = SUBSTRING_INDEX(@list, ';', 1);
            # ? Извлекаем ID характеристики.
            SET @featureValueIdIn = SUBSTRING_INDEX(@featureOneList, ':', 1);
            # ? Извлекаем значение характеристики.
            SET @featureValueIn = SUBSTRING_INDEX((SUBSTRING_INDEX(@featureOneList, ':', 2)), ':', -1);
            # ? Уменьшаем строку первоначальных параметров на длинну одной категории.
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@featureOneList) + 2);

            # ? Вот тут мы делаем проверку на существование названия такой характеристики. Если есть - просто присваиваем товару. Иначе будет куча одинаковых записей.
            SET @featureValueIdOld = (SELECT EXISTS(SELECT ps_feature_value_lang.id_feature_value
                                                    FROM ps_feature_value_lang
                                                    WHERE ps_feature_value_lang.value = @featureValueIn));

            # ? Есть такая характеристика.
            IF @featureValueIdOld = 1 THEN
                # ? Проверяем, если в характеристике нет значения - не запускаем присваивание характеристики. Иначе характеристика будет, а значения нет. И будет и на пром лететь пустая
                IF @featureValueIn <> '' THEN
                    # ? Присваиваем товару
                    INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                    VALUES (@featureValueIdIn, @productId, (SELECT ps_feature_value_lang.id_feature_value
                                                            FROM ps_feature_value_lang
                                                            WHERE ps_feature_value_lang.value = @featureValueIn
                                                            LIMIT 1));
                END IF;

                # ? Нет такой характеристики.
            ELSE
                # ? Проверяем, если в характеристике нет значения - не запускаем присваивание характеристики. Иначе характеристика будет, а значения нет. И будет и на пром лететь пустая
                IF @featureValueIn <> '' THEN
                    # ? Создаем новую характеристику.
                    INSERT IGNORE `ps_feature_value` (id_feature_value, id_feature, custom)
                    VALUES (DEFAULT, @featureValueIdIn, 0);
                    INSERT IGNORE `ps_feature_value_lang` (id_feature_value, id_lang, value)
                    VALUES (LAST_INSERT_ID(), 1, @featureValueIn);
                    INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                    VALUES (@featureValueIdIn, @productId, LAST_INSERT_ID());
                END IF;
            END IF;

        END WHILE;


    # * Отсюда и до конца while - вставка размеров товара.

    # ? Удаляем размеры из базы.
    DELETE
    FROM ps_attribute_impact
    WHERE id_product = @productId;
    BEGIN
        DECLARE id_product_att int;
        DECLARE done integer DEFAULT 0;
        DECLARE cur1 CURSOR FOR
            SELECT id_product_attribute AS id_product_att
            FROM ps_product_attribute
            WHERE id_product = @productId;
        DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;
        OPEN cur1;
        WHILE done = 0
            DO
                FETCH cur1 INTO id_product_att;
                DELETE
                FROM ps_product_attribute
                WHERE id_product_attribute = id_product_att;
                DELETE
                FROM ps_product_attribute_shop
                WHERE id_product_attribute = id_product_att;
                DELETE
                FROM ps_stock_available
                WHERE id_product_attribute = id_product_att;
            END WHILE;
        CLOSE cur1;
    END;

    # ? Присваеваем список размеров переменной.
    SET @list = listSizeNameIn;

    # ? Цикл по списку
    WHILE @list != ''
        DO
            # ? Извлекаем один размер.
            SET @sizeOne = SUBSTRING_INDEX(@list, ';', 1);
            # ? Извлекаем название размера
            SET @sizeName = SUBSTRING_INDEX(@sizeOne, ':', 1);
            # ? Извлекаем количество размера
            SET @sizeQuantity = SUBSTRING_INDEX((SUBSTRING_INDEX(@sizeOne, ':', 2)), ':', -1);
            # ? Извлекаем цену
            SET @sizePrice = SUBSTRING_INDEX((SUBSTRING_INDEX(@sizeOne, ':', 3)), ':', -1);
            # ? Уменьшаем строку первоначальных параметров на длинну одного размера
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@sizeOne) + 2);
            # ? Влияние на цену. Высчитываем разницу
            SET @sizePriceImpact = (@sizePrice - productPriceIn);

            # ? Минимальная цена должна быть по умолчанию. Минимальной считаем цену price_in
            IF @sizePriceImpact > 0 THEN
                SET @sizePriceDefault = NULL;
            ELSE
                SET @sizePriceDefault = 1;
            END IF;

            # ? Проверяем есть ли такой размер в базе.
            SET @sizeId = (SELECT EXISTS(SELECT ps_attribute.id_attribute
                                         FROM ps_attribute
                                                  INNER JOIN ps_attribute_lang
                                                             ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                                         WHERE ps_attribute.id_attribute_group = 1
                                           AND ps_attribute_lang.name = @sizeName));

            # ? Если размера нет.
            IF @sizeId = 0 THEN
                # ? Вставка размера.
                INSERT INTO ps_attribute (id_attribute, id_attribute_group, color, `position`)
                SELECT NULL,
                       1,
                       '',
                       MAX(`position`) + 1
                FROM ps_attribute
                WHERE id_attribute_group = 1;

                # ? Загоняем последний созданный ID в переменную.
                SET @sizeId = LAST_INSERT_ID();

                # ? Присваиваем размер языку магазина.
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name)
                VALUES (@sizeId, 1, @sizeName);
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name)
                VALUES (@sizeId, 2, @sizeName);

                # ? Присваиваем размер магазину.
                INSERT INTO ps_attribute_shop (id_attribute, id_shop)
                VALUES (@sizeId, defaultShopIdIn);

                # ? Теперь создаем комбинацию товар/размер. Тут же вставляем остаток.
                INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price, price,
                                                  ecotax, quantity, weight, unit_price_impact, minimal_quantity)
                VALUES (DEFAULT, @productId, productReferenceIn, 0, 0, 0, @sizeQuantity, 0, 0, 1);

                # ? Загоняем последний созданный ID в переменную.
                SET @attributeId = LAST_INSERT_ID();

                # ? И еще раз остаток
                INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                depends_on_stock, out_of_stock)
                VALUES (@productId, @attributeId, defaultShopIdIn, 0, @sizeQuantity, 0, 2);

                # ? Привязываем вновь созданную комбинацию магазину.
                # ? Проверяем на привязку к магазину по умолчанию. Нужно для мультимагазина.
                SET @defaultId = (SELECT EXISTS(SELECT ps_product_attribute_shop.id_product_attribute
                                                FROM ps_product_attribute_shop
                                                WHERE ps_product_attribute_shop.id_product = @productId
                                                  AND ps_product_attribute_shop.id_shop = defaultShopIdIn
                                                  AND ps_product_attribute_shop.id_product_attribute = @attributeId));
                IF @defaultId = 0 THEN
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@attributeId, @productId, '0', @sizePriceImpact, '0', '0', '0', '1',
                            @sizePriceDefault,
                            '0000-00-00', defaultShopIdIn);
                ELSE
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@attributeId, @productId, '0', @sizePriceImpact, '0', '0', '0', '1',
                            @sizePriceDefault,
                            '0000-00-00', defaultShopIdIn);
                END IF;

                # ? Сопоставление ID размера и ID атрибута.
                INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                VALUES (@sizeId, @attributeId);

                # ? Влияние на цену и вес.
                INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                VALUES (@productId, @sizeId, 0.000000, 0.00)
                ON DUPLICATE KEY UPDATE price = '0.00', weight = '0.000000';

            ELSE
                # ? Если размер есть.
                # ? Теперь проверяем, создана ли комбинация на этот размер.
                # ? Должен выполняться если мы получили size_id.
                SET @sizeId = (SELECT ps_attribute.id_attribute
                               FROM ps_attribute
                                        INNER JOIN ps_attribute_lang
                                                   ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                               WHERE ps_attribute.id_attribute_group = 1
                                 AND ps_attribute_lang.name = @sizeName);

                SET @attributeId = (EXISTS(SELECT ps_product_attribute.id_product_attribute
                                           FROM ps_product_attribute
                                                    INNER JOIN ps_product_attribute_combination
                                                               ON ps_product_attribute.id_product_attribute =
                                                                  ps_product_attribute_combination.id_product_attribute
                                           WHERE ps_product_attribute.id_product = @productId
                                             AND ps_product_attribute_combination.id_attribute = @sizeId));

                # ? Если запрос не возвращает ничего.
                IF @attributeId = 0 THEN

                    # ? Теперь создаем комбинацию товар/размер. Тут же вставляем остаток.
                    INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price,
                                                      price, ecotax, quantity, weight, unit_price_impact,
                                                      minimal_quantity)
                    VALUES (DEFAULT, @productId, productReferenceIn, 0, 0, 0, @sizeQuantity, 0, 0, 1);

                    # ? Загоняем последний созданный ID в переменную.
                    SET @attributeId = LAST_INSERT_ID();

                    # ? И еще раз остаток.
                    INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                    depends_on_stock, out_of_stock)
                    VALUES (@productId, @attributeId, defaultShopIdIn, 0, @sizeQuantity, 0, 2);

                    # ? Привязываем вновь созданную комбинацию магазину.
                    # ? Проверяем на привязку к магазину по умолчанию.
                    SET @defaultId = (SELECT ps_product_attribute_shop.id_product_attribute
                                      FROM ps_product_attribute_shop
                                      WHERE ps_product_attribute_shop.id_product = @productId
                                        AND ps_product_attribute_shop.id_shop = defaultShopIdIn
                                        AND ps_product_attribute_shop.id_product_attribute = @attributeId);
                    IF @defaultId = '' THEN
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@attributeId, @productId, '0', @sizePriceImpact, '0', '0', '0', '1',
                                @sizePriceDefault,
                                '0000-00-00', defaultShopIdIn);
                    ELSE
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@attributeId, @productId, '0', @sizePriceImpact, '0', '0', '0', '1',
                                @sizePriceDefault,
                                '0000-00-00', defaultShopIdIn);
                    END IF;

                    # ? Сопоставление ID размера и ID атрибута.
                    INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                    VALUES (@sizeId, @attributeId);

                    # ? Влияние на цену и вес.
                    INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                    VALUES (@productId, @sizeId, 0.000000, @sizePriceImpact)
                    ON DUPLICATE KEY UPDATE price = @sizePriceImpact, weight = '0.000000';

                    # ? Если вернул - просто обновляем остатки.
                ELSE
                    SET @attributeId = (SELECT ps_product_attribute.id_product_attribute
                                        FROM ps_product_attribute
                                                 INNER JOIN ps_product_attribute_combination
                                                            ON ps_product_attribute.id_product_attribute =
                                                               ps_product_attribute_combination.id_product_attribute
                                        WHERE ps_product_attribute.id_product = @productId
                                          AND ps_product_attribute_combination.id_attribute = @sizeId);
                    UPDATE ps_product_attribute
                    SET quantity = @sizeQuantity,
                        price    = @sizePriceImpact
                    WHERE id_product = @productId
                      AND id_product_attribute = @attributeId;
                    UPDATE ps_stock_available
                    SET quantity = @sizeQuantity
                    WHERE id_product_attribute = @attributeId
                      AND id_product = @productId;
                END IF;
            END IF;
        END WHILE;
END;

