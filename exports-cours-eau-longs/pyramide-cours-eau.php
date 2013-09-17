<?php
/*
Ce script est distribué sous licence BSD 

Il démontre comment, en temps réél, reconstruire à l'aide de postgis la pyramide des dépendances entre cours d'eau
(qui se jette dans qui)

-- sly 13/10/2010

Il n'a toute les chances de ne plus marcher, si d'ici 2014 personne n'en a voulu, alors poubelle

*/
require_once("config.php");
$my_name="pyramide-cours-eau.php"; // FIXME $_SERVER['script_name'] ou truc du genre, flemme de chercher --sly

/* Petite bidouille pour fournir le code source de moi même si ?src est passé en paramètre --sly */
if (isset($_GET['src']))
{
  header("Content-Type: text/plain; charset=UTF-8"); // de toute façon ça se lance dans un cron, sauf cas du :
  die(file_get_contents($_SERVER['SCRIPT_FILENAME'])); 
}
else
  header("Content-type: text/html; charset=UTF-8");

/* Connexion à  la base PostresSQL */
if (!$c=pg_connect("host=$pg_server user=$pg_user password=$pg_password dbname=$pg_data_base"))
  die("Erreur connexion SQL");

/* 
Récupération dans un fichier des minutes diff du planet de la dernière date de mise à jour 
Bien sûr je pourrais le faire en php, mais le code avait historiquement été écrit en shell, et le temps de 
taper ce commentaire j'aurais pû le refaire en php
-- sly
*/

$date=exec('grep timestamp ../state.txt | sed s/timestamp=// | sed s/\\\\\\\\//g | sed s/[TZ]/" "/g');

if (isset($_GET['osm_id']))
{
	$time1=microtime(true); // benchmarks

	$osm_id=$_GET['osm_id'];
	$requete_qui_suis_je="select name,-osm_id as osm_id from planet_osm_line where osm_id=-$osm_id and waterway is not null";
  	$res_qui_suis_je=pg_query($requete_qui_suis_je);
	$time2=microtime(true);
	if (pg_num_rows($res_qui_suis_je)==0)
		$resultat_html="Je ne sais pas qui je suis ($osm_id introuvable ou pas un cours d'eau)<br />";
	else
	{
		$riviere=pg_fetch_object($res_qui_suis_je);
		$resultat_html="Je suis <strong>$riviere->name</strong><br />";
	
		$requete_ou_vais_je="select p2.name as name,-p2.osm_id as osm_id
					from planet_osm_line as p1, planet_osm_line as p2 
					where p2.\"ref:sandre\" is not null and p1.osm_id=-$osm_id and p2.osm_id<>-$osm_id and st_intersects(st_endpoint(p1.way),p2.way) limit 1;";
		$res_ou_vais_je=pg_query($requete_ou_vais_je);
		$time3=microtime(true);
		if (pg_num_rows($res_ou_vais_je)==0)
			$resultat_html.="Je n'ai pas trouvé où je me jette<br />";
		else
		{
			$riviere=pg_fetch_object($res_ou_vais_je);
			$resultat_html.="Je me jette dans <a href=\"$my_name?osm_id=$riviere->osm_id\">$riviere->name</a><br />";
		}
	
		$d_ou_viens_je="select p2.name as name,-p2.osm_id as osm_id from planet_osm_line as p1, planet_osm_line as p2 
					where p1.osm_id=-$osm_id and p2.osm_id<>-$osm_id and p1.way && p2.way
					and p2.\"ref:sandre\" is not null and st_intersects(st_endpoint(p2.way),p1.way);";
		$res_d_ou_viens_je=pg_query($d_ou_viens_je);
		$time4=microtime(true);
		if (pg_num_rows($res_d_ou_viens_je)==0)
			$resultat_html.="Je ne sais pas d'où je viens<br />";
		else
			while ($rivieres=pg_fetch_object($res_d_ou_viens_je))
				$resultat_html.="<a href=\"$my_name?osm_id=$rivieres->osm_id\">$rivieres->name</a> m'alimente<br />";
	}

}
else 
{
	$osm_id = "";
	$resultat_html = "";
	$time1 = 0;
	$time2 = 0;
	$time3 = 0;
	$time4 = 0;
}

$benchs_html="r1=".($time2-$time1)." r2=".($time3-$time2)." r3=".($time4-$time3);
/* C'est la variable qui contient le HTML à renvoyer --sly*/
$html="
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" lang=\"en\" xml:lang=\"en\">
                    
<head>
<title>Rivière - confluents et affluent ?</title>
</head>
<body>
<p>En date du $date</p>
<p>
Cet outil vous permet, en rentrant l'identifiant osm d'une relation type=waterway, de retrouver où elle se jette et qui se jette dedans
</p>
<p>
(il faut que ref:sandre soit présents sur les rivières/fleuves concernés parce que c'est le seul champs sur lequel j'ai un index postgis ;-) )
</p>
<p>
Je RAME un peu parfois (souvent ?), alors patience...
</p>
<p>
<form action=\"./$my_name\" method=\"get\">
Id de la relation : <input type=\"text\" name=\"osm_id\" value=\"$osm_id\">
<input type=\"submit\" value=\"Qui suis-je ? Où vais-je ? d'où viens-je ?\"> Pas d'idées ?
<a href=\"$my_name?osm_id=1067839\">L'Isère</a>
<a href=\"$my_name?osm_id=274628\">Le Lot</a>
<a href=\"$my_name?osm_id=1075197\">La Meuse</a>
<a href=\"$my_name?osm_id=961832\">La Charente</a>
</form>
</p>
<p>
$resultat_html
</p>
<p>
$benchs_html
</p>
</body>
</html>";


print($html);
?>


