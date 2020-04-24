create procedure insert_product(IN id_product_in varchar(255), IN quantity_in varchar(255),
                                IN id_shop_default_in varchar(255), IN price_in varchar(255),
                                IN wholesale_price_in varchar(255), IN reference_in varchar(255),
                                IN weight_in varchar(255), IN meta_description_in varchar(255),
                                IN meta_keywords_in varchar(255), IN meta_title_in varchar(255),
                                IN description_in varchar(10000), IN description_short_in varchar(1000),
                                IN name_in varchar(255), IN list_name_razmer_in varchar(255),
                                IN list_of_category varchar(1000), IN id_feature_list_in varchar(255))
BEGIN

    # Универсальный, работает как на 1,6 так и на 1,7

    # Очистка временной таблицы, в которую мы пишем id товара.
    TRUNCATE z_product;

    # Заменяем запятую на точку, поскольку в базе разделитель - точка
    SET weight_in = REPLACE(weight_in, ',', '.');

    # Заменяем запятую на точку, поскольку в базе разделитель - точка
    SET price_in = REPLACE(price_in, ',', '.');

    # Заменяем запятую на точку, поскольку в базе разделитель - точка
    SET wholesale_price_in = REPLACE(wholesale_price_in, ',', '.');

    # Работа с описанием товара, убираем переносы строк, ставим теги. Иначе ломает абзацы и прочую хрень.
    SET description_in = REPLACE(description_in, '\n', '</p><p>'); # Заменяем перевод строки на теги начала/конца абзаца.
    SET description_in = REPLACE(description_in, '</p><p></p><p>',
                                 '</p>\r\n<p style="text-align:justify;">'); # Два перевода строки стали вот такими '</p><p></p><p>'. Заменяем на правильное. Заодно расставляем тег выключки по ширине
    SET description_in = CONCAT('<p style="text-align:justify;">', description_in, '</p>');
    # Ставим тег абзаца в начале текста и в конце текста


    /* Отсюда и до конца IF - вставка самого товара.*/

    # Если id_product_in пустой, значит товар еще не вставлялся на сайт.
    IF id_product_in = ''
    THEN

        # Вставка товара
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
        VALUES (NULL, 0, 0, 2, id_shop_default_in, 0, 0, 0, '', '', '0.000000', quantity_in, 1, price_in,
                wholesale_price_in, '', '0.000000', '0.00', reference_in, '', '', weight_in, '0.000000', '0.000000',
                '0.000000', 2, 0, 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, 0, 0,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0, 3);

        # Записываем id товара во временную таблицу.
        INSERT INTO z_product (id_product) VALUE (LAST_INSERT_ID());

        # Даем переменной последний вставленный id.
        SET @id_product = (LAST_INSERT_ID());

        # Вставка товара. Привязка к магазину.
        INSERT INTO `ps_product_shop` (`id_product`, `id_shop`, `id_category_default`, `id_tax_rules_group`, `on_sale`,
                                       `online_only`, `ecotax`, `minimal_quantity`, `price`, `wholesale_price`, `unity`,
                                       `unit_price_ratio`, `additional_shipping_cost`, `customizable`,
                                       `uploadable_files`, `text_fields`, `active`, `redirect_type`,
                                       `available_for_order`, `available_date`, `condition`, `show_price`, `indexed`,
                                       `visibility`, `cache_default_attribute`, `advanced_stock_management`, `date_add`,
                                       `date_upd`, `pack_stock_type`)
        VALUES (@id_product, id_shop_default_in, 2, 0, 0, 0, '0.000000', 1, price_in, wholesale_price_in, '',
                '0.000000', '0.00', 0, 0, 0, 1, '404', 1, '0000-00-00', 'new', 1, 1, 'both', 0, 0, CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP, 3);

        # Вставка товара. Описание товара.
        INSERT INTO `ps_product_lang` (`id_product`, `id_shop`, `id_lang`, `description`, `description_short`,
                                       `link_rewrite`, `meta_description`, `meta_keywords`, `meta_title`, `name`,
                                       `available_now`, `available_later`)
        VALUES (@id_product, id_shop_default_in, 1, description_in, description_short_in,
                (SELECT transliterate(CONCAT(reference_in, '-', name_in))), meta_description_in, meta_keywords_in,
                meta_title_in, name_in, 'В наличии!', 'Под заказ!');

        # Вставка товара. Остатки.
        INSERT INTO ps_stock_available (id_stock_available, id_product, id_product_attribute, id_shop, id_shop_group,
                                        quantity, depends_on_stock, out_of_stock)
        VALUES (NULL, @id_product, 0, id_shop_default_in, 0, quantity_in, 0, 2);

    ELSE
        # Даем переменной id товара. Сделано потому что дальше у нас используется id товара, будем теперь к ней обращаться
        SET @id_product = (id_product_in);
        # ОБновление товара
        UPDATE ps_product
        SET quantity        = quantity_in,
            price           = price_in,
            wholesale_price = wholesale_price_in,
            date_upd        = CURRENT_TIMESTAMP
        where id_product = id_product_in;

        # Обновление товара. Привязка к магазину.
        UPDATE ps_product_shop
        SET price           = price_in,
            wholesale_price = wholesale_price_in,
            date_upd        = CURRENT_TIMESTAMP
        where id_product = id_product_in;

        # Обновление товара. Описание товара.
        UPDATE ps_product_lang
        SET name              = name_in,
            meta_description  = meta_description_in,
            meta_keywords     = meta_keywords_in,
            meta_title        = meta_title_in,
            description       = description_in,
            description_short =description_short_in
        where id_product = id_product_in;

        # Обновление товара. Остатки.
        UPDATE ps_stock_available SET quantity = quantity_in where id_product = id_product_in;

        # Записываем id товара во временную таблицу.
        INSERT INTO z_product (id_product) VALUE (id_product_in);

    END IF;


    /* Присваиваем категории товару.*/

    # Сначала существующие удаляем привязки категорий товару
    DELETE FROM ps_category_product WHERE id_product = @id_product;

    # Присваиваем категорию по умолчанию. Весь товар должен быть присвоен главной категории
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    Select '2', @id_product, MAX(`position`) + 1
    from ps_category_product;
    INSERT IGNORE `ps_category_product` (`id_category`, id_product, `position`)
    Select '10', @id_product, MAX(`position`) + 1
    from ps_category_product;

    # Присваеваем список категорий переменной
    SET @list = list_of_category;

    # Цикл по списку
    WHILE @list != ''
        DO
            # Извлекаем одну категорию
            SET @one_category = SUBSTRING_INDEX(@list, ':', 1);
            # Уменьшаем строку первоначальных параметров на длинну одной категории
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@one_category) + 2);

            # Вставка категории
            INSERT `ps_category_product` (`id_category`, id_product, `position`)
            Select @one_category, @id_product, MAX(`position`) + 1
            from ps_category_product;
        END WHILE;


    /* Присваиваем характеристики товару.*/

    # Удаляем привязки характеристики товара
    DELETE FROM ps_feature_product WHERE id_product = @id_product;

    # Присваеваем список характеристик переменной
    SET @list = id_feature_list_in;

    # Цикл по списку
    WHILE @list != ''
        DO
            # Извлекаем одну характеристику
            SET @one_feature_list = SUBSTRING_INDEX(@list, ';', 1);
            # Извлекаем ID характеристики
            SET @id_feature_value_in = SUBSTRING_INDEX(@one_feature_list, ':', 1);
            # Извлекаем значение характеристики
            SET @value_in = SUBSTRING_INDEX((SUBSTRING_INDEX(@one_feature_list, ':', 2)), ':', -1);
            # Уменьшаем строку первоначальных параметров на длинну одной категории
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@one_feature_list) + 2);

            # Вот тут мы делаем проверку на существование названия такой характеристики. Если есть - просто присваиваем товару. Иначе будет куча одинаковых записей.
            SET @old_id_feature_value = (SELECT EXISTS(SELECT ps_feature_value_lang.id_feature_value
                                                       FROM ps_feature_value_lang
                                                       WHERE ps_feature_value_lang.value = @value_in));

            # Есть такая характеристика
            IF @old_id_feature_value = 1
            THEN

                # Присваиваем товару
                INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                VALUES (@id_feature_value_in, @id_product, (SELECT ps_feature_value_lang.id_feature_value
                                                            FROM ps_feature_value_lang
                                                            WHERE ps_feature_value_lang.value = @value_in
                                                            LIMIT 1));

                # Нет такой характеристики.
            ELSE

                # Создаем новую характеристику.
                INSERT IGNORE `ps_feature_value` (id_feature_value, id_feature, custom)
                VALUES (DEFAULT, @id_feature_value_in, 1);
                INSERT IGNORE `ps_feature_value_lang` (id_feature_value, id_lang, value)
                VALUES (LAST_INSERT_ID(), 1, @value_in);
                INSERT IGNORE `ps_feature_product` (id_feature, id_product, id_feature_value)
                VALUES (@id_feature_value_in, @id_product, LAST_INSERT_ID());

            END IF;

        END WHILE;


    /* Отсюда и до конца while - вставка размеров товара.*/

    # Удаляем размеры из базы.
    DELETE FROM ps_attribute_impact where id_product = @id_product;
    BEGIN
        DECLARE id_product_att INT;
        DECLARE done integer default 0;
        DECLARE cur1 CURSOR FOR SELECT id_product_attribute as id_product_att
                                FROM ps_product_attribute
                                where id_product = @id_product;
        DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;
        OPEN cur1;
        WHILE done = 0
            DO
                FETCH cur1 INTO id_product_att;
                delete from ps_product_attribute where id_product_attribute = id_product_att;
                delete from ps_product_attribute_shop where id_product_attribute = id_product_att;
                delete from ps_stock_available where id_product_attribute = id_product_att;
            END WHILE;
        CLOSE cur1;
    END;

    # Присваеваем список размеров переменной
    SET @list = list_name_razmer_in;

    # Цикл по списку
    WHILE @list != ''
        DO
            # Извлекаем один размер
            SET @one_razmer = SUBSTRING_INDEX(@list, ';', 1);
            # Извлекаем название размера
            SET @name_razmer = SUBSTRING_INDEX(@one_razmer, ':', 1);
            # Извлекаем количество размера
            SET @kol_razmer = SUBSTRING_INDEX((SUBSTRING_INDEX(@one_razmer, ':', 2)), ':', -1);
            # Уменьшаем строку первоначальных параметров на длинну одного размера
            SET @list = SUBSTRING(@list, CHAR_LENGTH(@one_razmer) + 2);

            # Проверяем есть ли такой размер в базе.
            SET @ID_Razmer = (SELECT EXISTS(SELECT ps_attribute.id_attribute
                                            FROM ps_attribute
                                                     INNER JOIN ps_attribute_lang
                                                                ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                                            WHERE ps_attribute.id_attribute_group = 1
                                              AND ps_attribute_lang.name = '"@name_razmer"'));

            # Если размера нет.
            IF @ID_Razmer = 0
            THEN
                # Вставка размера.
