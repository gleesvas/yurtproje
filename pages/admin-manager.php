<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php"); exit;
}

$dorm_id = $_SESSION['dorm_id'] ?? 1;
$msg = "";

// İZİN ONAY/RED İŞLEMLERİ (Sadece Yurt Yetkilisi Yapabilir)
if (isset($_GET['approve_leave'])) {
    $stmt = $db->prepare("UPDATE leave_requests SET status = 'Onaylandı' WHERE id = :lid");
    $stmt->execute(['lid' => $_GET['approve_leave']]);
    $msg = "İzin talebi başarıyla ONAYLANDI!";
}
if (isset($_GET['reject_leave'])) {
    $stmt = $db->prepare("UPDATE leave_requests SET status = 'Reddedildi' WHERE id = :lid");
    $stmt->execute(['lid' => $_GET['reject_leave']]);
    $msg = "İzin talebi REDDEDİLDİ!";
}

// DUYURU YAYINLAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_notice'])) {
    $stmt = $db->prepare("INSERT INTO notices (dorm_id, content) VALUES (:did, :cnt)");
    $stmt->execute(['did' => $dorm_id, 'cnt' => $_POST['notice_content']]);
    $msg = "Yeni duyuru başarıyla yayınlandı!";
}

$leaves = $db->query("SELECT l.*, u.full_name, u.username FROM leave_requests l JOIN users u ON l.student_id = u.id ORDER BY l.id DESC")->fetchAll();
$tickets = $db->query("SELECT t.*, u.full_name, u.username FROM tech_tickets t JOIN users u ON t.student_id = u.id ORDER BY t.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Yurt Yetkilisi Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
    .badge-beklemede { background: #fef3c7; color: #92400e; }
    .badge-onaylandi, .badge-tamamlandi { background: #d1fae5; color: #065f46; }
    .badge-reddedildi { background: #fee2e2; color: #991b1b; }
    .badge-islemde { background: #dbeafe; color: #1e40af; }
  </style>
</head>
<body>

  <header>
    <h1>👔 Yurt Yetkilisi Yönetim Paneli</h1>
    <div>
      <span style="margin-right: 15px; font-weight: 500;">Hoş Geldiniz, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
      <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
    </div>
  </header>

  <div style="padding: 2rem; max-width: 1100px; margin: 0 auto;">
    <?php if($msg): ?>
      <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- 1. Duyuru Yayınlama -->
    <div class="card">
      <h2>📢 Yeni Duyuru Yayınla</h2>
      <form method="POST" action="admin-manager.php">
        <input type="hidden" name="post_notice" value="1">
        <div class="form-group"><textarea name="notice_content" rows="3" required placeholder="Tüm öğrencilere duyurulacak metni yazınız..."></textarea></div>
        <button type="submit" class="btn btn-primary">Yayınla</button>
      </form>
    </div>

    <!-- 2. İzin Talepleri Yönetimi (Müdür Onaylar/Reddeder) -->
    <div class="card">
      <h2>📝 Öğrenci İzin Talepleri</h2>
      <table>
        <thead>
          <tr>
            <th>Öğrenci</th>
            <th>Çıkış</th>
            <th>Dönüş</th>
            <th>Adres</th>
            <th>Durum</th>
            <th>İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php if($leaves): foreach($leaves as $l): ?>
            <tr>
              <td><strong><?= htmlspecialchars($l['full_name'] ?: $l['username']) ?></strong></td>
              <td><?= date('d.m.Y', strtotime($l['start_date'])) ?></td>
              <td><?= date('d.m.Y', strtotime($l['end_date'])) ?></td>
              <td><?= htmlspecialchars($l['address']) ?></td>
              <td>
                <?php if($l['status'] === 'Beklemede'): ?>
                  <span class="badge badge-beklemede">Beklemede</span>
                <?php elseif($l['status'] === 'Onaylandı'): ?>
                  <span class="badge badge-onaylandi">✓ Onaylandı</span>
                <?php else: ?>
                  <span class="badge badge-reddedildi">✕ Reddedildi</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if($l['status'] === 'Beklemede'): ?>
                  <a href="admin-manager.php?approve_leave=<?= $l['id'] ?>" class="btn btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">✓ Onayla</a>
                  <a href="admin-manager.php?reject_leave=<?= $l['id'] ?>" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">✕ Reddet</a>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.85rem;">Tamamlandı</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6"><em>Bekleyen izin talebi yok.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- 3. Arıza & Mobilya Talepleri Gözlem Alanı (Müdür Sadece İzler) -->
    <div class="card">
      <h2>🛠️ Gelen Arıza ve Mobilya Talepleri (Takip)</h2>
      <table>
        <thead>
          <tr>
            <th>Öğrenci</th>
            <th>Tip</th>
            <th>Oda</th>
            <th>Açıklama</th>
            <th>Durum</th>
            <th>İşlem Durumu</th>
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
                <span style="color: #64748b; font-size: 0.85rem; font-weight: 500;">
                  <?php if($t['status'] === 'Beklemede'): ?>
                    Bakım Personeli Bekleniyor
                  <?php elseif($t['status'] === 'İşlemde'): ?>
                    Tamirci Onarıyor
                  <?php else: ?>
                    Onarım Bitti
                  <?php endif; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6"><em>Arıza talebi yok.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

</body>
</html>