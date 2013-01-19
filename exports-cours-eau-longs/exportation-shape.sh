#!/bin/bash
# script d'exportation des cours d'eau français
# - exporter le tout en shapefile

CHEMIN_EXPORT="/data/work/osm2pgsql/export-cours-eau"
osm_base=`grep pg_france_data_base ../config.php | cut -f2 -d\= | cut -f2 -d\"`

pgsql2shp -f ./tmp/cours_eau_france $osm_base "select \"ref:sandre\",name,st_transform(way,4326) as way from planet_osm_line where \"ref:sandre\" is not null and waterway='river'"
cd tmp
tar cvfz $CHEMIN_EXPORT/cours-eau-france.shp.tar.gz cours_eau_france*
rm -f  cours_eau_france*.shp cours_eau_france*.dbf cours_eau_france*.shx
cd ..

  
