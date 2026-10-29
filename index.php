<?php
require 'db.php';

// Безопасное удаление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $id = filter_input(INPUT_POST, 'delete_event_id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Мероприятие успешно удалено.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Ошибка удаления: системный сбой.";
        }
    }
    header("Location: index.php");
    exit();
}

$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$inst_filter = filter_input(INPUT_GET, 'institute_id', FILTER_VALIDATE_INT) ?: '';

// Сбрасываем кривые даты из адресной строки
if (!empty($date_from) && !validateDate($date_from)) $date_from = '';
if (!empty($date_to) && !validateDate($date_to)) $date_to = '';

$query = "SELECT e.*, i.short_name, c.name as category_name,
    (SELECT GROUP_CONCAT(participant_info SEPARATOR ', ') FROM event_participants WHERE event_id = e.id) as parts_list,
    (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as parts_count
    FROM events e
    JOIN institutes i ON e.institute_id = i.id
    JOIN event_categories c ON e.category_id = c.id WHERE 1=1";

$params = [];
if (!empty($date_from)) { $query .= " AND e.date_start >= ?"; $params[] = date('Y-m-d', strtotime($date_from)); }
if (!empty($date_to)) { $query .= " AND e.date_start <= ?"; $params[] = date('Y-m-d', strtotime($date_to)); }
if (!empty($inst_filter)) { $query .= " AND e.institute_id = ?"; $params[] = $inst_filter; }
$query .= " ORDER BY e.date_start DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

$institutes = $pdo->query("SELECT * FROM institutes")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Учет мероприятий - СГУВТ</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="page-title">Реестр мероприятий</h2>
        
        <?php displayAlerts(); ?>

        <form method="GET" class="filter-panel" style="display:flex; gap:20px; align-items:flex-end;">
            <div class="form-group"><label>С даты:</label><input type="text" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ"></div>
            <div class="form-group"><label>По дату:</label><input type="text" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ"></div>
            <div class="form-group">
                <label>Институт:</label>
                <select name="institute_id" class="form-control">
                    <option value="">-- Все --</option>
                    <?php foreach($institutes as $inst): ?>
                        <option value="<?php echo $inst['id']; ?>" <?php if($inst_filter == $inst['id']) echo 'selected'; ?>><?php echo htmlspecialchars($inst['short_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Отфильтровать</button>
            <a href="index.php" class="btn btn-gray" style="text-decoration:none; text-align:center;">Сбросить</a>
            <button type="button" onclick="window.print()" class="btn btn-gray" style="margin-left: auto;">🖨 Печать</button>
            <button type="button" onclick="generatePDF()" class="btn btn-red">СОХРАНИТЬ В PDF</button>
        </form>
        
        <div id="pdf_content">
            <h2 id="pdf_title" style="display:none; text-align:center; margin-bottom:20px;">Реестр мероприятий СГУВТ</h2>
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width:85px;">Дата</th>
                        <th style="width:90px;">Институт</th>
                        <th style="width:15%;">Тип и Название</th>
                        <th style="width:15%;">Организатор (Институт / ФИО)</th>
                        <th style="width:30%;">Описание мероприятия</th>
                        <th>Записи (Студенты/Группы)</th>
                        <th style="width:120px;" class="action-col">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($events)): ?>
                        <tr><td colspan="7" style="text-align:center;">Нет данных за этот период.</td></tr>
                    <?php else: ?>
                        <?php foreach($events as $e): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($e['date_start'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($e['short_name']); ?></strong></td>
                                <td>
                                    <span style="color:#529c81; font-size:12px; font-weight:bold; text-transform:uppercase;"><?php echo htmlspecialchars($e['category_name']); ?></span><br>
                                    <?php echo htmlspecialchars($e['title']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($e['organizer']); ?></strong><br>
                                    <em style="font-size:12px; color:#666;"><?php echo htmlspecialchars($e['location']); ?></em>
                                </td>
                                <td style="font-size:13px; line-height:1.4;">
                                    <?php echo nl2br(htmlspecialchars($e['description'] ?: 'ПУСТО')); ?>
                                </td>
                                <td>
                                    <strong>Всего записей: <?php echo $e['parts_count']; ?></strong><br>
                                    <span style="font-size:13px;"><?php echo htmlspecialchars($e['parts_list'] ?: 'Нет данных'); ?></span>
                                </td>
                                <td class="action-col">
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <a href="edit.php?id=<?php echo $e['id']; ?>" class="btn btn-small" style="text-align:center;">Редактировать</a>
                                        <form method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить мероприятие?');" style="margin:0;">
                                            <input type="hidden" name="delete_event_id" value="<?php echo $e['id']; ?>">
                                            <button type="submit" class="btn btn-red btn-small" style="width:100%;">Удалить</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        flatpickr(".datepicker", { locale: "ru", dateFormat: "d.m.Y", allowInput: true });

        function generatePDF() {
            const element = document.getElementById('pdf_content');
            const actionCols = document.querySelectorAll('.action-col');
            actionCols.forEach(col => col.style.display = 'none');
            document.getElementById('pdf_title').style.display = 'block';

            const opt = {
                margin:       10,
                filename:     'Reestr_meropriyatiy.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                actionCols.forEach(col => col.style.display = '');
                document.getElementById('pdf_title').style.display = 'none';
            });
        }
    </script>
</body>
</html>