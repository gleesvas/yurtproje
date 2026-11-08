<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php"); exit;
}

$user_id   = $_SESSION['user_id'];
$dorm_id   = $_SESSION['dorm_id'] ?? 1;
$full_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$msg = "";
$error = "";
$activeTab = "home"; // Varsayılan Sekme

// FORM VE İŞLEM YÖNETİMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Çamaşır Sırası Alma
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'laundry') {
        $activeTab = "laundry";
        $machine = $_POST['machine_no'];
        $time    = $_POST['laundry_time'];

        $stmt = $db->prepare("INSERT INTO laundry_reservations (student_id, dorm_id, machine_name, reservation_time, status) VALUES (:sid, :did, :mname, :rtime, 'Alındı')");
        $stmt->execute(['sid' => $user_id, 'did' => $dorm_id, 'mname' => $machine, 'rtime' => $time]);
        $msg = "Çamaşır randevunuz başarıyla oluşturuldu!";
    }
    // 2. Çamaşır Durumu Güncelleme
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'update_laundry_status') {
        $activeTab = "laundry";
        $res_id         = $_POST['reservation_id'];
        $new_status     = $_POST['new_status'];
        $target_machine = $_POST['machine_name'];

        $checkOwn = $db->prepare("SELECT * FROM laundry_reservations WHERE id = :rid AND student_id = :sid");
        $checkOwn->execute(['rid' => $res_id, 'sid' => $user_id]);
        $myRes = $checkOwn->fetch();

        if ($myRes) {
            if ($new_status === 'Yıkanıyor') {
                $checkActive = $db->prepare("SELECT * FROM laundry_reservations WHERE dorm_id = :did AND machine_name = :mname AND status = 'Yıkanıyor' AND id != :rid");
                $checkActive->execute(['did' => $dorm_id, 'mname' => $target_machine, 'rid' => $res_id]);
                
                if ($checkActive->fetch()) {
                    $error = "Bu makine şu anda kullanımda! Diğer öğrencinin çamaşırı bitmeden makineyi başlatamazsınız.";
                } else {
                    $up = $db->prepare("UPDATE laundry_reservations SET status = 'Yıkanıyor' WHERE id = :rid");
                    $up->execute(['rid' => $res_id]);
                    $msg = "Çamaşırınızın durumu 'Yıkanıyor' olarak güncellendi!";
                }
            } elseif ($new_status === 'Tamamlandı') {
                $up = $db->prepare("UPDATE laundry_reservations SET status = 'Tamamlandı' WHERE id = :rid");
                $up->execute(['rid' => $res_id]);
                $msg = "Çamaşır yıkama işlemi tamamlandı!";
            }
        } else {
            $error = "Başkasına ait bir randevunun durumunu değiştiremezsiniz!";
        }
    }
    // 3. İzin Talebi Gönderme
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'leave') {
        $activeTab = "leave";
        $stmt = $db->prepare("INSERT INTO leave_requests (student_id, dorm_id, start_date, end_date, address, status) VALUES (:sid, :did, :sdate, :edate, :addr, 'Beklemede')");
        $stmt->execute(['sid' => $user_id, 'did' => $dorm_id, 'sdate' => $_POST['start_date'], 'edate' => $_POST['end_date'], 'addr' => $_POST['address']]);
        $msg = "İzin talebiniz Yurt Müdürüne başarıyla iletildi!";
    } 
    // 4. Arıza / Mobilya Talebi Gönderme
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'ticket') {
        $activeTab = "tech";
        $stmt = $db->prepare("INSERT INTO tech_tickets (student_id, dorm_id, room_number, ticket_type, description, status) VALUES (:sid, :did, :room, :ttype, :desc, 'Beklemede')");
        $stmt->execute(['sid' => $user_id, 'did' => $dorm_id, 'room' => $_POST['room_number'], 'ttype' => $_POST['ticket_type'], 'desc' => $_POST['description']]);
        $msg = "Arıza/Mobilya talebiniz kayda alındı!";
    } 
    // 5. Yemekhane Yorumu Gönderme
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'comment') {
        $activeTab = "dining";
        $stmt = $db->prepare("INSERT INTO dining_comments (student_id, dorm_id, comment) VALUES (:sid, :did, :cm)");
        $stmt->execute(['sid' => $user_id, 'did' => $dorm_id, 'cm' => $_POST['comment']]);
        $msg = "Yemekhane yorumunuz başarıyla gönderildi!";
    }
}

