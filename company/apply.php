<?php
require_once __DIR__ . '/_common.php';

// Kiểm tra công ty đã đăng nhập và có hồ sơ công ty
if (!$company) {
    header('Location: profile.php');
    exit;
}


$allowed = ['submitted', 'reviewing', 'accepted', 'rejected'];

// Xử lý cập nhật trạng thái ứng tuyển
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = filter_input(
        INPUT_POST,
        'application_id',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? '';

    if ($application_id && in_array($status, $allowed, true)) {
        $stmt = $con->prepare(
            'UPDATE applications a
             JOIN internship_posts p ON p.id = a.post_id
             SET a.status = ?,
                 a.reviewed_at = NOW(),
                 a.accepted_at = CASE
                     WHEN ? = "accepted"
                     THEN COALESCE(a.accepted_at, NOW())
                     ELSE NULL
                 END
             WHERE a.id = ?
               AND p.company_id = ?
               AND a.status <> "accepted"'
        );

        $stmt->bind_param(
            'ssii',
            $status,
            $status,
            $application_id,
            $company['id']
        );

        $stmt->execute();
        $stmt->close();

        // Nếu chấp nhận, tạo bản ghi thực tập nếu chưa tồn tại
        if ($status === 'accepted') {
            $stmt = $con->prepare(
                'INSERT INTO internships
                    (application_id, student_id, company_id, lecturer_id,
                     post_id, start_date, end_date, status, company_supervisor)
                 SELECT
                    a.id, a.student_id, p.company_id, a.lecturer_id,
                    a.post_id, CURDATE(), NULL, "planned", NULL
                 FROM applications a
                 JOIN internship_posts p ON p.id = a.post_id
                 WHERE a.id = ?
                   AND p.company_id = ?
                   AND a.status = "accepted"
                   AND NOT EXISTS (
                       SELECT 1
                       FROM internships i
                       WHERE i.application_id = a.id
                   )'
            );

            $stmt->bind_param(
                'ii',
                $application_id,
                $company['id']
            );

            $stmt->execute();
            $stmt->close();
        }
    }

    // Tải lại trang sau khi xử lý POST
    header('Location: apply.php');
    exit;
}

// Đồng bộ các đơn đã được chấp nhận nhưng chưa có bản ghi thực tập
$stmt = $con->prepare(
    'INSERT INTO internships
        (application_id, student_id, company_id, lecturer_id,
         post_id, start_date, end_date, status, company_supervisor)
     SELECT
        a.id, a.student_id, p.company_id, a.lecturer_id,
        a.post_id, CURDATE(), NULL, "planned", NULL
     FROM applications a
     JOIN internship_posts p ON p.id = a.post_id
     WHERE a.status = "accepted"
       AND p.company_id = ?
       AND NOT EXISTS (
           SELECT 1
           FROM internships i
           WHERE i.application_id = a.id
       )'
);

$stmt->bind_param('i', $company['id']);
$stmt->execute();
$stmt->close();

// Lấy danh sách ứng viên của công ty đang đăng nhập
$stmt = $con->prepare(
    'SELECT
        a.id,
        a.student_id,
        a.status,
        a.applied_at,
        a.accepted_at,
        p.title,
        s.full_name,
        s.student_code,
        s.cv_file
     FROM applications a
     JOIN internship_posts p ON p.id = a.post_id
     JOIN students s ON s.id = a.student_id
     WHERE p.company_id = ?
       AND a.status <> "accepted"
     ORDER BY a.applied_at DESC'
);

$stmt->bind_param('i', $company['id']);
$stmt->execute();
$applications = $stmt->get_result();

company_header('Quản lý ứng tuyển');
?>

<div class="page-title">
    <h2>Danh sách ứng tuyển</h2>
    <p>Xem hồ sơ và cập nhật trạng thái ứng viên.</p>
</div>

<div class="block">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ứng viên</th>
                    <th>Vị trí</th>
                    <th>Ngày nộp</th>
                    <th>CV</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($applications->num_rows === 0): ?>
                    <tr>
                        <td colspan="5">Chưa có ứng viên.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($application = $applications->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="student-profile.php?student_id=<?= e($application['student_id']) ?>">
                                    <?= e($application['full_name']) ?>
                                </a>
                                <br>
                                <small>
                                    <?= e($application['student_code']) ?>
                                </small>
                            </td>

                            <td><?= e($application['title']) ?></td>

                            <td><?= e($application['applied_at']) ?></td>

                            <td>
                                <?php if (!empty($application['cv_file'])): ?>
                                    <a href="../uploads/cv/<?= rawurlencode($application['cv_file']) ?>"
                                       download>
                                        Tải CV
                                    </a>
                                <?php else: ?>
                                    Chưa có
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="post" action="apply.php">
                                    <input
                                        type="hidden"
                                        name="application_id"
                                        value="<?= e($application['id']) ?>"
                                    >

                                    <select name="status">
                                        <option value="submitted"
                                            <?= $application['status'] === 'submitted' ? 'selected' : '' ?>>
                                            Mới
                                        </option>

                                        <option value="reviewing"
                                            <?= $application['status'] === 'reviewing' ? 'selected' : '' ?>>
                                            Đang xem xét
                                        </option>

                                        <option value="accepted"
                                            <?= $application['status'] === 'accepted' ? 'selected' : '' ?>>
                                            Chấp nhận
                                        </option>

                                        <option value="rejected"
                                            <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>
                                            Từ chối
                                        </option>
                                    </select>

                                    <button type="submit">Lưu</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$stmt->close();
company_footer();
?>
