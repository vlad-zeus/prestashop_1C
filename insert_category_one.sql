create definer = srebro_root@`%` procedure insert_category_one(IN name_category_in varchar(255), IN parent_category_in varchar(255), IN id_category_in varchar(255), IN id_shop_in varchar(255), OUT id_cat_out varchar(255))
BEGIN
    
    # Универсальный, работает как на 1,6 так и на 1,7
    truncate z_category_id;

    # Если нет ID у категории - создаем её
    IF id_category_in = ''
    THEN

      # СКРИПТ ВСТАВКИ КАТЕГОРИЙ.

      # Функция запроса уровня вложенности категории. Необходимо для правильной организации фильтра
      # Оказывается, это уровень вложенности фильтра. Без него, при построении фильтра ломает наглухо дерево категорий. Оказывается parent category - недостаточно.
      SET @level_depth = (SELECT ps_category.level_depth + 1 AS level
                          FROM ps_category
                          WHERE ps_category.id_category = parent_category_in);

      # Вставляем новую категорию на сайт
      INSERT INTO `ps_category` (`id_category`,
                                 `id_parent`,
                                 `id_shop_default`,
                                 `level_depth`,
                                 `nleft`,
                                 `nright`,
                                 `active`,
                                 `date_add`,
                                 `date_upd`)
      VALUES (NULL, parent_category_in, '1', @level_depth, DEFAULT, DEFAULT, '1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

      # Вставляем имя новой категории на сайт
      INSERT INTO `ps_category_lang` (`id_category`,
                                      `id_shop`,
                                      `id_lang`,
                                      `name`,
                                      `description`,
                                      `link_rewrite`,
                                      `meta_title`,
                                      `meta_keywords`,
                                      `meta_description`)
      VALUES (LAST_INSERT_ID(), id_shop_in, '1', name_category_in, '', (SELECT transliterate(name_category_in)), '', '', ''), (LAST_INSERT_ID(), id_shop_in, '2', name_category_in, '', (SELECT transliterate(name_category_in)), '', '', '');

      # Какие категории покупателей видят. В данном случае все. Пока оставил 3 основные группы покупателей.
      INSERT INTO ps_category_group (id_category, id_group)
      VALUES (LAST_INSERT_ID(), 1),
             (LAST_INSERT_ID(), 2),
             (LAST_INSERT_ID(), 3);

      # Какому магазину принадлежит категория. Мультимагазина нет, но на всякий случай передаем id магазина.
      INSERT INTO ps_category_shop (id_category, id_shop, `position`) VALUES (LAST_INSERT_ID(), id_shop_in, DEFAULT);

      INSERT INTO z_category_id (id_category) VALUE (LAST_INSERT_ID());


    ELSE
      # Если в 1С уже есть категория - просто обновляем имя категории и транслитерацию
        UPDATE ps_category_lang
        SET name         = name_category_in,
            link_rewrite = (SELECT transliterate(name_category_in))
        WHERE id_category = id_category_in;
        # Присваиваем выходному параметру ID категории
        INSERT INTO z_category_id (id_category) VALUE (id_category_in);


    end if;
  END;

