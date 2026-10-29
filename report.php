<?php
require 'db.php';

// Сброс фильтра
if (isset($_GET['reset'])) {
    unset($_SESSION['report_date_from'], $_SESSION['report_date_to']);
    header("Location: report.php");
    exit();
}

if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $raw_from = trim($_GET['date_from'] ?? '');
    $raw_to = trim($_GET['date_to'] ?? '');
    
    $_SESSION['report_date_from'] = (!empty($raw_from) && validateDate($raw_from)) ? $raw_from : '';
    $_SESSION['report_date_to'] = (!empty($raw_to) && validateDate($raw_to)) ? $raw_to : '';
}

$date_from = !empty($_SESSION['report_date_from']) ? $_SESSION['report_date_from'] : date('d.m.Y', strtotime(date('Y-m-01')));
$date_to = !empty($_SESSION['report_date_to']) ? $_SESSION['report_date_to'] : date('d.m.Y', strtotime(date('Y-m-t')));

$db_date_from = date('Y-m-d', strtotime($date_from));
$db_date_to = date('Y-m-d', strtotime($date_to));

// Считаем метрики
$stmtMetrics = $pdo->prepare("SELECT 
    COUNT(DISTINCT e.id) as ev_count, 
    SUM(CASE WHEN p.role_in_event IN ('Победитель', 'Призер') THEN 1 ELSE 0 END) as winners_count,
    SUM(CASE WHEN p.award_details IS NOT NULL AND p.award_details != '' AND p.award_details != 'ПУСТО' AND p.award_details != '—' THEN 1 ELSE 0 END) as awards_count
    FROM events e LEFT JOIN event_participants p ON e.id = p.event_id 
    WHERE e.date_start >= ? AND e.date_start <= ?");
$stmtMetrics->execute([$db_date_from, $db_date_to]);
$metrics = $stmtMetrics->fetch();

$reportSql = "SELECT e.title, e.date_start, i.short_name, c.name as cat_name, p.participant_info, p.role_in_event, p.award_details
              FROM events e 
              JOIN institutes i ON e.institute_id = i.id 
              JOIN event_categories c ON e.category_id = c.id
              LEFT JOIN event_participants p ON e.id = p.event_id
              WHERE e.date_start >= ? AND e.date_start <= ?
              ORDER BY e.date_start ASC";
$stmtList = $pdo->prepare($reportSql);
$stmtList->execute([$db_date_from, $db_date_to]);
$records = $stmtList->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отчеты - СГУВТ</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <form method="GET" class="filter-panel" style="display:flex; gap:20px; align-items:flex-end;">
            <div class="form-group"><label>Период С:</label><input type="text" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ"></div>
            <div class="form-group"><label>ПО:</label><input type="text" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ"></div>
            <button type="submit" class="btn">Показать</button>
            <a href="report.php?reset=1" class="btn btn-gray" style="text-decoration:none; text-align:center; padding: 12px 25px;">Сбросить</a>
            <button type="button" onclick="window.print()" class="btn btn-gray" style="margin-left:auto;">🖨 ПЕЧАТЬ</button>
            <button type="button" onclick="generatePDF()" class="btn btn-red">СОХРАНИТЬ В PDF</button>
        </form>
        
        <div style="display:flex; gap:20px; margin-bottom:20px;">
            <div class="form-card" style="flex:1; text-align:center; margin:0;"><h2 style="margin:0; color:var(--ssuwt-green);"><?php echo $metrics['ev_count'] ?: 0; ?></h2><p style="margin:5px 0 0;">Мероприятий</p></div>
            <div class="form-card" style="flex:1; text-align:center; margin:0;"><h2 style="margin:0; color:var(--ssuwt-green);"><?php echo $metrics['winners_count'] ?: 0; ?></h2><p style="margin:5px 0 0;">Призовых мест</p></div>
            <div class="form-card" style="flex:1; text-align:center; margin:0;"><h2 style="margin:0; color:var(--ssuwt-green);"><?php echo $metrics['awards_count'] ?: 0; ?></h2><p style="margin:5px 0 0;">Выдано наград</p></div>
        </div>
        
        <div id="pdf_content" style="background:#fff; padding:20px;">
            <h2 style="text-align:center; margin-bottom:5px;">Отчетность по мероприятиям СГУВТ</h2>
            <p style="text-align:center; margin-top:0; color:#666; font-size:14px;">Период: с <?php echo htmlspecialchars($date_from); ?> по <?php echo htmlspecialchars($date_to); ?></p>
            <table class="main-table">
                <thead><tr><th>Дата</th><th>Интситут</th><th>Название</th><th>ФИО / Группа</th><th>Награды</th></tr></thead>
                <tbody>
                    <?php if(empty($records)): ?>
                        <tr><td colspan="5" style="text-align:center;">Данных нет.</td></tr>
                    <?php else: foreach($records as $r): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($r['date_start'])); ?></td>
                            <td><b><?php echo htmlspecialchars($r['short_name']); ?></b></td>
                            <td>
                                <span style="color:#529c81; font-size:12px; font-weight:bold; text-transform:uppercase;"><?php echo htmlspecialchars($r['cat_name']); ?></span><br>
                                <?php echo htmlspecialchars($r['title']); ?>
                            </td>
                            <td>
                                <b style="font-size:14px; color:#333;"><?php echo htmlspecialchars($r['participant_info'] ?: '—'); ?></b><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($r['role_in_event']); ?></span>
                            </td>
                            <td>
                                <?php 
                                $award = trim($r['award_details'] ?? '');
                                if (!empty($award) && $award !== 'ПУСТО' && $award !== '—') {
                                    echo "<strong style='font-size:14px; color:#333;'>" . htmlspecialchars($award) . "</strong>";
                                } else {
                                    echo "<span style='color:#777; font-size:13px;'>Награды отсутствуют</span>";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        flatpickr(".datepicker", { locale: "ru", dateFormat: "d.m.Y", allowInput: true });

        function generatePDF() {
            const element = document.getElementById('pdf_content');
            const opt = {
                margin:       10,
                filename:     'Otchet_SGUVT.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>