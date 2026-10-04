import csv
import re
from datetime import datetime
from pathlib import Path

import pandas as pd


ROOT = Path(__file__).resolve().parents[1]
DOWNLOADS = Path(r"C:\Users\ASUS\Downloads")
OUT = ROOT / "storage" / "app" / "import-ready"

STUDENT_HEADERS = [
    "nik", "nisn", "nama", "jenis_kelamin", "tanggal_lahir", "no_hp", "alamat", "nama_kelas",
    "agama", "tempat_lahir", "jalan", "desa", "kecamatan", "provinsi", "kota",
    "nama_ayah", "pendidikan_ayah", "pekerjaan_ayah", "nama_ibu", "pendidikan_ibu", "pekerjaan_ibu",
    "alamat_orang_tua", "provinsi_orang_tua", "kota_orang_tua", "kode_pos", "no_hp_orang_tua", "sekolah_asal",
]

TEACHER_HEADERS = [
    "nip", "nik", "nama", "tempat_lahir", "tanggal_lahir", "jenis_kelamin", "agama",
    "status_kepegawaian", "jabatan", "tanggal_masuk", "pendidikan_terakhir", "jurusan",
    "universitas", "no_hp", "alamat",
]

SCHEDULE_HEADERS = ["nip_guru", "nama_mapel", "nama_kelas", "hari", "jam_mulai", "jam_selesai", "ruangan"]

DAYS = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat"]
PERIOD_TIMES = {
    1: ("07:00", "07:40"),
    2: ("07:40", "08:20"),
    3: ("08:20", "09:00"),
    4: ("09:30", "10:10"),
    5: ("10:10", "10:50"),
    6: ("10:50", "11:30"),
    7: ("11:30", "12:10"),
    8: ("12:40", "13:20"),
    9: ("13:20", "14:00"),
}

SUBJECT_BY_CODE = {
    "1": "Informatika",
    "2": "IPS",
    "3": "PAI",
    "4": "Matematika",
    "5": "Bahasa Indonesia",
    "5TKA": "Bahasa Indonesia",
    "6": "KKA",
    "6KKA": "KKA",
    "6TIK": "TIK",
    "7": "IPS",
    "7PKN": "PKN",
    "8": "PKN",
    "9": "IPA",
    "10": "IPA",
    "11": "Matematika",
    "11TKA": "Matematika",
    "12": "Seni",
    "13": "Bahasa Indonesia",
    "14": "Bahasa Inggris",
    "15": "PKN",
    "16": "Bahasa Inggris",
    "17": "PJOK",
    "18": "PJOK",
    "18PI": "PAI",
    "19": "Bimbingan Konseling",
    "20": "Bahasa Indonesia",
    "21": "Matematika",
    "22": "KKA",
    "22TIK": "TIK",
}


def clean(value):
    if value is None or (isinstance(value, float) and pd.isna(value)):
        return ""
    text = str(value).strip()
    if text.endswith(".0") and text[:-2].isdigit():
        text = text[:-2]
    return text.replace("\ufffd", " ")


def normalize_date(value):
    value = clean(value)
    if not value:
        return ""
    for fmt in ("%d/%m/%Y", "%Y-%m-%d", "%d-%m-%Y"):
        try:
            return datetime.strptime(value, fmt).strftime("%Y-%m-%d")
        except ValueError:
            pass
    return value


def write_csv(path, headers, rows):
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        writer.writerows(rows)


def load_teachers():
    path = DOWNLOADS / "data_dummy_guru.txt"
    rows = []
    with path.open("r", encoding="utf-8-sig", errors="replace", newline="") as handle:
        reader = csv.DictReader(handle, delimiter=";")
        for row in reader:
            fixed = {key: clean(row.get(key, "")) for key in TEACHER_HEADERS}
            fixed["tanggal_lahir"] = normalize_date(fixed["tanggal_lahir"])
            fixed["tanggal_masuk"] = normalize_date(fixed["tanggal_masuk"])
            fixed["alamat"] = re.sub(r"\s+", " ", fixed["alamat"]).strip()
            rows.append(fixed)
    return rows


def student_row(nik, nisn, name, gender, class_name, previous_school="", idx=0):
    phone = f"08123{idx:07d}"[:12]
    parent_phone = f"08223{idx:07d}"[:12]
    address = f"Jl. Pendidikan No. {idx or 1}, Jakarta"
    return {
        "nik": clean(nik),
        "nisn": clean(nisn),
        "nama": clean(name),
        "jenis_kelamin": clean(gender) or ("P" if str(name).split()[-1].endswith("A") else "L"),
        "tanggal_lahir": "",
        "no_hp": phone,
        "alamat": address,
        "nama_kelas": clean(class_name),
        "agama": "Islam",
        "tempat_lahir": "Jakarta",
        "jalan": address,
        "desa": "Kebon Jeruk",
        "kecamatan": "Kebon Jeruk",
        "provinsi": "DKI Jakarta",
        "kota": "Jakarta Barat",
        "nama_ayah": f"AYAH {clean(name).split()[0]}",
        "pendidikan_ayah": "SMA",
        "pekerjaan_ayah": "Wiraswasta",
        "nama_ibu": f"IBU {clean(name).split()[0]}",
        "pendidikan_ibu": "SMA",
        "pekerjaan_ibu": "Ibu Rumah Tangga",
        "alamat_orang_tua": address,
        "provinsi_orang_tua": "DKI Jakarta",
        "kota_orang_tua": "Jakarta Barat",
        "kode_pos": "11530",
        "no_hp_orang_tua": parent_phone,
        "sekolah_asal": clean(previous_school) or "SMP IP Yakin",
    }


