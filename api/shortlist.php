<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json');

// Manual shortlisting is disabled — shortlisting is performed exclusively by AI.
http_response_code(403);
echo json_encode(['success' => false, 'error' => 'Manual shortlisting is disabled. Shortlisting is performed automatically by AI after screening.']);
exit;

try {
    require_login();
    require_role('COMPANY');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $applicationId = (int)($input['applicationId'] ?? 0);
    $shortlisted   = (bool)($input['shortlisted'] ?? false);

    if ($applicationId <= 0) throw new InvalidArgumentException('Invalid application ID');

    // Verify the application belongs to a job owned by this company
    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();
    if (!$company) throw new InvalidArgumentException('Company not found');

    $check = pdo()->prepare("SELECT a.id FROM applications a JOIN jobs j ON a.job_id=j.id WHERE a.id=? AND j.company_id=?");
    $check->execute([$applicationId, $company['id']]);
    if (!$check->fetch()) throw new InvalidArgumentException('Application not found');

    // Upsert screening_results shortlisted flag
    $exists = pdo()->prepare("SELECT id FROM screening_results WHERE application_id=?");
    $exists->execute([$applicationId]);
    if ($exists->fetch()) {
        pdo()->prepare("UPDATE screening_results SET is_shortlisted=? WHERE application_id=?")
             ->execute([$shortlisted ? 1 : 0, $applicationId]);
    } else {
        $appRow = pdo()->prepare("SELECT job_id FROM applications WHERE id=?");
        $appRow->execute([$applicationId]);
        $appRow = $appRow->fetch();
        pdo()->prepare("INSERT INTO screening_results (application_id, job_id, is_shortlisted) VALUES (?,?,?)")
             ->execute([$applicationId, $appRow['job_id'] ?? 0, $shortlisted ? 1 : 0]);
    }

    // Update application status
    $newStatus = $shortlisted ? 'SCREENED' : 'APPLIED';
    pdo()->prepare("UPDATE applications SET status=? WHERE id=?")->execute([$newStatus, $applicationId]);

    echo json_encode(['success' => true, 'shortlisted' => $shortlisted]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}
