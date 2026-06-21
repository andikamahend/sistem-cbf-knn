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
        .eval-box { background-color: #e9ecef; border-left: 5px solid #0d6efd; }
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
            <div class="input-group mb-3">
                <input type="text" class="form-control form-control-lg" name="judul" placeholder="Masukkan judul film..." 
                       value="<?= isset($_GET['judul']) ? htmlspecialchars($_GET['judul']) : '' ?>">
                <button class="btn btn-primary px-4" type="submit">Cari Rekomendasi</button>
            </div>
            <div class="text-center mt-2">
                <span class="text-muted small">atau</span><br>
                <button type="submit" name="aksi" value="evaluasi" class="btn btn-outline-dark btn-sm mt-2">
                    📊 Jalankan Evaluasi Sistem (Precision/Recall/F1)
                </button>
            </div>
        </form>
    </div>

    <?php
    // --- JIKA TOMBOL EVALUASI DITEKAN ---
    if (isset($_GET['aksi']) && $_GET['aksi'] == 'evaluasi') {
        $url_api_eval = "http://localhost:5000/evaluasi";
        $response_eval = @file_get_contents($url_api_eval);
        
        if ($response_eval === FALSE) {
            echo '<div class="alert alert-danger">❌ Gagal terhubung ke Engine Python. Pastikan <b>app.py</b> berjalan.</div>';
        } else {
            $data_eval = json_decode($response_eval, true);
            echo '<div class="card p-4 eval-box mb-4">';
            echo '<h5>📈 Hasil Evaluasi Sistem (Optimized KNN)</h5>';
            echo '<hr>';
            echo '<ul class="list-unstyled fs-5 mb-0">';
            echo '<li><strong>Total Data Uji:</strong> ' . $data_eval['sample_size'] . ' (K=' . $data_eval['k_value'] . ')</li>';
            echo '<li><strong>Rata-rata Precision@10:</strong> <span class="text-success">' . $data_eval['precision'] . '</span></li>';
            echo '<li><strong>Rata-rata Recall@10:</strong> <span class="text-primary">' . $data_eval['recall'] . '</span></li>';
            echo '<li><strong>Rata-rata F1-Score@10:</strong> <span class="text-danger">' . $data_eval['f1_score'] . '</span></li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    // --- JIKA TOMBOL PENCARIAN DITEKAN ---
    elseif (isset($_GET['judul']) && !empty(trim($_GET['judul']))) {
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
                
                echo '<div class="card p-4">';
                echo '<h5 class="mb-3">⭐ Top 5 Rekomendasi Film:</h5>';
                echo '<div class="table-responsive">';
                echo '<table class="table table-hover table-bordered align-middle text-center">';
                echo '<thead class="table-dark">';
                echo '<tr><th>Peringkat</th><th>Judul Film</th><th>Tahun</th><th>Genre</th><th>Tingkat Akurasi</th></tr>';
                echo '</thead><tbody>';
                
                foreach ($data['rekomendasi'] as $rek) {
                    echo '<tr>';
                    echo '<td><span class="badge bg-secondary">#' . $rek['peringkat'] . '</span></td>';
                    echo '<td class="text-start fw-bold">' . htmlspecialchars($rek['judul']) . '</td>';
                    echo '<td>' . htmlspecialchars($rek['tahun']) . '</td>';
                    echo '<td>' . htmlspecialchars($rek['genre']) . '</td>';
                    echo '<td><span class="badge bg-success fs-6">' . htmlspecialchars($rek['akurasi']) . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div></div>';
            }
        }
    }
    ?>
</div>

</body>
</html>