<?php
require_once 'config.php';

$error = "";
$success = "";

// KAYIT OLMA İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['reg_username']);
    $password  = trim($_POST['reg_password']);
    $role      = $_POST['role'];
    $dorm_id   = $_POST['dorm_id'] ?: 1;

    if (!empty($full_name) && !empty($username) && !empty($password)) {
        $check = $db->prepare("SELECT id FROM users WHERE username = :un");
        $check->execute(['un' => $username]);
        if ($check->fetch()) {
            $error = "Bu kullanıcı adı zaten kullanılıyor!";
        } else {
            $stmt = $db->prepare("INSERT INTO users (full_name, username, password, role, dorm_id) VALUES (:fn, :un, :pw, :rl, :did)");
            $stmt->execute([
                'fn'  => $full_name,
                'un'  => $username,
                'pw'  => $password,
                'rl'  => $role,
                'did' => $dorm_id
            ]);
            $success = "Kayıt başarıyla oluşturuldu! Şimdi giriş yapabilirsiniz.";
        }
    } else {
        $error = "Lütfen tüm alanları doldurunuz.";
    }
}

// GİRİŞ YAPMA İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['dorm_id']   = $user['dorm_id'];

            $logStmt = $db->prepare("INSERT INTO system_logs (user_id, action) VALUES (:uid, 'Sisteme giriş yaptı')");
            $logStmt->execute(['uid' => $user['id']]);

            // Rol tabanlı yönlendirmeler
            switch ($user['role']) {
                case 'student': header("Location: pages/student.php"); exit;
                case 'staff':
                case 'staff_security':
                case 'staff_dining':
                case 'staff_tech': header("Location: pages/admin-staff.php"); exit;
                case 'manager': header("Location: pages/admin-manager.php"); exit;
                case 'city': header("Location: pages/admin-city.php"); exit;
                case 'root': header("Location: pages/admin-root.php"); exit;
                case 'dev': header("Location: pages/admin-dev.php"); exit;
            }
        } else {
            $error = "Kullanıcı adı veya şifre hatalı!";
        }
    }
}

$dorms = $db->query("SELECT * FROM dorms")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>KYK Portal Giriş ve Kayıt</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .tab-btn-group { display: flex; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border); }
    .tab-btn { flex: 1; padding: 0.8rem; background: none; border: none; font-weight: 600; cursor: pointer; color: var(--text-muted); }
    .tab-btn.active { color: var(--primary-light); border-bottom: 3px solid var(--primary-light); margin-bottom: -2px; }
  </style>
</head>
<body class="login-body">

  <div class="login-card">
    <h2 style="color: var(--secondary); margin-bottom: 0.5rem;">🏢 KYK Yurt Portalı</h2>
    
    <div class="tab-btn-group">
      <button class="tab-btn active" id="btnLoginTab" onclick="switchForm('login')">Giriş Yap</button>
      <button class="tab-btn" id="btnRegisterTab" onclick="switchForm('register')">Kayıt Ol</button>
    </div>

    <?php if($error): ?>
      <div style="background: #fee2e2; color: #dc2626; padding: 0.8rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
      <div style="background: #d1fae5; color: #065f46; padding: 0.8rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- GİRİŞ FORMU -->
    <form method="POST" action="login.php" id="loginForm">
      <input type="hidden" name="login" value="1">
      <div class="form-group" style="text-align: left;">
        <label>Kullanıcı Adı</label>
        <input type="text" name="username" placeholder="Kullanıcı adınız" required>
      </div>

      <div class="form-group" style="text-align: left;">
        <label>Şifre</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Sisteme Giriş Yap</button>
    </form>

    <!-- KAYIT FORMU -->
    <form method="POST" action="login.php" id="registerForm" style="display: none;">
      <input type="hidden" name="register" value="1">
      <div class="form-group" style="text-align: left;">
        <label>Adınız Soyadınız</label>
        <input type="text" name="full_name" placeholder="Örn: Mehmet Can" required>
      </div>

      <div class="form-group" style="text-align: left;">
        <label>Kullanıcı Adı / T.C. No</label>
        <input type="text" name="reg_username" placeholder="Kullanıcı adı belirleyin" required>
      </div>

      <div class="form-group" style="text-align: left;">
        <label>Şifre</label>
        <input type="password" name="reg_password" placeholder="••••••••" required>
      </div>

      <!-- 3 AYRI PERSONEL ROLÜ EKLENDİ -->
      <div class="form-group" style="text-align: left;">
        <label>Kullanıcı Rolü</label>
        <select name="role" required>
          <option value="student">Öğrenci</option>
          <option value="staff_dining">Yemekhane Sorumlusu</option>
          <option value="staff_tech">Tamirci / Bakım Görevlisi</option>
          <option value="staff_security">Güvenlik Görevlisi</option>
          <option value="manager">Yurt Yetkilisi (İl Özelinde)</option>
          <option value="city">İl Yöneticisi</option>
          <option value="root">Root (Genel Otomasyon)</option>
          <option value="dev">Developer (Sayfa Düzenleyici)</option>
        </select>
      </div>

      <div class="form-group" style="text-align: left;">
        <label>Bağlı Olduğu Yurt</label>
        <select name="dorm_id">
          <?php foreach($dorms as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['city']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-success" style="width: 100%; justify-content: center;">Kayıt Ol ve Hesabı Oluştur</button>
    </form>

    <a href="index.php" style="display: block; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted); text-decoration: none;">← Ana Sayfaya Dön</a>
  </div>

  <script>
    function switchForm(type) {
      if (type === 'login') {
        document.getElementById('loginForm').style.display = 'block';
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('btnLoginTab').classList.add('active');
        document.getElementById('btnRegisterTab').classList.remove('active');
      } else {
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('registerForm').style.display = 'block';
        document.getElementById('btnLoginTab').classList.remove('active');
        document.getElementById('btnRegisterTab').classList.add('active');
      }
    }
  </script>
</body>
</html>