# 🎟️ Serbian Football Ticketing System

Web aplikacija za online prodaju karata za fudbalske utakmice Mozzart Bet Super Lige Srbije.

## 📋 Opis projekta

Aplikacija omogućava korisnicima da pretražuju, pregledaju i kupuju karte za fudbalske utakmice. Sistem podržava različite tribine, kategorije karata i nudi funkcionalnosti za upravljanje korpom, registraciju korisnika i admin panel za upravljanje utakmicama i kartama.

## ✨ Funkcionalnosti

### Za korisnike:
- 🔍 **Pretraga karata** - Pretraga po timovima, tribinama i kategorijama
- 🛒 **Korpa za kupovinu** - Dodavanje karata u korpu i upravljanje stavkama
- 👤 **Registracija i prijava** - Sistem za registraciju i autentifikaciju korisnika
- 💱 **Kalkulator cena** - Konverzija cena iz EUR u RSD
- 🎁 **Promocije** - Sistem za osvajanje popusta na karte
- 📄 **Izveštaji** - Pregled kupljenih karata

### Za administratore:
- ➕ **Dodavanje utakmica** - Kreiranje novih utakmica sa detaljima
- 🎫 **Upravljanje kartama** - Dodavanje, izmena i brisanje karata
- 👥 **Upravljanje korisnicima** - Aktivacija/deaktivacija korisničkih naloga
- 📊 **Pregled podataka** - Administrativni panel sa svim podacima

## 🛠️ Tehnologije

- **Backend:** PHP
- **Baza podataka:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Server:** Apache (XAMPP)

## 📁 Struktura projekta

```
PhpSqlSajt/
├── functions/          # PHP funkcije i logika
│   ├── conn.php       # Konekcija na bazu podataka
│   ├── get_timovi.php # API za pretragu timova
│   └── ...
├── view/              # PHP stranice
│   ├── admin.php      # Admin panel
│   ├── korpa.php      # Korpa za kupovinu
│   ├── login.php      # Prijava korisnika
│   └── ...
├── style/             # CSS fajlovi
├── slike/             # Slike i resursi
└── index.php          # Početna stranica
```

## 🚀 Instalacija

### Preduslovi
- XAMPP (ili sličan LAMP/WAMP stack)
- PHP 7.4 ili noviji
- MySQL 5.7 ili noviji

### Koraci za instalaciju

1. **Klonirajte repozitorijum**
   ```bash
   git clone https://github.com/Aleksa571/serbian-football-ticketing-system.git
   cd serbian-football-ticketing-system
   ```

2. **Postavite projekat u XAMPP**
   - Kopirajte folder u `C:\xampp\htdocs\` (Windows) ili `/var/www/html/` (Linux)

3. **Kreirajte bazu podataka**
   - Otvorite phpMyAdmin (`http://localhost/phpmyadmin`)
   - Kreirajte novu bazu podataka sa nazivom `prodaja_karata`
   - Importujte SQL fajl (ako postoji) ili kreirajte tabele ručno

4. **Konfigurišite konekciju na bazu**
   - Kreirajte fajl `functions/conn.php` sa sledećim sadržajem:
   ```php
   <?php
   $db_server = "localhost";
   $db_user = "root";
   $db_pass = "";
   $db_name = "prodaja_karata";
   
   try {
       $pdo = new PDO("mysql:host=$db_server;dbname=$db_name;charset=utf8", $db_user, $db_pass);
       $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   } catch (PDOException $e) {
       die("Konekcija nije uspela: " . $e->getMessage());
   }
   ```

5. **Pokrenite aplikaciju**
   - Otvorite `http://localhost/PhpSqlSajt` u browseru

## 🔐 Bezbednost

- Fajl `functions/conn.php` je u `.gitignore` i ne sadrži osetljive podatke u repozitorijumu
- Korisnici moraju da kreiraju svoj `conn.php` fajl sa lokalnim podacima za bazu
- Sesije se koriste za autentifikaciju korisnika
- Admin panel je zaštićen proverom uloge korisnika

## 📝 Napomene

- Ovaj projekat je kreiran za edukativne svrhe
- Baza podataka treba da bude kreirana pre pokretanja aplikacije
- Preporučuje se promena podrazumevanih kredencijala za produkciju

## 👤 Autor

**Aleksa Milošević**
- GitHub: [@Aleksa571](https://github.com/Aleksa571)

## 📄 Licenca

Ovaj projekat je otvorenog koda i dostupan je pod [MIT License](LICENSE).

---

⭐ Ako vam se projekat sviđa, ostavite zvezdu!

