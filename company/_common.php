<?php
session_start();
if (!isset($_SESSION['account_loggedin'])) {
	header('Location: ../index.php');
	exit;
}
if (($_SESSION['account_role'] ?? '') !== 'company') {
	http_response_code(403);
	exit('Bạn không có quyền truy cập khu vực doanh nghiệp.');
}

$con = mysqli_connect('localhost', 'root', '123456', 'phplogin');
if (!$con) {
	exit('Không thể kết nối cơ sở dữ liệu: ' . mysqli_connect_error());
}
$user_id = (int)$_SESSION['account_id'];
$stmt = $con->prepare('SELECT c.id, c.company_code, c.company_name, c.tax_code, c.phone, c.address, c.website, c.industry, c.description, c.verified, u.email FROM companies c JOIN users u ON u.id = c.user_id WHERE c.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc() ?: null;
$stmt->close();

function e($value) {
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function company_header($title) {
	echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,minimum-scale=1"><title>' . e($title) . '</title><link href="../css/style.css?v=' . filemtime(__DIR__ . '/../css/style.css') . '" rel="stylesheet" type="text/css"></head><body><header class="header company-header"><div class="wrapper company-header-wrapper"><h1>Cổng doanh nghiệp</h1><nav class="menu"><a href="dashboard.php">Tổng quan</a><a href="profile.php">Hồ sơ</a><a href="posts.php">Bài đăng</a><a href="apply.php">Ứng tuyển</a><a href="students_internship.php">Thực tập</a><a href="evaluations.php">Đánh giá</a><a href="../logout.php">Đăng xuất</a></nav></div></header><div class="content company-content">';
}
function company_footer() {
	echo '</div></body></html>';
}
