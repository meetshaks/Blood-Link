# City corporation areas

The source file is `Assets/city_corp.json`. It contains area names and ward
numbers for DNCC and DSCC. The source data is not redistributed here; confirm
its licence and attribution before production use.

Apply the schema migration:

```sh
mysql --skip-ssl -h127.0.0.1 -uroot bl < database/migrations/2026_11_city_corporation_areas.sql
```

Preview the import:

```sh
php tools/import_city_corp_json.php --dry-run
```

Import or refresh the areas:

```sh
php tools/import_city_corp_json.php
php tools/import_city_corp_json.php --replace
```

`--replace` refuses to remove areas referenced by donors unless
`--force-null` is explicitly supplied. Deactivating an area is preferred to
deleting it when it is already used.

Administrators can review or correct areas from the City areas administration
page. Changes should retain the original source id and ward where possible.
