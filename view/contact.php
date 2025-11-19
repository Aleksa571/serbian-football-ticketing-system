<?php include '../functions/session_checker.php'; ?>
<?php
include '../functions/conn.php';

// Definiši $idKorisnik iz sesije
$idKorisnik = $_SESSION['id_korisnika'] ?? null;

// Broj stavki u korpi
$brojStavki = 0;
if ($idKorisnik) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM korpa WHERE idKorisnik = ?");
    $stmt->execute([$idKorisnik]);
    $brojStavki = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontakt</title>
    <link rel="stylesheet" href="../style/index.css">
    <link rel="stylesheet" href="../style/contact.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
            <ul class="a">
                <li><a href="../index.php">Početna</a></li>
                <li><a href="about.html">O nama</a></li>
                <li><a href="contact.php">Kontakt</a></li>
            </ul>
            <div class="nav-logo">
                <a href="../index.php">
                    <img src="../slike/logo.png" alt="Online Ticket Logo" class="header-logo">
                </a>
            </div>
            <ul class="b">
                <?php if ($idKorisnik): ?>
                    <li><a href="../functions/logOut.php">LOG OUT</a></li>
                    <li>
                        <a href="korpa.php" class="korpa-link">
                            🛒
                            <span class="korpa-badge">
                                <?= $brojStavki ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <form id="isprazniForm" method="post" action="../functions/isprazni.php" class="inline-form">
                            <a href="#" class="isprazni" onclick="document.getElementById('isprazniForm').submit(); return false;">
                                Isprazni korpu
                            </a>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="login.php">LOGIN</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="contact-page-wrapper">
        <div class="container">
            <div class="contact-header">
                <h1>Kontaktirajte nas</h1>
                <h2>Pošaljite nam poruku ili nas kontaktirajte putem društvenih mreža</h2>
            </div>

            <?php
            $poruka = "";
            $greska = "";
            if (isset($_GET['success']) && $_GET['success'] == '1') {
                $poruka = "Vaša poruka je uspešno poslata! Odgovorićemo vam uskoro.";
            }
            if (isset($_GET['error'])) {
                $greska = "Došlo je do greške pri slanju poruke. Molimo pokušajte ponovo.";
            }
            ?>

            <?php if ($poruka): ?>
                <div class="success-message"><?php echo $poruka; ?></div>
            <?php endif; ?>

            <?php if ($greska): ?>
                <div class="error-message"><?php echo $greska; ?></div>
            <?php endif; ?>

            <div class="contact-form-container">
                <form action="https://formspree.io/f/mjklrbep" method="POST" class="contact-form" id="contactForm" autocomplete="off">
                    <div class="form-group">
                        <label for="ime">Ime i prezime:</label>
                        <input type="text" id="ime" name="name" placeholder="Unesite vaše ime i prezime" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email adresa:</label>
                        <input type="email" id="email" name="email" placeholder="vas.email@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="tema">Tema:</label>
                        <select id="tema" name="tema" required>
                            <option value="">Izaberite temu</option>
                            <option value="pitanje">Pitanje o kartama</option>
                            <option value="problem">Problem sa kupovinom</option>
                            <option value="predlog">Predlog ili sugestija</option>
                            <option value="ostalo">Ostalo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="poruka">Poruka:</label>
                        <textarea id="poruka" name="message" rows="6" placeholder="Unesite vašu poruku..." required></textarea>
                    </div>

                    <input type="hidden" name="_subject" value="Nova kontakt poruka sa sajta">
                    <input type="hidden" name="_format" value="plain">
                    <input type="hidden" name="_captcha" value="false">
                    <input type="hidden" name="_template" value="box">
                    <input type="hidden" name="_next" id="_next" value="">
                    <!-- Honeypot polje za zaštitu od spama -->
                    <input type="text" name="_gotcha" style="display:none" tabindex="-1" autocomplete="off">

                    <button type="submit" class="submit-btn">Pošalji poruku</button>
                </form>
            </div>

            <script>
            // Postavi URL za redirekciju nakon slanja
            document.getElementById('contactForm').addEventListener('submit', function() {
                const url = window.location.href.split('?')[0] + '?success=1';
                document.getElementById('_next').value = url;
            });

            // Funkcija za resetovanje forme
            function resetForm() {
                const form = document.getElementById('contactForm');
                if (form) {
                    form.reset();
                    if (window.location.search.includes('success=1')) {
                        window.history.replaceState({}, '', window.location.pathname);
                    }
                }
            }

            // Resetuj formu ako postoji success parametar
            if (window.location.search.includes('success=1')) {
                resetForm();
            }

            // Resetuj formu kada se stranica učita iz cache-a (npr. nakon "go back")
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || window.location.search.includes('success=1')) {
                    resetForm();
                }
            });
            </script>

            <div class="social-links">
                <h3>Ili nas kontaktirajte putem:</h3>
                <div class="social-icons">
                    <a href="https://www.instagram.com/aleksa_milosevicc/" target="_blank">
                        <img src="../slike/instagram.png" alt="Instagram">
                    </a>
                    <a href="https://www.facebook.com/" target="_blank">
                        <img src="../slike/facebook.png" alt="Facebook">
                    </a>
                </div>
            </div>

            <div class="map">
                <h3>Pronađite nas:</h3>
                <a href="https://www.google.com/maps/place/Univerzitet+Singidunum/@44.7726199,20.4549105,13.18z/data=!4m6!3m5!1s0x475a70675616a667:0x31457a4b1766e54a!8m2!3d44.781636!4d20.4793108!16s%2Fm%2F06zm5v1?entry=ttu&g_ep=EgoyMDI1MDQwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank">
                    <img src="../slike/map.png" alt="Singidunum Location">
                </a>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <img src="../slike/logo.png" alt="Online Ticket Logo" class="footer-logo">
            </div>
            <div class="footer-section">
                <h3>Brzi linkovi</h3>
                <ul>
                    <li><a href="../index.php">Početna</a></li>
                    <li><a href="about.html">O nama</a></li>
                    <li><a href="contact.php">Kontakt</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Politika</h3>
                <ul>
                    <li><a href="#">Privatnost</a></li>
                    <li><a href="#">Uslovi korišćenja</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Kontaktiraj nas</h3>
                <ul>
                    <li>info@onlineticket.rs</li>
                    <li>+381 11 123 4567</li>
                    <li>Beograd, Srbija</li>
                </ul>
            </div>
        </div>

        <div class="footer-divider"></div>

        <div class="footer-partners">
            <h3>Naši partneri</h3>
            <div class="partners-grid">
                <img src="../slike/novipazar.png" alt="Novi Pazar">
                <img src="../slike/napredak.png" alt="Napredak">
                <img src="../slike/cukaricki.png" alt="Cukaricki">
                <img src="../slike/tsc.png" alt="TSC">
                <img src="../slike/czv.svg" alt="Crvena Zvezda">
                <img src="https://upload.wikimedia.org/wikipedia/sr/thumb/2/24/FSS_logo.svg/500px-FSS_logo.svg.png?20250828220326" alt="FSS">
                <img src="../slike/partizan.png" alt="Partizan">
                <img src="../slike/vojvodina.png" alt="Vojvodina">
                <img src="../slike/radnicki.png" alt="Radnicki">
                <img src="../slike/ofkbg.png" alt="OFK Beograd">
                <img src="../slike/zeleznicar.png" alt="Zeleznicar">
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2025 Online Ticket. Sva prava zadržana.</p>
            <div class="footer-socials">
                <a href="https://instagram.com" target="_blank" title="Instagram">
                    <img src="../slike/instagram.png" alt="Instagram" class="instagram-icon">
                </a>
                <a href="https://facebook.com" target="_blank" title="Facebook">
                    <img src="../slike/facebook.png" alt="Facebook" class="instagram-icon">
                </a>
                <a href="https://twitter.com" target="_blank" title="Twitter">𝕏</a>
            </div>
        </div>
    </footer>
</body>
</html>
