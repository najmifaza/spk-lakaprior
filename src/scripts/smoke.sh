#!/usr/bin/env bash
# Uji HTTP aplikasi. Jalankan server dulu:
#   cd spk-lakaprior && php -S 127.0.0.1:8123 -t src
# lalu:
#   bash src/scripts/smoke.sh [BASE_URL]
set -u

BASE="${1:-http://127.0.0.1:8123}"
# curl.exe adalah program native Windows: direktori temp MSYS (/tmp/...) tidak dikenali,
# jadi pakai direktori di dalam project.
TMP="$(cygpath -m "$PWD" 2>/dev/null || echo "$PWD")/.smoke-tmp"
rm -rf "$TMP"
mkdir -p "$TMP"
JAR="$TMP/cookie.txt"
FAIL=0

ok()   { printf '%-58s OK\n' "$1"; }
bad()  { printf '%-58s GAGAL (%s)\n' "$1" "$2"; FAIL=$((FAIL + 1)); }
check(){ [ "$2" = "1" ] && ok "$1" || bad "$1" "$3"; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

# 1. halaman terproteksi harus redirect ke login
LOC=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/index.php")
CODE=${LOC%% *}
check "index.php tanpa login -> 302 login.php" \
      "$([ "$CODE" = 302 ] && echo 1 || echo 0)" "$LOC"

# 2. login: cek password salah dulu (jar terpisah), baru login benar
curl -s -c "$TMP/bad.txt" "$BASE/login.php" -o "$TMP/bad.html"
TOKENBAD=$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TMP/bad.html" | head -1 | sed 's/.*value="//;s/"//')
WRONG=$(curl -s -b "$TMP/bad.txt" -c "$TMP/bad.txt" -X POST \
        -d "csrf=$TOKENBAD&username=admin&password=salah" -o /dev/null -w '%{http_code}' "$BASE/login.php")
check "login password salah ditolak (tetap di form)" "$([ "$WRONG" = 200 ] && echo 1 || echo 0)" "code=$WRONG"

curl -s -c "$JAR" "$BASE/login.php" -o "$TMP/login.html"
TOKEN=$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TMP/login.html" | head -1 | sed 's/.*value="//;s/"//')
check "login.php memuat csrf token" "$([ -n "$TOKEN" ] && echo 1 || echo 0)" "token kosong"

LOGIN=$(curl -s -b "$JAR" -c "$JAR" -X POST \
        -d "csrf=$TOKEN&username=admin&password=admin123" \
        -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/login.php")
check "login POST -> 302 index.php" \
      "$([ "${LOGIN%% *}" = 302 ] && echo 1 || echo 0)" "$LOGIN"

# 3. dashboard
curl -s -b "$JAR" -c "$JAR" "$BASE/index.php" -o "$TMP/index.html"
check "index.php memuat kecamatan peringkat 1" \
      "$(grep -q 'Sukoharjo' "$TMP/index.html" && echo 1 || echo 0)" "teks tidak ada"
check "index.php menampilkan Ci 0,7099" \
      "$(grep -q '0,7099' "$TMP/index.html" && echo 1 || echo 0)" "angka tidak ada"

# 4. halaman perhitungan
curl -s -b "$JAR" -c "$JAR" "$BASE/hitung.php" -o "$TMP/hitung.html"
for bagian in "Matriks keputusan" "Matriks ternormalisasi R" "Matriks terbobot Y" \
              "Jarak dan nilai preferensi Ci" "SAW sebagai pembanding" \
              "Perbandingan peringkat TOPSIS vs SAW"; do
  check "hitung.php memuat: $bagian" \
        "$(grep -q "$bagian" "$TMP/hitung.html" && echo 1 || echo 0)" "bagian tidak ada"
done
for angka in "0,7099" "0,0628" "0,1537"; do
  check "hitung.php memuat angka uji manual $angka" \
        "$(grep -q "$angka" "$TMP/hitung.html" && echo 1 || echo 0)" "angka tidak ada"
done

# 5. halaman data + tulis bobot
curl -s -b "$JAR" -c "$JAR" "$BASE/data.php" -o "$TMP/data.html"
check "data.php memuat form nilai alternatif" \
      "$(grep -q 'Simpan semua nilai' "$TMP/data.html" && echo 1 || echo 0)" "form tidak ada"
CSRF2=$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TMP/data.html" | head -1 | sed 's/.*value="//;s/"//')
BOBOT="bobot[1]=0.20&bobot[2]=0.20&bobot[3]=0.10&bobot[4]=0.10&bobot[5]=0.15&bobot[6]=0.15&bobot[7]=0.10"
TIPE="tipe[1]=benefit&tipe[2]=benefit&tipe[3]=benefit&tipe[4]=benefit&tipe[5]=benefit&tipe[6]=benefit&tipe[7]=cost"
SAVE=$(curl -s -b "$JAR" -c "$JAR" -X POST -d "csrf=$CSRF2&action=simpan_bobot&$BOBOT&$TIPE" \
       -o /dev/null -w '%{http_code}' "$BASE/data.php")
check "simpan_bobot POST -> 302" "$([ "$SAVE" = 302 ] && echo 1 || echo 0)" "code=$SAVE"
check "pesan sukses tampil setelah simpan bobot" \
      "$(curl -s -b "$JAR" "$BASE/data.php" | grep -q 'Bobot dan tipe kriteria tersimpan' && echo 1 || echo 0)" "pesan tidak ada"

# 6. CSRF ditolak
CSRFBAD=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST -d "action=simpan_bobot" "$BASE/data.php")
check "POST tanpa csrf ditolak (400)" "$([ "$CSRFBAD" = 400 ] && echo 1 || echo 0)" "code=$CSRFBAD"

rm -rf "$TMP"
if [ "$FAIL" -eq 0 ]; then
  echo; echo "SEMUA UJI HTTP LULUS"
else
  echo; echo "$FAIL UJI HTTP GAGAL"
fi
exit "$FAIL"