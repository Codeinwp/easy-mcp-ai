<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cache add' => array(
        'command' => 'cache add',
        'fields' => null,
        'longdesc' => 'Errors if a value already exists for the key, which means the value can\'t
be added.

## OPTIONS

<key>
: Cache key.

<value>
: Value to add to the key.

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

[<expiration>]
: Define how long to keep the value, in seconds. `0` means as long as possible.
---
default: 0
---

## EXAMPLES

    # Add cache.
    $ wp cache add my_key my_group my_value 300
    Success: Added object \'my_key\' in group \'my_value\'.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 0,
                'name' => 'expiration',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Adds a value to the object cache.',
        'synopsis' => '<key> <value> [<group>] [<expiration>]',
    ),
    'cache decr' => array(
        'command' => 'cache decr',
        'fields' => null,
        'longdesc' => 'Errors if the value can\'t be decremented.

## OPTIONS

<key>
: Cache key.

[<offset>]
: The amount by which to decrement the item\'s value.
---
default: 1
---

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

## EXAMPLES

    # Decrease cache value.
    $ wp cache decr my_key 2 my_group
    48',
        'name' => 'decr',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 1,
                'name' => 'offset',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Decrements a value in the object cache.',
        'synopsis' => '<key> [<offset>] [<group>]',
    ),
    'cache delete' => array(
        'command' => 'cache delete',
        'fields' => null,
        'longdesc' => 'Errors if the value can\'t be deleted.

## OPTIONS

<key>
: Cache key.

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

## EXAMPLES

    # Delete cache.
    $ wp cache delete my_key my_group
    Success: Object deleted.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes a value from the object cache.',
        'synopsis' => '<key> [<group>]',
    ),
    'cache flush' => array(
        'command' => 'cache flush',
        'fields' => null,
        'longdesc' => 'For WordPress multisite instances using a persistent object cache,
flushing the object cache will typically flush the cache for all sites.
Beware of the performance impact when flushing the object cache in
production.

Errors if the object cache can\'t be flushed.

## EXAMPLES

    # Flush cache.
    $ wp cache flush
    Success: The cache was flushed.',
        'name' => 'flush',
        'params' => array(),
        'shortdesc' => 'Flushes the object cache.',
        'synopsis' => '',
    ),
    'cache flush-group' => array(
        'command' => 'cache flush-group',
        'fields' => null,
        'longdesc' => '## OPTIONS

<group>
: Cache group key.

## EXAMPLES

    # Clear cache group.
    $ wp cache flush-group my_group
    Success: Cache group \'my_group\' was flushed.',
        'name' => 'flush-group',
        'params' => array(
            array(
                'name' => 'group',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes all cache items in a group, if the object cache implementation supports it.',
        'synopsis' => '<group>',
    ),
    'cache get' => array(
        'command' => 'cache get',
        'fields' => null,
        'longdesc' => 'Errors if the value doesn\'t exist.

## OPTIONS

<key>
: Cache key.

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

## EXAMPLES

    # Get cache.
    $ wp cache get my_key my_group
    my_value',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets a value from the object cache.',
        'synopsis' => '<key> [<group>]',
    ),
    'cache incr' => array(
        'command' => 'cache incr',
        'fields' => null,
        'longdesc' => 'Errors if the value can\'t be incremented.

## OPTIONS

<key>
: Cache key.

[<offset>]
: The amount by which to increment the item\'s value.
---
default: 1
---

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

## EXAMPLES

    # Increase cache value.
    $ wp cache incr my_key 2 my_group
    50',
        'name' => 'incr',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 1,
                'name' => 'offset',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Increments a value in the object cache.',
        'synopsis' => '<key> [<offset>] [<group>]',
    ),
    'cache patch' => array(
        'command' => 'cache patch',
        'fields' => null,
        'longdesc' => '## OPTIONS

<action>
: Patch action to perform.
---
options:
  - insert
  - update
  - delete
---

<key>
: Cache key.

<key-path>...
: The name(s) of the keys within the value to locate the value to patch.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--group=<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

[--expiration=<expiration>]
 : Define how long to keep the value, in seconds. `0` means as long as possible.
 ---
 default: 0
 ---

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'patch',
        'params' => array(
            array(
                'name' => 'action',
                'optional' => false,
                'options' => array(
                    'insert',
                    'update',
                    'delete',
                ),
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'desc' => 'Method for grouping data within the cache which allows the same key to be used across groups.',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Update a nested value from the cache.',
        'synopsis' => '<action> <key> <key-path>... [<value>] [--group=<group>] [--format=<format>]',
    ),
    'cache pluck' => array(
        'command' => 'cache pluck',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: Cache key.

<key-path>...
: The name(s) of the keys within the value to locate the value to pluck.

[--group=<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

[--format=<format>]
: The output format of the value.
---
default: plaintext
options:
  - plaintext
  - json
  - yaml
---',
        'name' => 'pluck',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'desc' => 'Method for grouping data within the cache which allows the same key to be used across groups.',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The output format of the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Get a nested value from the cache.',
        'synopsis' => '<key> <key-path>... [--group=<group>] [--format=<format>]',
    ),
    'cache replace' => array(
        'command' => 'cache replace',
        'fields' => null,
        'longdesc' => 'Errors if the value can\'t be replaced.

## OPTIONS

<key>
: Cache key.

<value>
: Value to replace.

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

[<expiration>]
: Define how long to keep the value, in seconds. `0` means as long as possible.
---
default: 0
---

## EXAMPLES

    # Replace cache.
    $ wp cache replace my_key new_value my_group
    Success: Replaced object \'my_key\' in group \'my_group\'.',
        'name' => 'replace',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 0,
                'name' => 'expiration',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Replaces a value in the object cache, if the value already exists.',
        'synopsis' => '<key> <value> [<group>] [<expiration>]',
    ),
    'cache set' => array(
        'command' => 'cache set',
        'fields' => null,
        'longdesc' => 'Errors if the value can\'t be set.

## OPTIONS

<key>
: Cache key.

<value>
: Value to set on the key.

[<group>]
: Method for grouping data within the cache which allows the same key to be used across groups.
---
default: default
---

[<expiration>]
: Define how long to keep the value, in seconds. `0` means as long as possible.
---
default: 0
---

## EXAMPLES

    # Set cache.
    $ wp cache set my_key my_value my_group 300
    Success: Set object \'my_key\' in group \'my_group\'.',
        'name' => 'set',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'default',
                'name' => 'group',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 0,
                'name' => 'expiration',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Sets a value to the object cache, regardless of whether it already exists.',
        'synopsis' => '<key> <value> [<group>] [<expiration>]',
    ),
    'cache supports' => array(
        'command' => 'cache supports',
        'fields' => null,
        'longdesc' => '## OPTIONS

<feature>
: Name of the feature to check for.

## EXAMPLES

    # Check whether is add_multiple supported.
    $ wp cache supports add_multiple
    $ echo $?
    0

    # Bash script for checking whether for support like this:
    if ! wp cache supports non_existing; then
        echo \'non_existing is not supported\'
    fi',
        'name' => 'supports',
        'params' => array(
            array(
                'name' => 'feature',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Determines whether the object cache implementation supports a particular feature.',
        'synopsis' => '<feature>',
    ),
    'cache type' => array(
        'command' => 'cache type',
        'fields' => null,
        'longdesc' => 'Note that the guesses made by this function are based on the
WP_Object_Cache classes that define the 3rd party object cache extension.
Changes to those classes could render problems with this function\'s
ability to determine which object cache is being used.

## EXAMPLES

    # Check cache type.
    $ wp cache type
    Default',
        'name' => 'type',
        'params' => array(),
        'shortdesc' => 'Attempts to determine which object cache is being used.',
        'synopsis' => '',
    ),
);