#       end if;
                INSERT INTO ps_attribute (id_attribute, id_attribute_group, `position`)
                Select Null, 1, MAX(`position`) + 1
                from ps_attribute
                where id_attribute_group = 1;

                # Загоняем последний созданный ID в переменную
                SET @ID_Razmer = LAST_INSERT_ID();

                # Присваиваем размер языку магазина
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name) VALUES (@ID_Razmer, 1, @name_razmer);
                INSERT INTO ps_attribute_lang (id_attribute, id_lang, name) VALUES (@ID_Razmer, 2, @name_razmer);

                # Присваиваем размер магазину
                INSERT INTO ps_attribute_shop (id_attribute, id_shop) VALUES (@ID_Razmer, id_shop_default_in);

                # Теперь создаем комбинацию товар/размер. Тут же вставляем остаток
                INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price, price,
                                                  ecotax, quantity, weight, unit_price_impact, minimal_quantity)
                VALUES (DEFAULT, @id_product, reference_in, 0, 0, 0, @kol_razmer, 0, 0, 1);

                # Загоняем последний созданный ID в переменную
                SET @ID_Attribute = LAST_INSERT_ID();

                # И еще раз остаток
                INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                depends_on_stock, out_of_stock)
                VALUES (@id_product, @ID_Attribute, id_shop_default_in, 0, @kol_razmer, 0, 2);

                # Привязываем вновь созданную комбинацию магазину
                # Проверяем на привязку к магазину по умолчанию. Нужно для мультимагазина
                SET @ID_Default = (SELECT EXISTS(SELECT ps_product_attribute_shop.id_product_attribute
                                                 FROM ps_product_attribute_shop
                                                 WHERE ps_product_attribute_shop.id_product = @id_product
                                                   AND ps_product_attribute_shop.id_shop = id_shop_default_in));
                IF @ID_Default = 0
                THEN
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@ID_Attribute, @id_product, '0', '0', '0', '0', '0', '1', 1, '0000-00-00',
                            id_shop_default_in);
                ELSE
                    INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`, `wholesale_price`,
                                                             `price`, `ecotax`, `weight`, `unit_price_impact`,
                                                             `minimal_quantity`, `default_on`, `available_date`,
                                                             `id_shop`)
                    VALUES (@ID_Attribute, @id_product, '0', '0', '0', '0', '0', '1', NULL, '0000-00-00',
                            id_shop_default_in);
                end if;

                # Сопоставление ID размера и ID атрибута
                INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                VALUES (@ID_Razmer, @ID_Attribute);

                # Влияние на цену и вес
                INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                VALUES (@id_product, @ID_Razmer, 0.000000, 0.00)
                ON DUPLICATE KEY UPDATE price = '0.00', weight = '0.000000';

            ELSE
                # Если размер есть
                # Теперь проверяем, создана ли комбинация на этот размер
                # Должен выполняться если мы получили ID_Razmer
                SET @ID_Razmer = (SELECT ps_attribute.id_attribute
                                  FROM ps_attribute
                                           INNER JOIN ps_attribute_lang
                                                      ON ps_attribute.id_attribute = ps_attribute_lang.id_attribute
                                  WHERE ps_attribute.id_attribute_group = 1
                                    AND ps_attribute_lang.name = '"@name_razmer"');

                SET @ID_Attribute = (EXISTS(SELECT ps_product_attribute.id_product_attribute
                                            FROM ps_product_attribute
                                                     INNER JOIN ps_product_attribute_combination
                                                                ON ps_product_attribute.id_product_attribute =
                                                                   ps_product_attribute_combination.id_product_attribute
                                            WHERE ps_product_attribute.id_product = @id_product
                                              AND ps_product_attribute_combination.id_attribute = @ID_Razmer));

                # Если запрос не возвращает ничего
                IF @ID_Attribute = 0
                THEN

                    # Теперь создаем комбинацию товар/размер. Тут же вставляем остаток
                    INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, wholesale_price,
                                                      price, ecotax, quantity, weight, unit_price_impact,
                                                      minimal_quantity)
                    VALUES (DEFAULT, @id_product, reference_in, 0, 0, 0, @kol_razmer, 0, 0, 1);

                    # Загоняем последний созданный ID в переменную
                    SET @ID_Attribute = LAST_INSERT_ID();

                    # И еще раз остаток
                    INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity,
                                                    depends_on_stock, out_of_stock)
                    VALUES (@id_product, @ID_Attribute, id_shop_default_in, 0, @kol_razmer, 0, 2);

                    # Привязываем вновь созданную комбинацию магазину
                    # Проверяем на привязку к магазину по умолчанию
                    SET @ID_Default = (SELECT ps_product_attribute_shop.id_product_attribute
                                       FROM ps_product_attribute_shop
                                       WHERE ps_product_attribute_shop.id_product = @id_product
                                         AND ps_product_attribute_shop.id_shop = id_shop_default_in);
                    IF @ID_Default = ''
                    THEN
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@ID_Attribute, @id_product, '0', '0', '0', '0', '0', '1', 1, '0000-00-00',
                                id_shop_default_in);
                    ELSE
                        INSERT INTO `ps_product_attribute_shop` (`id_product_attribute`, `id_product`,
                                                                 `wholesale_price`, `price`, `ecotax`, `weight`,
                                                                 `unit_price_impact`, `minimal_quantity`, `default_on`,
                                                                 `available_date`, `id_shop`)
                        VALUES (@ID_Attribute, @id_product, '0', '0', '0', '0', '0', '1', NULL, '0000-00-00',
                                id_shop_default_in);
                    end if;

                    # Сопоставление ID размера и ID атрибута
                    INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute)
                    VALUES (@ID_Razmer, @ID_Attribute);

                    # Влияние на цену и вес
                    INSERT INTO ps_attribute_impact (id_product, id_attribute, weight, price)
                    VALUES (@id_product, @ID_Razmer, 0.000000, 0.00)
                    ON DUPLICATE KEY UPDATE price = '0.00', weight = '0.000000';

                    # Если вернул - просто обновляем остатки
                ELSE
                    SET @ID_Attribute = (SELECT ps_product_attribute.id_product_attribute
                                         FROM ps_product_attribute
                                                  INNER JOIN ps_product_attribute_combination
                                                             ON ps_product_attribute.id_product_attribute =
                                                                ps_product_attribute_combination.id_product_attribute
                                         WHERE ps_product_attribute.id_product = @id_product
                                           AND ps_product_attribute_combination.id_attribute = @ID_Razmer);
                    UPDATE ps_product_attribute
                    SET quantity = @kol_razmer
                    WHERE id_product = @id_product
                      AND id_product_attribute = @ID_Attribute;
                    UPDATE ps_stock_available
                    SET quantity = @kol_razmer
                    WHERE id_product_attribute = @ID_Attribute AND id_product = @id_product;

                end if;
            end if;
        end while;

END;

