# Struga Events — Rental Management System

**South East European University (SEEU)**  
Software Engineering · Semester 6, 2026  

**Student:** Drilon Merko (131990) · **Mentor:** Mentor Hamiti  
**Email:** dm31990@seeu.edu.mk · **Role:** Full Stack Developer  

---


## Përmbajtja e projektit

```
StrugaEventsRental/
├── frontend/          Faqet web (katalog, klient, admin)
├── backend/           API PHP (rezervime, fatura, inventar)
├── sql/schema.sql     Databaza MySQL
├── uploads/           Fotot e artikujve
├── docs/              Dokumentacioni akademik (Word-aligned)
└── README.md          Ky file
```

## Instalim i shpejtë (XAMPP — Windows)

### 1. Kopjo projektin

Kopjoje folderin **`StrugaEventsRental`** në:

```
C:\xampp\htdocs\StrugaEventsRental
```

### 2. Databaza

1. Hap **phpMyAdmin**: http://localhost/phpmyadmin  
2. Kliko **Import** → zgjidh `sql/schema.sql` → **Go**  

Ose në terminal:

```bash
mysql -u root -p < sql/schema.sql
```

### 3. Konfigurimi

Hap `backend/db.php` dhe vendos fjalëkalimin e MySQL (zakonisht bosh për XAMPP):

```php
$user = 'root';
$pass = '';   // ose fjalëkalimi yt
```

### 4. Hap aplikacionin

```
http://localhost/StrugaEventsRental/frontend/index.html
```

## Llogaritë demo

| Roli | Email | Fjalëkalimi |
|------|-------|-------------|
| Administrator | admin@strugaevents.mk | password |
| Punonjës | dm31990@seeu.edu.mk | password |
| Klient | ana.customer@example.com | password |


## Funksionalitetet (sipas propozimit)

- Katalog publik me kategori, çmime, foto  
- Kontroll disponueshmërie sipas datës (parandalon double-booking)  
- Shportë rezervimi (më shumë artikuj)  
- Panel klienti: rezervime, anulim, faturë (print/PDF)  
- Panel punonjësi/admin: aprovim, inventar, kalendar FullCalendar, raporte  

## Dokumentacioni për dorëzim

| File | Përshkrim |
|------|-----------|
| `docs/SOFTWARE_ENGINEERING_PROJECT.md` | Raporti i plotë (seksionet 1–8, diagramë) |
| `docs/TESTING.md` | Testet T01–T20 |
| `docs/PROJECT_PROPOSAL.md` | Përmbledhje |

## Kërkesat

- PHP 7.4+  
- MySQL 5.7+ / MariaDB  
- Apache (XAMPP/WAMP/MAMP)  

---

© 2026 Struga Events Rental — SEEU Tetovo
