<?php
/*
Ce script est distribué sous licence BSD avec une clause particulière :
L'utilisation, la modification et la distribution est interdite à toute personne en cours de rédaction d'un mémoire de
thèse et qui aurrait pris du retard dans sa rédaction.

L'auteur décline toute responsabilité quant au temps perdu et aux cheveux arrachés à tenter de comprendre ce code.

--
sly

 script de statistiques et d'exportation sur les communes françaises
 oui, tout en un, c'est moyen mais voilà.
 Pour chaque département français :
 - Obtenir la liste des communes présentes dans osm et leur nombre
 - Obtenir du cadastre la liste des communes qu'il y a vraiment
 - déterminer par l'attribu ref:INSEE lesquelles il manque dans osm
 - exportation au format shp des communes dont le département est couvert à 100%
 - statistiques totaux
*/

header("Content-Type: text/plain; charset=UTF-8"); // de toute façon ça se lance dans un cron, sauf cas du :
/* Petite bidouille pour fournir le code source de moi même si ?src est passé en paramètre */
if (isset($_GET['src']))
  die(file_get_contents($_SERVER['SCRIPT_FILENAME'])); 

/********* CONFIG ***********/
$use_cache=TRUE;
$exportation_shape=TRUE;
$chemin_suivi_communes=$argv[1];
$chemin_depot=$argv[2];
$dossier_stats_cadastre="$chemin_suivi_communes/stats-cadastre";
@mkdir($dossier_stats_cadastre);
@mkdir("$chemin_depot/incomplet");
// Dans la table france_polygon_manuel, quel osm_id porte actuellement le multipolygon qui contient la France (avec DOM/TOM)
$osm_id_france=5;


if (!$c=pg_connect("host=$argv[3] user=$argv[4] password=$argv[5] dbname=$argv[6]"))
	die("Erreur connexion SQL");
$total_osm=0;
$total_cadastre=0;
$total_cadastre_vecto=0;

// BIDOUILLE - Fonction pour gérer les problème de commune ayant des bugs de géométries
function query_mutante($c,$dep,$secure)
{
global $osm_id_france;
if ($secure)
  $conservatif="and st_isvalid(p1.way)";
else
  $conservatif="";

$query="select p1.name as nom_commune,p1.\"ref:INSEE\" as ref_commune,p2.osm_id, p2.ref, p2.name
		from planet_osm_polygon as p1,planet_osm_polygon as p2,france_polygon_manuel as f
		where 
			p2.admin_level='6' 
		and 
			p2.ref='$dep' 
		and 
			p1.simplified_way && p2.simplified_way
		and 
			ST_Within(ST_PointOnSurface(p1.way), p2.way) 
		and 
			p2.simplified_way && f.simplified_way 
		and 
			f.osm_id=$osm_id_france
		and 
			p1.admin_level='8' 
		and 
			p1.boundary='administrative' $conservatif"; 

//print($query);
$r=pg_query($query);
    
return $r;
}

//Préparation du fichier csv
$csv="ref;name;id relation;count;cadastre;cadastre_vecto;osm/cadastre %;cadastre_vecto dans osm/cadastre vecto %\n";

$compteur_commune_vecto_cadastre_pas_dans_osm=0;
// construction de la liste des département
// BIDOUILLE - tout vient de la particularité corse 2A et 2B à gérer
for ($i=1;$i<=95;$i++)
{
if ($i<=9)
  $departements[]="00$i"; // BIDOUILLE
else
{
    if ($i==20) // BIDOUILLE
    {
      $departements[]="02A";
      $departements[]="02B";
    }
    else
      $departements[]="0$i";
}    
}
// BIDOUILLE - Rajout à la main des départements d'outre-mer
$departements[]="971";
$departements[]="972";
$departements[]="973";
$departements[]="974";
//$departements[]="976"; Mayotte n'est pas présent au cadastre !


// Nettoyage des fichiers du cache s'ils sont vieux de plus de x jours
// flemme de le faire en php ;-)
exec("find $dossier_stats_cadastre -type f -ctime +5 -exec rm {} \;");

// temporaire pour test un seul département
//$departements=array("974");

$liste_communes_non_presentes_vecteur="\nFORMAT VECTEUR AU CADASTRE\n";
$liste_communes_non_presentes_vecteur_csv="";
$liste_communes_non_presentes_image="\nFORMAT IMAGE AU CADASTRE\n";
$liste_communes_non_presentes_image_csv="";

