<?php
declare(strict_types=1);

/**
 * Canonical profile completion calculation.
 * Used by dashboard.php, complete-profile.php, and apply.php so the
 * percentage is always consistent everywhere.
 */
function profile_completion_pct(int $uid): int {
    $user = pdo()->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$uid]);
    $user = $user->fetch();

    $fetchCount = function(string $table) use ($uid): int {
        return (int)pdo()->query("SELECT COUNT(*) FROM $table WHERE user_id=$uid")->fetchColumn();
    };
    $fetchOne = function(string $table) use ($uid): bool {
        return (bool)pdo()->query("SELECT COUNT(*) FROM $table WHERE user_id=$uid")->fetchColumn();
    };

    $checks = [
        !empty($user['national_id']) && !empty($user['gender']) && !empty($user['face_verified']), // identity + verification
        $fetchCount('user_education')  >= 1,
        $fetchCount('user_languages')  >= 1,
        $fetchOne('user_disability'),
        $fetchCount('user_referees')   >= 3,
        !empty($user['cv_path']),
        $fetchCount('user_experience')    >= 1,
        $fetchCount('user_certificates')  >= 1,
        $fetchCount('user_publications')  >= 1,
    ];

    return (int)round(array_sum(array_map('intval', $checks)) / count($checks) * 100);
}

/**
 * Returns the same checks as an array for display in complete-profile.php sidebar.
 */
function profile_completion_sections(int $uid): array {
    $user = pdo()->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$uid]);
    $user = $user->fetch();

    $count = fn(string $t) => (int)pdo()->query("SELECT COUNT(*) FROM $t WHERE user_id=$uid")->fetchColumn();
    $one   = fn(string $t) => (bool)pdo()->query("SELECT COUNT(*) FROM $t WHERE user_id=$uid")->fetchColumn();

    return [
        ['id'=>'identity',     'label'=>'Identity & Verification', 'required'=>true,  'done'=> !empty($user['national_id']) && !empty($user['gender']) && !empty($user['face_verified'])],
        ['id'=>'education',    'label'=>'Education',               'required'=>true,  'done'=> $count('user_education')  >= 1],
        ['id'=>'languages',    'label'=>'Languages',               'required'=>true,  'done'=> $count('user_languages')  >= 1],
        ['id'=>'disability',   'label'=>'Disability',              'required'=>true,  'done'=> $one('user_disability')],
        ['id'=>'referees',     'label'=>'3 Referees',              'required'=>true,  'done'=> $count('user_referees')   >= 3],
        ['id'=>'cv',           'label'=>'Upload CV',               'required'=>true,  'done'=> !empty($user['cv_path'])],
        ['id'=>'experience',   'label'=>'Experience',              'required'=>false, 'done'=> $count('user_experience')   >= 1],
        ['id'=>'certificates', 'label'=>'Certificates',            'required'=>false, 'done'=> $count('user_certificates') >= 1],
        ['id'=>'publications', 'label'=>'Publications',            'required'=>false, 'done'=> $count('user_publications') >= 1],
    ];
}
