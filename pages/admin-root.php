<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'root') {
    header("Location: ../login.php"); exit;
}

$logs = $db->query("SELECT l.*, u.username FROM system_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Root Yönetim Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>

  <header>
    <h1>👑 Root Otomasyon Kontrol Paneli</h1>
    <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
  </header>

  <div style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
    <div class="card">
      <h2>📑 Otomasyon Denetim Logları</h2>
      <table>
        <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Yapılan Eylem</th></tr></thead>
        <tbody>
          <?php foreach($logs as $log): ?>
            <tr><td><?= $log['created_at'] ?></td><td><?= htmlspecialchars($log['username'] ?: 'Sistem') ?></td><td><?= htmlspecialchars($log['action']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>