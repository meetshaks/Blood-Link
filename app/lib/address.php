<?php
declare(strict_types=1);

function get_districts_with_area_flags(PDO $pdo): array
{
    return $pdo->query(
        'SELECT d.id, d.division_id, d.name_bn, d.name_en,
                EXISTS (
                    SELECT 1
                    FROM city_corporations cc
                    JOIN city_corporation_areas ca
                      ON ca.city_corporation_id = cc.id
                     AND ca.is_active = 1
                    WHERE cc.district_id = d.id
                ) AS has_areas
         FROM districts d
         ORDER BY d.name_bn'
    )->fetchAll();
}
