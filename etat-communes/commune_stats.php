<?php
/*
Ce script est distribué sous licence WTFPL

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

/********* CONFIG ***********/
$use_cache=TRUE;
$exportation_shape=TRUE;

/**** le reste est a passer en paramètre lors de l'appel ****/
$chemin_suivi_communes=$argv[1];
$chemin_depot=$argv[2];
$date_base=$argv[10];
$dossier_stats_cadastre="$chemin_suivi_communes/stats-cadastre";

// Dans la quelle table annexe, quel id porte actuellement le multipolygon qui contient la France (avec DOM/TOM)
// voir dans le dossier data pour charger ce polygone et/ou simplement toute la table
$table_qui_contient_la_france=$argv[8];
$id_france_dans_cette_table=$argv[9];


/************/

@mkdir($dossier_stats_cadastre);
@mkdir("$chemin_depot/incomplet");


if (!$c=pg_connect("host=$argv[3] port=$argv[4] user=$argv[5] password=$argv[6] dbname=$argv[7]"))
	die("Erreur connexion SQL");
$total_osm=0;
$total_cadastre=0;
$total_cadastre_vecto=0;

// Cette requête est utilisée par la partie suivi mais aussi par l'export en shapefile
// et ne diffère que de pas grand chose, j'ai donc décidé de factoriser la requête
function query_mutante($numero_departement,$pour_export)
{
global $table_qui_contient_la_france;
global $id_france_dans_cette_table;

if ($pour_export) // en mode export on veut la géométrie en plus
	$champs_voulu=",st_transform(p1.way,4326) as way,p1.population as population,p1.tags->'addr:postcode' code_postal";
else
	$champs_voulu=",p2.osm_id, p2.ref, p2.name"; // en mode suivi

// note : j'aurais préféré "nom_commune" que "commune" mais le shapefile ne semble pas fichu de gére plus de 10 caractères et ça coupait en "nom_commun" !
$query="select p1.name as commune,p1.tags->'ref:INSEE' as ref_insee,(-p1.osm_id) as osm_id$champs_voulu
		from planet_osm_polygon as p1,planet_osm_polygon as p2,$table_qui_contient_la_france as f
		where 
			p2.admin_level='6' 
		and 
			p2.ref='$numero_departement' 
		and 
			p1.way && p2.way
		and 
			ST_Within(ST_PointOnSurface(p1.way), p2.way) 
		and 
			st_within(ST_PointOnSurface(p1.way),f.simplified_way)
		and 
			f.id=$id_france_dans_cette_table
		and 
			p1.admin_level='8' 
		and 
			p1.boundary='administrative'
		and
			st_isvalid(p1.way)
        and
			st_isvalid(p1.way) and p1.tags ? 'ref:INSEE'"; // BIDOUILLE : on devrait pouvoir s'en passer, mais osm2pgsql et ça gestion des polygone bizarre, créait des admin_level là où il n'y en avait pas (quand par exemple un membre avait admin_level=8) avec ça, je réduis les risques

return $query;
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
$departements[]="976"; //Mayotte n'est pas présent au cadastre ! mais ça n'empêche pas son exportation en shp, ça devrait par contre indiquer du 0% dans le suivi

// Nettoyage des fichiers du cache s'ils sont vieux de plus de x jours
// flemme de le faire en php ;-)
exec("find $dossier_stats_cadastre -type f -ctime +5 -exec rm {} \;");

// temporaire pour test un seul département
// $departements=array("073");

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

print("Recherche du département ref=$dep dans la base et ses communes...\n");
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
		if (!preg_match("/\ [0-9]/",$res[1][$i]) and $res[1][$i]!="")
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
$query=query_mutante($dep,False);
print($query."\n...Runing\n");
$r=pg_query($query);
unset($data);
if (@pg_num_rows($r)==0) // département vide ou non présent
{
	print("non trouvé\n");
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
		unset($liste_code_insee_vecteur[$liste_osm->ref_insee]);
		unset($liste_code_insee_image[$liste_osm->ref_insee]);
	}
	// BIDOUILLE CAS SPECIAL - gestion des non mises à jour du cadastre : cette commune n'existe plus (fusion)
	// Le cadastre s'est il mis à jour depuis ?
        // unset($liste_code_insee_vecteur["52266"]);
	// unset($liste_code_insee_image["52266"]);
	        
	$data->count=pg_num_rows($r);
	print ("trouvé : $data->name ($data->count communes)\n");
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

	$query=query_mutante($dep,True);
	// les espace ou guillemet simple ont tendance à poser problème dans les noms du fichier shape pour des logiciels "limité" (arcgis semble être dans ce cas pour retrouver son .prj/.dbf)
	$nom_departement_propre=str_replace(array(" ","'"),"-",$data->name);
	exec("pgsql2shp -h $argv[3] -u $argv[5] -P $argv[6] -f \"$dep-".addslashes($nom_departement_propre)."\" $argv[7] \"".addcslashes($query,'"\\/')."\"");
	
	// exportation en shp
	if ($data->count<$nombre_cadastre) //si incomplet, on le met dans un autre repertoire
		$incomplet_ou_pas="incomplet/";
	else
	{
		$incomplet_ou_pas="";
		// si le département est complet, on enlève le fichier du dossier incomplet (s'il n'existe pas, ça fera juste rien)
		@unlink("$chemin_depot/incomplet/$dep-$nom_departement_propre.shp.tar.gz");
	}
		
	exec("tar cvfz \"$chemin_depot/$incomplet_ou_pas$dep-$nom_departement_propre.shp.tar.gz\" $dep*");
	exec("rm -f  $dep*.shp $dep*.dbf $dep*.shx $dep*.prj $dep*.cpg");

}
}
$total_osm_cadastre=round($total_osm/$total_cadastre*100,2);
$total_osm_cadastre_vecto=round(($total_cadastre_vecto-$compteur_commune_vecto_cadastre_pas_dans_osm)/$total_cadastre_vecto*100,2);
$csv.="tous;tous;tous;$total_osm;$total_cadastre;$total_cadastre_vecto;$total_osm_cadastre;$total_osm_cadastre_vecto";
$suivi="\n$liste_communes_non_presentes_vecteur\n$liste_communes_non_presentes_image";

$bug_trouve="Vous pensez avoir trouvé un bug ? Vous pouvez le signaler ici : http://trac.openstreetmap.fr/newticket , (composant suivi/export admin)\n";
$en_tete="
Etat statistiques des limites de communes calculé le ".date(DATE_RFC822)." sur une copie de la base public osm datant du : $date_base\n";
file_put_contents("$chemin_suivi_communes/communes.csv",$csv);
file_put_contents("$chemin_suivi_communes/suivi.txt",$bug_trouve.$en_tete.$suivi);
file_put_contents("$chemin_suivi_communes/suivi-vectoriel.txt",$bug_trouve.$en_tete.$liste_communes_non_presentes_vecteur);
file_put_contents("$chemin_suivi_communes/suivi-image.txt",$bug_trouve.$en_tete.$liste_communes_non_presentes_image);
file_put_contents("$chemin_suivi_communes/communes.csv.txt",$bug_trouve.$en_tete.$csv.$suivi);
file_put_contents("$chemin_suivi_communes/communes-vectorielles-a-importer.csv",$liste_communes_non_presentes_vecteur_csv);
file_put_contents("$chemin_suivi_communes/communes-image-a-importer.csv",$liste_communes_non_presentes_image_csv);

?>
