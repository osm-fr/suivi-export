<?php
/*
Ce script est distribué sous licence BSD avec une clause particulière :
L'utilisation, la modification et la distribution est interdite à toute personne en cours de rédaction d'un mémoire de
thèse et qui aurrait pris du retard dans sa rédaction.

L'auteur décline toute responsabilité quant au temps perdu et aux cheveux arrachés à tenter de comprendre ce code.

 script de statistiques des cours d'eau français, principe ;
 - Obtenir la liste des cours d'eau et leur longueur depuis les informations de 2010 du sandre
 - Recouper les informations du sandre à celles d'OSM grâce au tag ref:sandre
 - comparer les deux et établir un tableau avec l'avancement par cours d'eau
 
 Il est supposé que la la table OSM s'appelle planet_osm_line et contient les "lignes" de la base OSM
 Le schéma est celui de osm2pgsql, dont un patch a été réaliser pour importer les relations type=waterway d'une manière similaire 
 à la relation type=route, mais sans les couper en morceaux (code de osm2pgsql patché sur demande)
 Pensez bien à un index sur le tag ref:sandre sans quoi c'est 6 jours qu'il faudra
 La table extraite du sandre doit s'appeler "sandre" et provient d'un import par shp2pgsql du fichier shapefile de 2010
 
-- sly 06/2010

Petit ajout pour changer l'ordre de tri
et permettre des totaux
+ Vincent 12/10/2010

*/

require_once("../config.php");
$somme_sandre = $somme_sandre_mapee = $somme_osm = $nb_sandre = $nb_osm = 0;
$suivi_pas_dans_osm = "";

/* Petite bidouille pour fournir le code source de moi même si ?src est passé en paramètre --sly */
if (isset($_GET['src']))
{
  header("Content-Type: text/plain; charset=UTF-8"); // de toute façon ça se lance dans un cron, sauf cas du :
  die(file_get_contents($_SERVER['SCRIPT_FILENAME'])); 
}
else
  header("Content-type: text/html; charset=UTF-8");

/* Connexion Ã  la base PostresSQL */
if (!$c=pg_connect("dbname=$pg_france_data_base"))
  die("Erreur connexion SQL");

/* 
Récupération dans un fichier des minutes diff du planet de la dernière date de mise à jour 
Bien sûr je pourrais le faire en php, mais le code avait historiquement été écrit en shell, et j'ai la flemme, quoi que le temps de 
taper ce commentaire j'aurais pû le refaire en php ;-)
-- sly
*/

$date=exec('grep timestamp '.$fichier_state_base_france.' | sed s/timestamp=// | sed s/\\\\\\\\//g | sed s/[TZ]/" "/g');

/* Un paramètre pour ne pas afficher les cours d'eau non présent dans osm d'une longeur de moins de X km, sinon la page est immense --sly*/
$seuil_longueur_max=80;

/* C'est la variable qui contient le HTML à renvoyer --sly*/
$suivi_dans_osm="
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" lang=\"en\" xml:lang=\"en\">
                    
