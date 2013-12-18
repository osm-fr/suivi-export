-- sans ça, sur une base monde, autant dire que y'en a pour des mois
create index hstore_tags_ref_sandre on planet_osm_line using hash ((tags->'ref:sandre'));