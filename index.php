<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekomendasi Film KNN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 900px; margin-top: 50px; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: none; }
        .eval-box { background-color: #f0f4f8; border-left: 5px solid #dc3545; }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mb-4">
        <h2 class="fw-bold">🎬 Movie Recommender System</h2>
        <p class="text-muted">Pencarian & Evaluasi menggunakan Algoritma K-Nearest Neighbors (KNN)</p>
    </div>

    <div class="card p-4 mb-4">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control form-control-lg" name="judul" placeholder="Masukkan judul film..." 
                       value="<?= isset($_GET['judul']) ? htmlspecialchars($_GET['judul']) : '' ?>" required>
                <button class="btn btn-primary px-4" type="submit">Cari Rekomendasi</button>
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
            echo '<div class="alert alert-danger">❌ Gagal terhubung ke Engine Python. Pastikan <b>app.py</b> berjalan.</div>';
        } else {
            $data = json_decode($response, true);
            
            if ($data['status'] == 'error') {
                echo '<div class="alert alert-warning">⚠️ ' . htmlspecialchars($data['pesan']) . '</div>';
            } else {
                $film_target = $data['film_dicari'];
                echo '<div class="alert alert-success">';
                echo '<strong>🎯 Film Referensi Anda:</strong> ' . htmlspecialchars($film_target['judul']) . ' (' . htmlspecialchars($film_target['tahun']) . ') - ' . htmlspecialchars($film_target['genre']);
                echo '</div>';
                
                // --- TABEL 1: TOP 5 REKOMENDASI FILM ---
                echo '<div class="card p-4 mb-4">';
                echo '<h5 class="mb-3 fw-bold">⭐ Top 5 Rekomendasi Film:</h5>';
                echo '<div class="table-responsive">';
                echo '<table class="table table-hover table-bordered align-middle text-center">';
                echo '<thead class="table-dark">';
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
                    echo '<h5 class="mb-3 text-danger fw-bold">📊 Tabel Hasil Evaluasi Performa Kinerja (Precision)</h5>';
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

</body>
</html>