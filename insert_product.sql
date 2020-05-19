create
    definer = srebro_root@`%` procedure insert_product(IN product_id_in varchar(255),
                                                       IN product_quantity_in varchar(255),
                                                       IN default_shop_id_in varchar(255),
                                                       IN product_price_in varchar(255),
                                                       IN product_wholesale_price_in varchar(255),
                                                       IN product_reference_in varchar(255),
                                                       IN product_weight_in varchar(255),
                                                       IN product_meta_description_in varchar(255),
                                                       IN product_meta_keywords_in varchar(255),
                                                       IN product_meta_title_in varchar(255),
                                                       IN product_description_in varchar(10000),
                                                       IN product_description_short_in varchar(1000),
                                                       IN product_name_in varchar(255),
                                                       IN list_size_name_in varchar(255),
                                                       IN list_category_id_in varchar(1000),
                                                       IN list_feature_id_in varchar(255))
BEGIN


    # !  Универсальный, работает как на 1,6 так и на 1,7

    # * Очистка временной таблицы, в которую мы пишем id товара.
    TRUNCATE z_product;

    # Заменяем запятую на точку, поскольку в базе разделитель - точка
    SET product_weight_in = REPLACE(product_weight_in, ',', '.');
    SET product_price_in = REPLACE(product_price_in, ',', '.');
    SET product_wholesale_price_in = REPLACE(product_wholesale_price_in, ',', '.');
    SET list_size_name_in = REPLACE(list_size_name_in, ',', '.');

    # * Работа с описанием товара, убираем переносы строк, ставим теги. Иначе ломает абзацы и прочую хрень. */
    # ? Заменяем перевод строки на теги начала/конца абзаца.
    SET product_description_in = REPLACE(product_description_in, '\n', '</p><p>');

    # ? Два перевода строки стали вот такими '</p><p></p><p>'. Заменяем на правильное. Заодно расставляем тег выключки по ширине.
    SET product_description_in = REPLACE(product_description_in, '</p><p></p><p>',
                                         '</p>\r\n<p style="text-align:justify;">');

    # ? Ставим тег абзаца в начале текста и в конце текста.
    SET product_description_in = CONCAT('<p style="text-align:justify;">', product_description_in, '</p>');


    # * Отсюда и до конца IF - вставка самого товара.

    # ? Если id_product_in пустой, значит товар еще не вставлялся на сайт.
    IF product_id_in = '' THEN

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
        VALUES (NULL, 0, 0, 2, default_shop_id_in, 0, 0, 0, '', '', '0.000000', product_quantity_in, 1,
                product_price_in,
                product_wholesale_price_in, '', '0.000000', '0.00', product_reference_in, '', '', product_weight_in,
                '0.000000', '0.000000',
                '0.000000', 2, 0, 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, 0, 0,
                current_timestamp, current_timestamp, 0, 3);

        # ? Записываем id товара во временную таблицу.
        INSERT INTO z_product (id_product) VALUE (LAST_INSERT_ID());

        # ? Даем переменной последний вставленный id.
        SET @product_id = (LAST_INSERT_ID());

        # ? Вставка товара. Привязка к магазину.
        INSERT INTO `ps_product_shop` (`id_product`, `id_shop`, `id_category_default`, `id_tax_rules_group`, `on_sale`,
                                       `online_only`, `ecotax`, `minimal_quantity`, `price`, `wholesale_price`, `unity`,
                                       `unit_price_ratio`, `additional_shipping_cost`, `customizable`,
                                       `uploadable_files`, `text_fields`, `active`, `redirect_type`,
                                       `available_for_order`, `available_date`, `condition`, `show_price`, `indexed`,
                                       `visibility`, `cache_default_attribute`, `advanced_stock_management`, `date_add`,
                                       `date_upd`, `pack_stock_type`)
        VALUES (@product_id, default_shop_id_in, 2, 0, 0, 0, '0.000000', 1, product_price_in,
                product_wholesale_price_in, '',
                '0.000000', '0.00', 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, current_timestamp,
                current_timestamp, 3);

        # ? Вставка товара. Описание товара.
        INSERT INTO `ps_product_lang` (`id_product`, `id_shop`, `id_lang`, `description`, `description_short`,
                                       `link_rewrite`, `meta_description`, `meta_keywords`, `meta_title`, `name`,
                                       `available_now`, `available_later`)
        VALUES (@product_id, default_shop_id_in, 1, product_description_in, product_description_short_in,
                (SELECT transliterate(CONCAT(product_reference_in, '-', product_name_in))), product_meta_description_in,
                product_meta_keywords_in,
                product_meta_title_in, product_name_in, 'В наличии!', 'Под заказ!');

        # ? Вставка товара. Остатки.
        INSERT INTO ps_stock_available (id_stock_available, id_product, id_product_attribute, id_shop, id_shop_group,
                                        quantity, depends_on_stock, out_of_stock)
        VALUES (NULL, @product_id, 0, default_shop_id_in, 0, product_quantity_in, 0, 2);

    ELSE
        # ? Даем переменной id товара. Сделано потому что дальше у нас используется id товара, будем теперь к ней обращаться
        SET @product_id = (product_id_in);

        # ? Обновление товара
        UPDATE ps_product
        SET quantity        = product_quantity_in,
            price           = product_price_in,
            wholesale_price = product_wholesale_price_in,
            date_upd        = current_timestamp
        WHERE id_product = product_id_in;

        # ? Обновление товара. Привязка к магазину.
        UPDATE ps_product_shop
        SET price           = product_price_in,
            wholesale_price = product_wholesale_price_in,
            date_upd        = current_timestamp
        WHERE id_product = product_id_in;

        # ? Обновление товара. Описание товара.
        UPDATE ps_product_lang
        SET name              = product_name_in,
            meta_description  = product_meta_description_in,
            meta_keywords     = product_meta_keywords_in,
            meta_title        = product_meta_title_in,
            description       = product_description_in,
            description_short = product_description_short_in
        WHERE id_product = product_id_in;

        # ? Обновление товара. Остатки.
        UPDATE ps_stock_available
        SET quantity = product_quantity_in
        WHERE id_product = product_id_in;

        # ? Записываем id товара во временную таблицу. 
        INSERT INTO z_product (id_product)
            VALUE (product_id_in);

    END IF;


    # * Присваиваем категории товару.

    # ? Сначала удаляем  существующие привязки категорий товару.
    DELETE
    FROM ps_category_product
    WHERE id_product = @product_id;

    # ? Присваиваем категорию по умолчанию. Весь товар должен быть присвоен главной категории.
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    SELECT '2',
           @product_id,
           MAX(`position`) + 1
    FROM ps_category_product;
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    SELECT '10',
           @product_id,
           MAX(`position`) + 1
    FROM ps_category_product;

    # ? Присваеваем список категорий переменной.
    SET @list = list_category_id_in;

    # ? Цикл по списку.
    WHILE @list != ''
        DO
            # ? Извлекаем одну категорию
            SET @one_category = SUBSTRING_INDEX(@list, ':', 1);
            # Уменьшаем строку первоначальных параметров на длинну одной категории
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@one_category) + 2);

            # ? Вставка категории
            INSERT `ps_category_product` (`id_category`, id_product, `position`)
            SELECT @one_category,
                   @product_id,
                   MAX(`position`) + 1
            FROM ps_category_product;
        END WHILE;


    # * Присваиваем характеристики товару.

    # ? Удаляем привязки характеристики товара.
    DELETE
    FROM ps_feature_product
    WHERE id_product = @product_id;

    # ? Присваеваем список характеристик переменной.
    SET @list = list_feature_id_in;

    # ? Цикл по списку.
    WHILE @list != ''
        DO
            # ? Извлекаем одну характеристику.
            SET @feature_one_list = SUBSTRING_INDEX(@list, ';', 1);
            # ? Извлекаем ID характеристики.
            SET @feature_value_id_in = SUBSTRING_INDEX(@feature_one_list, ':', 1);
            # ? Извлекаем значение характеристики.
            SET @feature_value_in = SUBSTRING_INDEX((SUBSTRING_INDEX(@feature_one_list, ':', 2)), ':', -1);
            # ? Уменьшаем строку первоначальных параметров на длинну одной категории.
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@feature_one_list) + 2);

            # ? Вот тут мы делаем проверку на существование названия такой характеристики. Если есть - просто присваиваем товару. Иначе будет куча одинаковых записей.
            SET @feature_value_id_old = (SELECT EXISTS(SELECT ps_feature_value_lang.id_feature_value
                                                       FROM ps_feature_value_lang
                                                       WHERE ps_feature_value_lang.value = @feature_value_in));

            # ? Есть такая характеристика.
            IF @feature_value_id_old = 1 THEN

                # Присваиваем товару
                INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                VALUES (@feature_value_id_in, @product_id, (SELECT ps_feature_value_lang.id_feature_value
                                                            FROM ps_feature_value_lang
                                                            WHERE ps_feature_value_lang.value = @feature_value_in
                                                            LIMIT 1));

                # ? Нет такой характеристики.
            ELSE

                # Создаем новую характеристику.
                INSERT IGNORE `ps_feature_value` (id_feature_value, id_feature, custom)
                VALUES (DEFAULT, @feature_value_id_in, 0);
                INSERT IGNORE `ps_feature_value_lang` (id_feature_value, id_lang, value)
                VALUES (LAST_INSERT_ID(), 1, @feature_value_in);
                INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                VALUES (@feature_value_id_in, @product_id, LAST_INSERT_ID());

            END IF;

        END WHILE;


    # * Отсюда и до конца while - вставка размеров товара.

    # ? Удаляем размеры из базы.
    DELETE
    FROM ps_attribute_impact
    WHERE id_product = @product_id;
    BEGIN
        DECLARE id_product_att int;
        DECLARE done integer DEFAULT 0;
        DECLARE cur1 CURSOR FOR
            SELECT id_product_attribute AS id_product_att
            FROM ps_product_attribute
            WHERE id_product = @product_id;
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
    SET @list = list_size_name_in;

    # ? Цикл по списку
    WHILE @list != ''
        DO
            # ? Извлекаем один размер.
            SET @size_one = SUBSTRING_INDEX(@list, ';', 1);
            # ? Извлекаем название размера
            SET @size_name = SUBSTRING_INDEX(@size_one, ':', 1);
            # ? Извлекаем количество размера
            SET @size_quantity = SUBSTRING_INDEX((SUBSTRING_INDEX(@size_one, ':', 2)), ':', -1);
            # ? Извлекаем цену
            SET @size_price = SUBSTRING_INDEX((SUBSTRING_INDEX(@size_one, ':', 3)), ':', -1);
            # ? Уменьшаем строку первоначальных параметров на длинну одного размера
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@size_one) + 2);
            # ? Влияние на цену. Высчитываем разницу
            SET @size_price_impact = (@size_price - product_price_in);

            # ? Минимальная цена должна быть по умолчанию. Минимальной считаем цену price_in
            IF @size_price_impact > 0 THEN
                SET @size_price_default = NULL;
            ELSE
                SET @size_price_default = 1;
            END IF;

            # ? Проверяем есть ли такой размер в базе.
            SET @size_id = (SELECT EXISTS(SELECT ps_attribute.id_attribute
                                          FROM ps_attribute
                                                   INNER JOIN ps_attribute_lang
                                                              ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                                          WHERE ps_attribute.id_attribute_group = 1
                                            AND ps_attribute_lang.name = @size_name));

            # ? Если размера нет.
            IF @size_id = 0 THEN
                # ? Вставка размера.
                INSERT INTO ps_attribute (id_attribute, id_attribute_group, color, `position`)
                SELECT NULL,
                       1,
                       '',
                       MAX(`position`) + 1
                FROM ps_attribute
                WHERE id_attribute_group = 1;

                # ? Загоняем последний созданный ID в переменную.
                SET @size_id = LAST_INSERT_ID();

                # ? Присваиваем размер языку магазина.
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name)
                VALUES (@size_id, 1, @size_name);
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name)
                VALUES (@size_id, 2, @size_name);

                # ? Присваиваем размер магазину.
                INSERT INTO ps_attribute_shop (id_attribute, id_shop)
                VALUES (@size_id, default_shop_id_in);

                # ? Теперь создаем комбинацию товар/размер. Тут же вставляем остаток.
                INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price, price,
                                                  ecotax, quantity, weight, unit_price_impact, minimal_quantity)
                VALUES (DEFAULT, @product_id, product_reference_in, 0, 0, 0, @size_quantity, 0, 0, 1);

                # ? Загоняем последний созданный ID в переменную.
                SET @attribute_id = LAST_INSERT_ID();

                # ? И еще раз остаток
                INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                depends_on_stock, out_of_stock)
                VALUES (@product_id, @attribute_id, default_shop_id_in, 0, @size_quantity, 0, 2);

                # ? Привязываем вновь созданную комбинацию магазину.
                # ? Проверяем на привязку к магазину по умолчанию. Нужно для мультимагазина.
                SET @default_id = (SELECT EXISTS(SELECT ps_product_attribute_shop.id_product_attribute
                                                 FROM ps_product_attribute_shop
                                                 WHERE ps_product_attribute_shop.id_product = @product_id
                                                   AND ps_product_attribute_shop.id_shop = default_shop_id_in
                                                   AND ps_product_attribute_shop.id_product_attribute = @attribute_id));
                IF @default_id = 0 THEN
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@attribute_id, @product_id, '0', @size_price_impact, '0', '0', '0', '1',
                            @size_price_default,
                            '0000-00-00', default_shop_id_in);
                ELSE
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@attribute_id, @product_id, '0', @size_price_impact, '0', '0', '0', '1',
                            @size_price_default,
                            '0000-00-00', default_shop_id_in);
                END IF;

                # ? Сопоставление ID размера и ID атрибута.
                INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                VALUES (@size_id, @attribute_id);

                # ? Влияние на цену и вес.
                INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                VALUES (@product_id, @size_id, 0.000000, 0.00)
                ON DUPLICATE KEY UPDATE price = '0.00', weight = '0.000000';

            ELSE
                # ? Если размер есть.
                # ? Теперь проверяем, создана ли комбинация на этот размер.
                # ? Должен выполняться если мы получили size_id.
                SET @size_id = (SELECT ps_attribute.id_attribute
                                FROM ps_attribute
                                         INNER JOIN ps_attribute_lang
                                                    ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                                WHERE ps_attribute.id_attribute_group = 1
                                  AND ps_attribute_lang.name = @size_name);

                SET @attribute_id = (EXISTS(SELECT ps_product_attribute.id_product_attribute
                                            FROM ps_product_attribute
                                                     INNER JOIN ps_product_attribute_combination
                                                                ON ps_product_attribute.id_product_attribute =
                                                                   ps_product_attribute_combination.id_product_attribute
                                            WHERE ps_product_attribute.id_product = @product_id
                                              AND ps_product_attribute_combination.id_attribute = @size_id));

                # ? Если запрос не возвращает ничего.
                IF @attribute_id = 0 THEN

                    # ? Теперь создаем комбинацию товар/размер. Тут же вставляем остаток.
                    INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price,
                                                      price, ecotax, quantity, weight, unit_price_impact,
                                                      minimal_quantity)
                    VALUES (DEFAULT, @product_id, product_reference_in, 0, 0, 0, @size_quantity, 0, 0, 1);

                    # ? Загоняем последний созданный ID в переменную.
                    SET @attribute_id = LAST_INSERT_ID();

                    # ? И еще раз остаток.
                    INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                    depends_on_stock, out_of_stock)
                    VALUES (@product_id, @attribute_id, default_shop_id_in, 0, @size_quantity, 0, 2);

                    # ? Привязываем вновь созданную комбинацию магазину.
                    # ? Проверяем на привязку к магазину по умолчанию.
                    SET @default_id = (SELECT ps_product_attribute_shop.id_product_attribute
                                       FROM ps_product_attribute_shop
                                       WHERE ps_product_attribute_shop.id_product = @product_id
                                         AND ps_product_attribute_shop.id_shop = default_shop_id_in
                                         AND ps_product_attribute_shop.id_product_attribute = @attribute_id);
                    IF @default_id = '' THEN
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@attribute_id, @product_id, '0', @size_price_impact, '0', '0', '0', '1',
                                @size_price_default,
                                '0000-00-00', default_shop_id_in);
                    ELSE
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@attribute_id, @product_id, '0', @size_price_impact, '0', '0', '0', '1',
                                @size_price_default,
                                '0000-00-00', default_shop_id_in);
                    END IF;

                    # ? Сопоставление ID размера и ID атрибута.
                    INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                    VALUES (@size_id, @attribute_id);

                    # ? Влияние на цену и вес.
                    INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                    VALUES (@product_id, @size_id, 0.000000, @size_price_impact)
                    ON DUPLICATE KEY UPDATE price = @size_price_impact, weight = '0.000000';

                    # ? Если вернул - просто обновляем остатки.
                ELSE
                    SET @attribute_id = (SELECT ps_product_attribute.id_product_attribute
                                         FROM ps_product_attribute
                                                  INNER JOIN ps_product_attribute_combination
                                                             ON ps_product_attribute.id_product_attribute =
                                                                ps_product_attribute_combination.id_product_attribute
                                         WHERE ps_product_attribute.id_product = @product_id
                                           AND ps_product_attribute_combination.id_attribute = @size_id);
                    UPDATE ps_product_attribute
                    SET quantity = @size_quantity,
                        price    = @size_price_impact
                    WHERE id_product = @product_id
                      AND id_product_attribute = @attribute_id;
                    UPDATE ps_stock_available
                    SET quantity = @size_quantity
                    WHERE id_product_attribute = @attribute_id
                      AND id_product = @product_id;

                END IF;
            END IF;
        END WHILE;

END;

