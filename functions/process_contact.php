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
            
            // Slanje email obaveštenja
            $to = "aleksamilosevic649@gmail.com";
            $subject = "Nova kontakt poruka - " . ucfirst($tema);
            
            // Formatiranje email poruke
            $message = "Dobili ste novu kontakt poruku sa sajta:\n\n";
            $message .= "Ime i prezime: " . $ime . "\n";
            $message .= "Email: " . $email . "\n";
            $message .= "Tema: " . $tema . "\n";
            $message .= "Datum: " . date('d.m.Y H:i:s') . "\n\n";
            $message .= "Poruka:\n";
            $message .= $poruka . "\n\n";
            $message .= "---\n";
            $message .= "Ova poruka je automatski generisana sa kontakt forme.";
            
            // Email headers
            $headers = "From: noreply@onlineticket.rs\r\n";
            $headers .= "Reply-To: " . filter_var($email, FILTER_SANITIZE_EMAIL) . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Slanje emaila (ne prekidaj proces ako email ne uspe)
            @mail($to, $subject, $message, $headers);
            
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
