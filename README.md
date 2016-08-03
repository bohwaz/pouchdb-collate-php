# pouchdb-collate-php

PHP port of PouchDB collate/serialization library.

Pouch-Collate serialization SHOULD NOT be used as a (primary) key for referencing your data in CouchDB, it SHOULD only be used for collation and sorting your records. This is because float precision and other issues in the parsing of data may arise between Javascript and PHP and you would get inconsistant data sets.

To serialize data you should look at Bencode, JSON, serialize() or others, not this library.

## Authors

- Matthew Mills
- BohwaZ