foreach($departements as $dep)
{
// Le site du cadastre prends les numéro de département au format 005, 073 et 974 c'est le format de notre tableau plus haut
$dep_cadastre=$dep;

// BIDOUILLE - Par contre nous on le veut au format 05, 73 et 974 et c'est aussi comme ça que c'est dans OSM
if ($dep[0]=="0")
	$dep=substr($dep,1,10);

print("dep :$dep...\n");
// CADASTRE
//Récupération des infos du cadastre
$liste_communes_non_presentes_vecteur.="### Département $dep, communes dont les limites ne sont pas, ou n'ont pas de tag ref:INSEE ou ayant un problème dans osm (mais existent en vecteur au cadastre):\n";

$file="$dossier_stats_cadastre/$dep.csv";

if ($use_cache and is_file($file))
{
	$liste_cadastre=explode("\n",trim(file_get_contents($file)));
}
else
{
	exec("curl -c $dossier_stats_cadastre/cookies-1 \"http://www.cadastre.gouv.fr/scpc/rechercherPlan.do\" > $dossier_stats_cadastre/page-1.html 2>/dev/null");
	exec("curl -b $dossier_stats_cadastre/cookies-1 -c $dossier_stats_cadastre/cookies-2  \"http://www.cadastre.gouv.fr/scpc/listerCommune.do?codeDepartement=$dep_cadastre&libelle=&keepVolatileSession=&offset=5000\" > $dossier_stats_cadastre/page-2.html 2>/dev/null");
	
	$liste_cadastre=array();
	
	// FIXME MEGA BIDOUILLE - Youpi, faisons du parsing de page html toute mal fichue, autant dire qu'a la moindre modification de la structure de la page et tout foire
	$flot_html=shell_exec("cat $dossier_stats_cadastre/page-2.html | sed \"s/<\/tbody><\/table>/<\/tbody><\/table>\\n/g\" | grep 'ajoutArticle'");
	preg_match_all(	"/<strong>(.*)\ \((.*)\).*ajoutArticle\('([A-Z0-9]*)','([A-Z0-9]*)'/",$flot_html,$res);

	// création du tableau avec une commune par ligne au format nom commune,code postal, code INSEE, VECT ou IMAG
	$nombre=count($res[1]);
	for ($i=0;$i<$nombre;$i++)
	{
		// BIDOUILLE - Ignorons les arrondissements des villes de marseille, lyon et Paris (la syntaxe a repérer est soit \ 1ER,\ 2EME ou \ 01-\ 20 pour paris ... soit un espace un ou deux chiffres
		if (!preg_match("/\ [0-9]/",$res[1][$i]))
		{
		       	$ligne=$res[1][$i].",".$res[2][$i].",".$res[3][$i].",".$res[4][$i];
		       	file_put_contents($file,$ligne."\n",FILE_APPEND);
	        	$liste_cadastre[]=$ligne;
		}
	}
        	
}
$nombre_cadastre=count($liste_cadastre);
$total_cadastre+=$nombre_cadastre;

$cadastre_vecto=0;
$liste_code_insee_vecteur=array();
$liste_code_insee_image=array();
foreach ($liste_cadastre as $ligne_commune)
{
	$info_commune=explode(",",$ligne_commune);

	// BIDOUILLE - Le cadastre donne un autre format pour les ref insee des DOM/TOM avec un chiffre répété en colonne 2 genre 424 au lieu de 24
	if (strlen($dep)==3)
		$position=3;
	else
		$position=2;
	$code_insee=$dep.substr($info_commune[2],$position);
		
	if (ereg(",VECT$",$ligne_commune))
	{
		$cadastre_vecto++;
		$liste_code_insee_vecteur[$code_insee]=$info_commune[0];
	}
	elseif ( ereg(",IMAG$",$ligne_commune))
		$liste_code_insee_image[$code_insee]=$info_commune[0];
	else // BIDOUILLE - on la suppose image
		$liste_code_insee_image[$code_insee]=$info_commune[0]." (Mais ligne de la liste au cadastre mal formée)";
}
$total_cadastre_vecto+=$cadastre_vecto;

// BIDOUILLE - ici contournement du problème que osm2pgsql stocke en format polygon au lieu de multipolygon et permet alors la présence
// de polygone invalide sur lesquels je ne peux obtenir un point sur la surface
$requete_qui_marche="normal";
$r=query_mutante($c,$dep,True);

/* C'est con en fait car justement on veut repérer les bugs ! donc si une commune est buggée, on ne la considère pas comme valide
if (@pg_num_rows($r)==0) // on a rien eu avec la requête normal
{
	$r=query_mutante($c,$dep,TRUE);
	$requete_qui_marche="secure";
}
*/
unset($data);
if (@pg_num_rows($r)==0) // département vide ou non présent
{
	$data->ref=$dep;
	$data->name="(none)";
	$data->osm_id="(not in db)";
	$data->count=0;
	$requete_qui_marche="aucune";
}
else
{
	// communes absentes de osm
	while($liste_osm=pg_fetch_object($r))
	{
		if (!isset($data)) // on en garde une copie pour avoir nom, id, et ref du département
			$data=$liste_osm;
		// celle là y est
		unset($liste_code_insee_vecteur[$liste_osm->ref_commune]);
		unset($liste_code_insee_image[$liste_osm->ref_commune]);
	}
	// BIDOUILLE CAS SPECIAL - gestion des non mises à jour du cadastre : cette commune n'existe plus (fusion)
	// Le cadastre s'est il mis à jour depuis ?
        // unset($liste_code_insee_vecteur["52266"]);
	// unset($liste_code_insee_image["52266"]);
	        
	$data->count=pg_num_rows($r);
}
$j=0;
foreach ($liste_code_insee_vecteur as $commune => $son_nom)
{
	$liste_communes_non_presentes_vecteur.="$son_nom, Référence INSEE:$commune\n";
	$liste_communes_non_presentes_vecteur_csv.="$dep;$son_nom;$commune\n";
        $j++;
        $compteur_commune_vecto_cadastre_pas_dans_osm++;
}
$liste_communes_non_presentes_image.="### Département $dep, communes dont les limites ne sont pas, ou n'ont pas de tag ref:INSEE ou ayant un problème dans osm (mais existent en image au cadastre):\n";
foreach ($liste_code_insee_image as $commune => $son_nom)
{
	$liste_communes_non_presentes_image.="$son_nom, Référence INSEE:$commune\n";
	$liste_communes_non_presentes_image_csv.="$dep;$son_nom;$commune\n";

}
$osm_cadastre=round($data->count/$nombre_cadastre*100,1);
$osm_cadastre_vecto=round(($cadastre_vecto-$j)/$cadastre_vecto*100,1);
$total_osm+=$data->count;
$csv.="$data->ref;$data->name;".(-$data->osm_id).";$data->count;$nombre_cadastre;$cadastre_vecto;$osm_cadastre;$osm_cadastre_vecto\n";

// EXPORTATION
if ($exportation_shape AND $data->count!=0)
{

	$query="select p1.name,p1.\\\"ref:INSEE\\\" as code_insee,st_transform(p1.way,4326) as way
	from planet_osm_polygon as p1,planet_osm_polygon as p2,france_polygon_manuel as f
	where p2.admin_level='6' and p2.ref='$dep' and p1.simplified_way && p2.simplified_way
	and ST_Within(ST_PointOnSurface(p1.way), p2.simplified_way) and p2.simplified_way && f.simplified_way and f.osm_id=$osm_id_france
	and p1.admin_level='8' and p1.boundary='administrative' and st_isvalid(p1.way)";
	
	exec("pgsql2shp -h $argv[3] -u $argv[4] -P $argv[5] -f \"$dep-$data->name\" $argv[6] \"$query\"");
	
	// exportation en shp
	if ($data->count<$nombre_cadastre) //si incomplet, on le met dans un autre repertoire
		$incomplet_ou_pas="incomplet/";
	else
	{
		$incomplet_ou_pas="";
		// si le département est complet, on enlève le fichier du dossier incomplet (s'il n'existe pas, ça fera juste rien)
		@unlink("$chemin_depot/incomplet/$dep-$data->name.shp.tar.gz");
	}
		
	exec("tar cvfz \"$chemin_depot/$incomplet_ou_pas$dep-$data->name.shp.tar.gz\" $dep*");
	exec("rm -f  $dep*.shp $dep*.dbf $dep*.shx $dep*.prj");

}
}
$total_osm_cadastre=round($total_osm/$total_cadastre*100,2);
$total_osm_cadastre_vecto=round(($total_cadastre_vecto-$compteur_commune_vecto_cadastre_pas_dans_osm)/$total_cadastre_vecto*100,2);
$csv.="tous;tous;tous;$total_osm;$total_cadastre;$total_cadastre_vecto;$total_osm_cadastre;$total_osm_cadastre_vecto";
$suivi="\n$liste_communes_non_presentes_vecteur\n$liste_communes_non_presentes_image";

