<?php
require_once '../config.php';

$allowed_roles = ['staff', 'staff_dining', 'staff_tech', 'staff_security'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../login.php"); exit;
}

$dorm_id = $_SESSION['dorm_id'] ?? 1;
$user_role = $_SESSION['role'];
$msg = "";

// ARIZA İŞLEMLERİ
if (isset($_GET['process_ticket'])) {
    $stmt = $db->prepare("UPDATE tech_tickets SET status = 'İşlemde' WHERE id = :tid");
    $stmt->execute(['tid' => $_GET['process_ticket']]);
    $msg = "Arıza kaydı İŞLEME ALINDI!";
}
if (isset($_GET['complete_ticket'])) {
    $stmt = $db->prepare("UPDATE tech_tickets SET status = 'Tamamlandı' WHERE id = :tid");
    $stmt->execute(['tid' => $_GET['complete_ticket']]);
    $msg = "Arıza kaydı TAMAMLANDI olarak işaretlendi!";
}

// YEMEK & TUTANAK İŞLEMLERİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_menu'])) {
        $today = date('Y-m-d');
        $breakfast = trim($_POST['breakfast']);
        $dinner = trim($_POST['dinner']);
        $eaten_count = intval($_POST['eaten_count']);

        $check = $db->query("SELECT id FROM menus ORDER BY id DESC LIMIT 1");
        $existing = $check->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE menus SET dorm_id = :did, date = :dt, breakfast = :bf, dinner = :dn, eaten_count = :ec WHERE id = :id");
            $stmt->execute(['did' => $dorm_id, 'dt' => $today, 'bf' => $breakfast, 'dn' => $dinner, 'ec' => $eaten_count, 'id' => $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO menus (dorm_id, date, breakfast, dinner, eaten_count) VALUES (:did, :dt, :bf, :dn, :ec)");
            $stmt->execute(['did' => $dorm_id, 'dt' => $today, 'bf' => $breakfast, 'dn' => $dinner, 'ec' => $eaten_count]);
        }
        $msg = "Günün yemek menüsü başarıyla kaydedildi!";
    } elseif (isset($_POST['save_report'])) {
        $stmt = $db->prepare("INSERT INTO staff_reports (staff_id, dorm_id, report_type, description) VALUES (:sid, :did, 'Tutanak', :desc)");
        $stmt->execute(['sid' => $_SESSION['user_id'], 'did' => $dorm_id, 'desc' => $_POST['report_desc']]);
        $msg = "Güvenlik tutanağı veritabanına işlendi!";
    }
}

// Verileri Çek
$tickets = $db->query("SELECT t.*, u.full_name, u.username FROM tech_tickets t JOIN users u ON t.student_id = u.id ORDER BY t.id DESC")->fetchAll();
$comments = $db->query("SELECT c.*, u.full_name, u.username FROM dining_comments c JOIN users u ON c.student_id = u.id ORDER BY c.id DESC")->fetchAll();
$reports = $db->query("SELECT r.*, u.full_name, u.username FROM staff_reports r JOIN users u ON r.staff_id = u.id ORDER BY r.id DESC")->fetchAll();

