<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cron event delete' => array(
        'command' => 'cron event delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<hook>...]
: One or more hooks to delete.

[--due-now]
: Delete all hooks due right now.

[--exclude=<hooks>]
: Comma-separated list of hooks to exclude.

[--all]
: Delete all hooks.

## EXAMPLES

    # Delete all scheduled cron events for the given hook
    $ wp cron event delete cron_test
    Success: Deleted a total of 2 cron events.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'hook',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'due-now',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Comma-separated list of hooks to exclude.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deletes all scheduled cron events for the given hook.',
        'synopsis' => '[<hook>...] [--due-now] [--exclude=<hooks>] [--all]',
    ),
    'cron event list' => array(
        'command' => 'cron event list',
        'fields' => array(
            'default' => array(
                'hook',
                'next_run_gmt',
                'next_run_relative',
                'recurrence',
            ),
            'optional' => array(
                'time',
                'sig',
                'args',
                'schedule',
                'interval',
                'next_run',
            ),
        ),
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific object fields.

[--<field>=<value>]
: Filter by one or more fields.

[--field=<field>]
: Prints the value of a single field for each event.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - count
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each cron event:
* hook
* next_run_gmt
* next_run_relative
* recurrence

These fields are optionally available:
* time
* sig
* args
* schedule
* interval
* next_run

## EXAMPLES

    # List scheduled cron events
    $ wp cron event list
    +-------------------+---------------------+---------------------+------------+
    | hook              | next_run_gmt        | next_run_relative   | recurrence |
    +-------------------+---------------------+---------------------+------------+
    | wp_version_check  | 2016-05-31 22:15:13 | 11 hours 57 minutes | 12 hours   |
    | wp_update_plugins | 2016-05-31 22:15:13 | 11 hours 57 minutes | 12 hours   |
    | wp_update_themes  | 2016-05-31 22:15:14 | 11 hours 57 minutes | 12 hours   |
    +-------------------+---------------------+---------------------+------------+

    # List scheduled cron events in JSON
    $ wp cron event list --fields=hook,next_run --format=json
    [{"hook":"wp_version_check","next_run":"2016-05-31 10:15:13"},{"hook":"wp_update_plugins","next_run":"2016-05-31 10:15:13"},{"hook":"wp_update_themes","next_run":"2016-05-31 10:15:14"}]',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Limit the output to specific object fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each event.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'ids',
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists scheduled cron events.',
        'synopsis' => '[--fields=<fields>] [--<field>=<value>] [--field=<field>] [--format=<format>]',
    ),
    'cron event run' => array(
        'command' => 'cron event run',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<hook>...]
: One or more hooks to run.

[--due-now]
: Run all hooks due right now.

[--exclude=<hooks>]
: Comma-separated list of hooks to exclude.

[--all]
: Run all hooks.

## EXAMPLES

    # Run all cron events due right now
    $ wp cron event run --due-now
    Executed the cron event \'cron_test_1\' in 0.01s.
    Executed the cron event \'cron_test_2\' in 0.006s.
    Success: Executed a total of 2 cron events.',
        'name' => 'run',
        'params' => array(
            array(
                'name' => 'hook',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'due-now',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Comma-separated list of hooks to exclude.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Runs the next scheduled cron event for the given hook.',
        'synopsis' => '[<hook>...] [--due-now] [--exclude=<hooks>] [--all]',
    ),
    'cron event schedule' => array(
        'command' => 'cron event schedule',
        'fields' => null,
        'longdesc' => '## OPTIONS

<hook>
: The hook name.

[<next-run>]
: A Unix timestamp or an English textual datetime description compatible with `strtotime()`. Defaults to now.

[<recurrence>]
: How often the event should recur. See `wp cron schedule list` for available schedule names. Defaults to no recurrence.

[--<field>=<value>]
: Arguments to pass to the hook for the event. <field> should be a numeric key, not a string.

## EXAMPLES

    # Schedule a new cron event
    $ wp cron event schedule cron_test
    Success: Scheduled event with hook \'cron_test\' for 2016-05-31 10:19:16 GMT.

    # Schedule new cron event with hourly recurrence
    $ wp cron event schedule cron_test now hourly
    Success: Scheduled event with hook \'cron_test\' for 2016-05-31 10:20:32 GMT.

    # Schedule new cron event and pass arguments
    $ wp cron event schedule cron_test \'+1 hour\' --0=first-argument --1=second-argument
    Success: Scheduled event with hook \'cron_test\' for 2016-05-31 11:21:35 GMT.',
        'name' => 'schedule',
        'params' => array(
            array(
                'name' => 'hook',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'next-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'recurrence',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
        ),
        'shortdesc' => 'Schedules a new cron event.',
        'synopsis' => '<hook> [<next-run>] [<recurrence>] [--<field>=<value>]',
    ),
    'cron event unschedule' => array(
        'command' => 'cron event unschedule',
        'fields' => null,
        'longdesc' => '## OPTIONS

<hook>
: Name of the hook for which all events should be unscheduled.

## EXAMPLES

    # Unschedule a cron event on given hook.
    $ wp cron event unschedule cron_test
    Success: Unscheduled 2 events for hook \'cron_test\'.',
        'name' => 'unschedule',
        'params' => array(
            array(
                'name' => 'hook',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Unschedules all cron events for a given hook.',
        'synopsis' => '<hook>',
    ),
    'cron schedule list' => array(
        'command' => 'cron schedule list',
        'fields' => array(
            'default' => array(
                'name',
                'display',
                'interval',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific object fields.

[--field=<field>]
: Prints the value of a single field for each schedule.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each cron schedule:

* name
* display
* interval

There are no additional fields.

## EXAMPLES

    # List available cron schedules
    $ wp cron schedule list
    +------------+-------------+----------+
    | name       | display     | interval |
    +------------+-------------+----------+
    | hourly     | Once Hourly | 3600     |
    | twicedaily | Twice Daily | 43200    |
    | daily      | Once Daily  | 86400    |
    +------------+-------------+----------+

    # List id of available cron schedule
    $ wp cron schedule list --fields=name --format=ids
    hourly twicedaily daily',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Limit the output to specific object fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Prints the value of a single field for each schedule.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'ids',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'List available cron schedules.',
        'synopsis' => '[--fields=<fields>] [--field=<field>] [--format=<format>]',
    ),
    'cron test' => array(
        'command' => 'cron test',
        'fields' => null,
        'longdesc' => 'This command tests the spawning system by performing the following steps:

* Checks to see if the `DISABLE_WP_CRON` constant is set; errors if true
because WP-Cron is disabled.
* Checks to see if the `ALTERNATE_WP_CRON` constant is set; warns if true.
* Attempts to spawn WP-Cron over HTTP; warns if non 200 response code is
returned.

## EXAMPLES

    # Cron test runs successfully.
    $ wp cron test
    Success: WP-Cron spawning is working as expected.',
        'name' => 'test',
        'params' => array(),
        'shortdesc' => 'Tests the WP Cron spawning system and reports back its status.',
        'synopsis' => '',
    ),
);