// VERİTABANINDAN VERİ ÇEKİMİ
// 1. Çamaşır Sıraları
$laundryQuery = $db->prepare("SELECT l.*, u.full_name, u.username FROM laundry_reservations l JOIN users u ON l.student_id = u.id WHERE l.dorm_id = :did ORDER BY l.id DESC");
$laundryQuery->execute(['did' => $dorm_id]);
$laundryList = $laundryQuery->fetchAll();

// 2. Öğrencinin Kendi Açtığı Arıza / Mobilya Talepleri
$myTickets = $db->prepare("SELECT * FROM tech_tickets WHERE student_id = :sid ORDER BY id DESC");
$myTickets->execute(['sid' => $user_id]);
$ticketList = $myTickets->fetchAll();

// 3. Öğrencinin Kendi İzin Talepleri
$myLeaves = $db->prepare("SELECT * FROM leave_requests WHERE student_id = :sid ORDER BY id DESC");
$myLeaves->execute(['sid' => $user_id]);
$leaveList = $myLeaves->fetchAll();

// 4. Duyurular ve Günlük Menü
$notices = $db->prepare("SELECT * FROM notices WHERE dorm_id = :did ORDER BY id DESC");
$notices->execute(['did' => $dorm_id]);
$noticeList = $notices->fetchAll();

