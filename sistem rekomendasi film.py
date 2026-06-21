from flask import Flask, request, jsonify
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.neighbors import NearestNeighbors
import re

app = Flask(__name__)

# ==========================================
# 1. LOAD DATASET & PREPROCESSING 
# ==========================================
print("⏳ Memuat dataset dan melatih model KNN... Mohon tunggu.")
df = pd.read_csv('filmtv_movies.csv', delimiter=';')

features_text = ['genre', 'directors', 'actors', 'description']
features_num = ['year', 'humor', 'rhythm', 'effort', 'tension', 'erotism']

for feature in features_text:
    df[feature] = df[feature].fillna('')

for feature in features_num:
    df[feature] = df[feature].fillna(0).astype(int)

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
    
    year_feat = f"year_{row['year']}" if row['year'] > 0 else ""
    humor_feat = f"humor_{row['humor']}"
    rhythm_feat = f"rhythm_{row['rhythm']}"
    effort_feat = f"effort_{row['effort']}"
    tension_feat = f"tension_{row['tension']}"
    erotism_feat = f"erotism_{row['erotism']}"
    
    return f"{text_base} {year_feat} {humor_feat} {rhythm_feat} {effort_feat} {tension_feat} {erotism_feat}"

df['combined_features'] = df.apply(combine_features, axis=1)
df = df.dropna(subset=['title']).reset_index(drop=True)

# ==========================================
# 2. PEMBAGIAN DATA & VEKTORISASI TF-IDF
# ==========================================
df_train, df_test = train_test_split(df, test_size=0.2, random_state=42)
df_train = df_train.reset_index(drop=True)
df_test = df_test.reset_index(drop=True)

stop_words_list = list(TfidfVectorizer(stop_words='english').get_stop_words()) + ['il', 'lo', 'i', 'gli', 'la', 'le', 'un', 'una', 'di', 'del', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra']

tfidf = TfidfVectorizer(stop_words=stop_words_list)
tfidf_matrix_train = tfidf.fit_transform(df_train['combined_features'])
train_genres = df_train['genre'].values 

knn_model = NearestNeighbors(metric='cosine', algorithm='brute')
knn_model.fit(tfidf_matrix_train)
print("✅ Model Siap Digunakan!")

# ==========================================
# 3. ENDPOINT API PENCARIAN
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
    vector_target = tfidf.transform([film_terpilih['combined_features']])
    
    distances, indices = knn_model.kneighbors(vector_target, n_neighbors=15)
    sim_scores = 1 - distances[0]
    indices = indices[0]
    
    top_rekomendasi = []
    peringkat = 1
    
    for idx, score in zip(indices, sim_scores):
        if df_train.iloc[idx]['title'].lower() == film_terpilih['title'].lower():
            continue 
            
        row = df_train.iloc[idx]
        top_rekomendasi.append({
            "peringkat": peringkat,
            "judul": row['title'],
            "tahun": int(row['year']),
            "genre": row['genre'],
            "akurasi": f"{score * 100:.2f}%"
        })
        peringkat += 1
        if len(top_rekomendasi) == 5:
            break

    return jsonify({
        "status": "success",
        "film_dicari": {
            "judul": film_terpilih['title'],
            "tahun": int(film_terpilih['year']),
            "genre": film_terpilih['genre']
        },
        "rekomendasi": top_rekomendasi
    })

# ==========================================
# 4. ENDPOINT API EVALUASI
# ==========================================
@app.route('/evaluasi', methods=['GET'])
def evaluasi_sistem():
    k = 10
    sample_size = 200
    
    df_test_sample = df_test.head(sample_size)
    tfidf_matrix_test = tfidf.transform(df_test_sample['combined_features'])
    
    distances, indices = knn_model.kneighbors(tfidf_matrix_test, n_neighbors=k)
    
    total_precision, total_recall, total_f1 = 0, 0, 0
    train_genres_clean = [set(bersihkan_teks(str(g)).split()) for g in train_genres]
    
    for i in range(sample_size):
        target_genre_raw = df_test_sample.iloc[i]['genre']
        target_genre_set = set(bersihkan_teks(str(target_genre_raw)).split())
        
        if not target_genre_set:
            continue
            
        total_relevant_in_train = sum(1 for g_set in train_genres_clean if target_genre_set.intersection(g_set))
        top_k_indices = indices[i]
        relevant_recommended = 0
        
        for idx in top_k_indices:
            rec_genre_set = train_genres_clean[idx]
            if target_genre_set.intersection(rec_genre_set):
                relevant_recommended += 1
        
        precision = relevant_recommended / k
        recall = relevant_recommended / total_relevant_in_train if total_relevant_in_train > 0 else 0
        f1 = 2 * (precision * recall) / (precision + recall) if (precision + recall) > 0 else 0
        
        total_precision += precision
        total_recall += recall
        total_f1 += f1
        
    avg_precision = total_precision / sample_size
    avg_recall = total_recall / sample_size
    avg_f1 = total_f1 / sample_size
    
    return jsonify({
        "status": "success",
        "sample_size": sample_size,
        "k_value": k,
        "precision": f"{avg_precision:.4f}",
        "recall": f"{avg_recall:.4f}",
        "f1_score": f"{avg_f1:.4f}"
    })

if __name__ == '__main__':
    app.run(debug=True, port=5000)