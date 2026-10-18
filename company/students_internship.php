<?php
require_once __DIR__ . '/_common.php';

/*
|--------------------------------------------------------------------------
| 1. KIỂM TRA ĐĂNG NHẬP VÀ QUYỀN TRUY CẬP
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['account_loggedin']) ||
    !isset($_SESSION['account_id'])
) {
    header('Location: ../index.php');
    exit;
}

if (($_SESSION['account_role'] ?? '') !== 'company') {
    http_response_code(403);
    exit('Bạn không có quyền truy cập khu vực doanh nghiệp.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| 2. KHỞI TẠO DỮ LIỆU
|--------------------------------------------------------------------------
*/

$students = [];
$keyword = trim($_GET['q'] ?? '');
$totalStudents = 0;
$company = null;
$con = null;

try {
   

    $con = new mysqli(
        'localhost',
        'root',
        '123456',
        'phplogin'
    );

    $con->set_charset('utf8mb4');

    /*
    |--------------------------------------------------------------------------
    | 4. LẤY THÔNG TIN DOANH NGHIỆP ĐANG ĐĂNG NHẬP
    |--------------------------------------------------------------------------
    |
    | Chỉ dùng user_id để xác định hồ sơ doanh nghiệp.
    | Không dùng user_id này để lọc danh sách sinh viên.
    |
    */

    $accountId = (int) $_SESSION['account_id'];

    $stmt = $con->prepare(
        'SELECT id, user_id, company_name
         FROM companies
         WHERE user_id = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $accountId);
    $stmt->execute();

    $company = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$company) {
        http_response_code(404);
        throw new RuntimeException(
            'Không tìm thấy hồ sơ doanh nghiệp của tài khoản đang đăng nhập.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. LẤY DANH SÁCH SINH VIÊN
    |--------------------------------------------------------------------------
    |
    | Lấy toàn bộ sinh viên có trong bảng students.
    | Không lọc theo user_id, id hoặc tham số tài khoản doanh nghiệp.
    |
    */

    $sql = "
        SELECT
            s.id AS student_id,
            s.user_id,
            s.student_code,
            s.full_name,
            s.date_of_birth,
            s.gender,
            s.phone,
            s.address,
            s.major,
            s.class_name,
            s.cohort,
            s.faculty,
            s.gpa,
            s.skills,
            s.cv_file,
            u.email
        FROM students AS s
        LEFT JOIN users AS u
            ON u.id = s.user_id
        WHERE 1 = 1
    ";

    $params = [];
    $types = '';

    /*
    |--------------------------------------------------------------------------
    | 6. TÌM KIẾM SINH VIÊN
    |--------------------------------------------------------------------------
    */

    if ($keyword !== '') {
        $sql .= "
            AND (
                s.student_code LIKE ?
                OR s.full_name LIKE ?
                OR u.email LIKE ?
                OR s.phone LIKE ?
                OR s.major LIKE ?
                OR s.class_name LIKE ?
                OR s.cohort LIKE ?
                OR s.faculty LIKE ?
            )
        ";

        $like = '%' . $keyword . '%';

        $params = array_fill(0, 8, $like);
        $types = 'ssssssss';
    }

    $sql .= ' ORDER BY s.full_name ASC, s.id ASC';

    $stmt = $con->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $students = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    $totalStudents = count($students);

    $con->close();
    $con = null;

} catch (Throwable $exception) {
    if ($con instanceof mysqli) {
        $con->close();
    }

    error_log(
        'students_internship.php: ' . $exception->getMessage()
    );

    if (http_response_code() < 400) {
        http_response_code(500);
    }

    // Hiển thị lỗi hồ sơ doanh nghiệp đã được xác định ở trên.
    if ($exception instanceof RuntimeException) {
        exit(e($exception->getMessage()));
    }

    exit(
        'Không thể tải danh sách sinh viên. ' .
        'Vui lòng kiểm tra kết nối database và cấu trúc bảng users, students, companies.'
    );
}

/*
|--------------------------------------------------------------------------
| 7. HIỂN THỊ GIAO DIỆN
|--------------------------------------------------------------------------
*/

company_header('Danh sách sinh viên');
?>

<main class="si-page">

    <!-- TIÊU ĐỀ -->
    <section class="si-heading">
        <div class="si-eyebrow">
            CỔNG DOANH NGHIỆP / QUẢN LÝ
        </div>

        <h2>Danh sách sinh viên</h2>

        <p class="si-subtitle">
            Tra cứu thông tin hồ sơ sinh viên trong hệ thống.
        </p>

        <p class="si-company">
            Doanh nghiệp:
            <strong>
                <?= e($company['company_name'] ?? '') ?>
            </strong>
        </p>
    </section>

    <!-- THỐNG KÊ -->
    <section class="si-stats">
        <div class="si-stat-icon">👥</div>

        <div>
            <p class="si-stat-label">
                Tổng sinh viên tìm thấy
            </p>

            <div class="si-stat-number">
                <?= $totalStudents ?>
            </div>

            <span class="si-stat-note">
                <?= $keyword !== ''
                    ? 'Kết quả phù hợp với từ khóa tìm kiếm'
                    : 'Tổng số hồ sơ sinh viên trong hệ thống' ?>
            </span>
        </div>
    </section>

    <!-- DANH SÁCH SINH VIÊN -->
    <section class="si-panel">

        <div class="si-panel-header">
            <div>
                <h3>Thông tin sinh viên</h3>

                <p>
                    Tìm kiếm trong danh sách sinh viên có sẵn.
                </p>
            </div>

            <span class="si-count">
                <?= $totalStudents ?> sinh viên
            </span>
        </div>

        <!-- FORM TÌM KIẾM -->
        <form
            class="si-search"
            method="get"
            action="students_internship.php"
        >
            <div class="si-search-box">
                <span class="si-search-icon" aria-hidden="true">
                    ⌕
                </span>

                <input
                    type="search"
                    name="q"
                    value="<?= e($keyword) ?>"
                    placeholder="Tìm mã sinh viên, họ tên, email, ngành, lớp..."
                    aria-label="Tìm kiếm sinh viên"
                >
            </div>

            <button
                class="si-btn si-btn-primary"
                type="submit"
            >
                Tìm kiếm
            </button>

            <?php if ($keyword !== ''): ?>
                <a
                    class="si-btn si-btn-light"
                    href="students_internship.php"
                >
                    Xóa tìm kiếm
                </a>
            <?php endif; ?>
        </form>

        <!-- BẢNG SINH VIÊN -->
        <div class="si-table-wrap">
            <table class="si-table">

                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Sinh viên</th>
                        <th>Email</th>
                        <th>Điện thoại</th>
                        <th>Ngành</th>
                        <th>Lớp</th>
                        <th>Khóa</th>
                        <th>Khoa</th>
                        <th>GPA</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($students)): ?>

                    <tr>
                        <td colspan="9">
                            <div class="si-empty">
                                <div class="si-empty-icon">
                                    📋
                                </div>

                                <strong>
                                    <?= $keyword !== ''
                                        ? 'Không tìm thấy kết quả phù hợp'
                                        : 'Chưa có sinh viên trong hệ thống' ?>
                                </strong>

                                <p>
                                    <?= $keyword !== ''
                                        ? 'Hãy kiểm tra từ khóa hoặc thử tìm kiếm bằng thông tin khác.'
                                        : 'Danh sách sẽ hiển thị khi có hồ sơ sinh viên trong database.' ?>
                                </p>

                                <?php if ($keyword !== ''): ?>
                                    <a href="students_internship.php">
                                        Xem lại toàn bộ danh sách
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($students as $index => $student): ?>
                        <?php
                        $fullName = trim(
                            $student['full_name'] ?? ''
                        );

                        if ($fullName === '') {
                            $avatar = '?';
                        } elseif (function_exists('mb_substr')) {
                            $avatar = mb_substr(
                                $fullName,
                                0,
                                1,
                                'UTF-8'
                            );
                        } else {
                            $avatar = substr($fullName, 0, 1);
                        }

                        $email = $student['email'] ?? '';
                        $phone = $student['phone'] ?? '';
                        $major = $student['major'] ?? '';
                        $className = $student['class_name'] ?? '';
                        $cohort = $student['cohort'] ?? '';
                        $faculty = $student['faculty'] ?? '';
                        ?>

                        <tr>
                            <!-- STT -->
                            <td class="si-index">
                                <?= $index + 1 ?>
                            </td>

                            <!-- SINH VIÊN -->
                            <td>
                                <div class="si-student">
                                    <div class="si-avatar">
                                        <?= e($avatar) ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?= e($fullName !== ''
                                                ? $fullName
                                                : 'Chưa cập nhật') ?>
                                        </strong>

                                        <span>
                                            <?= e(
                                                $student['student_code']
                                                ?? 'Chưa có mã'
                                            ) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- EMAIL -->
                            <td>
                                <?= e(
                                    $email !== ''
                                        ? $email
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- ĐIỆN THOẠI -->
                            <td>
                                <?= e(
                                    $phone !== ''
                                        ? $phone
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- NGÀNH -->
                            <td>
                                <?= e(
                                    $major !== ''
                                        ? $major
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- LỚP -->
                            <td>
                                <?= e(
                                    $className !== ''
                                        ? $className
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- KHÓA -->
                            <td>
                                <?= e(
                                    $cohort !== ''
                                        ? $cohort
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- KHOA -->
                            <td>
                                <?= e(
                                    $faculty !== ''
                                        ? $faculty
                                        : 'Chưa cập nhật'
                                ) ?>
                            </td>

                            <!-- GPA -->
                            <td>
                                <?= e(
                                    $student['gpa'] !== null
                                        ? (string) $student['gpa']
                                        : '—'
                                ) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>

            </table>
        </div>

        <!-- CHÂN BẢNG -->
        <div class="si-panel-footer">
            Hiển thị <?= $totalStudents ?> sinh viên
            <?php if ($keyword !== ''): ?>
                với từ khóa
                <strong><?= e($keyword) ?></strong>
            <?php endif; ?>
        </div>

    </section>

</main>

<?php company_footer(); ?>
