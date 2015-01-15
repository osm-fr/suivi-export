#!/bin/bash
# Config file for postgresql database access to an osm2pgsql worldwide database

#Certains scripts se contentent d'une base france pendant que d'autre on besoin de plus (dom/tom)
pg_base_monde="osm"
pg_role_monde="suivi-export"
pg_password_monde="eihooyai"
pg_serveur_monde="osm2pgsql-monde.openstreetmap.fr"
pg_serveur_port_monde=5432

#Pour certains tests, on s'assurera d'être dans un polygones défini (exemple : la france métropolitaine, l'Italie, etc.)
table_qui_contient_polygone_englobant="other_polygons"
id_france=1
id_france_metropolitaine=2

fichier_state_base_monde="http://osm2pgsql-monde.openstreetmap.fr/~osm2pgsql/state.txt"


chemin_suivi="/data/work/suivi"
chemin_export="/data/work/export"
dossier_temporaire="/dev/shm"

chemin_export_administratif="$chemin_export/contours-administratifs"

chemin_export_cours_eau="$chemin_export/cours-eau"
chemin_suivi_cours_eau="$chemin_suivi/longeur-cours-eau-france/"

chemin_suivi_commune="$chemin_suivi/communes"
chemin_export_commune="$chemin_export_administratif/communes"




