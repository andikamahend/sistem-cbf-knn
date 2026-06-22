from flask import Flask, request, jsonify
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.neighbors import NearestNeighbors
from sklearn.preprocessing import MinMaxScaler
from scipy.sparse import hstack
import re

app = Flask(__name__)

# ==========================================
# 1. LOAD DATASET & PREPROCESSING 
# ==========================================
print("⏳ Memuat dataset dan melatih model KNN... Mohon tunggu.")
df = pd.read_csv('filmtv_movies.csv', delimiter=';')

features_text = ['genre', 'directors', 'actors', 'description']
features_num = ['year'] 
# Fitur rating yang akan digunakan
features_float = ['avg_vote', 'critics_vote', 'public_vote']

for feature in features_text:
    df[feature] = df[feature].fillna('')

for feature in features_num:
    df[feature] = df[feature].fillna(0).astype(int)

# Menangani nilai kosong pada kolom rating dengan menjadikannya 0.0 (float)
for feature in features_float:
    df[feature] = pd.to_numeric(df[feature], errors='coerce').fillna(0.0).astype(float)

def bersihkan_teks(teks):
    teks = teks.lower()
    teks = re.sub(r'[^\w\s]', ' ', teks)
    teks = re.sub(r'\s+', ' ', teks).strip()
    return teks

def combine_features(row):
    genre_clean = bersihkan_teks(str(row['genre']))
    genre_weighted = (genre_clean + " ") * 3
    directors_clean = bersihkan_teks(str(row['directors']))
    actors_clean = bersihkan_teks(str(row['actors']))
    description_clean = bersihkan_teks(str(row['description']))
    
    text_base = f"{genre_weighted}{directors_clean} {actors_clean} {description_clean}"
    
    # Tahun tetap dibiarkan sebagai teks dekade untuk konteks ekstra di TF-IDF
    tahun = int(row['year'])
    decade_feat = f"decade_{tahun - (tahun % 10)}s" if tahun > 0 else ""
    
    return f"{text_base} {decade_feat}"

df['combined_features'] = df.apply(combine_features, axis=1)
df = df.dropna(subset=['title']).reset_index(drop=True)

# ==========================================
# 2. PEMBAGIAN DATA, VEKTORISASI & SCALING
# ==========================================
df_train, df_test = train_test_split(df, test_size=0.2, random_state=42)
df_train = df_train.reset_index(drop=True)
df_test = df_test.reset_index(drop=True)

# A. Transformasi Teks (TF-IDF)
stop_words_list = list(TfidfVectorizer(stop_words='english').get_stop_words()) + ['il', 'lo', 'i', 'gli', 'la', 'le', 'un', 'una', 'di', 'del', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra']

tfidf = TfidfVectorizer(stop_words=stop_words_list)
tfidf_matrix_train = tfidf.fit_transform(df_train['combined_features'])

# B. Transformasi Numerik (MinMaxScaler)
scaler = MinMaxScaler()
# Kolom numerik yang ingin dievaluasi kedekatannya
kolom_numerik = ['year', 'avg_vote', 'critics_vote', 'public_vote'] 
scaled_num_train = scaler.fit_transform(df_train[kolom_numerik])

# C. Menggabungkan Vektor Teks dan Vektor Numerik (hstack)
# hstack digunakan karena TF-IDF menghasilkan matriks sparse (jarang)
combined_matrix_train = hstack([tfidf_matrix_train, scaled_num_train])

# D. Melatih Model KNN
knn_model = NearestNeighbors(metric='cosine', algorithm='brute')
knn_model.fit(combined_matrix_train)
print("✅ Model Siap Digunakan!")

# ==========================================
# 3. ENDPOINT API PENCARIAN & EVALUASI
# ==========================================
@app.route('/cari', methods=['GET'])
def cari_rekomendasi():
    query_judul = request.args.get('judul', '').strip()
    if not query_judul:
        return jsonify({"status": "error", "pesan": "Judul tidak boleh kosong."})

    pencarian = df[df['title'].str.contains(query_judul, case=False, na=False)]
    
    if pencarian.empty:
        return jsonify({"status": "error", "pesan": f"Film '{query_judul}' tidak ditemukan di database."})
    
    film_terpilih = pencarian.iloc[0]
    
    # 1. Transformasi target teks
    vector_text_target = tfidf.transform([film_terpilih['combined_features']])
    
    # 2. Transformasi target numerik (pastikan bentuknya 2D array / DataFrame)
    data_numerik_target = film_terpilih[kolom_numerik].to_frame().T
    vector_num_target = scaler.transform(data_numerik_target)
    
    # 3. Gabungkan keduanya untuk film yang dicari
    vector_target = hstack([vector_text_target, vector_num_target])
    
    # 4. Cari tetangga terdekat (rekomendasi)
    distances, indices = knn_model.kneighbors(vector_target, n_neighbors=15)
    sim_scores = 1 - distances[0]
    indices = indices[0]
    
    top_rekomendasi = []
    peringkat = 1
    
    target_genre_set = set(bersihkan_teks(str(film_terpilih['genre'])).split())
    relevant_recommended = 0
    
    for idx, score in zip(indices, sim_scores):
        if df_train.iloc[idx]['title'].lower() == film_terpilih['title'].lower():
            continue 
            
        row = df_train.iloc[idx]
        
        rec_genre_set = set(bersihkan_teks(str(row['genre'])).split())
        is_relevant = bool(target_genre_set.intersection(rec_genre_set))
        
        if is_relevant:
            relevant_recommended += 1
            
        top_rekomendasi.append({
            "peringkat": peringkat,
            "judul": row['title'],
            "tahun": int(row['year']),
            "genre": row['genre'],
            "avg_vote": float(row['avg_vote']),
            "critics_vote": float(row['critics_vote']),
            "public_vote": float(row['public_vote']),
            "kemiripan": f"{score * 100:.2f}%"
        })
        peringkat += 1
        if len(top_rekomendasi) == 5:
            break

    jumlah_rekomendasi = len(top_rekomendasi)
    precision = relevant_recommended / jumlah_rekomendasi if jumlah_rekomendasi > 0 else 0

    return jsonify({
        "status": "success",
        "film_dicari": {
            "judul": film_terpilih['title'],
            "tahun": int(film_terpilih['year']),
            "genre": film_terpilih['genre'],
            "avg_vote": float(film_terpilih['avg_vote']),
            "critics_vote": float(film_terpilih['critics_vote']),
            "public_vote": float(film_terpilih['public_vote'])
        },
        "rekomendasi": top_rekomendasi,
        "evaluasi_pencarian": {
            "judul_film": film_terpilih['title'],
            "jumlah_rekomendasi": jumlah_rekomendasi,
            "jumlah_rekomendasi_relevan": relevant_recommended,
            "precision": f"{precision:.4f}"
        }
    })

if __name__ == '__main__':
    app.run(debug=True, use_reloader=False, port=5000)