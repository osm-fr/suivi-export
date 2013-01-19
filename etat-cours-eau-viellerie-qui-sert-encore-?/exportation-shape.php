<?php
/*
Ce script est distribué sous licence BSD avec une clause particulière :
L'utilisation, la modification et la distribution est interdite à toute personne en cours de rédaction d'un mémoire de
thèse et qui aurrait pris du retard dans sa rédaction.

L'auteur décline toute responsabilité quant au temps perdu et aux cheveux arrachés à tenter de comprendre ce code.

--
sly

 script d'exportation des cours d'eau français
 - exporter le tout en shapefile
*/
// EXPORTATION

  $query="select \\\"ref:sandre\\\",name,way
  from planet_osm_line 
  where \\\"ref:sandre\\\" is not null and waterway='river' ";
  
  exec("pgsql2shp -f \"cours_eau_france\" gis \"$query\"");
  
  exec("tar cvfz \"/home/sites/www.openhikingmaps.org/ressources/export-cours-eau/cours-eau-france.shp.tar.gz\" cours_eau_france*");
  exec("rm -f  cours_eau_france*.shp cours_eau_france*.dbf cours_eau_france*.shx");
  
?>