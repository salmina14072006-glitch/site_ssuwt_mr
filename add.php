<?php
require 'db.php';

$categories = $pdo->query("SELECT * FROM event_categories")->fetchAll();
$institutes = $pdo->query("SELECT * FROM institutes WHERE short_name != 'ОБЩЕЕ'")->fetchAll();

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $institute_id = isset($_POST['is_general']) ? 1 : filter_input(INPUT_POST, 'institute_id', FILTER_VALIDATE_INT);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        
        $title = trim($_POST['title'] ?? '');
        $date_start_raw = trim($_POST['date_start'] ?? '');
        $date_end_raw = trim($_POST['date_end'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $org_inst = trim($_POST['org_inst'] ?? '');
        $org_fio = trim($_POST['org_fio'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // 1. Проверка на пустоту
        if (empty($title) || empty($date_start_raw) || empty($location) || empty($org_inst) || empty($org_fio) || empty($description) || !$institute_id || !$category_id) {
            throw new Exception("Не все обязательные поля заполнены корректно.");
        }

        // 2. Проверка дат
        if (!validateDate($date_start_raw)) throw new Exception("Неверный формат даты начала (ДД.ММ.ГГГГ).");
        $date_start = date('Y-m-d', strtotime($date_start_raw));
        
        $date_end = $date_start;
        if (!empty($date_end_raw)) {
            if (!validateDate($date_end_raw)) throw new Exception("Неверный формат даты окончания.");
            $date_end = date('Y-m-d', strtotime($date_end_raw));
            if ($date_end < $date_start) throw new Exception("Дата окончания не может быть раньше даты начала.");
        }

        $organizer = $org_inst . ' / ' . $org_fio;

        // Транзакция для безопасного инсерта
        $pdo->beginTransaction();

        $sql = "INSERT INTO events (title, category_id, institute_id, date_start, date_end, location, organizer, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$title, $category_id, $institute_id, $date_start, $date_end, $location, $organizer, $description]);
        
        $event_id = $pdo->lastInsertId();
        
        // Обработка участников
        if (!empty($_POST['participant_info']) && is_array($_POST['participant_info'])) {
            $sql_part = "INSERT INTO event_participants (event_id, participant_info, role_in_event, award_details, financial_reward) VALUES (?, ?, ?, ?, ?)";
            $stmt_part = $pdo->prepare($sql_part);
            
            for ($i = 0; $i < count($_POST['participant_info']); $i++) {
                $info = trim($_POST['participant_info'][$i]);
                if ($info !== '') {
                    $reward_raw = trim($_POST['financial_reward'][$i] ?? '0');
                    if (!is_numeric($reward_raw) || (float)$reward_raw < 0) {
                        throw new Exception("Приз участника должен быть положительным числом.");
                    }
                    $reward = (float)$reward_raw;
                    $role = trim($_POST['role_in_event'][$i] ?? 'Участник');
                    $award = trim($_POST['award_details'][$i] ?? '');
                    
                    $stmt_part->execute([$event_id, $info, $role, $award ?: null, $reward]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Мероприятие успешно добавлено.";
        header("Location: index.php"); 
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить запись - СГУВТ</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="[https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css](https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css)">
    <script src="[https://cdn.jsdelivr.net/npm/flatpickr](https://cdn.jsdelivr.net/npm/flatpickr)"></script>
    <script src="[https://npmcdn.com/flatpickr/dist/l10n/ru.js](https://npmcdn.com/flatpickr/dist/l10n/ru.js)"></script>
    <script>
        function toggleGeneral() {
            let chk = document.getElementById('is_general');
            let sel = document.getElementById('institute_id');
            if(chk.checked) { sel.disabled = true; sel.required = false; sel.style.backgroundColor = '#eee'; }
            else { sel.disabled = false; sel.required = true; sel.style.backgroundColor = '#fff'; }
        }
        
        let partCounter = 1;
        function addParticipantRow() {
            partCounter++;
            const container = document.getElementById('participants_container');
            const row = document.createElement('div');
            row.className = 'participant-card';
            row.id = 'part_' + partCounter;
            row.innerHTML = `
                <button type="button" class="btn-delete" onclick="document.getElementById('part_${partCounter}').remove()">✖ Удалить</button>
                <div class="form-grid" style="margin-right: 120px;">
                    <div class="form-group"><label>Студент или Группа:</label><input type="text" name="participant_info[]" class="form-control" placeholder="Например: ИТ-241 или Иванов Иван" required></div>
                    <div class="form-group"><label>Роль:</label><select name="role_in_event[]" class="form-control"><option value="Участник">Участник</option><option value="Победитель">Победитель</option><option value="Призер">Призер</option><option value="Волонтер">Волонтер</option></select></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Награда / Достижение:</label><input type="text" name="award_details[]" class="form-control" placeholder="Грамота / Сертификат"></div>
                </div>
            `;
            container.appendChild(row);
        }

        function toggleCustomLocation() {
            let sel = document.getElementById('location_select');
            let cust = document.getElementById('location_custom');
            if(sel.value === 'other') {
                sel.removeAttribute('name');
                cust.setAttribute('name', 'location');
                cust.style.display = 'block';
                cust.disabled = false;
                cust.required = true;
                cust.focus();
            } else {
                cust.removeAttribute('name');
                sel.setAttribute('name', 'location');
                cust.style.display = 'none';
                cust.disabled = true;
                cust.required = false;
            }
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="page-title">Новое мероприятие</h2>

        <?php if($error_msg): ?>
            <div class="alert alert-error"><b>Ошибка:</b> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST" class="form-card">
            <div style="background: #fdfdfd; border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px;">
                <label style="cursor:pointer; font-weight:bold; color:#cc0000; font-size:14px; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="is_general" id="is_general" onchange="toggleGeneral()" <?php if(isset($_POST['is_general'])) echo 'checked'; ?> style="transform: scale(1.3);">
                    Отметить как ОБЩЕУНИВЕРСИТЕТСКОЕ мероприятие
                </label>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Институт:</label>
                    <select name="institute_id" id="institute_id" class="form-control" <?php if(isset($_POST['is_general'])) echo 'disabled style="background-color:#eee;"'; else echo 'required'; ?>>
                        <option value="">-- Выберите --</option>
                        <?php foreach($institutes as $inst): ?>
                            <option value="<?php echo $inst['id']; ?>" <?php if(isset($_POST['institute_id']) && $_POST['institute_id'] == $inst['id']) echo 'selected'; ?>><?php echo htmlspecialchars($inst['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Категория (Тип):</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Выберите --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php if(isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;"><label>Название мероприятия:</label><input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" class="form-control" required></div>
                <div class="form-group"><label>Дата начала:</label><input type="text" name="date_start" value="<?php echo htmlspecialchars($_POST['date_start'] ?? ''); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ" required></div>
                <div class="form-group"><label>Дата окончания (опционально):</label><input type="text" name="date_end" value="<?php echo htmlspecialchars($_POST['date_end'] ?? ''); ?>" class="form-control datepicker" placeholder="ДД.ММ.ГГГГ"></div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label>Место проведения:</label>
                    <?php 
                    $std_locations = ["Главный корпус: г. Новосибирск, ул. Щетинкина, 33", "УЛК1: г. Новосибирск, ул. Советская, 60", "УЛК2: г. Новосибирск, ул. Мичурина, 48"];
                    $posted_loc = $_POST['location'] ?? '';
                    $is_custom = $posted_loc !== '' && !in_array($posted_loc, $std_locations);
                    ?>
                    <select id="location_select" name="<?php echo $is_custom ? '' : 'location'; ?>" class="form-control" onchange="toggleCustomLocation()" required>
                        <option value="Главный корпус: г. Новосибирск, ул. Щетинкина, 33" <?php if($posted_loc == 'Главный корпус: г. Новосибирск, ул. Щетинкина, 33') echo 'selected'; ?>>Главный корпус: г. Новосибирск, ул. Щетинкина, 33</option>
                        <option value="УЛК1: г. Новосибирск, ул. Советская, 60" <?php if($posted_loc == 'УЛК1: г. Новосибирск, ул. Советская, 60') echo 'selected'; ?>>УЛК1: г. Новосибирск, ул. Советская, 60</option>
                        <option value="УЛК2: г. Новосибирск, ул. Мичурина, 48" <?php if($posted_loc == 'УЛК2: г. Новосибирск, ул. Мичурина, 48') echo 'selected'; ?>>УЛК2: г. Новосибирск, ул. Мичурина, 48</option>
                        <option value="other" style="font-weight: bold; color: #cc0000;" <?php if($is_custom) echo 'selected'; ?>>-- Свой вариант (ввести вручную) --</option>
                    </select>
                    <input type="text" id="location_custom" <?php echo $is_custom ? 'name="location"' : ''; ?> value="<?php echo $is_custom ? htmlspecialchars($posted_loc) : ''; ?>" class="form-control" placeholder="Введите свой адрес..." style="margin-top: 10px; <?php echo $is_custom ? 'display:block;' : 'display:none;'; ?>" <?php echo $is_custom ? 'required' : 'disabled'; ?>>
                </div>

                <div class="form-group"><label>Институт (Организатор):</label><input type="text" name="org_inst" value="<?php echo htmlspecialchars($_POST['org_inst'] ?? ''); ?>" class="form-control" placeholder="Например: ИИЦТ" required></div>
                <div class="form-group"><label>ФИО (Организатор):</label><input type="text" name="org_fio" value="<?php echo htmlspecialchars($_POST['org_fio'] ?? ''); ?>" class="form-control" placeholder="Например: Иванов И.И." required></div>
                
                <div class="form-group" style="grid-column: span 2;"><label>Краткое описание:</label><textarea name="description" rows="3" class="form-control" placeholder="Добавьте сюда информацию о мероприятии" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea></div>
            </div>
            
            <h3 style="color:var(--text-dark); margin-top:30px; border-bottom:2px solid var(--ssuwt-green); padding-bottom:10px;">Участники и Награды</h3>
            <div id="participants_container">
                <div class="participant-card">
                    <div class="form-grid" style="margin-right: 120px;">
                        <div class="form-group"><label>Студент или Группа:</label><input type="text" name="participant_info[]" class="form-control" placeholder="Например: ИТ-241 или Иванов Иван" required></div>
                        <div class="form-group"><label>Роль:</label><select name="role_in_event[]" class="form-control"><option value="Участник">Участник</option><option value="Победитель">Победитель</option><option value="Призер">Призер</option><option value="Волонтер">Волонтер</option></select></div>
                        <div class="form-group" style="grid-column: span 2;"><label>Награда / Достижение:</label><input type="text" name="award_details[]" class="form-control" placeholder="Грамота / Сертификат"></div>
                    </div>
                </div>
            </div>
            <button type="button" onclick="addParticipantRow()" class="btn btn-gray" style="margin-bottom: 20px;">+ Добавить еще участника / группу</button>
            <br>
            <div style="display:flex; align-items:center;">
                <a href="index.php" class="btn btn-back" style="padding: 15px 30px; font-size: 16px; text-decoration: none; margin-right: 15px;">Назад</a>
                <button type="submit" class="btn" style="padding: 15px 30px; font-size: 16px;">Сохранить в базу данных</button>
            </div>
        </form>
    </div>
    <script>
        flatpickr(".datepicker", { locale: "ru", dateFormat: "d.m.Y", allowInput: true });
    </script>
</body>
</html>