<head>
<meta http-equiv='Content-type' content='text/html;charset=UTF-8' />
<title>Comparaison cours d'eau sandre et osm</title>
<base html='http://beta.letuffe.org/ressources/cartes/hydrographie-france.png' />
<style type=\"text/css\">
<!--
thead th {background-color: #cccccc}
tr:nth-child(even) {background-color: #ccffff}
td.a0_50 { color: red; }
td.a50_80 { color: orange; }
/* normal color for 80-95 */
td.a95_ { color: green; }
div.warning {
  border-left: 2px solid black;
  margin-left: 10px;
  padding-left: 5px;
}
div.warning span {
  color :red;
}
-->

</style>
<script src='sorttable.js' type='text/javascript'></script>
</head>
<body>
<p>État d'avancement du tracé des rivières françaises de plus de $seuil_longueur_max km en date du $date (<a href=\"http://wiki.openstreetmap.org/wiki/User:Sletuffe\">sly est l'actuel mainteneur de cet outil)</a> </p>
<p>
<a href=\"http://wiki.openstreetmap.org/wiki/WikiProject_France/Cours_d%27eau#Outils_de_suivi\">Explications</a>
</p>
<table class='sortable' border='1'>
<thead><tr><th><a href='?order=toponyme'>Rivière</a></th><th>id_osm</th><th><a href='?order=code_hydro'>ref sandre</a></th><th class='sorttable_sorted'><a href='?order=longueur'>km sandre</a><span id='sorttable_sortfwdind'>&nbsp;▾</span></th><th>km osm en france</th><th class='sorttable_numeric'>Avancement</th></tr></thead>
<tbody>\n";

function print_osm_id($id)
{
  if ($id >= 0) {  // way
    $type = "way";
    $real_id = $id;
    $is_way = " (w)";
  } else {  // relation
    $type = "relation";
    $real_id = -$id;
    $is_way = "";
  }
  $s  = "<a href='http://www.openstreetmap.org/browse/$type/$real_id'>$real_id</a> ";
  $s .= "<a href='http://localhost:8111/import?url=http://api.openstreetmap.org/api/0.6/$type/$real_id/full'>josm$is_way</a>";
  return $s;
}

/* Par défaut, on classe par longeur, sinon par le paramètre order passé en paramètre GET --vincent */
$order = isset($_GET['order']) ? $_GET['order'] : 'longueur';
$order='longueur';


/* Si on veut un classement par longeur, alors le plus long en haut*/
if ($order=='longueur')
  $croissant_decroissant="desc";
else
  $croissant_decroissant="asc";
  
$query_sandre="select toponyme,code_hydro, st_length(the_geom) as longueur from sandre order by $order $croissant_decroissant;";
$res_sandre=pg_query($query_sandre);

while($liste_sandre=pg_fetch_object($res_sandre))
{

  /* Le sandre ne donnant que les km en france alors qu'osm n'a pas cette limite, j'utilise le polygone france que j'ai dans france_polygon 
  pour construitre l'intersection et son osm_id est 4 (issue d'une recombinaison depuis osm dont j'ai perdu l'histoire depuis toutes ces années).
  J'utilise une colonne spécialement simplifiée pour l'occasion pour accélérer le calcul --sly
  */
/* Cette solution permettait de couper juste ce qui est en france pour le comprer au sandre, mais je n'arrive plus à constituer le bon polygone "france" ceux dans le 
dossier data semble avoir des problèmes de validité
  $query_osm="select
  l.osm_id as osm_id,sum(st_length(st_transform(st_intersection(f.simplified_way,l.way),2154))) as longueur
  from planet_osm_line as l,france_polygon as f
  where f.osm_id=4 and \"ref:sandre\"='$liste_sandre->code_hydro' and (l.waterway='river' or l.waterway='canal' or l.waterway='stream')
  group by l.osm_id
  order by longueur desc";
*/
  $query_osm="select
  l.osm_id as osm_id,sum(st_length(st_transform(l.way,2154))) as longueur
  from planet_osm_line as l
  where \"ref:sandre\"='$liste_sandre->code_hydro' and (l.waterway='river' or l.waterway='canal' or l.waterway='stream')
  group by l.osm_id
  order by longueur desc";
  
  $res_osm=pg_query($query_osm);
  $l_sandre=round($liste_sandre->longueur/1000,1);
  // cartographié
  if (($nombre_lignes=pg_num_rows($res_osm))>=1)
  {
    $riviere_dans_osm=pg_fetch_object($res_osm);
    $longueur_riviere_dans_osm=round($riviere_dans_osm->longueur/1000,1);
    $avancee=round($riviere_dans_osm->longueur/$liste_sandre->longueur*100,1);
    $osm_id=$riviere_dans_osm->osm_id;
    $somme_osm += $longueur_riviere_dans_osm;
    $somme_sandre_mapee += $l_sandre;
    $nb_osm++;
    if ($osm_id<0) // il s'agit d'une relation
    {
      $osm_id_reel=-$osm_id;
    }
    else // il s'agit d'un way
    {
      $osm_id_reel=$osm_id;
    }
      if ($nombre_lignes>1) {
        $erreur="<div class=\"warning\"><span>$nombre_lignes morceaux:</span><br />";
        $num_printed = 0;
        while ($num_printed < 3 && ($data = pg_fetch_object($res_osm))) {
          $num_printed ++;
          $add_osm_id = $data->osm_id;
          $erreur .= print_osm_id($add_osm_id);
          $erreur .= "<br />";
        }
        if (($num_printed + 1) < $nombre_lignes)
          $erreur .= "…";
        $erreur .= "</div>";
      } else {
        $erreur="";
      }
      $analyse=" <a href=\"http://analyser.openstreetmap.fr/cgi-bin/index.py?relation=$osm_id_reel\">Analyse</a> $erreur";
      
      $osm_id_lien = print_osm_id($osm_id) . $analyse;
  }
  // pas cartographié
  else
  {
    $avancee=0;
    $longueur_riviere_dans_osm='';
    $osm_id_lien="Pas dans osm";
    if ($liste_sandre->longueur<$seuil_longueur_max*1000)
      $avancee=-1;
    $osm_id=-1;
  }
  $ligne="$liste_sandre->toponyme;$osm_id;$liste_sandre->code_hydro;$l_sandre;$longueur_riviere_dans_osm;$avancee\n";

  if ($avancee < 50) { $style_avancement = "a0_50"; }
  else if ($avancee < 80) { $style_avancement = "a50_80"; }
  else if ($avancee < 95) { $style_avancement = "a80_95"; }
  else { $style_avancement = "a95_"; }

  $ligne_html="<tr><td>$liste_sandre->toponyme</td><td>$osm_id_lien</td><td>$liste_sandre->code_hydro</td><td>$l_sandre</td><td>$longueur_riviere_dans_osm</td><td class=\"$style_avancement\">$avancee %</td></tr>\n";
  if ($avancee==0)
  {
    $somme_sandre += $l_sandre;
    $nb_sandre++;
    $suivi_pas_dans_osm.=$ligne_html;
  }
  elseif($avancee==-1)
    ;
  else
  {
    $somme_sandre += $l_sandre;
    $nb_sandre++;
    $suivi_dans_osm.=$ligne_html;
  }
}
print $suivi_dans_osm.$suivi_pas_dans_osm;
print "</tbody>";

// ligne total
if ($somme_sandre_mapee!=0)
print "<tr><th>total</th><th>$nb_osm cours d'eau référencés dans OSM</th><th>$nb_sandre dans Sandre</th><th>$somme_sandre km Sandre</th><th>$somme_osm km OSM</th><th>".
      (round($somme_osm/$somme_sandre_mapee,3)*100).
      "% de ces rivières dans Sandre<br />".(round($somme_osm/$somme_sandre,3)*100).
      "% du total Sandre</th></tr>\n";
print "</table>
</body>
</html>";



?>
