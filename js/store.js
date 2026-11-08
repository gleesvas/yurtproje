const DB = {
    get: (key) => JSON.parse(localStorage.getItem('kyk_' + key)) || [],
    set: (key, data) => localStorage.setItem('kyk_' + key, JSON.stringify(data)),

    addLog: (user, role, location, action) => {
        const logs = DB.get('logs');
        logs.unshift({
            time: new Date().toLocaleString('tr-TR'),
            user: user || 'Bilinmeyen Kullanıcı',
            role: role || 'Genel',
            location: location || 'Tüm Sistem',
            action
        });
        DB.set('logs', logs);
    }
};

// Varsayılan Test Verilerini Yükle
if (!localStorage.getItem('kyk_dorms')) {
    DB.set('dorms', [
        { name: "Tahsin Banguoğlu KYK Yurdu", city: "Ankara", district: "Çankaya", capacity: 1500, img: "https://picsum.photos/400/250?random=1" },
        { name: "Zübeyde Hanım KYK Yurdu", city: "Ankara", district: "Altındağ", capacity: 1200, img: "https://picsum.photos/400/250?random=2" },
        { name: "Fatih Sultan Mehmet KYK Yurdu", city: "İstanbul", district: "Fatih", capacity: 2000, img: "https://picsum.photos/400/250?random=3" }
    ]);
}