<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/face.php';

header('Content-Type: application/json');
try {
    // Determine the user ID — either fully logged in, or mid-login pending
    $userId = null;
    $isLoginPending = false;

    if (!empty($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    } elseif (!empty($_SESSION['login_pending']['id'])) {
        $userId = (int)$_SESSION['login_pending']['id'];
        $isLoginPending = true;
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Bypass when face verification disabled
    if (!is_flag_enabled('FACE_VERIFY_ENABLED')) {
        $_SESSION['face_verified'] = true;
        if ($isLoginPending) {
            $_SESSION['face_verified_for_login'] = true;
        }
        echo json_encode(['success' => true, 'match' => true, 'mocked' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $liveDescriptor = $input['descriptor'] ?? [];
    if (empty($liveDescriptor)) throw new InvalidArgumentException('Missing descriptor');

    $stmt = pdo()->prepare("SELECT face_descriptor FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $stored = json_decode($user['face_descriptor'] ?? '[]', true) ?: [];
    if (empty($stored)) throw new InvalidArgumentException('No stored face data');

    $result = compare_face($liveDescriptor, $stored);
    if ($result['match']) {
        if ($isLoginPending) {
            // Login flow — mark face verified for this pending login
            $_SESSION['face_verified_for_login'] = true;
        } else {
            // Normal flow (exam gate, interview gate)
            $_SESSION['face_verified'] = true;
        }
    }

    echo json_encode(['success' => true, 'match' => $result['match'], 'distance' => $result['distance']]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}
