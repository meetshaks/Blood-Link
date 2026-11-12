<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/donations.php';

function search_donors(array $filters, int $page = 1, int $perPage = 12): array
{
    $pdo = db();
    $previousEmulation = (bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $gap = max(1, (int) setting_value('donation_gap_days', '120'));
        $params = ['blood_group' => (string) ($filters['bg'] ?? '')];
        $locationColumns = [
            'division' => 'division_id',
            'district' => 'district_id',
            'upazila' => 'upazila_id',
            'union' => 'union_id',
            'city_corporation' => 'city_corporation_id',
            'city_corporation_area' => 'city_corporation_area_id',
        ];
        $conditions = [];
        $hasLocationFilter = false;
        foreach ($locationColumns as $key => $column) {
            $value = filter_var($filters[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $placeholder = $key . '_filter';
                $conditions[] = "COALESCE(ul.{$column}, u.{$column}) = :{$placeholder}";
                $params[$placeholder] = (int) $value;
                $hasLocationFilter = true;
            }
        }
        $availabilityStatus = (string) ($filters['availability_status'] ?? (!empty($filters['only_available']) ? 'available' : 'all'));
        if ($availabilityStatus === 'available') {
            $conditions[] = '(u.last_donation_date IS NULL OR DATEDIFF(CURDATE(), u.last_donation_date) >= :gap_filter)';
            $params['gap_filter'] = $gap;
        } elseif ($availabilityStatus === 'resting') {
            $conditions[] = 'u.last_donation_date IS NOT NULL AND DATEDIFF(CURDATE(), u.last_donation_date) < :gap_filter';
            $params['gap_filter'] = $gap;
        }
        $where = [
            "u.status = 'approved'",
            'u.available_toggle = 1',
            '(u.is_suspended = 0 OR u.is_suspended = 1 AND u.suspended_until IS NOT NULL AND u.suspended_until <= CURDATE())',
            'u.blood_group = :blood_group',
        ];
        if ($conditions !== []) $where = array_merge($where, $conditions);
        $whereSql = implode(' AND ', $where);
        $countFromSql = 'FROM users u';
        if ($hasLocationFilter) {
            $countFromSql .= ' LEFT JOIN user_locations ul ON ul.user_id = u.id
                AND ul.is_visible = 1';
        }
        $fromSql = 'FROM users u
            JOIN districts d ON d.id = u.district_id
            LEFT JOIN upazilas up ON up.id = u.upazila_id
            LEFT JOIN unions un ON un.id = u.union_id
            LEFT JOIN city_corporations cc ON cc.id = u.city_corporation_id
            LEFT JOIN city_corporation_areas hca ON hca.id = u.city_corporation_area_id
            LEFT JOIN user_locations ul ON ul.user_id = u.id
                AND ul.is_visible = 1
            LEFT JOIN divisions wdv ON wdv.id = ul.division_id
            LEFT JOIN districts wd ON wd.id = ul.district_id
            LEFT JOIN upazilas wup ON wup.id = ul.upazila_id
            LEFT JOIN unions wun ON wun.id = ul.union_id
            LEFT JOIN city_corporations wcc ON wcc.id = ul.city_corporation_id';
        $fromSql .= ' LEFT JOIN city_corporation_areas wca ON wca.id = ul.city_corporation_area_id';

        $count = $pdo->prepare("SELECT COUNT(*) {$countFromSql} WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $params['gap_available'] = $gap;
        $params['gap_days_left'] = $gap;
        $params['gap_phone'] = $gap;
        $params['limit'] = ($page - 1) * $perPage;
        $params['per_page'] = $perPage;
        $sql = "SELECT u.id, u.name, u.phone, u.photo, u.blood_group, u.gender, u.village,
            TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age, u.last_donation_date,
            d.name_bn AS district_bn, d.name_en AS district_en,
            up.name_bn AS upazila_bn, up.name_en AS upazila_en,
            un.name_bn AS union_bn, un.name_en AS union_en, un.type AS union_type,
            cc.name_bn AS cc_bn, cc.name_en AS cc_en,
            hca.name_bn AS cc_area_bn, hca.name_en AS cc_area_en, hca.ward_no AS cc_area_ward,
            dvi.name_bn AS division_bn, dvi.name_en AS division_en,
            wdv.name_bn AS work_division_bn, wdv.name_en AS work_division_en,
            wd.name_bn AS work_district_bn, wd.name_en AS work_district_en,
            wup.name_bn AS work_upazila_bn, wup.name_en AS work_upazila_en,
            wun.name_bn AS work_union_bn, wun.name_en AS work_union_en, wun.type AS work_union_type,
            wcc.name_bn AS work_cc_bn, wcc.name_en AS work_cc_en,
            wca.name_bn AS work_cc_area_bn, wca.name_en AS work_cc_area_en, wca.ward_no AS work_cc_area_ward,
            ul.area_text AS work_area_text, ul.updated_at AS work_updated_at,
            CASE WHEN ul.id IS NULL THEN 'home' ELSE 'work' END AS matched_on,
            CASE WHEN ul.id IS NULL THEN 'home' ELSE 'work' END AS matched_location,
            CASE WHEN u.present_at = 'work' AND ul.id IS NOT NULL THEN 'work' ELSE 'home' END AS present_at,
            CASE WHEN ul.id IS NULL THEN NULL ELSE ul.is_visible END AS work_is_visible,
            CASE WHEN u.last_donation_date IS NULL OR DATEDIFF(CURDATE(), u.last_donation_date) >= :gap_available THEN 1 ELSE 0 END AS is_available,
            CASE WHEN u.last_donation_date IS NULL THEN 0 ELSE GREATEST(0, :gap_days_left - DATEDIFF(CURDATE(), u.last_donation_date)) END AS days_left,
            CASE WHEN u.last_donation_date IS NULL OR DATEDIFF(CURDATE(), u.last_donation_date) >= :gap_phone THEN u.phone ELSE NULL END AS phone
            {$fromSql}
            LEFT JOIN divisions dvi ON dvi.id = u.division_id
            WHERE {$whereSql}
            ORDER BY is_available DESC, days_left ASC, u.name ASC
            LIMIT :per_page OFFSET :limit";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $donors = $stmt->fetchAll();
        $donationCounts = [];
        $donorIds = array_values(array_filter(array_map(static fn (array $donor): int => (int) $donor['id'], $donors)));
        if ($donorIds !== []) {
            $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
            $donationStmt = $pdo->prepare("SELECT user_id, COUNT(*) AS donation_count FROM donations WHERE user_id IN ({$placeholders}) GROUP BY user_id");
            $donationStmt->execute($donorIds);
            foreach ($donationStmt->fetchAll() as $row) $donationCounts[(int) $row['user_id']] = (int) $row['donation_count'];
        }
        foreach ($donors as &$donor) {
            $donor['donation_count'] = $donationCounts[(int) $donor['id']] ?? 0;
            $donor['badge'] = donor_badge_tier((int) $donor['donation_count']);
            $donor['home_location'] = [
                'division_bn' => $donor['division_bn'], 'division_en' => $donor['division_en'],
                'district_bn' => $donor['district_bn'], 'district_en' => $donor['district_en'],
                'upazila_bn' => $donor['upazila_bn'], 'upazila_en' => $donor['upazila_en'],
                'union_bn' => $donor['union_bn'], 'union_en' => $donor['union_en'],
                'union_type' => $donor['union_type'], 'city_corporation_bn' => $donor['cc_bn'],
                'city_corporation_en' => $donor['cc_en'], 'area_text' => $donor['village'],
                'city_corporation_area_bn' => $donor['cc_area_bn'], 'city_corporation_area_en' => $donor['cc_area_en'], 'city_corporation_area_ward' => $donor['cc_area_ward'],
            ];
            $donor['work_location'] = $donor['work_district_bn'] === null ? null : [
                'division_bn' => $donor['work_division_bn'], 'division_en' => $donor['work_division_en'],
                'district_bn' => $donor['work_district_bn'], 'district_en' => $donor['work_district_en'],
                'upazila_bn' => $donor['work_upazila_bn'], 'upazila_en' => $donor['work_upazila_en'],
                'union_bn' => $donor['work_union_bn'], 'union_en' => $donor['work_union_en'],
                'union_type' => $donor['work_union_type'], 'city_corporation_bn' => $donor['work_cc_bn'],
                'city_corporation_en' => $donor['work_cc_en'], 'city_corporation_area_bn' => $donor['work_cc_area_bn'], 'city_corporation_area_en' => $donor['work_cc_area_en'], 'city_corporation_area_ward' => $donor['work_cc_area_ward'], 'area_text' => $donor['work_area_text'],
            ];
        }
        unset($donor);
        if (setting_value('phone_visibility', 'public') === 'login' && session_get('uid') === null) {
            foreach ($donors as &$donor) $donor['phone'] = null;
            unset($donor);
        }
        return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'donors' => $donors];
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previousEmulation);
    }
}
