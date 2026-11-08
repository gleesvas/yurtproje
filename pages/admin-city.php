<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'city') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dorm'])) {
    $stmt = $db->prepare("INSERT INTO dorms (name, city, district, capacity) VALUES (:nm, :ct, :ds, :cp)");
    $stmt->execute(['nm' => $_POST['name'], 'ct' => $_POST['city'], 'ds' => $_POST['district'], 'cp' => $_POST['capacity']]);
}

$dorms = $db->query("SELECT * FROM dorms ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>İl Yöneticisi Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>

  <header>
    <h1>🏛️ İl Yöneticisi Paneli</h1>
    <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
  </header>

  <div style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
    <div class="card">
      <h2>➕ Yeni Yurt Tanımla</h2>
      <form method="POST" action="admin-city.php">
        <input type="hidden" name="add_dorm" value="1">
        <div class="form-group"><label>Yurt Adı</label><input type="text" name="name" required></div>
        <div class="form-group"><label>İl</label><input type="text" name="city" required></div>
        <div class="form-group"><label>İlçe</label><input type="text" name="district" required></div>
        <div class="form-group"><label>Kapasite</label><input type="number" name="capacity" required></div>
        <button type="submit" class="btn btn-primary">Yurdu Kaydet</button>
      </form>
    </div>

    <div class="card">
      <h2>🏢 Kayıtlı Yurtlar</h2>
      <table>
        <thead><tr><th>Yurt Adı</th><th>İl / İlçe</th><th>Kapasite</th></tr></thead>
        <tbody>
          <?php foreach($dorms as $d): ?>
            <tr><td><?= htmlspecialchars($d['name']) ?></td><td><?= htmlspecialchars($d['city'] . ' / ' . $d['district']) ?></td><td><?= $d['capacity'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>