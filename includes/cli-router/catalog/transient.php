<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'transient delete' => array(
        'command' => 'transient delete',
        'fields' => null,
        'longdesc' => 'For a more complete explanation of the transient cache, including the
network|site cache, please see docs for `wp transient`.

## OPTIONS

[<key>]
: Key for the transient.

[--network]
: Delete the value of a network|site transient. On single site, this is
is a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).

[--all]
: Delete all transients.

[--expired]
: Delete all expired transients.

## EXAMPLES

    # Delete transient.
    $ wp transient delete sample_key
    Success: Transient deleted.

    # Delete expired transients.
    $ wp transient delete --expired
    Success: 12 expired transients deleted from the database.

    # Delete expired site transients.
    $ wp transient delete --expired --network
    Success: 1 expired transient deleted from the database.

    # Delete all transients.
    $ wp transient delete --all
    Success: 14 transients deleted from the database.

    # Delete all site transients.
    $ wp transient delete --all --network
    Success: 2 transients deleted from the database.

    # Delete all transients in a multisite.
    $ wp transient delete --all --network && wp site list --field=url | xargs -n1 -I % wp --url=% transient delete --all',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'expired',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deletes a transient value.',
        'synopsis' => '[<key>] [--network] [--all] [--expired]',
    ),
    'transient get' => array(
        'command' => 'transient get',
        'fields' => null,
        'longdesc' => 'For a more complete explanation of the transient cache, including the
network|site cache, please see docs for `wp transient`.

## OPTIONS

<key>
: Key for the transient.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
---

[--network]
: Get the value of a network|site transient. On single site, this is
is a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).

## EXAMPLES

    $ wp transient get sample_key
    test data

    $ wp transient get random_key
    Warning: Transient with key "random_key" is not set.',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Gets a transient value.',
        'synopsis' => '<key> [--format=<format>] [--network]',
    ),
    'transient list' => array(
        'command' => 'transient list',
        'fields' => array(
            'default' => array(
                'name',
                'value',
                'expiration',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--search=<pattern>]
: Use wildcards ( * and ? ) to match transient name.

[--exclude=<pattern>]
: Pattern to exclude. Use wildcards ( * and ? ) to match transient name.

[--network]
: Get the values of network|site transients. On single site, this is
a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).

[--unserialize]
: Unserialize transient values in output.

[--human-readable]
: Human-readable output for expirations.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: The serialization format for the value.
---
default: table
options:
  - table
  - json
  - csv
  - count
  - yaml
---

## AVAILABLE FIELDS

This field will be displayed by default for each matching option:

* name
* value
* expiration

## EXAMPLES

    # List all transients
    $ wp transient list
     +------+-------+---------------+
     | name | value | expiration    |
     +------+-------+---------------+
     | foo  | bar   | 39 mins       |
     | foo2 | bar2  | no expiration |
     | foo3 | bar2  | expired       |
     | foo4 | bar4  | 4 hours       |
     +------+-------+---------------+',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Use wildcards ( * and ? ) to match transient name.',
                'name' => 'search',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Pattern to exclude. Use wildcards ( * and ? ) to match transient name.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'unserialize',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'human-readable',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Limit the output to specific object fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'json',
                    'csv',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists transients and their values.',
        'synopsis' => '[--search=<pattern>] [--exclude=<pattern>] [--network] [--unserialize] [--human-readable] [--fields=<fields>] [--format=<format>]',
    ),
    'transient patch' => array(
        'command' => 'transient patch',
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
: Key for the transient.

<key-path>...
: The name(s) of the keys within the value to locate the value to patch.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

[--expiration=<expiration>]
: Time until expiration, in seconds.

[--network]
: Get the value of a network|site transient. On single site, this is
a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).',
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
            array(
                'desc' => 'Time until expiration, in seconds.',
                'name' => 'expiration',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Update a nested value from a transient.',
        'synopsis' => '<action> <key> <key-path>... [<value>] [--format=<format>] [--expiration=<expiration>] [--network]',
    ),
    'transient pluck' => array(
        'command' => 'transient pluck',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: Key for the transient.

<key-path>...
: The name(s) of the keys within the value to locate the value to pluck.

[--format=<format>]
: The output format of the value.
---
default: plaintext
options:
  - plaintext
  - json
  - yaml
---

[--network]
: Get the value of a network|site transient. On single site, this is
a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).',
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
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Get a nested value from a transient.',
        'synopsis' => '<key> <key-path>... [--format=<format>] [--network]',
    ),
    'transient set' => array(
        'command' => 'transient set',
        'fields' => null,
        'longdesc' => '`<expiration>` is the time until expiration, in seconds.

For a more complete explanation of the transient cache, including the
network|site cache, please see docs for `wp transient`.

## OPTIONS

<key>
: Key for the transient.

<value>
: Value to be set for the transient.

[<expiration>]
: Time until expiration, in seconds.

[--network]
: Set the value of a network|site transient. On single site, this is
is a specially-named cache key. On multisite, this is a global cache
(instead of local to the site).

## EXAMPLES

    $ wp transient set sample_key "test data" 3600
    Success: Transient added.',
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
                'name' => 'expiration',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Sets a transient value.',
        'synopsis' => '<key> <value> [<expiration>] [--network]',
    ),
    'transient type' => array(
        'command' => 'transient type',
        'fields' => null,
        'longdesc' => 'Indicates whether the transients API is using an object cache or the
database.

For a more complete explanation of the transient cache, including the
network|site cache, please see docs for `wp transient`.

## EXAMPLES

    $ wp transient type
    Transients are saved to the database.',
        'name' => 'type',
        'params' => array(),
        'shortdesc' => 'Determines the type of transients implementation.',
        'synopsis' => '',
    ),
);