// Başlık İsimlendirmesi
$roleTitles = [
    'staff_dining' => '🍽️ Yemekhane Sorumlusu Paneli',
    'staff_tech'   => '🛠️ Tamirci & Bakım Görevlisi Paneli',
    'staff_security' => '🛡️ Güvenlik Görevlisi Paneli',
    'staff'        => '📋 Genel Personel Paneli'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Personel Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
    .badge-beklemede { background: #fef3c7; color: #92400e; }
    .badge-islemde { background: #dbeafe; color: #1e40af; }
    .badge-tamamlandi { background: #d1fae5; color: #065f46; }
  </style>
</head>
<body>

  <header>
    <h1><?= $roleTitles[$user_role] ?? '📋 Personel Paneli' ?></h1>
    <div>
      <span style="margin-right: 15px; font-weight: 500;">Hoş Geldiniz, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
      <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
    </div>
  </header>

  <div style="padding: 2rem; max-width: 1100px; margin: 0 auto;">
    <?php if($msg): ?>
      <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- 1. TAMİRCİ / BAKIM GÖREVLİSİ MODÜLÜ -->
    <?php if($user_role === 'staff_tech' || $user_role === 'staff'): ?>
    <div class="card">
      <h2>🛠️ Arıza ve Mobilya İş Listesi</h2>
      <table>
        <thead>
          <tr>
            <th>Öğrenci</th>
            <th>Arıza Tipi</th>
            <th>Oda No</th>
            <th>Açıklama</th>
            <th>Durum</th>
            <th>İşlem (Müdahale)</th>
          </tr>
        </thead>
        <tbody>
          <?php if($tickets): foreach($tickets as $t): ?>
            <tr>
              <td><strong><?= htmlspecialchars($t['full_name'] ?: $t['username']) ?></strong></td>
              <td><?= htmlspecialchars($t['ticket_type']) ?></td>
              <td><?= htmlspecialchars($t['room_number']) ?></td>
              <td><?= htmlspecialchars($t['description']) ?></td>
              <td>
                <?php if($t['status'] === 'Beklemede'): ?>
                  <span class="badge badge-beklemede">Beklemede</span>
                <?php elseif($t['status'] === 'İşlemde'): ?>
                  <span class="badge badge-islemde">⚡ İşlemde</span>
                <?php else: ?>
                  <span class="badge badge-tamamlandi">✓ Tamamlandı</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if($t['status'] === 'Beklemede'): ?>
                  <a href="admin-staff.php?process_ticket=<?= $t['id'] ?>" class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">İşleme Al</a>
                <?php elseif($t['status'] === 'İşlemde'): ?>
                  <a href="admin-staff.php?complete_ticket=<?= $t['id'] ?>" class="btn btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Tamamla</a>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.85rem;">Kapatıldı</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6"><em>Bekleyen arıza kaydı yok.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- 2. YEMEKHANE SORUMLUSU MODÜLÜ -->
    <?php if($user_role === 'staff_dining' || $user_role === 'staff'): ?>
    <div class="card">
      <h2>🍽️ Yemekhane Menü Girişi</h2>
      <form method="POST" action="admin-staff.php">
        <input type="hidden" name="save_menu" value="1">
        <div class="form-group"><label>Kahvaltı Menüsü</label><textarea name="breakfast" rows="2" required placeholder="Haşlanmış Yumurta, Peynir, Zeytin, Çay"></textarea></div>
        <div class="form-group"><label>Akşam Yemeği Menüsü</label><textarea name="dinner" rows="2" required placeholder="Mercimek Çorbası, Orman Kebabı, Pilav"></textarea></div>
        <div class="form-group"><label>Yemek Yiyen Öğrenci Sayısı</label><input type="number" name="eaten_count" required placeholder="Örn: 450"></div>
        <button type="submit" class="btn btn-primary">Menüyü Yayınla</button>
      </form>
    </div>

    <div class="card">
      <h2>💬 Öğrencilerden Gelen Yemekhane Yorumları</h2>
      <table>
        <thead><tr><th>Öğrenci</th><th>Yorum</th><th>Tarih</th></tr></thead>
        <tbody>
          <?php if($comments): foreach($comments as $c): ?>
            <tr><td><?= htmlspecialchars($c['full_name'] ?: $c['username']) ?></td><td><?= htmlspecialchars($c['comment']) ?></td><td><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td></tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3"><em>Henüz yorum yapılmadı.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- 3. GÜVENLİK GÖREVLİSİ MODÜLÜ -->
    <?php if($user_role === 'staff_security' || $user_role === 'staff'): ?>
    <div class="card">
      <h2>🛡️ Güvenlik Görevlisi - Tutanak Girişi</h2>
      <form method="POST" action="admin-staff.php">
        <input type="hidden" name="save_report" value="1">
        <div class="form-group"><label>Tutanak Açıklaması</label><textarea name="report_desc" rows="3" required placeholder="Olay, saat ve detay bilgileri..."></textarea></div>
        <button type="submit" class="btn btn-danger">Tutanağı Kaydet</button>
      </form>
    </div>

    <div class="card">
      <h2>📋 Kayıtlı Tutanağa Ait Geçmiş</h2>
      <table>
        <thead><tr><th>Güvenlik Görevlisi</th><th>Tutanak Açıklaması</th><th>Tarih</th></tr></thead>
        <tbody>
          <?php if($reports): foreach($reports as $r): ?>
            <tr><td><?= htmlspecialchars($r['full_name'] ?: $r['username']) ?></td><td><?= htmlspecialchars($r['description']) ?></td><td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td></tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3"><em>Kayıtlı tutanak yok.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>

</body>
</html>