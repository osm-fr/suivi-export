#!/bin/bash
# script d'exportation des cours d'eau français
# - exporter le tout en shapefile

CHEMIN_EXPORT="/data/work/osm2pgsql/export-cours-eau"
osm_base=`grep pg_france_data_base ../config.php | cut -f2 -d\= | cut -f2 -d\"`
dossier_temporaire=`grep dossier_temporaire ../config.php | cut -f2 -d\= | cut -f2 -d\"`
mkdir $dossier_temporaire 2>/dev/null

pgsql2shp -f $dossier_temporaire/cours_eau_france $osm_base "select \"ref:sandre\",name,st_transform(way,4326) as way from planet_osm_line where \"ref:sandre\" is not null and waterway='river'"
cd $dossier_temporaire
tar cvfz $CHEMIN_EXPORT/cours-eau-france.shp.tar.gz cours_eau_france*
cd -
rm -f  $dossier_temporaire/cours_eau_france.*

  
