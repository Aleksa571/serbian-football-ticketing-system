<?php
include 'conn.php';

$greska = "";
$uspeh = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preuzimanje i čišćenje podataka
    $ime = isset($_POST['ime']) ? trim($_POST['ime']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tema = isset($_POST['tema']) ? trim($_POST['tema']) : '';
    $poruka = isset($_POST['poruka']) ? trim($_POST['poruka']) : '';

    // Validacija podataka
    if (empty($ime) || empty($email) || empty($tema) || empty($poruka)) {
        $greska = "Molimo popunite sva polja.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $greska = "Unesite validnu email adresu.";
    } elseif (strlen($ime) < 2) {
        $greska = "Ime mora imati najmanje 2 karaktera.";
    } elseif (strlen($poruka) < 10) {
        $greska = "Poruka mora imati najmanje 10 karaktera.";
    } else {
        // Kreiranje tabele ako ne postoji
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS kontakt_poruke (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ime VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                tema VARCHAR(50) NOT NULL,
                poruka TEXT NOT NULL,
                datum_kreiranja TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                procitano TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Tabela već postoji ili neka druga greška
        }

        // Unos poruke u bazu podataka
        try {
            $stmt = $pdo->prepare("INSERT INTO kontakt_poruke (ime, email, tema, poruka) 
                                   VALUES (:ime, :email, :tema, :poruka)");
            $stmt->execute([
                ':ime' => $ime,
                ':email' => $email,
                ':tema' => $tema,
                ':poruka' => $poruka
            ]);
            $uspeh = true;
            
            // Slanje email obaveštenja preko Formspree API-ja
            // ZAMENITE 'YOUR_FORMSPREE_FORM_ID' sa vašim stvarnim Formspree form ID-jem
            // Možete ga dobiti na: https://formspree.io/ - kreirajte novi form i kopirajte ID
            $formspree_url = "https://formspree.io/f/mjklrbep";
            
            // Priprema podataka za Formspree
            $formspree_data = array(
                'name' => $ime,
                'email' => $email,
                'subject' => "Nova kontakt poruka - " . ucfirst($tema),
                'tema' => $tema,
                'message' => $poruka,
                '_replyto' => $email,
                '_subject' => "Nova kontakt poruka - " . ucfirst($tema)
            );
            
            // Slanje zahteva ka Formspree API-ju
            $ch = curl_init($formspree_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formspree_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Provera da li je zahtev uspešan (ne prekidamo proces ako email ne uspe)
            if ($http_code !== 200) {
                error_log("Formspree greška: HTTP " . $http_code . " - " . $response);
            }
            
        } catch (PDOException $e) {
            $greska = "Došlo je do greške pri čuvanju poruke. Molimo pokušajte ponovo.";
        }
    }

    // Redirekcija na osnovu rezultata
    if ($uspeh) {
        header("Location: ../view/contact.php?success=1");
        exit();
    } else {
        header("Location: ../view/contact.php?error=1");
        exit();
    }
} else {
    // Ako nije POST zahtev, redirektuj na kontakt stranu
    header("Location: ../view/contact.php");
    exit();
}
?>
