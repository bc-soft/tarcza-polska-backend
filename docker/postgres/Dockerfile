# PostgreSQL 16 + PostGIS 3.5 (multi-arch Debian image) + H3 (h3-pg) from the PGDG apt repository.
# H3 gives us hexagonal cells for the Dynamic Area engine straight from SQL.
FROM imresamu/postgis:16-3.5-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-16-h3 \
    && rm -rf /var/lib/apt/lists/*

COPY initdb/*.sql /docker-entrypoint-initdb.d/
