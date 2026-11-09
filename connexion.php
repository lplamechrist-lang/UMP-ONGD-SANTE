<?php
declare(strict_types=1);
require __DIR__.'/fonctions.php';
$erreur='';$installation=false;$pret=false;
try {
    $installation=(int)requete('SELECT COUNT(*) FROM admins')->fetchColumn()===0;
    $local=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
    $pret=true;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrf_valide($_POST['csrf']??null)) $erreur='Votre session a expiré. Rechargez cette page.';
        elseif (isset($_POST['deconnexion'])) { $_SESSION=[];session_regenerate_id(true);header('Location: connexion.php');exit; }
        elseif ($installation && !$local) $erreur='Créez le premier compte sur le serveur local, puis transférez la base avec le site pour un hébergement en ligne.';
        else {
            $email=strtolower(trim((string)($_POST['email']??'')));$motdepasse=(string)($_POST['password']??'');
            $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');
            requete('DELETE FROM login_attempts WHERE attempted_at<?',[time()-900]);
            $essais=(int)requete('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=?',[$ip])->fetchColumn();
            if ($essais>=10) $erreur='Trop de tentatives. Réessayez dans quinze minutes.';
            elseif ($installation) {
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>180) $erreur='Saisissez une adresse e-mail valide.';
                elseif (strlen($motdepasse)<10 || strlen($motdepasse)>200) $erreur='Choisissez un mot de passe de 10 à 200 caractères.';
                elseif ($motdepasse!==(string)($_POST['confirmation']??'')) $erreur='Les deux mots de passe ne correspondent pas.';
                else {
                    db()->beginTransaction();
                    if ((int)requete('SELECT COUNT(*) FROM admins')->fetchColumn()!==0) {db()->rollBack();$erreur='Un compte a déjà été créé. Rechargez cette page pour vous connecter.';}
                    else {
                        requete('INSERT INTO admins(email,password_hash,created_at) VALUES(?,?,?)',[$email,password_hash($motdepasse,PASSWORD_DEFAULT),gmdate('c')]);
                        $id=(int)db()->lastInsertId();db()->commit();session_regenerate_id(true);$_SESSION['admin_id']=$id;$_SESSION['admin_email']=$email;$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: admin.php');exit;
                    }
                }
            } else {
                $compte=requete('SELECT * FROM admins WHERE email=?',[$email])->fetch();
                if ($compte && strlen($motdepasse)<=200 && password_verify($motdepasse,$compte['password_hash'])) {
                    session_regenerate_id(true);$_SESSION['admin_id']=(int)$compte['id'];$_SESSION['admin_email']=$compte['email'];$_SESSION['csrf']=bin2hex(random_bytes(32));requete('DELETE FROM login_attempts WHERE ip_hash=?',[$ip]);header('Location: admin.php');exit;
                }
                requete('INSERT INTO login_attempts(ip_hash,attempted_at) VALUES(?,?)',[$ip,time()]);$erreur='Adresse e-mail ou mot de passe incorrect.';
            }
        }
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('UMP connexion : '.$e->getMessage());
    $erreur=str_contains($e->getMessage(),'pdo_sqlite')?$e->getMessage():'La base ne peut pas être ouverte. Vérifiez PHP et les droits du dossier de données indiqués dans LISEZ_MOI.txt.';
}
page_debut('Connexion administrateur','connexion.php');
?>
<section class="pagehead"><div class="wrap"><p class="eyebrow">Administration</p><h1><?=admin()?'Votre compte.':($installation?'Configurer l’administration.':'Bienvenue dans votre espace.')?></h1><p class="intro">Un espace réservé au suivi des demandes et à la publication des actualités.</p></div></section>
<section class="section"><div class="wrap" style="max-width:560px"><div class="formcard">
<?php if($erreur!==''):?><p class="error" role="alert"><?=h($erreur)?></p><?php endif;?>
<?php if(admin()):?>
<h2 style="font-size:28px">Vous êtes connecté.</h2><p class="muted"><?=h($_SESSION['admin_email']??'')?></p><p><a class="button" href="admin.php">Ouvrir l’administration →</a></p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><button class="button outline" name="deconnexion" value="1" type="submit">Se déconnecter</button></form>
<?php elseif($pret && (!$installation || $local)):?>
<h2 style="font-size:28px"><?=$installation?'Créer le premier compte':'Connexion administrateur'?></h2>
<p class="muted"><?=$installation?'Choisissez votre adresse e-mail et votre propre mot de passe. Aucun compte par défaut n’est installé.':'Utilisez le compte créé lors de l’installation.'?></p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label for="login-email">Adresse e-mail</label><input type="email" id="login-email" name="email" required maxlength="180" autocomplete="username" value="<?=h($_POST['email']??'')?>"><label for="login-password">Mot de passe</label><input type="password" id="login-password" name="password" required <?=$installation?'minlength="10"':''?> maxlength="200" autocomplete="<?=$installation?'new-password':'current-password'?>">
<?php if($installation):?><label for="login-confirmation">Confirmer le mot de passe</label><input type="password" id="login-confirmation" name="confirmation" required minlength="10" maxlength="200" autocomplete="new-password"><?php endif;?>
<button class="button" type="submit"><?=$installation?'Créer mon compte':'Se connecter'?> →</button></form>
<?php elseif($pret):?><h2 style="font-size:26px">Installation locale nécessaire</h2><p class="muted">La création du premier compte est autorisée depuis le serveur local uniquement. Consultez LISEZ_MOI.txt.</p><?php endif;?>
</div></div></section>
<?php page_fin(); ?>
