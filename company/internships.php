<!-- <?php
require __DIR__ . '/_common.php';
if (!$company) { header('Location: profile.php'); exit; }

$stmt = $con->prepare('ALTER TABLE internships ADD COLUMN IF NOT EXISTS completed_at timestamp NULL DEFAULT NULL AFTER created_at');
$stmt->execute();
$stmt->close();
$stmt = $con->prepare('ALTER TABLE internships ADD COLUMN IF NOT EXISTS cancelled_at timestamp NULL DEFAULT NULL AFTER completed_at');
$stmt->execute();
$stmt->close();

$internship_statuses = [
	'planned' => 'Chưa bắt đầu',
	'ongoing' => 'Đang thực tập',
	'completed' => 'Hoàn thành',
	'cancelled' => 'Đã hủy',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$internship_id = filter_input(INPUT_POST, 'internship_id', FILTER_VALIDATE_INT);
	$action = $_POST['action'] ?? 'update';
	$status = $_POST['status'] ?? '';
	$company_supervisor = trim($_POST['company_supervisor'] ?? '');
	$end_date = $_POST['end_date'] ?? '';
	if ($action === 'set_end_date' && $internship_id && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
		$stmt = $con->prepare('UPDATE internships SET end_date=? WHERE id=? AND company_id=? AND end_date IS NULL');
		$stmt->bind_param('sii', $end_date, $internship_id, $company['id']);
		$stmt->execute();
		$stmt->close();
	} elseif ($action === 'set_supervisor' && $internship_id && $company_supervisor !== '') {
		$stmt = $con->prepare('UPDATE internships SET company_supervisor=? WHERE id=? AND company_id=? AND company_supervisor IS NULL');
		$stmt->bind_param('sii', $company_supervisor, $internship_id, $company['id']);
		$stmt->execute();
		$stmt->close();
	} elseif ($action === 'update' && $internship_id && isset($internship_statuses[$status])) {
		$stmt = $con->prepare('UPDATE internships SET status=?, completed_at=CASE WHEN ?="completed" THEN COALESCE(completed_at, NOW()) ELSE NULL END, cancelled_at=CASE WHEN ?="cancelled" THEN COALESCE(cancelled_at, NOW()) ELSE NULL END WHERE id=? AND company_id=? AND status NOT IN ("completed", "cancelled")');
		$stmt->bind_param('sssii', $status, $status, $status, $internship_id, $company['id']);
		$stmt->execute();
		$stmt->close();
	}
}

$stmt = $con->prepare('SELECT i.id, i.start_date, i.end_date, i.status, i.completed_at, i.cancelled_at, i.company_supervisor, s.full_name AS student_name, s.student_code, p.title AS position_title, c.company_name FROM internships i JOIN students s ON s.id = i.student_id JOIN internship_posts p ON p.id = i.post_id JOIN companies c ON c.id = i.company_id WHERE i.company_id = ? ORDER BY i.start_date DESC, i.created_at DESC');
$stmt->bind_param('i', $company['id']);
$stmt->execute();
$internships = $stmt->get_result();
$stmt->close();

company_header('Quản lý thực tập');
?>
<div class="page-title">
	<h2>Danh sách thực tập</h2>
	<p>Quản lý sinh viên đang làm thực tập tại doanh nghiệp. <a class="btn btn-secondary" href="evaluations.php">Đánh giá sinh viên</a></p>
</div>
<div class="company-internship-list">
	<?php if ($internships->num_rows === 0): ?>
		<div class="block"><p>Chưa có sinh viên nào được chấp nhận thực tập.</p></div>
	<?php else: while ($internship = $internships->fetch_assoc()): ?>
		<article class="company-internship-card">
			<div class="company-internship-card-header">
				<div>
					<h3><?=e($internship['student_name'])?></h3>
					<p><?=e($internship['student_code'])?> · <?=e($internship['position_title'])?></p>
				</div>
				<span class="status-badge status-<?=e($internship['status'])?>"><?=e($internship_statuses[$internship['status']] ?? $internship['status'])?></span>
			</div>
			<div class="company-internship-summary">
				<div><strong>Doanh nghiệp</strong><span><?=e($internship['company_name'])?></span></div>
				<div><strong>Bắt đầu</strong><span><?=e(date('d/m/Y', strtotime($internship['start_date'])))?></span></div>
				<div><strong>Kết thúc</strong><span><?=e($internship['end_date'] ? date('d/m/Y', strtotime($internship['end_date'])) : 'Chưa xác định')?></span></div>
				<?php if ($internship['status'] === 'completed'): ?><div><strong>Ngày hoàn thành</strong><span><?=e($internship['completed_at'] ? date('d/m/Y H:i', strtotime($internship['completed_at'])) : '-')?></span></div><?php elseif ($internship['status'] === 'cancelled'): ?><div><strong>Ngày hủy</strong><span><?=e($internship['cancelled_at'] ? date('d/m/Y H:i', strtotime($internship['cancelled_at'])) : '-')?></span></div><?php endif; ?>
				<div><strong>Người hướng dẫn</strong><span><?=e($internship['company_supervisor'] ?: 'Chưa phân công')?></span></div>
			</div>
			<div class="company-internship-actions">
				<?php if (!$internship['end_date']): ?><form method="post" class="inline-action-form"><input type="hidden" name="action" value="set_end_date"><input type="hidden" name="internship_id" value="<?=e($internship['id'])?>"><label>Ngày kết thúc<input class="form-input" type="date" name="end_date" required></label><button class="btn btn-secondary" type="submit">Lưu ngày</button></form><?php endif; ?>
				<?php if (!$internship['company_supervisor']): ?><form method="post" class="inline-action-form"><input type="hidden" name="action" value="set_supervisor"><input type="hidden" name="internship_id" value="<?=e($internship['id'])?>"><label>Người hướng dẫn<input class="form-input" type="text" name="company_supervisor" placeholder="Nhập họ tên" required></label><button class="btn btn-secondary" type="submit">Lưu người hướng dẫn</button></form><?php endif; ?>
				<?php if (!in_array($internship['status'], ['completed', 'cancelled'], true)): ?><form method="post" class="inline-action-form status-action-form"><input type="hidden" name="action" value="update"><input type="hidden" name="internship_id" value="<?=e($internship['id'])?>"><label>Trạng thái<select class="form-input" name="status"><?php foreach ($internship_statuses as $value => $label): ?><option value="<?=e($value)?>" <?=$internship['status'] === $value ? 'selected' : ''?>><?=e($label)?></option><?php endforeach; ?></select></label><button class="btn" type="submit">Cập nhật</button></form><?php endif; ?>
			</div>
		</article>
	<?php endwhile; endif; ?>
</div>
<?php company_footer(); ?> -->
