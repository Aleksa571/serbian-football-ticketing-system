<?php
include 'conn.php';

$greska = "";
$uspeh = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ime = isset($_POST['ime']) ? trim($_POST['ime']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tema = isset($_POST['tema']) ? trim($_POST['tema']) : '';
    $poruka = isset($_POST['poruka']) ? trim($_POST['poruka']) : '';

    // Validacija
    if (empty($ime) || empty($email) || empty($tema) || empty($poruka)) {
        $greska = "Molimo popunite sva polja.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $greska = "Unesite validnu email adresu.";
    } elseif (strlen($ime) < 2) {
        $greska = "Ime mora imati najmanje 2 karaktera.";
    } elseif (strlen($poruka) < 10) {
        $greska = "Poruka mora imati najmanje 10 karaktera.";
    } else {
        // Proveri da li postoji tabela kontakt_poruke, ako ne, kreiraj je
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

        // Unesi poruku u bazu
        try {
            $stmt = $pdo->prepare("INSERT INTO kontakt_poruke (ime, email, tema, poruka) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ime, $email, $tema, $poruka]);
            $uspeh = true;
            
            // Pošalji email obaveštenje
            $to = "aleksamilosevic649@gmail.com";
            $subject = "Nova kontakt poruka - " . ucfirst($tema);
            
            // Formatiranje email poruke
            $message = "Dobili ste novu kontakt poruku sa sajta:\n\n";
            $message .= "Ime i prezime: " . htmlspecialchars($ime) . "\n";
            $message .= "Email: " . htmlspecialchars($email) . "\n";
            $message .= "Tema: " . htmlspecialchars($tema) . "\n";
            $message .= "Datum: " . date('d.m.Y H:i:s') . "\n\n";
            $message .= "Poruka:\n";
            $message .= htmlspecialchars($poruka) . "\n\n";
            $message .= "---\n";
            $message .= "Ova poruka je automatski generisana sa kontakt forme.";
            
            // Email headers
            $headers = "From: noreply@onlineticket.rs\r\n";
            $headers .= "Reply-To: " . htmlspecialchars($email) . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Pošalji email (ne prekidaj proces ako email ne uspe)
            @mail($to, $subject, $message, $headers);
            
        } catch (PDOException $e) {
            $greska = "Došlo je do greške pri čuvanju poruke. Molimo pokušajte ponovo.";
        }
    }

    if ($uspeh) {
        header("Location: ../view/contact.php?success=1");
        exit();
    } else {
        header("Location: ../view/contact.php?error=1");
        exit();
    }
} else {
    header("Location: ../view/contact.php");
    exit();
}
?>