def load_students():
    rows = []
    seen_nik = set()

    def add(row):
        nik = row["nik"] or row["nisn"]
        if not nik:
            return
        row["nik"] = nik
        if nik in seen_nik:
            return
        seen_nik.add(nik)
        rows.append(row)

    idx = 1
    for sheet in pd.ExcelFile(DOWNLOADS / "Pembagian Kelas 8 A-G FIX.xlsx").sheet_names:
        if not re.fullmatch(r"8[A-G]", sheet):
            continue
        df = pd.read_excel(DOWNLOADS / "Pembagian Kelas 8 A-G FIX.xlsx", sheet_name=sheet)
        for _, rec in df.iterrows():
            add(student_row(rec.get("NISN"), rec.get("NISN"), rec.get("Nama"), rec.get("JK"), sheet, rec.get("Asal"), idx))
            idx += 1

    raw9 = pd.read_excel(DOWNLOADS / "Rolling Kelas 9 A-E 2026-2027 FIX (1).xlsx", header=None)
    header_row = raw9.index[raw9.apply(lambda r: r.astype(str).str.contains("NAMA", case=False, na=False).any(), axis=1)][0]
    df9 = pd.read_excel(DOWNLOADS / "Rolling Kelas 9 A-E 2026-2027 FIX (1).xlsx", header=header_row)
    for _, rec in df9.iterrows():
        name = clean(rec.get("NAMA"))
        if not name:
            continue
        add(student_row(rec.get("NISN"), rec.get("NISN"), name, "", rec.get("KELAS BARU"), rec.get("KELAS ASAL"), idx))
        idx += 1

    template_path = DOWNLOADS / "template-import-siswafux_fix.csv"
    with template_path.open("r", encoding="utf-8-sig", errors="replace") as handle:
        for line in handle:
            line = line.strip().strip('"')
            if not line or line.startswith("nik;"):
                continue
            parts = line.split(";")
            add(student_row(parts[0], parts[1] if len(parts) > 1 else parts[0], parts[2] if len(parts) > 2 else "", "", "7A", "SD/MI", idx))
            idx += 1

    return rows


def teacher_nip_by_code(teachers):
    mapping = {}
    for idx, teacher in enumerate(teachers, start=1):
        mapping[str(idx)] = teacher["nip"]
    return mapping


def code_base(value):
    match = re.match(r"^(\d+)", str(value))
    return match.group(1) if match else ""


def load_schedules(teachers):
    df = pd.read_excel(DOWNLOADS / "jadwal kelas FIX.xlsx", sheet_name=0, header=None)
    nip_map = teacher_nip_by_code(teachers)
    groups = [
        ("Senin", 5, 20, 0, list(range(3, 11))),
        ("Selasa", 5, 20, 12, list(range(14, 23))),
        ("Rabu", 5, 20, 24, list(range(26, 34))),
        ("Kamis", 24, 40, 12, list(range(14, 23))),
        ("Jumat", 24, 40, 24, list(range(26, 31))),
    ]
    rows = []
    seen = set()
    for day, start_row, end_row, class_col, period_cols in groups:
        for r in range(start_row, end_row):
            class_name = clean(df.iat[r, class_col])
            if not re.fullmatch(r"[789][A-G]", class_name):
                continue
            slots = []
            for offset, col in enumerate(period_cols, start=1):
                value = clean(df.iat[r, col])
                if not value or not re.match(r"^\d", value):
                    continue
                slots.append((offset, value))
            if not slots:
                continue
            block_start = slots[0][0]
            prev_period, prev_code = slots[0]
            for period, value in slots[1:] + [(None, None)]:
                if value != prev_code or period != prev_period + 1:
                    start_time = PERIOD_TIMES[block_start][0]
                    end_time = PERIOD_TIMES[prev_period][1]
                    base = code_base(prev_code)
                    subject = SUBJECT_BY_CODE.get(prev_code, SUBJECT_BY_CODE.get(base, f"Mapel {prev_code}"))
                    key = (day, class_name, start_time, end_time)
                    if base in nip_map and key not in seen:
                        rows.append({
                            "nip_guru": nip_map[base],
                            "nama_mapel": subject,
                            "nama_kelas": class_name,
                            "hari": day,
                            "jam_mulai": start_time,
                            "jam_selesai": end_time,
                            "ruangan": class_name,
                        })
                        seen.add(key)
                    block_start = period
                prev_period, prev_code = period, value
    return rows


def main():
    teachers = load_teachers()
    students = load_students()
    schedules = load_schedules(teachers)
    write_csv(OUT / "import-guru-final.csv", TEACHER_HEADERS, teachers)
    write_csv(OUT / "import-siswa-final.csv", STUDENT_HEADERS, students)
    write_csv(OUT / "import-jadwal-final.csv", SCHEDULE_HEADERS, schedules)
    print(f"guru={len(teachers)} siswa={len(students)} jadwal={len(schedules)}")
    print(OUT)


if __name__ == "__main__":
    main()