$date=rtrim(str_replace("\\","",exec("wget $fichier_state_date_base -q -O -  | grep timestamp | sed s/timestamp=// | sed s/T/\\ / | sed s/:..Z// ")));
$bug_trouve="Vous pensez avoir trouvé un bug ? Vous pouvez le signaler ici : http://trac.openstreetmap.fr/newticket , (composant suivi/export admin)\n";
$en_tete="
Etat statistiques des limites de communes calculé le ".date(DATE_RFC822)." sur une copie de la base public osm datant du : $date\n";
file_put_contents("$chemin_suivi_communes/communes.csv",$csv);
file_put_contents("$chemin_suivi_communes/suivi.txt",$bug_trouve.$en_tete.$suivi);
file_put_contents("$chemin_suivi_communes/suivi-vectoriel.txt",$bug_trouve.$en_tete.$liste_communes_non_presentes_vecteur);
file_put_contents("$chemin_suivi_communes/suivi-image.txt",$bug_trouve.$en_tete.$liste_communes_non_presentes_image);
file_put_contents("$chemin_suivi_communes/communes.csv.txt",$bug_trouve.$en_tete.$csv.$suivi);
file_put_contents("$chemin_suivi_communes/communes-vectorielles-a-importer.csv",$liste_communes_non_presentes_vecteur_csv);
file_put_contents("$chemin_suivi_communes/communes-image-a-importer.csv",$liste_communes_non_presentes_image_csv);

?>
