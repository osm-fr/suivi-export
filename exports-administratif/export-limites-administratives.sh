#!/bin/bash
CHEMIN_EXPORT="/data/work/osm2pgsql/export-contours-administratifs/"
DB_OSM2PGSQL=`grep pg_france_data_base ../config.php | cut -f2 -d\= | cut -f2 -d\"`
dossier_temporaire=`grep dossier_temporaire ../config.php | cut -f2 -d\= | cut -f2 -d\"`
mkdir $dossier_temporaire 2>/dev/null

if [ a$3 = "a" ] ;  then
echo "Utilisation : ./export-limites-administratives.sh <admin_level> <nombre_attendu> <nom du fichier>"
echo ""
echo "Où <admin_level> vaut 6 pour les départements, 4 pour les regions"
echo "Où <nombre_attendu> et le nombre de contours attendu pour déterminer si l'export est complet ou incomplet"
echo "Où <nom du fichier> et le nom souhaité pour le fichier d'archive et des shapefiles"

echo "Exemple : ./export-limites-administratives.sh 6 96 departements"
echo "Exemple : ./export-limites-administratives.sh 4 22 regions"

exit
fi

# Bidouille car les regions on comme référence principale ref:INSEE, mais les départements ref
if  [ $1 == 4 ] ; then
  champ_contenant_ref="\"admin.ref:INSEE\""
else
  champ_contenant_ref="admin.ref"
fi

pgsql2shp -f $dossier_temporaire/$3-metropole $DB_OSM2PGSQL "select st_transform(admin.way,4326) as way,admin.name as nom,admin.ref as numero from planet_osm_polygon as admin,france_polygon as f where admin.admin_level='$1' and f.osm_id=4  and $champ_contenant_ref is not null and admin.simplified_way && f.simplified_way and st_within(st_pointonsurface(admin.way),f.simplified_way) and isvalid(admin.way)='t';" > /dev/null 2>/dev/null
RES=`cat $dossier_temporaire/resultat | grep "\[$2"` 

if [ "a$RES" = "a" ] ; then
complet_ou_pas="incomplet"
else
complet_ou_pas="complet"
rm $CHEMIN_EXPORT/$3-metropole-incomplet.tar.gz 2>/dev/null
fi

cd $dossier_temporaire
tar cvfz $CHEMIN_EXPORT/$3-metropole-$complet_ou_pas.tar.gz $3-metropole.*
cd -
rm $dossier_temporaire/$3-metropole.*