$menu = $db->query("SELECT * FROM menus ORDER BY id DESC LIMIT 1");
$todayMenu = $menu->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Öğrenci Paneli</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
    .badge-alindi, .badge-beklemede { background: #fef3c7; color: #92400e; }
    .badge-yikaniyor, .badge-islemde { background: #dbeafe; color: #1e40af; }
    .badge-tamamlandi, .badge-onaylandi { background: #d1fae5; color: #065f46; }
    .badge-reddedildi { background: #fee2e2; color: #991b1b; }
    .disabled-text { color: #94a3b8; font-size: 0.85rem; font-weight: 500; }
  </style>
</head>
<body>

  <header>
    <h1>🏢 KYK Öğrenci Portalı</h1>
    <div>
      <span style="margin-right: 15px; font-weight: 500;">Hoş Geldiniz, <?= htmlspecialchars($full_name) ?></span>
      <a href="../login.php" class="btn btn-danger">Çıkış Yap</a>
    </div>
  </header>

  <div class="dashboard-container">
    <div class="sidebar" id="sidebar">
      <button class="btn" style="color: white; background: transparent;" onclick="toggleSidebar()">☰ <span>Menü</span></button>
      <ul>
        <li id="menu-home" class="active" onclick="showTab('home', this)"><a href="#">🏠 <span>Ana Sayfa</span></a></li>
        <li id="menu-laundry" onclick="showTab('laundry', this)"><a href="#">🧺 <span>Çamaşırhane</span></a></li>
        <li id="menu-dining" onclick="showTab('dining', this)"><a href="#">🍽️ <span>Yemek Menüsü</span></a></li>
        <li id="menu-tech" onclick="showTab('tech', this)"><a href="#">🛠️ <span>Arıza - Talep</span></a></li>
        <li id="menu-leave" onclick="showTab('leave', this)"><a href="#">📝 <span>İzin Alma</span></a></li>
      </ul>
    </div>

    <div class="main-content">
      <?php if($msg): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if($error): ?>
        <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- 1. Ana Sayfa Sekmesi -->
      <div id="home" class="tab-content">
        <div class="card">
          <h2>📢 Yurt Duyuruları</h2>
          <ul style="margin-top: 1rem; padding-left: 1.2rem; line-height: 1.8;">
            <?php if($noticeList): foreach($noticeList as $n): ?>
              <li><strong>[<?= date('d.m.Y', strtotime($n['created_at'])) ?>]:</strong> <?= htmlspecialchars($n['content']) ?></li>
            <?php endforeach; else: ?>
              <li><em>Yayınlanmış duyuru bulunmuyor.</em></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- 2. Çamaşırhane Sekmesi -->
      <div id="laundry" class="tab-content" style="display:none;">
        <div class="card">
          <h2>🧺 Çamaşır Makinesi Randevusu Al</h2>
          <form method="POST" action="student.php">
            <input type="hidden" name="action_type" value="laundry">
            <div class="form-group">
              <label>Saat Seçiniz</label>
              <input type="time" name="laundry_time" required>
            </div>
            <div class="form-group">
              <label>Makine Seçiniz</label>
              <select name="machine_no" required>
                <option value="Çamaşır Makinesi 1">Çamaşır Makinesi 1</option>
                <option value="Çamaşır Makinesi 2">Çamaşır Makinesi 2</option>
                <option value="Çamaşır Makinesi 3">Çamaşır Makinesi 3</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Sıra Al</button>
          </form>
        </div>

        <div class="card">
          <h2>📋 Çamaşır Sıraları ve Makine Durumları</h2>
          <table>
            <thead>
              <tr>
                <th>Öğrenci</th>
                <th>Makine</th>
                <th>Rezervasyon Saati</th>
                <th>Durum</th>
                <th>İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php if($laundryList): foreach($laundryList as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['full_name'] ?: $item['username']) ?></td>
                  <td><?= htmlspecialchars($item['machine_name']) ?></td>
                  <td><?= htmlspecialchars($item['reservation_time']) ?></td>
                  <td>
                    <?php if($item['status'] === 'Alındı'): ?>
                      <span class="badge badge-alindi">Alındı</span>
                    <?php elseif($item['status'] === 'Yıkanıyor'): ?>
                      <span class="badge badge-yikaniyor">⚡ Yıkanıyor</span>
                    <?php else: ?>
                      <span class="badge badge-tamamlandi">✓ Tamamlandı</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if($item['student_id'] == $user_id): ?>
                      <?php if($item['status'] === 'Alındı'): ?>
                        <form method="POST" action="student.php" style="display:inline;">
                          <input type="hidden" name="action_type" value="update_laundry_status">
                          <input type="hidden" name="reservation_id" value="<?= $item['id'] ?>">
                          <input type="hidden" name="machine_name" value="<?= htmlspecialchars($item['machine_name']) ?>">
                          <input type="hidden" name="new_status" value="Yıkanıyor">
                          <button type="submit" class="btn btn-success" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">Yıkanıyor Yap</button>
                        </form>
                      <?php elseif($item['status'] === 'Yıkanıyor'): ?>
                        <form method="POST" action="student.php" style="display:inline;">
                          <input type="hidden" name="action_type" value="update_laundry_status">
                          <input type="hidden" name="reservation_id" value="<?= $item['id'] ?>">
                          <input type="hidden" name="machine_name" value="<?= htmlspecialchars($item['machine_name']) ?>">
                          <input type="hidden" name="new_status" value="Tamamlandı">
                          <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">Tamamla & Boşalt</button>
                        </form>
                      <?php else: ?>
                        <span class="disabled-text">İşlem Bitti</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="disabled-text">Müdahale Edilemez</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5"><em>Henüz çamaşır sırası alınmadı.</em></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 3. Yemek Menüsü Sekmesi -->
      <div id="dining" class="tab-content" style="display:none;">
        <div class="card">
          <h2>🍽️ Günlük Yemek Menüsü</h2>
          <table>
            <thead><tr><th>Öğün</th><th>Menü İçeriği</th></tr></thead>
            <tbody>
              <?php if($todayMenu): ?>
                <tr><td>Kahvaltı</td><td><?= htmlspecialchars($todayMenu['breakfast']) ?></td></tr>
                <tr><td>Akşam Yemeği</td><td><?= htmlspecialchars($todayMenu['dinner']) ?></td></tr>
              <?php else: ?>
                <tr><td colspan="2"><em>Henüz bugüne ait menü girilmedi.</em></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h3>💬 Yemekhane Hakkında Yorum Yap</h3>
          <form method="POST" action="student.php">
            <input type="hidden" name="action_type" value="comment">
            <div class="form-group"><textarea name="comment" rows="3" required></textarea></div>
            <button type="submit" class="btn btn-primary">Yorum Gönder</button>
          </form>
        </div>
      </div>

      <!-- 4. Arıza Sekmesi (Taleplerim Tablosu Eklendi) -->
      <div id="tech" class="tab-content" style="display:none;">
        <div class="card">
          <h2>🛠️ Arıza ve Mobilya Talebi Oluştur</h2>
          <form method="POST" action="student.php">
            <input type="hidden" name="action_type" value="ticket">
            <div class="form-group">
              <label>Talep Tipi</label>
              <select name="ticket_type">
                <option value="Elektrik/Su">Elektrik / Su Tesisatı</option>
                <option value="Mobilya">Mobilya (Yatak, Dolap, Masa Kırığı)</option>
              </select>
            </div>
            <div class="form-group"><label>Oda Numarası</label><input type="text" name="room_number" required></div>
            <div class="form-group"><label>Açıklama</label><textarea name="description" rows="3" required></textarea></div>
            <button type="submit" class="btn btn-primary">Kaydı Aç</button>
          </form>
        </div>

        <!-- Öğrencinin Oluşturduğu Arıza Talepleri -->
        <div class="card">
          <h2>📋 Açtığınız Arıza ve Mobilya Talepleri</h2>
          <table>
            <thead>
              <tr>
                <th>Talep Tipi</th>
                <th>Oda No</th>
                <th>Açıklama</th>
                <th>Tarih</th>
                <th>Durum</th>
              </tr>
            </thead>
            <tbody>
              <?php if($ticketList): foreach($ticketList as $t): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($t['ticket_type']) ?></strong></td>
                  <td><?= htmlspecialchars($t['room_number']) ?></td>
                  <td><?= htmlspecialchars($t['description']) ?></td>
                  <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                  <td>
                    <?php if($t['status'] === 'Beklemede'): ?>
                      <span class="badge badge-beklemede">Beklemede</span>
                    <?php elseif($t['status'] === 'İşlemde'): ?>
                      <span class="badge badge-islemde">🛠️ İşlemde</span>
                    <?php else: ?>
                      <span class="badge badge-tamamlandi">✓ Tamamlandı</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5"><em>Henüz açtığınız bir arıza talebi bulunmuyor.</em></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 5. İzin Sekmesi (İzinlerim Tablosu Eklendi) -->
      <div id="leave" class="tab-content" style="display:none;">
        <div class="card">
          <h2>📝 İzin Talebi Oluştur</h2>
          <form method="POST" action="student.php">
            <input type="hidden" name="action_type" value="leave">
            <div class="form-group"><label>Çıkış Tarihi</label><input type="date" name="start_date" required></div>
            <div class="form-group"><label>Dönüş Tarihi</label><input type="date" name="end_date" required></div>
            <div class="form-group"><label>Kalınacak Adres</label><input type="text" name="address" required></div>
            <button type="submit" class="btn btn-primary">İzni Gönder</button>
          </form>
        </div>

        <!-- Öğrencinin Oluşturduğu İzin Talepleri -->
        <div class="card">
          <h2>📋 Oluşturduğunuz İzin Talepleri</h2>
          <table>
            <thead>
              <tr>
                <th>Çıkış Tarihi</th>
                <th>Dönüş Tarihi</th>
                <th>Kalınacak Adres</th>
                <th>Talep Tarihi</th>
                <th>Durum</th>
              </tr>
            </thead>
            <tbody>
              <?php if($leaveList): foreach($leaveList as $l): ?>
                <tr>
                  <td><?= date('d.m.Y', strtotime($l['start_date'])) ?></td>
                  <td><?= date('d.m.Y', strtotime($l['end_date'])) ?></td>
                  <td><?= htmlspecialchars($l['address']) ?></td>
                  <td><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
                  <td>
                    <?php if($l['status'] === 'Beklemede'): ?>
                      <span class="badge badge-beklemede">Beklemede</span>
                    <?php elseif($l['status'] === 'Onaylandı'): ?>
                      <span class="badge badge-onaylandi">✓ Onaylandı</span>
                    <?php else: ?>
                      <span class="badge badge-reddedildi">✕ Reddedildi</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5"><em>Henüz oluşturduğunuz bir izin talebi bulunmuyor.</em></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script>
    function showTab(tabId, element) {
      document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.sidebar li').forEach(el => el.classList.remove('active'));
      document.getElementById(tabId).style.display = 'block';
      if (element) {
        element.classList.add('active');
      }
    }
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

    // Aktif sekmede kalma kontrolü
    const currentTab = "<?= $activeTab ?>";
    if (currentTab !== "home") {
      showTab(currentTab, document.getElementById('menu-' + currentTab));
    }
  </script>
</body>
</html>