<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dev') {
    header("Location: ../login.php"); exit;
}

$msg = "";

// 1. Yeni Yurt/Sayfa Görseli ve Bilgisi Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dorm_cms'])) {
    $dorm_id = intval($_POST['dorm_id']);
    $img_url = trim($_POST['image_url']);
    $capacity = intval($_POST['capacity']);

    $stmt = $db->prepare("UPDATE dorms SET image_url = :img, capacity = :cap WHERE id = :did");
    $stmt->execute(['img' => $img_url, 'cap' => $capacity, 'did' => $dorm_id]);
    $msg = "Yurt içerik ve kapak görseli bilgisi başarıyla güncellendi!";
}

// 2. Sistem Loglarını Temizleme (Developer Bakım Modülü)
if (isset($_GET['clear_logs'])) {
    $db->query("DELETE FROM system_logs");
    $msg = "Sistem denetim logları temizlendi!";
}

// Veritabanından Yurtları Çek
$dorms = $db->query("SELECT * FROM dorms ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Developer Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>

  <header>
    <h1>💻 Developer CMS & İçerik Yönetim Paneli</h1>
    <div>
      <span style="margin-right: 15px; font-weight: 500;">Hoş Geldiniz, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
      <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
    </div>
  </header>

  <div style="padding: 2rem; max-width: 1100px; margin: 0 auto;">
    <?php if($msg): ?>
      <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- 1. Ana Sayfa Yurt İçerik & Kart Düzenleme Formu -->
    <div class="card">
      <h2>🖼️ Ana Sayfa Yurt Kartları & Fotoğraf Yönetimi</h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
        Ana sayfada (`index.php`) sergilenen yurtların kapak fotoğraflarını ve kapasitelerini buradan düzenleyebilirsiniz.
      </p>

      <form method="POST" action="admin-dev.php">
        <input type="hidden" name="update_dorm_cms" value="1">
        <div class="form-group">
          <label>Düzenlenecek Yurdu Seçiniz</label>
          <select name="dorm_id" required>
            <?php foreach($dorms as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['city']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Yeni Kapak Fotoğrafı URL (Resim Bağlantısı)</label>
          <input type="url" name="image_url" placeholder="https://örnek.com/resim.jpg" required>
        </div>

        <div class="form-group">
          <label>Güncel Yurt Kapasitesi</label>
          <input type="number" name="capacity" placeholder="Örn: 1500" required>
        </div>

        <button type="submit" class="btn btn-primary">İçeriği Güncelle ve Yayınla</button>
      </form>
    </div>

    <!-- 2. Geliştirici Sistem Bakım Modülü -->
    <div class="card">
      <h2>⚙️ Geliştirici Bakım & Veritabanı Modülü</h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
        Sistem log tablosunu sıfırlayabilir veya veritabanı önbelleğini temizleyebilirsiniz.
      </p>
      <a href="admin-dev.php?clear_logs=1" class="btn btn-danger" onclick="return confirm('Tüm sistem logları silinecektir, emin misiniz?')">🧹 Sistem Loglarını Temizle</a>
    </div>
  </div>

</body>
</html>