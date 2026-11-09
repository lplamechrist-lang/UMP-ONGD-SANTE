<?php
declare(strict_types=1);
/* Configuration : SQLite reste hors du dossier public du serveur. */
const UMP_NOM = 'Union des Médecins pour la Population';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ump_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function admin(): bool { return !empty($_SESSION['admin_id']); }
function csrf_valide(?string $token): bool { return is_string($token) && hash_equals(csrf(), $token); }
function db(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Activez les extensions pdo_sqlite et sqlite3 dans le fichier php.ini de XAMPP, puis redémarrez Apache.');
    $racine = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: __DIR__;
    $dossier = getenv('UMP_DATA_DIR') ?: dirname($racine).DIRECTORY_SEPARATOR.'ump_donnees_'.substr(hash('sha256', __DIR__), 0, 10);
    if (!is_dir($dossier) && !mkdir($dossier, 0700, true) && !is_dir($dossier)) throw new RuntimeException('Le dossier de données ne peut pas être créé. Configurez UMP_DATA_DIR vers un dossier accessible en écriture et situé hors du dossier public.');
    $normaliser = static fn(string $p): string => strtolower(rtrim(str_replace('\\','/',$p),'/'));
    $r=$normaliser($racine); $d=$normaliser((string)realpath($dossier));
    if ($d === $r || str_starts_with($d, $r.'/')) throw new RuntimeException('Le dossier de données doit être situé hors du dossier public du serveur.');
    $db = new PDO('sqlite:'.$dossier.DIRECTORY_SEPARATOR.'ump.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec("CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS inquiries (id TEXT PRIMARY KEY, created_at TEXT NOT NULL, kind TEXT NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL, organization TEXT NOT NULL DEFAULT '', message TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'nouveau', notes TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS publications (id TEXT PRIMARY KEY, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, summary TEXT NOT NULL, body TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'brouillon');
CREATE TABLE IF NOT EXISTS login_attempts (ip_hash TEXT NOT NULL, attempted_at INTEGER NOT NULL);
CREATE INDEX IF NOT EXISTS inquiries_date ON inquiries(created_at);
CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip_hash,attempted_at);");
    return $db;
}
function requete(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function domaines(): array {
    return [
        ['id'=>'sante','title'=>'Santé et nutrition','subtitle'=>'Prévention et sensibilisation','body'=>'La sensibilisation à l’épilepsie, aux accidents vasculaires cérébraux et au VIH/sida fait partie des activités de l’UMP ONGD.','label'=>'Santé et nutrition'],
        ['id'=>'formation','title'=>'Recherche et formation','subtitle'=>'Transmettre et approfondir les savoirs','body'=>'Les domaines d’intervention comprennent la recherche, l’éducation, le genre et la formation professionnelle.','label'=>'Recherche et formation'],
        ['id'=>'humanitaire','title'=>'Action humanitaire','subtitle'=>'Protection et action sociale','body'=>'La protection, l’action sociale et les interventions humanitaires font partie des domaines d’engagement de l’Union des Médecins pour la Population.','label'=>'Action humanitaire'],
        ['id'=>'environnement','title'=>'Environnement','subtitle'=>'Développement durable','body'=>'L’ONG intègre l’environnement et le développement durable dans ses domaines d’intervention.','label'=>'Développement durable'],
        ['id'=>'communication','title'=>'Communication','subtitle'=>'Information et numérique','body'=>'La communication numérique figure parmi les activités de l’UMP ONGD. Le site facilite la découverte de ces domaines et la prise de contact.','label'=>'Communication numérique'],
        ['id'=>'paix','title'=>'Justice et paix','subtitle'=>'Solidarité et vivre ensemble','body'=>'La justice et la paix complètent les domaines d’intervention présentés par l’ONG.','label'=>'Justice et paix']
    ];
}
function logo(): string { return '<span class="logo"><img src="logo.png" alt="Logo officiel UMP ONGD" width="686" height="1536"></span>'; }
function page_debut(string $titre, string $courante): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    if (in_array($courante, ['admin.php','connexion.php'], true)) header('Cache-Control: no-store');
    ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#14263d"><meta name="csrf-token" content="<?=h(csrf())?>"><title><?=h($titre)?> — UMP ONGD</title><meta name="description" content="<?=h(UMP_NOM.' — '.$titre)?>"><link rel="stylesheet" href="style.css"><script src="script.js" defer></script></head><body>
<a class="skip" href="#contenu">Aller au contenu</a><div class="topbar"><div class="wrap"><span><?=h(UMP_NOM)?></span><span>Information · Bénévolat · Partenariat</span></div></div>
<header><div class="wrap navigation"><a class="brand" href="index.php" aria-label="UMP ONGD accueil"><?=logo()?><span>UMP ONGD<small>Santé et solidarité</small></span></a><button class="mobilemenu" aria-expanded="false" aria-controls="navlinks">Menu <span>☰</span></button><nav id="navlinks" aria-label="Navigation principale"><?php
    foreach (['index.php'=>'Accueil','ong.php'=>'L’ONG','activites.php'=>'Activités','actualites.php'=>'Actualités','ressources.php'=>'Ressources','engager.php'=>'S’engager'] as $url=>$nom) {
        echo '<a href="'.h($url).'"'.($url===$courante?' aria-current="page"':'').'>'.h($nom).'</a>';
    }
    ?></nav><div class="navtools"><button class="searchtrigger" aria-label="Rechercher sur le site">⌕</button><a class="button small" href="contact.php">Contact <span>→</span></a></div></div></header><main id="contenu">
<?php }
function page_fin(): void { ?>
</main><footer><div class="wrap footgrid"><div><a class="brand" href="index.php"><?=logo()?><span>UMP ONGD<small><?=h(UMP_NOM)?></small></span></a><p>La santé, la solidarité et le développement au service des populations.</p></div><div><h3>Découvrir</h3><a href="ong.php">L’ONG</a><a href="activites.php">Nos activités</a><a href="actualites.php">Actions et actualités</a></div><div><h3>Participer</h3><a href="engager.php?type=Bénévolat">Devenir bénévole</a><a href="engager.php?type=Partenariat">Proposer un partenariat</a><a href="engager.php?type=Don%20et%20soutien">Soutenir l’ONG</a></div><div><h3>Informations</h3><a href="contact.php">Contact</a><a href="ressources.php">Documents et questions</a><a href="confidentialite.php">Confidentialité</a><a href="admin.php">Espace de gestion →</a><?php if(admin()):?><a href="connexion.php">Compte et déconnexion</a><?php endif;?></div></div><div class="wrap footbottom"><span>UMP ASBL/ONGD · <?=h(UMP_NOM)?></span><span>Santé · Solidarité · Développement</span></div></footer>
<dialog id="search-dialog" class="searchdialog"><div class="dialogtop"><h2>Rechercher sur le site</h2><button class="close-dialog" aria-label="Fermer la recherche">×</button></div><label for="global-search">Un domaine, une activité, une question…</label><input id="global-search" type="search" placeholder="Ex. bénévolat, nutrition, partenariat"><div id="search-results" aria-live="polite"></div></dialog><dialog id="detail-dialog"><div class="dialogtop"><p class="eyebrow" id="detail-category"></p><button class="close-dialog" aria-label="Fermer le détail">×</button></div><div id="detail-content"></div></dialog><button id="back-top" aria-label="Retour en haut" hidden>↑</button></body></html>
<?php }
