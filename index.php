<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekomendasi Film KNN</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Reset CSS Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            /* Latar belakang web keseluruhan */
            background-image: url('background (2).jpeg');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #333;
            line-height: 1.6;
        }

        /* Bagian Header */
        header {
            /* Latar belakang header digabung dengan overlay gelap agar tulisan menonjol */
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('background header.png');
            background-size: cover;
            background-position: center;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .header-content h1 {
            /* Tulisan judul berwarna merah marun dengan pinggiran shadow gelap */
            color: #800000; /* Maroon */
            font-size: 3.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.8);
            margin-bottom: 10px;
            font-weight: bold;
        }

        .header-content p {
            font-size: 1.2rem;
            background-color: rgba(255, 255, 255, 0.85);
            color: #333;
            padding: 5px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        /* Konten Utama */
        .main-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.93); /* Background putih transparan agar teks terbaca */
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .card { 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            border: none; 
            background-color: rgba(255, 255, 255, 0.9); 
        }
        
        .eval-box { 
            background-color: #f0f4f8; 
            border-left: 5px solid #dc3545; 
        }
        
        /* Footer */
        footer {
            background-color: rgba(51, 51, 51, 0.9);
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
    </style>
</head>
<body>

<!-- Header Kustom -->
<header>
    <div class="header-content">
        <h1>Rekomendasi Film TV-Movies</h1>
        <p>Sistem Rekomendasi Film menggunakan CBF Algoritma KNN</p>
    </div>
</header>

<!-- Konten Utama Sistem Rekomendasi -->
<div class="main-container">
    <div class="card p-4 mb-4">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control form-control-lg" name="judul" placeholder="Masukkan judul film..." 
                       value="<?= isset($_GET['judul']) ? htmlspecialchars($_GET['judul']) : '' ?>" required>
                <button class="btn btn-danger px-4 fw-bold" type="submit" style="background-color: #800000; border-color: #800000;">Cari Rekomendasi</button>
            </div>
        </form>
    </div>

    <?php
    // --- JIKA TOMBOL PENCARIAN DITEKAN ---
    if (isset($_GET['judul']) && !empty(trim($_GET['judul']))) {
        $judul = urlencode(trim($_GET['judul']));
        $url_api = "http://localhost:5000/cari?judul=" . $judul;
        $response = @file_get_contents($url_api);
        
        if ($response === FALSE) {
            echo '<div class="alert alert-danger shadow-sm">❌ Gagal terhubung ke Engine Python. Pastikan <b>app.py</b> berjalan.</div>';
        } else {
            $data = json_decode($response, true);
            
            if ($data['status'] == 'error') {
                echo '<div class="alert alert-warning shadow-sm">⚠️ ' . htmlspecialchars($data['pesan']) . '</div>';
            } else {
                $film_target = $data['film_dicari'];
                echo '<div class="alert alert-success shadow-sm">';
                echo '<strong>Film Referensi Anda:</strong> ' . htmlspecialchars($film_target['judul']) . ' (' . htmlspecialchars($film_target['tahun']) . ') - ' . htmlspecialchars($film_target['genre']);
                echo '</div>';
                
                // --- TABEL 1: TOP 5 REKOMENDASI FILM ---
                echo '<div class="card p-4 mb-4">';
                echo '<h5 class="mb-3 fw-bold" style="color: #800000;">Top 5 Rekomendasi Film:</h5>';
                echo '<div class="table-responsive">';
                echo '<table class="table table-hover table-bordered align-middle text-center">';
                echo '<thead class="table-dark" style="background-color: #333;">';
                echo '<tr><th>Peringkat</th><th>Judul Film</th><th>Tahun</th><th>Genre</th><th>Tingkat Kemiripan</th></tr>';
                echo '</thead><tbody>';
                
                foreach ($data['rekomendasi'] as $rek) {
                    echo '<tr>';
                    echo '<td><span class="badge bg-secondary">#' . $rek['peringkat'] . '</span></td>';
                    echo '<td class="text-start fw-bold">' . htmlspecialchars($rek['judul']) . '</td>';
                    echo '<td>' . htmlspecialchars($rek['tahun']) . '</td>';
                    echo '<td>' . htmlspecialchars($rek['genre']) . '</td>';
                    echo '<td><span class="badge bg-success fs-6">' . htmlspecialchars($rek['kemiripan']) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div></div>';

                // --- TABEL 2: TABEL EVALUASI PRECISION PENCARIAN ---
                if (isset($data['evaluasi_pencarian'])) {
                    $eval = $data['evaluasi_pencarian'];
                    echo '<div class="card p-4 eval-box">';
                    echo '<h5 class="mb-3 text-danger fw-bold">Tabel Hasil Evaluasi Performa Kinerja (Precision)</h5>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-bordered align-middle text-center bg-white">';
                    echo '<thead class="table-primary">';
                    echo '<tr>';
                    echo '<th>Judul Film</th>';
                    echo '<th>Jumlah Rekomendasi</th>';
                    echo '<th>Jumlah Rekomendasi Relevan</th>';
                    echo '<th>Precision</th>';
                    echo '</tr>';
                    echo '</thead><tbody>';
                    echo '<tr>';
                    echo '<td class="text-start fw-bold">' . htmlspecialchars($eval['judul_film']) . '</td>';
                    echo '<td>' . htmlspecialchars($eval['jumlah_rekomendasi']) . '</td>';
                    echo '<td>' . htmlspecialchars($eval['jumlah_rekomendasi_relevan']) . '</td>';
                    echo '<td><span class="badge bg-danger fs-6">' . htmlspecialchars($eval['precision']) . '</span></td>';
                    echo '</tr>';
                    echo '</tbody></table></div></div>';
                }
            }
        }
    }
    ?>
</div>

<!-- Footer -->
<footer>
    <p>&copy; 2026 Sistem Rekomendasi Film. All Rights Reserved.</p>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>