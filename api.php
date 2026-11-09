<?php
declare(strict_types=1);
require __DIR__.'/fonctions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reponse(array $valeur, int $statut=200): never { http_response_code($statut); echo json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function texte($v, int $max): string { if (!is_string($v)) return ''; preg_match('/^.{0,'.$max.'}/us', trim($v), $m); return $m[0] ?? ''; }
$action = $_GET['action'] ?? ''; $methode=$_SERVER['REQUEST_METHOD'];
if ($action==='domains' && $methode==='GET') { echo json_encode(domaines(),JSON_UNESCAPED_UNICODE); exit; }
if ($action==='session' && $methode==='GET') reponse(['admin'=>admin(),'signedIn'=>admin()]);
if (str_starts_with($action,'admin/') && !admin()) reponse(['error'=>'Connectez-vous avec votre compte administrateur.'],403);
try {
    if ($methode!=='GET') {
        if (!in_array($methode,['POST','PATCH'],true)) reponse(['error'=>'Méthode non autorisée.'],405);
        if (!csrf_valide($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) reponse(['error'=>'Votre session a expiré. Rechargez la page avant de réessayer.'],403);
        $origine=$_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origine!=='') {
            $o=parse_url($origine); $h=($o['host']??'').(isset($o['port'])?':'.$o['port']:'');
            if (strtolower($h)!==strtolower($_SERVER['HTTP_HOST']??'')) reponse(['error'=>'Origine non autorisée.'],403);
        }
        if (!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')) reponse(['error'=>'Format JSON attendu.'],415);
        $raw=file_get_contents('php://input',false,null,0,30001);
        if (strlen((string)$raw)>30000) reponse(['error'=>'Demande trop longue.'],413);
        $b=json_decode((string)$raw,true);
        if (!is_array($b) || json_last_error()!==JSON_ERROR_NONE) reponse(['error'=>'Données invalides.'],400);
    }
    if ($action==='inquiries' && $methode==='POST') {
        if (!empty($b['website'])) reponse(['error'=>'Demande non acceptée.'],400);
        $nom=texte($b['name']??'',120); $email=strtolower(texte($b['email']??'',180)); $organisation=texte($b['organization']??'',180); $message=texte($b['message']??'',5000); $objet=texte($b['kind']??'',80);
        if ($nom==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($message)<10 || !in_array($objet,['Information','Bénévolat','Partenariat','Don et soutien','Protection des données'],true) || ($b['consent']??false)!==true) reponse(['error'=>'Complétez le nom, un e-mail valide, un message de 10 caractères minimum et votre accord.'],400);
        $nb=requete('SELECT COUNT(*) FROM inquiries WHERE email=? AND created_at>?',[$email,gmdate('Y-m-d\TH:i:s\Z',time()-3600)])->fetchColumn();
        if ($nb>=5) reponse(['error'=>'Plusieurs demandes ont déjà été envoyées. Réessayez dans une heure.'],429);
        $id=bin2hex(random_bytes(16));
        requete('INSERT INTO inquiries(id,created_at,kind,name,email,organization,message) VALUES(?,?,?,?,?,?,?)',[$id,gmdate('Y-m-d\TH:i:s\Z'),$objet,$nom,$email,$organisation,$message]);
        reponse(['id'=>$id,'reference'=>'UMP-'.strtoupper(substr($id,0,8))],201);
    }
    if ($action==='publications' && $methode==='GET') reponse(['items'=>requete("SELECT id,created_at,updated_at,title,category,summary,body FROM publications WHERE state='publié' ORDER BY created_at DESC LIMIT 100")->fetchAll()]);
    if ($action==='admin/inquiries' && $methode==='GET') reponse(['items'=>requete('SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 500')->fetchAll()]);
    if ($action==='admin/inquiries' && $methode==='PATCH') {
        $statut=texte($b['status']??'',40); $notes=texte($b['notes']??'',3000); $id=texte($_GET['id']??'',64);
        if (!in_array($statut,['nouveau','en cours','traité','archivé'],true)) reponse(['error'=>'Statut invalide.'],400);
        if (!requete('SELECT id FROM inquiries WHERE id=?',[$id])->fetch()) reponse(['error'=>'Demande introuvable.'],404);
        requete('UPDATE inquiries SET status=?,notes=? WHERE id=?',[$statut,$notes,$id]); reponse(['ok'=>true]);
    }
    if ($action==='admin/publications' && $methode==='GET') reponse(['items'=>requete('SELECT * FROM publications ORDER BY created_at DESC LIMIT 100')->fetchAll()]);
    if ($action==='admin/publications' && $methode==='POST') {
        $titre=texte($b['title']??'',160);$resume=texte($b['summary']??'',400);$corps=texte($b['body']??'',10000);$categorie=texte($b['category']??'',80);$etat=texte($b['state']??'',40);
        if (strlen($titre)<5 || strlen($resume)<10 || strlen($corps)<20 || !in_array($categorie,['Santé et nutrition','Recherche et formation','Action humanitaire','Environnement','Communication','Justice et paix'],true) || !in_array($etat,['brouillon','publié'],true)) reponse(['error'=>'Vérifiez le titre, le résumé, le contenu et la catégorie.'],400);
        $id=bin2hex(random_bytes(16));$date=gmdate('Y-m-d\TH:i:s\Z');
        requete('INSERT INTO publications(id,created_at,updated_at,title,category,summary,body,state) VALUES(?,?,?,?,?,?,?,?)',[$id,$date,$date,$titre,$categorie,$resume,$corps,$etat]);reponse(['id'=>$id],201);
    }
    if ($action==='admin/publications' && $methode==='PATCH') {
        $etat=texte($b['state']??'',40);$id=texte($_GET['id']??'',64);
        if (!in_array($etat,['brouillon','publié'],true)) reponse(['error'=>'État invalide.'],400);
        if (!requete('SELECT id FROM publications WHERE id=?',[$id])->fetch()) reponse(['error'=>'Publication introuvable.'],404);
        requete('UPDATE publications SET state=?,updated_at=? WHERE id=?',[$etat,gmdate('Y-m-d\TH:i:s\Z'),$id]);reponse(['ok'=>true]);
    }
    reponse(['error'=>'Ressource introuvable.'],404);
} catch (Throwable $e) {
    error_log('UMP API : '.$e->getMessage());
    reponse(['error'=>str_contains($e->getMessage(),'pdo_sqlite')?$e->getMessage():'Le service est indisponible. Vérifiez la configuration PHP et le dossier de données.'],503);
}
