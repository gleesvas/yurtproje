<?php
require_once 'config.php';

// Veritabanından yurtları çek
$stmt = $db->query("SELECT * FROM dorms ORDER BY id DESC");
$dorms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KYK Yurt Portalı - Yurt Ara</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <header>
    <h1>🏢 KYK Yurt Portalı</h1>
    <a href="login.php" class="btn btn-primary">Giriş Yap</a>
  </header>

  <div class="hero">
    <h2>Yurdunu Bul ve Bilgilerine Ulaş</h2>
    <div class="filter-box">
      <input type="text" id="searchInput" placeholder="Yurt Adı veya İlçe Ara..." onkeyup="filterDorms()">
      <select id="citySelect" onchange="filterDorms()">
        <option value="">Tüm İller</option>
        <option value="Ankara">Ankara</option>
        <option value="İstanbul">İstanbul</option>
        <option value="Kayseri">Kayseri</option>
        <option value="İzmir">İzmir</option>
      </select>
    </div>
  </div>

  <div class="dorm-container" id="dormList">
    <?php foreach($dorms as $dorm): ?>
      <div class="dorm-card" data-city="<?= htmlspecialchars($dorm['city']) ?>" data-name="<?= htmlspecialchars(strtolower($dorm['name'] . ' ' . $dorm['district'])) ?>">
        <img src="<?= htmlspecialchars($dorm['image_url'] ?: 'https://picsum.photos/400/250') ?>" alt="Yurt Fotoğrafı">
        <div class="content">
          <h3><?= htmlspecialchars($dorm['name']) ?></h3>
          <p>📍 <?= htmlspecialchars($dorm['city']) ?> / <?= htmlspecialchars($dorm['district']) ?></p>
          <p>👥 Kapasite: <?= htmlspecialchars($dorm['capacity']) ?> Öğrenci</p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <footer>
    T.C. Gençlik ve Spor Bakanlığı - KYK Yurt Bilgi Sistemi © 2026
  </footer>

  <script>
    function filterDorms() {
      const search = document.getElementById('searchInput').value.toLowerCase();
      const city = document.getElementById('citySelect').value;
      const cards = document.querySelectorAll('.dorm-card');

      cards.forEach(card => {
        const cardCity = card.getAttribute('data-city');
        const cardName = card.getAttribute('data-name');
        
        const matchesSearch = cardName.includes(search);
        const matchesCity = (city === "" || cardCity === city);

        if (matchesSearch && matchesCity) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>