php-active-record
=================

A PHP implementation of the active record pattern taken from my personal PHP framework CaPHPy.

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
