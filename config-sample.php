<?php

// Config file for postgresql database access to an osm2pgsql worldwide database
$pg_data_base="osm2pgsql";
$pg_user="mapnik";
$pg_password="";
$pg_server="osm2pgsql-monde.openstreetmap.fr";
$fichier_state_date_base="http://osm2pgsql-monde.openstreetmap.fr/~osm2pgsql/state.txt";
$fichier_state_base_france="/data/project/osm2pgsql/import-base-osm/state.txt";

$chemin_suivi="/data/work/suivi";
$chemin_export="/data/work/export";
$dossier_temporaire="/dev/shm/";


// Config file to access a local osm2pgsql France only database
$pg_france_data_base="osm";


?>