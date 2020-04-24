create function transliterate(original varchar(512)) returns varchar(512)
BEGIN

    DECLARE translit varchar(512) DEFAULT '';
    DECLARE len int(3) DEFAULT 0;
    DECLARE pos int(3) DEFAULT 1;
    DECLARE letter char(4);

    SET original = TRIM(LOWER(original));
    SET len = CHAR_LENGTH(original);

    WHILE (pos <= len)
        DO
            SET letter = SUBSTRING(original, pos, 1);

            CASE TRUE

                WHEN letter IN ('a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'а', 'а') THEN SET letter = 'a';
                WHEN letter IN ('c', 'c', 'c', 'c') THEN SET letter = 'c';
                WHEN letter IN ('d', 'd', 'д', 'д') THEN SET letter = 'd';
                WHEN letter IN ('e', 'e', 'e', 'e', 'e', 'e', 'e', 'е', 'е') THEN SET letter = 'e';
                WHEN letter IN ('g', 'g') THEN SET letter = 'g';
                WHEN letter IN ('i', 'i', 'i', 'i', 'i', 'и', 'і') THEN SET letter = 'i';
                WHEN letter IN ('k') THEN SET letter = 'k';
                WHEN letter IN ('l', 'l', 'l', 'l') THEN SET letter = 'l';
                WHEN letter IN ('n', 'n', 'n', 'n') THEN SET letter = 'n';
                WHEN letter IN ('o', 'o', 'o', 'o', 'o', 'o', 'o', 'о', 'о') THEN SET letter = 'o';
                WHEN letter IN ('r', 'r', 'р', 'р') THEN SET letter = 'r';
                WHEN letter IN ('s', 's', '?', 's', 'с', 'с') THEN SET letter = 's';
                WHEN letter IN ('t', '?') THEN SET letter = 't';
                WHEN letter IN ('u', 'u', 'u', 'u', 'u', 'u', 'u', 'u') THEN SET letter = 'u';
                WHEN letter IN ('y', 'у', 'у') THEN SET letter = 'y';
                WHEN letter IN ('z', 'z', 'z') THEN SET letter = 'z';

                WHEN letter = 'б' THEN SET letter = 'b';
                WHEN letter = 'в' THEN SET letter = 'v';
                WHEN letter = 'г' THEN SET letter = 'g';
                WHEN letter = 'д' THEN SET letter = 'd';
                WHEN letter = 'ж' THEN SET letter = 'zh';
                WHEN letter = 'з' THEN SET letter = 'z';
                WHEN letter = 'и' THEN SET letter = 'i';
                WHEN letter = 'й' THEN SET letter = 'i';
                WHEN letter = 'к' THEN SET letter = 'k';
                WHEN letter = 'л' THEN SET letter = 'l';
                WHEN letter = 'м' THEN SET letter = 'm';
                WHEN letter = 'н' THEN SET letter = 'n';
                WHEN letter = 'п' THEN SET letter = 'p';
                WHEN letter = 'т' THEN SET letter = 't';
                WHEN letter = 'ф' THEN SET letter = 'f';
                WHEN letter = 'х' THEN SET letter = 'ch';
                WHEN letter = 'ц' THEN SET letter = 'c';
                WHEN letter = 'ч' THEN SET letter = 'ch';
                WHEN letter = 'ш' THEN SET letter = 'sh';
                WHEN letter = 'щ' THEN SET letter = 'shch';
                WHEN letter = 'ъ' THEN SET letter = '';
                WHEN letter = 'ы' THEN SET letter = 'y';
                WHEN letter = 'э' THEN SET letter = 'e';
                WHEN letter = 'ю' THEN SET letter = 'ju';
                WHEN letter = 'я' THEN SET letter = 'ja';

                WHEN letter IN
                     ('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
                      't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9')
                    THEN SET letter = letter;

                ELSE SET letter = '-';

                END CASE;

            SET translit = CONCAT(translit, letter);
            SET pos = pos + 1;
        END WHILE;

    WHILE (translit REGEXP '-{2,}')
        DO
            SET translit = REPLACE(translit, '--', '-');
        END WHILE;

    RETURN TRIM(BOTH '-' FROM translit);

END;

