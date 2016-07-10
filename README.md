php-active-record
=================

A (naive) PHP implementation of the active record pattern.

Usage examples
--------------

```php
$member = Member::select('WHERE id = ?', $id);
$member->name = $name;
$member->update();

$member = new Member;
$member->name = 'Foo';
$member->insert();
```
