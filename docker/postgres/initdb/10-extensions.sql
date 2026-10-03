-- Executed once when the data volume is created (the app user is superuser in the dev container).
-- The first Doctrine migration repeats these statements with IF NOT EXISTS for databases created elsewhere.
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_raster;   -- required by h3_postgis
CREATE EXTENSION IF NOT EXISTS h3;
CREATE EXTENSION IF NOT EXISTS h3_postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
