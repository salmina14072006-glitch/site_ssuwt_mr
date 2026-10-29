<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_inst'])) {
            $short = trim($_POST['short_name'] ?? '');
            $full = trim($_POST['full_name'] ?? '');
            if ($short === '' || $full === '') throw new Exception("Поля подразделения не могут быть пустыми.");
            
            $stmt = $pdo->prepare("INSERT INTO institutes (short_name, full_name) VALUES (?, ?)");
            $stmt->execute([$short, $full]);
            $_SESSION['success'] = "Подразделение успешно добавлено.";
        }
        
        if (isset($_POST['del_inst'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM institutes WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Подразделение удалено.";
            }
        }
        
        if (isset($_POST['add_cat'])) {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception("Название категории не может быть пустым.");
            
            $stmt = $pdo->prepare("INSERT INTO event_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $_SESSION['success'] = "Категория успешно добавлена.";
        }
        
        if (isset($_POST['del_cat'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM event_categories WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Категория удалена.";
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Ошибка: Запись уже используется в базе данных (привязана к мероприятию) или произошел сбой БД.";
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: directories.php"); 
    exit();
}

$institutes = $pdo->query("SELECT * FROM institutes")->fetchAll();
$categories = $pdo->query("SELECT * FROM event_categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Справочники - СГУВТ</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="page-title">Справочники системы</h2>
        
        <?php displayAlerts(); ?>
        
        <div style="display:flex; gap:30px;">
            <div class="form-card" style="flex:1;">
                <h3>Подразделения</h3>
                <form method="POST" style="display:flex; gap:10px; margin-bottom:15px;">
                    <input type="text" name="short_name" class="form-control" placeholder="Аббревиатура" required>
                    <input type="text" name="full_name" class="form-control" placeholder="Полное название" required>
                    <button type="submit" name="add_inst" class="btn">Добавить</button>
                </form>
                <table class="main-table">
                    <?php foreach($institutes as $i): ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($i['short_name']); ?></b> - <?php echo htmlspecialchars($i['full_name']); ?></td>
                            <td style="width:100px;">
                                <form method="POST" onsubmit="return confirm('Точно удалить?');">
                                    <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                    <button type="submit" name="del_inst" class="btn btn-red">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="form-card" style="flex:1;">
                <h3>Типы мероприятий</h3>
                <form method="POST" style="display:flex; gap:10px; margin-bottom:15px;">
                    <input type="text" name="name" class="form-control" placeholder="Название категории" style="flex:1;" required>
                    <button type="submit" name="add_cat" class="btn">Добавить</button>
                </form>
                <table class="main-table">
                    <?php foreach($categories as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                            <td style="width:100px;">
                                <form method="POST" onsubmit="return confirm('Точно удалить?');">
                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" name="del_cat" class="btn btn-red">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>