<?php
session_start();

// Устанавливаем часовой пояс (Новосибирск)
date_default_timezone_set('Asia/Novosibirsk');

$host = '127.0.0.1';
$db   = 'sguvt_events_db';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Ошибка подключения к БД. Сервер временно недоступен.");
}

// Функция для вывода текущей даты на русском
function getRussianDate() {
    $months = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    $days = ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'];
    return "Сегодня " . date('j') . " " . $months[date('n')-1] . " " . date('Y') . ", " . $days[date('w')];
}

// Строгая проверка правильности даты (формат ДД.ММ.ГГГГ)
function validateDate($date, $format = 'd.m.Y') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Вывод системных сообщений (ошибок или успехов)
function displayAlerts() {
    if (!empty($_SESSION['error'])) {
        echo '<div class="alert alert-error"><b>Ошибка:</b> ' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    if (!empty($_SESSION['success'])) {
        echo '<div class="alert alert-success"><b>Успешно:</b> ' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
}
?>